<?php

namespace App\Http\Controllers;

use App\Models\SepaPaymentOrder;
use App\Models\SepaPaymentBeneficiary;
use App\Models\Administration;
use App\Models\Participation;
use App\Models\ParticipationCollection;
use App\Models\ParticipationCollectionItem;
use App\Services\SepaXmlGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use App\Rules\ValidCalendarDate;

class SepaPaymentOrderController extends Controller
{
    protected $xmlGenerator;

    public function __construct(SepaXmlGeneratorService $xmlGenerator)
    {
        $this->xmlGenerator = $xmlGenerator;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $orders = SepaPaymentOrder::with(['administration', 'beneficiaries'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('sepa_payments.index', compact('orders'));
    }

    /**
     * Show the form for creating a new resource.
     * Los beneficiarios se añaden manualmente. El listado de participation_collections está en Configuration (nueva orden por entidad).
     */
    public function create()
    {
        $administrations = Administration::all();
        return view('sepa_payments.create', compact('administrations'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'administration_id' => 'nullable|exists:administrations,id',
            'execution_date' => ValidCalendarDate::onOrAfterToday(),
            'debtor_name' => 'required|string|max:255',
            'debtor_nif_cif' => ['nullable', 'string', 'max:50', new \App\Rules\SpanishDocument],
            'debtor_iban' => ['required', 'string', 'max:22', 'regex:/^[0-9]{22}$/'],
            'debtor_address' => 'nullable|string|max:500',
            'batch_booking' => 'nullable|boolean',
            'beneficiaries' => 'required|array|min:1',
            'beneficiaries.*.creditor_name' => 'required|string|max:255',
            'beneficiaries.*.creditor_nif_cif' => ['nullable', 'string', 'max:50', new \App\Rules\SpanishDocument],
            'beneficiaries.*.creditor_iban' => ['required', 'string', 'max:22', 'regex:/^[0-9]{22}$/'],
            'beneficiaries.*.amount' => 'required|numeric|min:0.01',
            'beneficiaries.*.currency' => 'required|string|size:3',
            'beneficiaries.*.purpose_code' => 'nullable|string|max:10',
            'beneficiaries.*.remittance_info' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Validar IBANs completos después de agregar ES
        $debtorIban = 'ES' . $validated['debtor_iban'];
        $validator = \Validator::make(['iban' => $debtorIban], [
            'iban' => [new \App\Rules\SpanishIban]
        ]);
        
        if ($validator->fails()) {
            return back()->withErrors(['debtor_iban' => 'El IBAN del deudor no es válido.'])->withInput();
        }

        foreach ($validated['beneficiaries'] as $index => $beneficiary) {
            $creditorIban = 'ES' . $beneficiary['creditor_iban'];
            $validator = \Validator::make(['iban' => $creditorIban], [
                'iban' => [new \App\Rules\SpanishIban]
            ]);
            
            if ($validator->fails()) {
                return back()->withErrors(["beneficiaries.{$index}.creditor_iban" => 'El IBAN del beneficiario no es válido.'])->withInput();
            }
        }

        try {
            DB::beginTransaction();

            // Calcular totales
            $totalAmount = collect($validated['beneficiaries'])->sum('amount');
            $numberOfTransactions = count($validated['beneficiaries']);

            // Normalizar IBANs (agregar ES si no lo tienen)
            $debtorIban = $validated['debtor_iban'];
            if (!str_starts_with(strtoupper($debtorIban), 'ES')) {
                $debtorIban = 'ES' . $debtorIban;
            }

            // Crear orden de pago
            $order = SepaPaymentOrder::create([
                'administration_id' => $validated['administration_id'] ?? null,
                'message_id' => SepaPaymentOrder::generateMessageId(),
                'creation_date' => now(),
                'execution_date' => $validated['execution_date'],
                'number_of_transactions' => $numberOfTransactions,
                'control_sum' => $totalAmount,
                'payment_info_id' => SepaPaymentOrder::generateMessageId(),
                'batch_booking' => $validated['batch_booking'] ?? false,
                'charge_bearer' => 'SLEV',
                'debtor_name' => $validated['debtor_name'],
                'debtor_nif_cif' => $validated['debtor_nif_cif'] ?? null,
                'debtor_iban' => $debtorIban,
                'debtor_address' => $validated['debtor_address'] ?? null,
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            // Crear beneficiarios y vincular participation_collections si aplica
            foreach ($validated['beneficiaries'] as $index => $beneficiary) {
                $creditorIban = $beneficiary['creditor_iban'];
                if (!str_starts_with(strtoupper($creditorIban), 'ES')) {
                    $creditorIban = 'ES' . $creditorIban;
                }

                SepaPaymentBeneficiary::create([
                    'sepa_payment_order_id' => $order->id,
                    'end_to_end_id' => SepaPaymentBeneficiary::generateEndToEndId(),
                    'amount' => $beneficiary['amount'],
                    'currency' => $beneficiary['currency'] ?? 'EUR',
                    'creditor_name' => $beneficiary['creditor_name'],
                    'creditor_nif_cif' => $beneficiary['creditor_nif_cif'] ?? null,
                    'creditor_iban' => $creditorIban,
                    'purpose_code' => $beneficiary['purpose_code'] ?? 'CASH',
                    'remittance_info' => $beneficiary['remittance_info'] ?? null,
                ]);
            }

            DB::commit();

            return redirect()->route('sepa-payments.index')
                ->with('success', 'Orden de pago creada correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->withErrors(['error' => 'Error al crear la orden de pago: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(SepaPaymentOrder $sepaPaymentOrder)
    {
        $sepaPaymentOrder->load(['administration', 'beneficiaries']);
        return view('sepa_payments.show', compact('sepaPaymentOrder'));
    }

    /**
     * Generar XML para una orden de pago
     */
    public function generateXml(SepaPaymentOrder $sepaPaymentOrder)
    {
        try {
            $sepaPaymentOrder->load('beneficiaries');

            $exportableCount = $sepaPaymentOrder->beneficiaries
                ->filter(fn ($beneficiary) => $beneficiary->isExportableToSepa())
                ->count();

            if ($exportableCount === 0) {
                return back()->withErrors([
                    'error' => 'No hay beneficiarios para incluir en el XML. Las solicitudes revertidas se excluyen.',
                ]);
            }

            $filePath = $this->xmlGenerator->generateAndSave($sepaPaymentOrder);

            return response()->download($filePath, $sepaPaymentOrder->xml_filename, [
                'Content-Type' => 'application/xml',
            ])->deleteFileAfterSend(false);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al generar el XML: ' . $e->getMessage()]);
        }
    }

    /**
     * Marcar la orden como Listo (pago realizado manualmente por el usuario).
     * Marca como pagados todos los beneficiarios pendientes vinculados a participation_collections.
     */
    public function markAsReady(Request $request, SepaPaymentOrder $sepaPaymentOrder)
    {
        $pendingIds = $sepaPaymentOrder->beneficiaries()
            ->where('status', SepaPaymentBeneficiary::STATUS_PENDING)
            ->pluck('id')
            ->all();

        if (empty($pendingIds)) {
            return $this->redirectAfterBeneficiaryAction($request, $sepaPaymentOrder)
                ->withErrors(['error' => 'No hay beneficiarios pendientes para marcar como pagados.']);
        }

        $count = $this->markBeneficiariesAsPaid($sepaPaymentOrder, $pendingIds);

        return $this->redirectAfterBeneficiaryAction($request, $sepaPaymentOrder)
            ->with('success', "Se marcaron {$count} beneficiario(s) como pagados.");
    }

    /**
     * Marcar beneficiarios seleccionados como pagados.
     */
    public function markBeneficiariesPaid(Request $request, SepaPaymentOrder $sepaPaymentOrder)
    {
        $validated = $request->validate([
            'beneficiary_ids' => 'required|array|min:1',
            'beneficiary_ids.*' => 'integer|exists:sepa_payment_beneficiaries,id',
            'redirect_to' => 'nullable|string',
            'entity_id' => 'nullable|integer|exists:entities,id',
            'order_id' => 'nullable|integer',
        ]);

        $count = $this->markBeneficiariesAsPaid($sepaPaymentOrder, $validated['beneficiary_ids']);

        if ($count === 0) {
            return $this->redirectAfterBeneficiaryAction($request, $sepaPaymentOrder)
                ->withErrors(['error' => 'Ningún beneficiario pendiente seleccionado pudo marcarse como pagado.']);
        }

        return $this->redirectAfterBeneficiaryAction($request, $sepaPaymentOrder)
            ->with('success', "Se marcaron {$count} beneficiario(s) como pagados.");
    }

    /**
     * Revertir beneficiarios con error bancario: las participaciones quedan de nuevo cobrables
     * y la solicitud de cobro se cierra (no vuelve a pendientes de gestionar).
     */
    public function revertBeneficiariesToCobrable(Request $request, SepaPaymentOrder $sepaPaymentOrder)
    {
        $validated = $request->validate([
            'beneficiary_ids' => 'required|array|min:1',
            'beneficiary_ids.*' => 'integer|exists:sepa_payment_beneficiaries,id',
            'redirect_to' => 'nullable|string',
            'entity_id' => 'nullable|integer|exists:entities,id',
            'order_id' => 'nullable|integer',
        ]);

        $count = $this->revertBeneficiariesAsCobrable($sepaPaymentOrder, $validated['beneficiary_ids']);

        if ($count === 0) {
            return $this->redirectAfterBeneficiaryAction($request, $sepaPaymentOrder)
                ->withErrors(['error' => 'Ningún beneficiario pendiente seleccionado pudo revertirse.']);
        }

        return $this->redirectAfterBeneficiaryAction($request, $sepaPaymentOrder)
            ->with('success', "Se revirtieron {$count} beneficiario(s). Las participaciones vuelven a estar cobrables y la solicitud ya no aparece en pendientes de gestionar.");
    }

    private function markBeneficiariesAsPaid(SepaPaymentOrder $order, array $beneficiaryIds): int
    {
        $beneficiaries = $order->beneficiaries()
            ->whereIn('id', $beneficiaryIds)
            ->where('status', SepaPaymentBeneficiary::STATUS_PENDING)
            ->get();

        if ($beneficiaries->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($beneficiaries, $order) {
            foreach ($beneficiaries as $beneficiary) {
                $beneficiary->update([
                    'status' => SepaPaymentBeneficiary::STATUS_PAID,
                    'paid_at' => now(),
                ]);
                $this->updateParticipationsForPaidBeneficiary($beneficiary);
            }
            $this->syncOrderStatus($order);
        });

        return $beneficiaries->count();
    }

    private function revertBeneficiariesAsCobrable(SepaPaymentOrder $order, array $beneficiaryIds): int
    {
        $beneficiaries = $order->beneficiaries()
            ->whereIn('id', $beneficiaryIds)
            ->where('status', SepaPaymentBeneficiary::STATUS_PENDING)
            ->get();

        if ($beneficiaries->isEmpty()) {
            return 0;
        }

        $revertedCount = 0;

        DB::transaction(function () use ($beneficiaries, $order, &$revertedCount) {
            foreach ($beneficiaries as $beneficiary) {
                if ($this->beneficiaryParticipationsArePaid($beneficiary)) {
                    continue;
                }
                $beneficiary->update(['status' => SepaPaymentBeneficiary::STATUS_REVERTED]);
                $this->revertParticipationsForBeneficiary($beneficiary);
                $revertedCount++;
            }
            $this->syncOrderStatus($order);
        });

        return $revertedCount;
    }

    private function updateParticipationsForPaidBeneficiary(SepaPaymentBeneficiary $beneficiary): void
    {
        $collectionId = $beneficiary->participation_collection_id;
        if (!$collectionId) {
            return;
        }

        $ids = ParticipationCollectionItem::where('collection_id', $collectionId)
            ->pluck('participation_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if (empty($ids)) {
            return;
        }

        $updates = [];
        if (Schema::hasColumn('participations', 'status')) {
            $updates['status'] = 'pagada';
        }
        if (!empty($updates)) {
            Participation::whereIn('id', $ids)->update($updates);
        }
    }

    private function revertParticipationsForBeneficiary(SepaPaymentBeneficiary $beneficiary): void
    {
        $collection = $beneficiary->participationCollection;
        if (!$collection) {
            return;
        }

        $collection->revertAsCobrable();
    }

    private function beneficiaryParticipationsArePaid(SepaPaymentBeneficiary $beneficiary): bool
    {
        $collectionId = $beneficiary->participation_collection_id;
        if (!$collectionId || !Schema::hasColumn('participations', 'status')) {
            return false;
        }

        $ids = ParticipationCollectionItem::where('collection_id', $collectionId)
            ->pluck('participation_id')
            ->unique()
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return false;
        }

        return Participation::whereIn('id', $ids)->where('status', 'pagada')->exists();
    }

    private function syncOrderStatus(SepaPaymentOrder $order): void
    {
        $order->refresh();
        $statuses = $order->beneficiaries()->pluck('status');

        if ($statuses->isEmpty()) {
            return;
        }

        $allResolved = $statuses->every(fn ($s) => in_array($s, [
            SepaPaymentBeneficiary::STATUS_PAID,
            SepaPaymentBeneficiary::STATUS_REVERTED,
        ], true));

        if ($allResolved) {
            $order->update(['status' => 'listo']);
        }
    }

    private function redirectAfterBeneficiaryAction(Request $request, SepaPaymentOrder $order)
    {
        if ($request->input('redirect_to') === 'configuration' && $request->filled('entity_id')) {
            $params = [
                'section' => 'ordenes-pago-entidades',
                'step' => 3,
                'entity_id' => $request->input('entity_id'),
            ];
            if ($request->filled('order_id')) {
                $params['order_id'] = $request->input('order_id');
            }
            return redirect()->route('configuration.index', $params);
        }

        return redirect()->route('sepa-payments.show', $order->id);
    }

    /**
     * Eliminar una orden de pago.
     * Las participation_collections vinculadas no se borran; se les pone sepa_payment_order_id = null
     * para que sigan disponibles (pagos pendientes).
     */
    public function destroy(Request $request, SepaPaymentOrder $sepaPaymentOrder)
    {
        try {
            if (Schema::hasColumn('participation_collections', 'sepa_payment_order_id')) {
                ParticipationCollection::where('sepa_payment_order_id', $sepaPaymentOrder->id)
                    ->update(['sepa_payment_order_id' => null]);
            }

            // Eliminar archivo XML si existe
            if ($sepaPaymentOrder->xml_filename) {
                $filePath = storage_path('app/sepa_payments/' . $sepaPaymentOrder->xml_filename);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $sepaPaymentOrder->delete();

            if ($request->input('redirect_to') === 'configuration' && $request->filled('entity_id')) {
                return redirect()->route('configuration.index', [
                    'section' => 'ordenes-pago-entidades',
                    'step' => 2,
                    'entity_id' => $request->input('entity_id'),
                ])->with('success', 'Orden de pago eliminada correctamente.');
            }

            return redirect()->route('sepa-payments.index')
                ->with('success', 'Orden de pago eliminada correctamente.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Error al eliminar la orden: ' . $e->getMessage()]);
        }
    }
}
