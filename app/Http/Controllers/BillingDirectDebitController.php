<?php

namespace App\Http\Controllers;

use App\Models\Administration;
use App\Models\BillingDirectDebitOrder;
use App\Services\AdministrationBillingService;
use App\Services\BillingDirectDebitService;
use App\Services\BillingDirectDebitXmlGeneratorService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingDirectDebitController extends Controller
{
    public function __construct(
        private BillingDirectDebitService $directDebitService,
        private BillingDirectDebitXmlGeneratorService $xmlGenerator
    ) {}

    public function index()
    {
        return redirect()->route('configuration.index', ['section' => 'facturacion-cobros']);
    }

    public function store(Request $request, Administration $administration)
    {
        if (! auth()->user()?->isSuperAdmin()) {
            abort(403);
        }

        $data = $request->validate([
            'charge_ids' => 'required|array|min:1',
            'charge_ids.*' => 'integer|exists:billing_charges,id',
            'collection_date' => 'required|date|after_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        $order = $this->directDebitService->createOrderFromCharges(
            $administration,
            auth()->user(),
            $data['charge_ids'],
            now()->parse($data['collection_date']),
            $data['notes'] ?? null
        );

        return $this->configurationRedirect($administration, $order)
            ->with('success', 'Orden de adeudo creada. Ya puede generar el XML pain.008.');
    }

    public function show(BillingDirectDebitOrder $billingDirectDebit)
    {
        return $this->configurationRedirect(
            $billingDirectDebit->administration,
            $billingDirectDebit
        );
    }

    public function generateXml(BillingDirectDebitOrder $billingDirectDebit): StreamedResponse
    {
        if (! auth()->user()?->isSuperAdmin()) {
            abort(403);
        }

        $billingDirectDebit->load('charges');
        $xml = $this->xmlGenerator->generateXml($billingDirectDebit);
        $filename = $billingDirectDebit->message_id.'.xml';
        $this->xmlGenerator->saveXmlToFile($billingDirectDebit, $xml);
        $this->directDebitService->markExported($billingDirectDebit, $filename);

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $filename, ['Content-Type' => 'application/xml']);
    }

    public function markCollected(BillingDirectDebitOrder $billingDirectDebit)
    {
        if (! auth()->user()?->isSuperAdmin()) {
            abort(403);
        }

        $this->directDebitService->markCollected($billingDirectDebit);

        return $this->configurationRedirect($billingDirectDebit->administration, $billingDirectDebit)
            ->with('success', 'Adeudo marcado como cobrado.');
    }

    public function cancel(BillingDirectDebitOrder $billingDirectDebit)
    {
        if (! auth()->user()?->isSuperAdmin()) {
            abort(403);
        }

        $administration = $billingDirectDebit->administration;
        $this->directDebitService->cancelOrder($billingDirectDebit);

        return $this->configurationRedirect($administration)
            ->with('success', 'Orden de adeudo anulada. Los cargos vuelven a estar pendientes.');
    }

    private function configurationRedirect(?Administration $administration = null, ?BillingDirectDebitOrder $order = null)
    {
        $params = ['section' => 'facturacion-cobros'];

        if ($administration) {
            $params['billing_administration_id'] = $administration->id;
        }

        if ($order) {
            $params['billing_order_id'] = $order->id;
        }

        return redirect()->route('configuration.index', $params);
    }
}
