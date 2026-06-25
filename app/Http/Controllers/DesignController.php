<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLotteryDrawDateGuard;
use Illuminate\Http\Request;
use App\Models\Entity;
use App\Models\Lottery;
use App\Models\Set;
use App\Models\Participation;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\DesignFormat;
use App\Models\DesignExternalInvitation;
use App\Models\DesignExternalInvitationFile;
use App\Models\PrintConfiguration;
use App\Models\PrintOrder;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Mail\DesignExternalInvitationMail;
use App\Mail\ManagementFeePaymentRequestMail;
use App\Models\EmailCommunicationLog;
use App\Models\Manager;
use App\Services\CommunicationEmailService;
use App\Support\FpdiPdfMerge;
use App\Support\GeneratedPdfCatalog;
use App\Services\DesignApprovalService;
use App\Services\ImageOptimizationService;
use App\Services\ManagementFeePaymentService;
use App\Services\ManagementFeeService;
use App\Services\AdministrationBillingService;
use App\Services\PrintQuoteService;
use App\Services\QrCodeService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class DesignController extends Controller
{
    use HandlesLotteryDrawDateGuard;
    use \App\Http\Controllers\Concerns\AutoSelectsPanelScope;

    // Paso 1: Seleccionar entidad
    public function selectEntity(Request $request)
    {
        if ($redirect = $this->redirectIfImplicitEntityForDesign($request)) {
            return $redirect;
        }

        $entities = Entity::forUser(auth()->user())->get();
        return view('design.add', compact('entities'));
    }

    // Paso 2: Seleccionar sorteo
    public function selectLottery($entity_id = null)
    {
        if (!$entity_id) {
            $entity_id = session('design_entity_id');
        }

        if ($entity_id) {
            session(['design_entity_id' => (int) $entity_id]);
        }

        if (!auth()->user()->canAccessEntity((int) $entity_id)) {
            abort(403, 'No tienes permisos para gestionar esta entidad.');
        }

        $entity = Entity::forUser(auth()->user())->findOrFail($entity_id);
        if ($redirect = $this->redirectIfEntityCannotDesign($entity)) {
            return $redirect;
        }
        
        // Mostrar solo sorteos que tienen sets asociados para esta entidad
        $lotteries = \App\Models\Lottery::whereHas('reserves', function($query) use ($entity_id) {
                $query->where('entity_id', $entity_id)
                      ->whereHas('sets', function($setQuery) {
                          $setQuery->where('status', 1); // Solo sets activos
                      });
            })
            ->openForOperations()
            ->whereDate('deadline_date', '!=', date('Y-m-d')) // Excluir sorteos de hoy
            ->orderBy('draw_date', 'desc')
            ->get();
            
        return view('design.add_lottery', compact('entity', 'lotteries'));
    }

    // Paso 3: Seleccionar set
    public function selectSet()
    {
        $entity_id = session('design_entity_id');
        $lottery_id = session('design_lottery_id');

        $entity = Entity::forUser(auth()->user())->findOrFail($entity_id);
        if ($redirect = $this->redirectIfEntityCannotDesign($entity)) {
            return $redirect;
        }
        $lottery = \App\Models\Lottery::findOrFail($lottery_id);
        // Buscar todos los sets de la entidad y sorteo (a través de la reserva)
        $sets = Set::forUser(auth()->user())
            ->where('entity_id', $entity_id)
            ->whereHas('reserve', function($q) use ($lottery_id) {
                $q->where('lottery_id', $lottery_id);
            })
            ->get();
        $setIds = $sets->pluck('id')->all();
        $setLocksBySetId = $this->batchDesignLockContextsForSetIds($setIds);
        $setAvailabilityBySetId = $this->batchSetDesignAvailabilityForSetIds($setIds, $sets);
        // Obtener la reserva principal (opcional, para la vista)
        $reserve = \App\Models\Reserve::forUser(auth()->user())
            ->where('entity_id', $entity_id)
            ->where('lottery_id', $lottery_id)
            ->first();
        return view('design.add_set', compact('entity', 'lottery', 'sets', 'reserve', 'setLocksBySetId', 'setAvailabilityBySetId'));
    }

    /**
     * Elegir tipo: Diseño (propio) o Diseño e impresión externo (tarea 9).
     * POST desde add_set con set_id.
     */
    public function chooseType(Request $request)
    {
        $request->validate(['set_id' => 'required|integer|exists:sets,id']);
        $entity_id = session('design_entity_id');
        if (!auth()->user()->canAccessEntity((int) $entity_id)) {
            abort(403, 'No tienes permisos para gestionar esta entidad.');
        }
        session(['design_set_id' => $request->set_id]);
        $entity = Entity::forUser(auth()->user())->findOrFail($entity_id);
        if ($redirect = $this->redirectIfEntityCannotDesign($entity)) {
            return $redirect;
        }
        return redirect()->route('design.showChooseType');
    }

    /**
     * Mostrar pantalla de elección: Diseño vs Diseño externo.
     */
    public function showChooseType()
    {
        $entity_id = session('design_entity_id');
        $lottery_id = session('design_lottery_id');
        $set_id = session('design_set_id');
        if (!$entity_id || !$lottery_id || !$set_id) {
            return redirect()->route('design.selectSet')->with('error', 'Debes seleccionar un set.');
        }
        if (!auth()->user()->canAccessEntity((int) $entity_id)) {
            abort(403, 'No tienes permisos para gestionar esta entidad.');
        }
        $entity = Entity::forUser(auth()->user())->findOrFail($entity_id);
        if ($redirect = $this->redirectIfEntityCannotDesign($entity)) {
            return $redirect;
        }
        $lottery = Lottery::findOrFail($lottery_id);
        $set = Set::forUser(auth()->user())->findOrFail($set_id);
        $designLock = $this->getSetDesignLockContext($set);

        return view('design.choose_type', compact('entity', 'lottery', 'set', 'designLock'));
    }

    /**
     * Paso 1 diseño externo: Indicaciones / Archivos.
     */
    public function externalStep1(Request $request)
    {
        $this->ensureDesignSession();
        $mode = $request->query('mode', session('design_external_mode', 'external'));
        if (!in_array($mode, ['external', 'partilot'], true)) {
            $mode = 'external';
        }
        session(['design_external_mode' => $mode]);
        $entity = Entity::forUser(auth()->user())->findOrFail(session('design_entity_id'));
        $lottery = Lottery::findOrFail(session('design_lottery_id'));
        $set = Set::forUser(auth()->user())->findOrFail(session('design_set_id'));
        $invitation = null;
        if (session('design_external_invitation_id')) {
            $invitation = DesignExternalInvitation::with('files')->where('created_by_user_id', auth()->id())->find(session('design_external_invitation_id'));
        }
        return view('design.external_step1', compact('entity', 'lottery', 'set', 'invitation', 'mode'));
    }

    /**
     * Paso 2 diseño externo: Invitación (email).
     */
    public function externalStep2()
    {
        $this->ensureDesignSession();
        $mode = session('design_external_mode', 'external');
        $invitationId = session('design_external_invitation_id');
        if (!$invitationId) {
            return redirect()->route('design.external.step1')->with('error', 'Completa primero el paso de indicaciones y archivos.');
        }
        $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())->findOrFail($invitationId);
        $entity = $invitation->entity;
        $lottery = $invitation->lottery;
        $set = $invitation->set;

        $selectedPrintShop = null;
        $quote = null;

        if ($mode === 'partilot') {
            $selectedPrintShop = PrintConfiguration::resolveDefault();
            $invitation->update(['print_configuration_id' => $selectedPrintShop->id]);
            $quote = $this->calculateExternalInvitationQuote($set, $invitation);
            $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContextForEntity($entity, auth()->user());
            if (empty($printPayment['user_may_submit'])) {
                return redirect()->route('design.external.step1')
                    ->with('warning', $printPayment['user_submit_block_reason'] ?? 'No puede gestionar el pago de este pedido.');
            }
        } else {
            $printPayment = null;
        }

        return view('design.external_step2', compact(
            'entity',
            'lottery',
            'set',
            'invitation',
            'quote',
            'mode',
            'selectedPrintShop',
            'printPayment'
        ));
    }

    public function externalPreviewQuote(Request $request)
    {
        $this->ensureDesignSession();
        if (session('design_external_mode', 'external') !== 'partilot') {
            return response()->json(['ok' => false, 'message' => 'Modo inválido.'], 422);
        }

        $request->validate([
            'print_configuration_id' => ['nullable', 'integer', 'exists:print_configurations,id'],
        ]);

        $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())
            ->findOrFail(session('design_external_invitation_id'));
        $cfg = PrintConfiguration::resolveDefault();
        $invitation->print_configuration_id = $cfg->id;
        $quote = $this->calculateExternalInvitationQuote($invitation->set, $invitation);
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContextForEntity($invitation->entity, auth()->user());
        [$publishableKey, $secretKey] = $this->resolveStripeKeys($cfg);

        return response()->json([
            'ok' => true,
            'quote' => $quote,
            'print_payment' => $printPayment,
            'stripe_payment_enabled' => $cfg->hasStripeConfigured() && ! empty($printPayment['can_pay_stripe']),
            'stripe_publishable_key' => $publishableKey,
        ]);
    }

    public function externalAcceptSummary(Request $request)
    {
        $this->ensureDesignSession();
        if (session('design_external_mode', 'external') !== 'partilot') {
            return redirect()->route('design.external.step2');
        }

        $request->validate([
            'print_configuration_id' => ['nullable', 'integer', 'exists:print_configurations,id'],
        ]);

        $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())
            ->findOrFail(session('design_external_invitation_id'));
        $cfg = PrintConfiguration::resolveDefault();
        $quote = $this->calculateExternalInvitationQuote($invitation->set, $invitation);
        $invitation->update([
            'print_configuration_id' => $cfg->id,
            'quoted_amount' => $quote['total'],
            'quote_breakdown' => $quote,
        ]);

        return redirect()->route('design.external.step3');
    }

    /**
     * Paso 3 diseño PARTILOT: Pantalla de pago (mock Stripe-ready).
     */
    public function externalStep3()
    {
        $this->ensureDesignSession();
        $mode = session('design_external_mode', 'external');
        if ($mode !== 'partilot') {
            return redirect()->route('design.external.step2');
        }

        $invitationId = session('design_external_invitation_id');
        if (!$invitationId) {
            return redirect()->route('design.external.step1')->with('error', 'Completa primero el paso de indicaciones y archivos.');
        }

        $invitation = DesignExternalInvitation::with('printConfiguration')
            ->where('created_by_user_id', auth()->id())
            ->findOrFail($invitationId);

        if (! $invitation->print_configuration_id) {
            return redirect()->route('design.external.step2')
                ->with('error', 'No hay imprenta por defecto configurada. Revisa Ajustes → Imprenta.');
        }

        $entity = $invitation->entity;
        $lottery = $invitation->lottery;
        $set = $invitation->set;
        $quote = $this->calculateExternalInvitationQuote($set, $invitation);
        $selectedPrintShop = $this->printConfigurationForInvitation($invitation);
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContextForEntity($entity, auth()->user());
        if (empty($printPayment['user_may_submit'])) {
            return redirect()->route('design.external.step1')
                ->with('warning', $printPayment['user_submit_block_reason'] ?? 'No puede gestionar el pago de este pedido.');
        }
        [$stripePublishableKey, $stripeSecretKey] = $this->resolveStripeKeys($selectedPrintShop);
        $stripePaymentEnabled = $selectedPrintShop->hasStripeConfigured() && ! empty($printPayment['can_pay_stripe']);

        return view('design.external_step3', compact(
            'entity',
            'lottery',
            'set',
            'invitation',
            'quote',
            'mode',
            'selectedPrintShop',
            'stripePublishableKey',
            'stripePaymentEnabled',
            'printPayment'
        ));
    }

    public function externalCreatePaymentIntent(Request $request)
    {
        $this->ensureDesignSession();
        $mode = session('design_external_mode', 'external');
        if ($mode !== 'partilot') {
            return response()->json(['ok' => false, 'message' => 'Modo inválido para pago.'], 422);
        }

        $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())
            ->findOrFail(session('design_external_invitation_id'));
        $cfg = $this->printConfigurationForInvitation($invitation);
        $quote = $this->calculateExternalInvitationQuote($invitation->set, $invitation);
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContextForEntity($invitation->entity, auth()->user());

        if (empty($printPayment['can_pay_stripe'])) {
            return response()->json([
                'ok' => false,
                'message' => $printPayment['user_submit_block_reason'] ?? 'Este pedido no admite pago con tarjeta para su perfil.',
            ], 422);
        }

        [$publishableKey, $secretKey] = $this->resolveStripeKeys($cfg);
        if ($secretKey === '' || $publishableKey === '') {
            return response()->json(['ok' => false, 'message' => 'Stripe no configurado para esta imprenta.'], 500);
        }

        try {
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $response = $client->post('payment_intents', [
                'auth' => [$secretKey, ''],
                'form_params' => [
                    'amount' => (int) round(((float) $quote['total']) * 100),
                    'currency' => 'eur',
                    'description' => 'Diseño e Impresión PARTILOT',
                    'metadata[invitation_id]' => (string) $invitation->id,
                    'metadata[set_id]' => (string) $invitation->set_id,
                    'metadata[print_configuration_id]' => (string) $cfg->id,
                    'automatic_payment_methods[enabled]' => 'true',
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            if (!is_array($payload) || empty($payload['client_secret']) || empty($payload['id'])) {
                return response()->json(['ok' => false, 'message' => 'No se pudo crear PaymentIntent.'], 500);
            }

            session(['design_external_payment_intent_id' => (string) $payload['id']]);

            return response()->json([
                'ok' => true,
                'client_secret' => (string) $payload['client_secret'],
                'publishable_key' => $publishableKey,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe PaymentIntent error', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => 'Error creando PaymentIntent de Stripe.'], 500);
        }
    }

    /**
     * Guardar paso 1 (comentario + archivos) y redirigir a paso 2.
     */
    public function externalStoreStep1(Request $request)
    {
        $this->ensureDesignSession();
        $request->validate([
            'comment' => 'nullable|string|max:5000',
            'print_size' => 'nullable|string|in:a3_6,a3_8,custom',
            'participations_per_book' => 'required|integer|min:1|max:1000',
            'back_mode' => 'nullable|string|in:bw,color',
            'files' => 'nullable|array|max:20',
            'files.*' => 'nullable|file|max:51200|mimes:pdf,doc,docx,jpg,jpeg,png,gif,webp,zip',
        ], [
            'files.*.max' => 'Cada archivo puede pesar como máximo 50 MB.',
            'files.*.mimes' => 'Formatos permitidos: PDF, Word, imágenes (jpg, png, gif, webp) y ZIP.',
        ]);
        $invitationId = session('design_external_invitation_id');
        if ($invitationId) {
            $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())->find($invitationId);
            if ($invitation && $invitation->status === DesignExternalInvitation::STATUS_PENDING) {
                $invitation->update([
                    'comment' => $request->comment,
                    'print_size' => $request->input('print_size', 'custom'),
                    'participations_per_book' => (int) $request->input('participations_per_book'),
                    'back_mode' => $request->input('back_mode', 'bw'),
                ]);
            } else {
                $invitation = null;
            }
        }
        if (!isset($invitation) || !$invitation) {
            $defaultPrintShop = PrintConfiguration::resolveDefault();
            $invitation = DesignExternalInvitation::create([
                'entity_id' => session('design_entity_id'),
                'lottery_id' => session('design_lottery_id'),
                'set_id' => session('design_set_id'),
                'print_configuration_id' => $defaultPrintShop->id,
                'created_by_user_id' => auth()->id(),
                'comment' => $request->comment,
                'print_size' => $request->input('print_size', 'custom'),
                'participations_per_book' => (int) $request->input('participations_per_book'),
                'back_mode' => $request->input('back_mode', 'bw'),
                'email' => null, // se rellena en el paso 2 (enviar invitación)
                'token' => DesignExternalInvitation::generateToken(),
                'orden_id' => DesignExternalInvitation::generateOrdenId(),
                'status' => DesignExternalInvitation::STATUS_PENDING,
            ]);
        }
        foreach ($request->file('files', []) as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('design_external/'.$invitation->id, 'public');
            DesignExternalInvitationFile::create([
                'design_external_invitation_id' => $invitation->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
            ]);
        }
        session(['design_external_invitation_id' => $invitation->id]);
        return redirect()->route('design.external.step2');
    }

    /**
     * Enviar invitación por email (paso 2).
     */
    public function externalSendInvitation(Request $request)
    {
        $mode = session('design_external_mode', 'external');
        $createdOrder = null;
        $rules = ['email' => 'required|email'];
        if ($mode === 'partilot') {
            $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())
                ->findOrFail(session('design_external_invitation_id'));
            $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContextForEntity($invitation->entity, auth()->user());
            if (empty($printPayment['user_may_submit'])) {
                return redirect()->back()->with('error', $printPayment['user_submit_block_reason'] ?? 'No puede gestionar el pago de este pedido.');
            }

            $rules['email'] = 'nullable|email';
            $rules['payment_method'] = 'nullable|in:stripe,remittance';
            if (! empty($printPayment['can_queue_remittance'])) {
                $rules['stripe_payment_intent_id'] = 'nullable|string';
            } else {
                $rules['stripe_payment_intent_id'] = 'required|string';
            }
        }
        $request->validate($rules, [
            'stripe_payment_intent_id.required' => 'No se encontró el pago de Stripe confirmado.',
        ]);
        $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())->findOrFail(session('design_external_invitation_id'));
        $invitation->loadMissing('set.reserve.lottery');
        if ($response = $this->redirectIfLotteryDrawDateBlocked($invitation->set?->reserve?->lottery)) {
            return $response;
        }
        $cfg = $this->printConfigurationForInvitation($invitation);
        $quote = $this->calculateExternalInvitationQuote($invitation->set, $invitation);
        $invitation->update([
            'email' => $mode === 'partilot' ? null : $request->email,
            'print_configuration_id' => $cfg->id,
            'quoted_amount' => $quote['total'],
            'quote_breakdown' => $quote,
        ]);

        if ($mode === 'partilot') {
            $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContextForEntity($invitation->entity, auth()->user());
            if (! empty($printPayment['can_queue_remittance'])
                && ($request->input('payment_method') === 'remittance' || empty($request->input('stripe_payment_intent_id')))) {
                return $this->submitExternalPartilotViaRemittance($invitation, $quote, $cfg);
            }

            $paymentIntentId = (string) $request->input('stripe_payment_intent_id');
            if ($paymentIntentId === '') {
                return redirect()->back()->with('error', 'No se encontró el pago de Stripe confirmado.');
            }
            if (! $this->isStripePaymentSucceeded($paymentIntentId, $cfg)) {
                return redirect()->back()->with('error', 'El pago no está confirmado en Stripe. Intenta nuevamente.');
            }

            $duplicateOrder = null;
            $createdOrder = null;
            $lock = Cache::lock('print-order-stripe-pi:'.sha1($paymentIntentId), 25);
            $lock->block(12);
            try {
                DB::transaction(function () use ($paymentIntentId, $invitation, $quote, $cfg, &$duplicateOrder, &$createdOrder) {
                    $existing = PrintOrder::query()
                        ->where('payment_intent_id', $paymentIntentId)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        $duplicateOrder = $existing;
                        $this->insertPrintOrderAuditRow(
                            printOrder: $existing,
                            action: 'duplicate_payment_intent_blocked',
                            message: 'Intento de registrar otra orden con el mismo PaymentIntent Stripe ('.$paymentIntentId.').',
                            userId: auth()->id()
                        );

                        return;
                    }

                    $design = DesignFormat::query()
                        ->where('entity_id', (int) $invitation->entity_id)
                        ->where('set_id', (int) $invitation->set_id)
                        ->orderByDesc('id')
                        ->first();

                    if (! $design) {
                        $design = DesignFormat::create([
                            'entity_id' => (int) $invitation->entity_id,
                            'lottery_id' => (int) $invitation->lottery_id,
                            'set_id' => (int) $invitation->set_id,
                            'output' => [
                                'participations_per_book' => (int) ($invitation->participations_per_book ?? 50),
                            ],
                        ]);
                    }

                    $orderCode = 'OPI'.str_pad((string) (PrintOrder::max('id') + 1), 6, '0', STR_PAD_LEFT);
                    $createdOrder = PrintOrder::create([
                        'print_configuration_id' => $cfg->id,
                        'order_code' => $orderCode,
                        'design_format_id' => (int) $design->id,
                        'set_id' => $invitation->set_id,
                        'entity_id' => $invitation->entity_id,
                        'lottery_id' => $invitation->lottery_id,
                        'created_by_user_id' => auth()->id(),
                        'status' => PrintOrder::STATUS_PENDING_REVIEW,
                        'payment_provider' => 'stripe',
                        'payment_intent_id' => $paymentIntentId,
                        'payment_status' => PrintOrder::PAYMENT_STATUS_PAID,
                        'print_size' => $invitation->print_size,
                        'participations_per_book' => $invitation->participations_per_book,
                        'back_mode' => $invitation->back_mode,
                        'quoted_amount' => $quote['total'],
                        'quote_breakdown' => $quote,
                        'notes' => trim((string) ($invitation->comment ?? ''))."\n[PAGO STRIPE] Flujo Diseño e Impresión PARTILOT.",
                        'sent_at' => null,
                        'paid_at' => now(),
                    ]);

                    $this->insertPrintOrderAuditRow(
                        printOrder: $createdOrder,
                        action: 'order_created_stripe',
                        message: 'Orden creada con pago Stripe confirmado. PI: '.$paymentIntentId,
                        userId: auth()->id()
                    );

                    $this->afterPrintOrderCreated($createdOrder, $design);
                });
            } finally {
                $lock->release();
            }

            if ($duplicateOrder) {
                $invitation->update(['status' => DesignExternalInvitation::STATUS_SENT, 'sent_at' => now()]);
                session()->forget(['design_external_invitation_id', 'design_external_mode']);

                return redirect()->route('design.external.list')->with(
                    'warning',
                    'Este pago ya tiene una orden de imprenta registrada ('.$duplicateOrder->order_code.'). No se ha duplicado.'
                );
            }
        } else {
            $communicationEmailService = app(CommunicationEmailService::class);
            $log = $communicationEmailService->sendAndLog(
                recipientEmail: (string) $request->email,
                recipientRole: 'diseñador_externo',
                recipientUser: null,
                messageType: 'design_external_invitation',
                templateKey: null,
                mailClass: \App\Mail\DesignExternalInvitationMail::class,
                mailPayload: ['invitation_id' => $invitation->id],
                context: ['invitation_id' => $invitation->id],
            );

            if ($log->status === EmailCommunicationLog::STATUS_CANCELLED) {
                return redirect()->back()->withInput()->with('error', 'No se pudo enviar el correo. Comprueba la configuración de correo (MAIL_*) en .env.');
            }
        }

        $invitation->update(['status' => DesignExternalInvitation::STATUS_SENT, 'sent_at' => now()]);
        session()->forget(['design_external_invitation_id', 'design_external_mode']);

        $partilotSuccess = 'Pago confirmado y orden de imprenta registrada correctamente.';
        if ($mode === 'partilot' && ! empty($createdOrder) && ! $createdOrder->fresh()->isVisibleToPrintShop()) {
            $partilotSuccess = 'Pago confirmado. La imprenta recibirá el pedido cuando la entidad abone la cuota de gestión PARTILOT.';
        }

        return redirect()->route('design.external.list')->with(
            'success',
            $mode === 'partilot'
                ? $partilotSuccess
                : ('Invitación enviada a ' . $request->email)
        );
    }

    /**
     * Auditoría de pedido imprenta (pagos, duplicados, creación). Usa la misma tabla que los cambios de estado.
     */
    private function insertPrintOrderAuditRow(
        PrintOrder $printOrder,
        string $action,
        ?string $message,
        ?int $userId
    ): void {
        try {
            DB::table('print_order_status_audits')->insert([
                'print_order_id' => $printOrder->id,
                'entity_id' => $printOrder->entity_id,
                'set_id' => $printOrder->set_id,
                'design_format_id' => $printOrder->design_format_id,
                'user_id' => $userId,
                'action' => $action,
                'from_status' => null,
                'to_status' => null,
                'message' => $message ?? $action,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Auditoría pedido imprenta: '.$e->getMessage(), ['order_id' => $printOrder->id, 'action' => $action]);
        }
    }

    private function isStripePaymentSucceeded(string $paymentIntentId, ?PrintConfiguration $cfg = null): bool
    {
        if ($paymentIntentId === '') {
            return false;
        }

        [, $secretKey] = $this->resolveStripeKeys($cfg);
        if ($secretKey === '') {
            return false;
        }

        try {
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $response = $client->get('payment_intents/' . $paymentIntentId, [
                'auth' => [$secretKey, ''],
            ]);
            $payload = json_decode((string) $response->getBody(), true);
            return is_array($payload) && (($payload['status'] ?? '') === 'succeeded');
        } catch (\Throwable $e) {
            Log::error('Stripe verify payment error', ['error' => $e->getMessage(), 'pi' => $paymentIntentId]);
            return false;
        }
    }

    private function resolveActivePrintConfiguration(?int $id = null): PrintConfiguration
    {
        return PrintConfiguration::resolveDefault();
    }

    private function resolveStripeKeys(?PrintConfiguration $cfg = null): array
    {
        $cfg ??= PrintConfiguration::resolveDefault();

        if (! $cfg->hasStripeConfigured()) {
            return ['', ''];
        }

        return [$cfg->stripePublishableKey(), $cfg->stripeSecretKey()];
    }

    private function printConfigurationForInvitation(DesignExternalInvitation $invitation): PrintConfiguration
    {
        return PrintConfiguration::resolveDefault();
    }

    /**
     * Presupuesto para invitación externa / flujo PARTILOT con pago: incluye tarifa de diseño.
     */
    private function calculateExternalInvitationQuote(Set $set, DesignExternalInvitation $invitation): array
    {
        $cfg = $this->printConfigurationForInvitation($invitation);

        return app(PrintQuoteService::class)->calculateForExternalInvitation($set, $cfg, $invitation);
    }

    /**
     * Listado de invitaciones de diseño externo (tabla como en captura).
     * Solo se muestran invitaciones de entidades accesibles por el usuario (respeta rol contexto).
     */
    public function externalList()
    {
        $entityIds = auth()->user()->accessibleEntityIds();
        $invitations = DesignExternalInvitation::where('created_by_user_id', auth()->id())
            ->whereIn('entity_id', $entityIds)
            ->with(['entity', 'set', 'lottery', 'files'])
            ->orderBy('created_at', 'desc')
            ->get();
        return view('design.external_list', compact('invitations'));
    }

    public function externalDestroy($id)
    {
        $invitation = DesignExternalInvitation::where('created_by_user_id', auth()->id())->findOrFail($id);
        if (!auth()->user()->canAccessEntity((int) $invitation->entity_id)) {
            abort(403, 'No tienes permisos para gestionar esta invitación.');
        }
        foreach ($invitation->files as $f) {
            Storage::disk('public')->delete($f->path);
        }
        $invitation->files()->delete();
        $invitation->delete();
        return redirect()->route('design.external.list')->with('success', 'Invitación eliminada.');
    }

    /**
     * Entrada por enlace de invitación (token). Pública; si no está logueado redirige a login.
     */
    public function externalInviteByToken(string $token)
    {
        $invitation = DesignExternalInvitation::where('token', $token)->first();

        if (!$invitation) {
            abort(404, 'Invitación no encontrada o enlace caducado.');
        }
        if ($invitation->isExpired()) {
            abort(410, 'El enlace de invitación ha caducado. Solicita uno nuevo.');
        }

        session([
            'design_entity_id' => $invitation->entity_id,
            'design_lottery_id' => $invitation->lottery_id,
            'design_set_id' => $invitation->set_id,
            'design_external_invitation_id' => $invitation->id,
        ]);

        if (in_array($invitation->status, [DesignExternalInvitation::STATUS_PENDING, DesignExternalInvitation::STATUS_SENT], true)) {
            $invitation->update(['status' => DesignExternalInvitation::STATUS_IN_PROGRESS]);
        }

        return redirect()->route('design.external.editor');
    }

    /**
     * Editor de diseño para invitado (usa sesión de invitación).
     */
    public function externalEditor()
    {
        $invitationId = session('design_external_invitation_id');
        if (!$invitationId) {
            return redirect()->to(url('/'))->with('error', 'Sesión de invitación no encontrada. Use el enlace que recibió por correo.');
        }

        $invitation = DesignExternalInvitation::with(['entity', 'set', 'lottery', 'files'])->find($invitationId);
        if (! $invitation) {
            session()->forget(['design_entity_id', 'design_lottery_id', 'design_set_id', 'design_external_invitation_id']);
            return redirect()->to(url('/'))->with('error', 'Invitación no encontrada o expirada.');
        }
        if ($invitation->isExpired()) {
            session()->forget(['design_entity_id', 'design_lottery_id', 'design_set_id', 'design_external_invitation_id']);
            return redirect()->to(url('/'))->with('error', 'El enlace de invitación ha caducado. Solicita uno nuevo.');
        }

        $entity = $invitation->entity;
        $lottery = $invitation->lottery;
        $set = $invitation->set;
        if (!$entity || !$lottery || !$set) {
            return redirect()->to(url('/'))->with('error', 'Datos de la invitación incompletos.');
        }

        $reservation_numbers = $set->reserve ? $set->reserve->reservation_numbers : [];
        $isDigitalSet = $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
        $entityId = $entity->id;
        $design = null;
        $reserveId = $set->reserve_id ?? null;
        if ($reserveId) {
            $design = DesignFormat::where('entity_id', $entityId)
                ->whereHas('set', fn ($q) => $q->where('reserve_id', $reserveId))
                ->first();
        }
        if ($design) {
            $design = $this->hydrateDesignHtmlFromBlocks($design);
        }

        return view('design.format', [
            'entity' => $entity,
            'lottery' => $lottery,
            'set' => $set,
            'reservation_numbers' => $reservation_numbers,
            'isDigitalSet' => $isDigitalSet,
            'design' => $design,
            'layout' => 'layouts.layout_external_design',
            'save_format_url' => route('design.external.saveFormat'),
            'redirect_after_save' => route('design.external.thankYou'),
            'externalInvitation' => $invitation,
        ]);
    }

    /**
     * Descarga de archivo adjunto (diseñador con sesión de invitación activa).
     */
    public function externalDownloadFileSession(int $id)
    {
        $file = DesignExternalInvitationFile::findOrFail($id);
        $invitationId = session('design_external_invitation_id');
        if (! $invitationId || (int) $file->design_external_invitation_id !== (int) $invitationId) {
            abort(403, 'No autorizado.');
        }
        if (! Storage::disk('public')->exists($file->path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('public')->download($file->path, $file->original_name ?: basename($file->path));
    }

    /**
     * Descarga de archivo adjunto (quien creó la invitación, desde el panel).
     */
    public function externalDownloadFileAuth(int $invitation, int $file)
    {
        $inv = DesignExternalInvitation::where('created_by_user_id', auth()->id())->findOrFail($invitation);
        if (! auth()->user()->canAccessEntity((int) $inv->entity_id)) {
            abort(403);
        }
        $row = $inv->files()->where('id', $file)->firstOrFail();
        if (! Storage::disk('public')->exists($row->path)) {
            abort(404, 'Archivo no encontrado.');
        }

        return Storage::disk('public')->download($row->path, $row->original_name ?: basename($row->path));
    }

    /**
     * Guardar diseño desde invitación (ruta pública; valida sesión de invitación).
     */
    public function externalSaveFormat(Request $request)
    {
        $invitationId = session('design_external_invitation_id');
        if (!$invitationId) {
            return response()->json(['success' => false, 'message' => 'Sesión de invitación no encontrada.'], 403);
        }
        $invitation = DesignExternalInvitation::find($invitationId);
        if (!$invitation) {
            return response()->json(['success' => false, 'message' => 'Invitación no válida.'], 403);
        }
        // Asegurar que el request tenga entity/set de la invitación (por si el front no los manda)
        $request->merge([
            'design_entity_id' => $request->input('design_entity_id') ?: $invitation->entity_id,
            'design_lottery_id' => $request->input('design_lottery_id') ?: $invitation->lottery_id,
        ]);
        if (!$request->has('set_id')) {
            $request->merge(['set_id' => $invitation->set_id]);
        }
        return $this->saveFormat($request);
    }

    /**
     * Página de agradecimiento tras guardar el diseño por invitación.
     */
    public function externalThankYou()
    {
        session()->forget(['design_entity_id', 'design_lottery_id', 'design_set_id', 'design_external_invitation_id', 'print_shop_order_id']);
        return view('design.external_thank_you');
    }

    /**
     * Abre el editor de diseño desde el panel de imprenta.
     */
    public function printShopOpenDesign(Request $request, PrintOrder $printOrder)
    {
        $printOrder->loadMissing(['design', 'set.reserve', 'entity', 'lottery']);
        $this->assertPrintShopMayDesignOrder($printOrder);

        session([
            'print_shop_order_id' => $printOrder->id,
            'design_entity_id' => $printOrder->entity_id,
            'design_lottery_id' => $printOrder->lottery_id,
            'design_set_id' => $printOrder->set_id,
        ]);

        $design = $printOrder->design;
        if (! $design) {
            return redirect()->route('print-shop.orders.show', $printOrder->id)
                ->with('error', 'Esta orden no tiene un diseño vinculado.');
        }

        $approvalService = app(DesignApprovalService::class);
        if (! $approvalService->designHasParticipationContent($design)) {
            $subRequest = Request::create(route('design.format'), 'POST', [
                'set_id' => $printOrder->set_id,
                'new_design' => 1,
            ]);
            $subRequest->setLaravelSession(session());

            return $this->format($subRequest);
        }

        $format = $this->hydrateDesignHtmlFromBlocks($design);
        $set = $printOrder->set;
        $reservation_numbers = $set && $set->reserve ? $set->reserve->reservation_numbers : [];
        $isDigitalSet = $set && $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
        $printShopOrder = $printOrder;
        $update_format_url = route('print-shop.orders.update-design', $printOrder->id);

        return view('design.edit_format', compact(
            'format',
            'set',
            'reservation_numbers',
            'isDigitalSet',
            'printShopOrder',
            'update_format_url'
        ));
    }

    public function printShopSaveFormat(Request $request, PrintOrder $printOrder)
    {
        $printOrder->loadMissing('design');
        $this->assertPrintShopMayDesignOrder($printOrder);

        session([
            'print_shop_order_id' => $printOrder->id,
            'design_entity_id' => $printOrder->entity_id,
            'design_lottery_id' => $printOrder->lottery_id,
            'design_set_id' => $printOrder->set_id,
        ]);

        $request->merge([
            'design_entity_id' => $printOrder->entity_id,
            'design_lottery_id' => $printOrder->lottery_id,
            'set_id' => $printOrder->set_id,
            'design_id' => $printOrder->design_format_id,
        ]);

        return $this->saveFormat($request);
    }

    public function printShopUpdateFormat(Request $request, PrintOrder $printOrder)
    {
        $printOrder->loadMissing('design');
        $this->assertPrintShopMayDesignOrder($printOrder);

        session(['print_shop_order_id' => $printOrder->id]);

        $design = $printOrder->design;
        if (! $design) {
            abort(404, 'Diseño no encontrado para esta orden.');
        }

        return $this->updateFormat($request, $design->id);
    }

    private function ensureDesignSession()
    {
        if (!session('design_entity_id') || !session('design_lottery_id') || !session('design_set_id')) {
            abort(redirect()->route('design.selectSet')->with('error', 'Sesión de diseño perdida. Selecciona de nuevo el set.'));
        }
    }

    // Paso 4: Mostrar formato final
    public function format(Request $request)
    {
        $entityId = session('design_entity_id');
        $byInvitation = (bool) session('design_external_invitation_id');
        $printShopOrder = $this->resolveAuthorizedPrintShopOrder();
        $byPrintShop = $printShopOrder !== null;

        if ($byPrintShop) {
            $entity = Entity::findOrFail($printShopOrder->entity_id);
            $set = Set::findOrFail($printShopOrder->set_id);
            $entityId = (int) $printShopOrder->entity_id;
            session([
                'design_entity_id' => $printShopOrder->entity_id,
                'design_lottery_id' => $printShopOrder->lottery_id,
                'design_set_id' => $printShopOrder->set_id,
            ]);
        } elseif ($byInvitation) {
            $invitation = DesignExternalInvitation::find(session('design_external_invitation_id'));
            if (!$invitation || $invitation->entity_id != $entityId || $invitation->set_id != (int) $request->set_id) {
                abort(403, 'Invitación no válida para este diseño.');
            }
            $entity = Entity::findOrFail($entityId);
            $set = Set::findOrFail($request->set_id);
        } else {
            if (!auth()->user()->canAccessEntity((int) $entityId)) {
                abort(403, 'No tienes permisos para gestionar esta entidad.');
            }
            $entity = Entity::forUser(auth()->user())->findOrFail($entityId);
            $set = Set::forUser(auth()->user())->findOrFail($request->set_id);
        }

        if (! $byInvitation && ! $byPrintShop) {
            if ($redirect = $this->redirectIfEntityCannotDesign($entity)) {
                return $redirect;
            }

            $approvalService = app(DesignApprovalService::class);
            $feeService = app(ManagementFeeService::class);
            if ($feeService->blocksAdminDesignUntilEntityPays($set)) {
                $lottery = Lottery::findOrFail(session('design_lottery_id'));
                $placeholder = $this->ensurePlaceholderDesign($set, $entity, (int) $lottery->id);

                if ($approvalService->isAdministrationSideUser(auth()->user())) {
                    if ($approvalService->entityDesignEnabled($entity)) {
                        return redirect()->route('design.summary', $placeholder->id)
                            ->with('warning', 'La entidad debe pagar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
                    }

                    $this->notifyEntityManagementFeePaymentRequired($placeholder);

                    return redirect()->route('design.summary', $placeholder->id)
                        ->with('warning', 'La entidad debe pagar la cuota de gestión PARTILOT antes de que la administración pueda continuar con el diseño.');
                }

                return redirect()->route('design.managementFee.pay', $set->id)
                    ->with('info', 'Debe confirmar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
            }
        }

        if (
            ! $byInvitation
            && ! $byPrintShop
            && $request->boolean('new_design')
            && $this->getSetDesignLockContext($set)['locked']
        ) {
            return redirect()->route('design.index')
                ->with('error', 'No se puede iniciar un diseño nuevo: el set tiene participaciones comprometidas y el diseño está bloqueado.');
        }

        $lottery = Lottery::findOrFail(session('design_lottery_id'));
        $reservation_numbers = $set->reserve ? $set->reserve->reservation_numbers : [];
        $isDigitalSet = $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
        // Un diseño por set: designFormatId = id a actualizar al guardar; $design = HTML a mostrar (puede ser plantilla de otro set).
        $design = null;
        $designFormatId = null;
        $existingForSet = DesignFormat::where('entity_id', $entityId)
            ->where('set_id', $set->id)
            ->orderByDesc('updated_at')
            ->first();

        if ($request->filled('design_id')) {
            $candidate = DesignFormat::where('id', $request->design_id)
                ->where('entity_id', $entityId)
                ->first();
            if ($candidate) {
                $design = $candidate;
                if ((int) $candidate->set_id === (int) $set->id) {
                    $designFormatId = $candidate->id;
                }
            }
        }

        if ($request->boolean('new_design')) {
            if ($existingForSet) {
                $design = $existingForSet;
                $designFormatId = $existingForSet->id;
                $forceFreshDraft = ! app(DesignApprovalService::class)->designHasParticipationContent($existingForSet);
            } else {
                $forceFreshDraft = true;
            }
        } elseif ($existingForSet) {
            $design = $existingForSet;
            $designFormatId = $existingForSet->id;
        } elseif (! $design) {
            $reserveId = $set->reserve_id ?? null;
            if ($reserveId) {
                $design = DesignFormat::where('entity_id', $entityId)
                    ->where('set_id', '!=', $set->id)
                    ->whereHas('set', fn ($q) => $q->where('reserve_id', $reserveId))
                    ->orderByDesc('updated_at')
                    ->first();
            }
        }
        if ($design) {
            $design = $this->hydrateDesignHtmlFromBlocks($design);
        }
        $designLock = $this->getSetDesignLockContext($set);
        $forceFreshDraft = $forceFreshDraft ?? (bool) $request->filled('new_design');
        $loadedFromPicker = $request->filled('design_id');
        if ($loadedFromPicker && $design) {
            $forceFreshDraft = false;
        }

        $externalInvitation = null;
        if ($byPrintShop) {
            $externalInvitation = DesignExternalInvitation::query()
                ->where('set_id', $printShopOrder->set_id)
                ->where('entity_id', $printShopOrder->entity_id)
                ->when($printShopOrder->print_configuration_id, function ($query) use ($printShopOrder) {
                    $query->where('print_configuration_id', $printShopOrder->print_configuration_id);
                })
                ->with('files')
                ->orderByDesc('id')
                ->first();
        }

        return view('design.format', [
            'entity' => $entity,
            'lottery' => $lottery,
            'set' => $set,
            'reservation_numbers' => $reservation_numbers,
            'isDigitalSet' => $isDigitalSet,
            'design' => $design,
            'designFormatId' => $designFormatId,
            'designLock' => $designLock,
            'forceFreshDraft' => $forceFreshDraft,
            'loadedFromPicker' => $loadedFromPicker,
            'save_format_url' => $byPrintShop
                ? route('print-shop.orders.save-design', $printShopOrder->id)
                : route('design.saveFormat'),
            'redirect_after_save' => $byPrintShop
                ? route('print-shop.orders.show', $printShopOrder->id)
                : null,
            'printShopOrder' => $byPrintShop ? $printShopOrder : null,
            'externalInvitation' => $externalInvitation,
        ]);
    }

    // Tarea 8: listado de diseños de la entidad para reutilizar
    public function listFormats(Request $request)
    {
        $entityId = session('design_entity_id');
        if (! $entityId || ! auth()->user()->canAccessEntity((int) $entityId)) {
            abort(403, 'No tienes permisos para gestionar esta entidad.');
        }
        $request->validate(['set_id' => 'required|integer|exists:sets,id']);
        $set = Set::forUser(auth()->user())->findOrFail($request->set_id);
        session(['design_set_id' => $set->id]);
        $designs = DesignFormat::where('entity_id', $entityId)
            ->with('set.reserve')
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();
        $currentSetLock = $this->getSetDesignLockContext($set);

        return view('design.list_formats', compact('designs', 'set', 'currentSetLock'));
    }

    // Guardar selección de entidad en sesión y redirigir a selección de sorteo
    public function storeEntity(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id'
        ]);
        $entity_id = $request->entity_id;

        if (!auth()->user()->canAccessEntity((int) $entity_id)) {
            abort(403, 'No tienes permisos para gestionar esta entidad.');
        }

        $entity = Entity::forUser(auth()->user())->findOrFail($entity_id);
        if ($entity->status != 1) {
            return redirect()->back()->with('error', 'Solo se puede seleccionar una entidad activa.');
        }

        session(['design_entity_id' => $entity_id]);
        return redirect()->route('design.selectLottery');
    }

    // Guardar selección de sorteo y redirigir a selección de set
    public function storeLottery(Request $request)
    {
        $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'lottery_id' => 'required|integer|exists:lotteries,id'
        ]);

        if (!auth()->user()->canAccessEntity((int) $request->entity_id)) {
            abort(403, 'No tienes permisos para gestionar esta entidad.');
        }

        $lottery = Lottery::findOrFail($request->lottery_id);
        if ($response = $this->redirectIfLotteryDrawDateBlocked($lottery)) {
            return $response;
        }

        session(['design_entity_id' => $request->entity_id]);
        session(['design_lottery_id' => $request->lottery_id]);

        return redirect()->route('design.selectSet');
    }
/*
    // Guardar el formato de diseño enviado desde la vista
    public function storeFormat(Request $request)
    {
        $data = $request->validate([
            'entity_id' => 'required|integer|exists:entities,id',
            'lottery_id' => 'required|integer|exists:lotteries,id',
            'set_id' => 'required|integer|exists:sets,id',
            'format' => 'nullable|string',
            'page' => 'nullable|string',
            'rows' => 'nullable|integer',
            'cols' => 'nullable|integer',
            'orientation' => 'nullable|string',
            'margin_up' => 'nullable|numeric',
            'margin_right' => 'nullable|numeric',
            'margin_left' => 'nullable|numeric',
            'margin_top' => 'nullable|numeric',
            'identation' => 'nullable|numeric',
            'matrix_box' => 'nullable|numeric',
            'page_rigth' => 'nullable|numeric',
            'page_bottom' => 'nullable|numeric',
            'guide_color' => 'nullable|string',
            'guide_weight' => 'nullable|numeric',
            'participation_number' => 'nullable|integer',
            'participation_from' => 'nullable|integer',
            'participation_to' => 'nullable|integer',
            'participation_page' => 'nullable|integer',
            'guides' => 'nullable|boolean',
            'generate' => 'nullable|string',
            'documents' => 'nullable|string',
            'blocks' => 'nullable|json',
        ]);

        // Decodificar blocks si viene como string JSON
        if (isset($data['blocks']) && is_string($data['blocks'])) {
            $data['blocks'] = json_decode($data['blocks'], true);
        }

        $designFormat = DesignFormat::create($data);

        return redirect()->back()->with('success', 'Formato guardado correctamente.');
    }*/

    // Guardar el formato de diseño enviado desde el frontend (API)
    public function saveFormat(Request $request)
    {
        $data = $request->validate([
            'design_id' => 'nullable|integer|exists:design_formats,id',
            'expected_updated_at' => 'nullable|string',
            'save_reason' => 'nullable|string|in:manual-save,autosave,final-save',
            'format' => 'nullable|string',
            'page' => 'nullable|string',
            'rows' => 'nullable|integer',
            'cols' => 'nullable|integer',
            'orientation' => 'nullable|string',
            'margins' => 'nullable|array',
            'margin_custom' => 'nullable|numeric',
            'identation' => 'nullable|numeric',
            'matrix_box' => 'nullable|numeric',
            'horizontal_space' => 'nullable|numeric',
            'vertical_space' => 'nullable|numeric',
            'participation_html' => 'nullable|string',
            'cover_html' => 'nullable|string',
            'back_html' => 'nullable|string',
            'back_skipped' => 'nullable|boolean',
            'design_name' => 'nullable|string|max:120',
            'backgrounds' => 'nullable|array',
            'output' => 'nullable|array',
            'snapshot_path' => 'nullable|string',
        ]);

        $data['set_id'] = $request->input('set_id', 1);
        $data['entity_id'] = $request->design_entity_id ?? 1;
        $data['lottery_id'] = $request->design_lottery_id ?? 1;

        $entityForSave = Entity::query()->find((int) $data['entity_id']);
        $approvalService = app(DesignApprovalService::class);
        $saveUser = auth()->user();
        if ($entityForSave && $saveUser
            && $saveUser->isEntity()
            && ! $approvalService->isAdministrationSideUser($saveUser)
            && $approvalService->administrationDesignOnly($entityForSave)) {
            return response()->json([
                'success' => false,
                'message' => 'Para esta entidad el diseño lo realiza la administración.',
                'code' => 'ENTITY_DESIGN_DISABLED',
            ], 403);
        }

        // Asegurar backgrounds desde el request
        $requestBackgrounds = $request->input('backgrounds');
        if (is_array($requestBackgrounds) && ! empty($requestBackgrounds)) {
            $data['backgrounds'] = $requestBackgrounds;
        } elseif (! is_array($data['backgrounds'] ?? null)) {
            $data['backgrounds'] = [];
        }
        // Guardar los bloques de diseño y configuración en el campo blocks (JSON)
        $data['blocks'] = [
            'participation_html' => $data['participation_html'] ?? '',
            'cover_html' => $data['cover_html'] ?? '',
            'back_html' => $data['back_html'] ?? '',
            'backgrounds' => $data['backgrounds'],
            'output' => $data['output'] ?? [],
            'margins' => $data['margins'] ?? [],
        ];
        $data['participation_html'] = $data['blocks']['participation_html'];
        $data['cover_html'] = $data['blocks']['cover_html'];
        $data['back_html'] = $data['blocks']['back_html'];
        $data['output'] = $data['blocks']['output'];
        $data['margins'] = $data['blocks']['margins'];
        $data['snapshot_path'] = $data['snapshot_path'] ?? null;

        $set = Set::find($data['set_id'] ?? null);
        $byPrintShop = (bool) session('print_shop_order_id');
        if ($set && ! $byPrintShop) {
            $set->loadMissing('entity');
            if (app(ManagementFeeService::class)->blocksAdminDesignUntilEntityPays($set)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La cuota de gestión PARTILOT debe confirmarse antes de guardar el diseño.',
                    'code' => 'MANAGEMENT_FEE_REQUIRED',
                ], 402);
            }

            $set->loadMissing('reserve.lottery');
            if ($response = $this->jsonIfLotteryDrawDateBlocked($set->reserve?->lottery)) {
                return $response;
            }
            $designLock = $this->getSetDesignLockContext($set);
            $existingForLock = $this->resolveDesignFormatForSave($data, $request->input('design_id'));
            $lockApplies = $designLock['locked']
                && (! $existingForLock || app(DesignApprovalService::class)->operationalDesignLockApplies(
                    auth()->user(),
                    $existingForLock,
                    $designLock
                ));
            if ($lockApplies) {
                $this->logDesignLockAudit($set, 'save_format_blocked', $designLock);
                return response()->json([
                    'success' => false,
                    'message' => $designLock['message'],
                    'code' => 'SET_DESIGN_LOCKED',
                ], 422);
            }
        }
        if ($set && $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0) {
            $data['output']['participations_per_book'] = (int) $set->total_participations;
        }
        $data['output'] = DesignFormat::mergeTacoQrsIntoOutput($data['set_id'] ?? null, $data['output'] ?? []);

        $existing = $this->resolveDesignFormatForSave($data, $request->input('design_id'));
        if ($existing) {
            $conflict = $this->designFormatConflictResponse($existing, $request->input('expected_updated_at'));
            if ($conflict) {
                return $conflict;
            }

            $this->fillDesignFormatFromSaveData($existing, $data);
            if (! app(DesignApprovalService::class)->canEntityEditDesign(auth()->user(), $existing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este diseño fue creado por la administración. La entidad solo puede aprobarlo o rechazarlo.',
                    'code' => 'DESIGN_ENTITY_READ_ONLY',
                ], 403);
            }
            app(DesignApprovalService::class)->assignDesignerTypeIfMissing($existing, $this->resolveDesignSaveUser());
            $existing->save();
            if (in_array($request->input('save_reason'), ['manual-save', 'final-save'], true)) {
                app(DesignApprovalService::class)->invalidateApprovalAfterEdit($existing->refresh());
            }
            $this->linkInvitationToDesignIfNeeded($existing->id);

            return response()->json([
                'success' => true,
                'id' => $existing->id,
                'updated_at' => optional($existing->updated_at)->toISOString(),
            ]);
        }

        $designFormat = DesignFormat::create($data);
        app(DesignApprovalService::class)->assignDesignerTypeIfMissing($designFormat, $this->resolveDesignSaveUser());
        $this->linkInvitationToDesignIfNeeded($designFormat->id);
        return response()->json([
            'success' => true,
            'id' => $designFormat->id,
            'updated_at' => optional($designFormat->updated_at)->toISOString(),
        ]);
    }

    /**
     * Un solo diseño por set: actualizar por design_id válido o por set_id existente.
     */
    private function resolveDesignFormatForSave(array $data, mixed $designIdFromRequest): ?DesignFormat
    {
        $setId = (int) ($data['set_id'] ?? 0);
        $entityId = (int) ($data['entity_id'] ?? 0);
        if ($setId <= 0 || $entityId <= 0) {
            return null;
        }

        if ($designIdFromRequest) {
            $byId = DesignFormat::find($designIdFromRequest);
            if ($byId
                && (int) $byId->entity_id === $entityId
                && (int) $byId->set_id === $setId) {
                return $byId;
            }
        }

        return DesignFormat::where('entity_id', $entityId)
            ->where('set_id', $setId)
            ->orderByDesc('updated_at')
            ->first();
    }

    private function designFormatConflictResponse(DesignFormat $existing, ?string $expectedUpdatedAt): ?\Illuminate\Http\JsonResponse
    {
        // if (! $expectedUpdatedAt) {
        //     return null;
        // }

        // $currentUpdatedAt = optional($existing->updated_at)->toISOString();
        // if ($currentUpdatedAt && $currentUpdatedAt !== $expectedUpdatedAt) {
        //     return response()->json([
        //         'success' => false,
        //         'code' => 'DESIGN_CONFLICT',
        //         'message' => 'El diseño fue actualizado desde otra sesión. Recarga antes de continuar para evitar sobreescritura.',
        //         'current_updated_at' => $currentUpdatedAt,
        //     ], 409);
        // }

        return null;
    }

    private function fillDesignFormatFromSaveData(DesignFormat $existing, array $data): void
    {
        $existing->format = $data['format'] ?? $existing->format;
        $existing->page = $data['page'] ?? $existing->page;
        $existing->rows = $data['rows'] ?? $existing->rows;
        $existing->cols = $data['cols'] ?? $existing->cols;
        $existing->orientation = $data['orientation'] ?? $existing->orientation;
        $existing->blocks = $data['blocks'];
        $existing->participation_html = $data['participation_html'];
        $existing->cover_html = $data['cover_html'];
        $existing->back_html = $data['back_html'];
        $existing->back_skipped = (bool) ($data['back_skipped'] ?? false);
        if (array_key_exists('design_name', $data) && $data['design_name'] !== null && $data['design_name'] !== '') {
            $existing->design_name = $data['design_name'];
        }
        $existing->backgrounds = $data['backgrounds'];
        $existing->output = $data['output'];
        $existing->snapshot_path = $data['snapshot_path'];
    }

    /**
     * Usuario que guarda el diseño: sesión web, o quien creó la invitación externa.
     */
    private function resolveDesignSaveUser(): ?User
    {
        $user = auth()->user();
        if ($user instanceof User) {
            return $user;
        }

        $invitationId = session('design_external_invitation_id');
        if (! $invitationId) {
            return null;
        }

        $invitation = DesignExternalInvitation::find($invitationId);

        return $invitation?->created_by_user_id
            ? User::find($invitation->created_by_user_id)
            : null;
    }

    /**
     * Si el diseño se guarda desde una invitación externa, vincular invitación y marcar completada.
     */
    private function linkInvitationToDesignIfNeeded(int $designFormatId): void
    {
        $invitationId = session('design_external_invitation_id');
        if (!$invitationId) {
            return;
        }
        $invitation = DesignExternalInvitation::find($invitationId);
        if ($invitation) {
            $invitation->update([
                'design_format_id' => $designFormatId,
                'status' => DesignExternalInvitation::STATUS_COMPLETED,
            ]);
        }
    }

    // PDF: Participación
    public function generatePdfParticipation($id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeParticipationQrExport($design);
        $html = $design->participation_html;
        return $this->renderPdfFromHtml($html, 'participation.pdf');
    }

    // PDF: Portada
    public function generatePdfCover($id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);
        $html = $design->cover_html;
        return $this->renderPdfFromHtml($html, 'cover.pdf');
    }

    // PDF: Trasera
    public function generatePdfBack($id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);
        $html = $design->back_html;
        return $this->renderPdfFromHtml($html, 'back.pdf');
    }

    // Utilidad para renderizar PDF desde HTML crudo
    protected function renderPdfFromHtml($html, $filename = 'document.pdf')
    {
        return view('design.pdf_base', ['html' => $html]);
        $pdf = \PDF::loadView('design.pdf_base', ['html' => $html]);
        return $pdf->download($filename);
    }

    public function exportPdf(Request $request)
    {
        // Aumentar límites para PDFs grandes
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');
        
        $html = $request->input('participation_html');
        
        // Optimizar HTML antes de generar PDF
        $publicPath = public_path();
        $html = $this->replaceApplicationWebRootsWithPublicPath($html, $publicPath);
        $html = $this->ensureLocalPathsForPdf($html, $publicPath);
        $html = $this->adjustWidthsForDomPdf($html);
        
        // Configurar opciones de DomPDF para mejor rendimiento
        $pdf = Pdf::loadHTML($html);
        $dompdf = $pdf->getDomPDF();
        $options = $dompdf->getOptions();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        return $pdf->download('diseño.pdf');
    }

    /**
     * HTML de participación listo para DomPDF (misma lógica en web y colas).
     * Mantiene el flujo histórico: base de la app -> ruta de public/ y url(uploads/...) en CSS.
     */
    public function prepareParticipationHtmlForPdf(string $html): string
    {
        $publicPath = public_path();
        $html = $this->replaceApplicationWebRootsWithPublicPath($html, $publicPath);
        $html = $this->ensureLocalPathsForPdf($html, $publicPath);

        return $this->adjustWidthsForDomPdf($html);
    }

    /**
     * Prefijos URL (origen de la app) que se reemplazan por la ruta de public/ en HTML para DomPDF.
     *
     * @return list<string>
     */
    private function pdfApplicationUrlPrefixesForReplace(): array
    {
        $prefixes = array_filter([
            rtrim((string) config('app.url'), '/'),
            rtrim(str_replace('\\', '/', (string) url('/')), '/'),
        ]);

        $asset = config('asset.url');
        if (is_string($asset) && $asset !== '') {
            $prefixes[] = rtrim(str_replace('\\', '/', $asset), '/');
        }

        $prefixes = array_merge($prefixes, [
            'http://127.0.0.1:8000',
            'http://localhost:8000',
            'http://127.0.0.1',
            'http://localhost',
            'https://127.0.0.1:8000',
            'https://localhost:8000',
            'https://127.0.0.1',
            'https://localhost',
        ]);

        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST);
        if (is_string($host) && $host !== '') {
            $port = parse_url($appUrl, PHP_URL_PORT);
            $withPort = $port !== null && $port !== false && (string) $port !== ''
                ? ':'.(string) $port
                : '';
            $prefixes[] = 'https://'.$host.$withPort;
            $prefixes[] = 'http://'.$host.$withPort;
            // Protocolo-relativo //host/... (navegadores y plantillas suelen guardarlo así).
            $prefixes[] = '//'.$host.$withPort;
        }

        $appPort = parse_url($appUrl, PHP_URL_PORT);
        $appScheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        if ($appPort !== null && $appPort !== false && (string) $appPort !== '') {
            $port = (string) $appPort;
            $prefixes[] = "{$appScheme}://127.0.0.1:{$port}";
            $prefixes[] = "{$appScheme}://localhost:{$port}";
            $altScheme = $appScheme === 'https' ? 'http' : 'https';
            $prefixes[] = "{$altScheme}://127.0.0.1:{$port}";
            $prefixes[] = "{$altScheme}://localhost:{$port}";
        }

        $prefixes = array_values(array_unique(array_filter($prefixes)));
        usort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $prefixes;
    }

    /**
     * Hosts de confianza para mapear https?://host/.../uploads|storage a disco (sólo esas rutas).
     *
     * @return list<string>
     */
    private function pdfTrustedHostsForAssetPaths(): array
    {
        $hosts = ['127.0.0.1', 'localhost', '[::1]'];
        foreach ([config('app.url'), url('/'), config('asset.url')] as $u) {
            $h = parse_url((string) $u, PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                $hosts[] = $h;
                if (str_starts_with(strtolower($h), 'www.')) {
                    $hosts[] = substr($h, 4);
                } else {
                    $hosts[] = 'www.'.$h;
                }
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /**
     * Sustituye la raíz web de la aplicación (todas las variantes habituales) por la ruta real de public/.
     * Evita URLs absolutas típicas de prod (panel.partilot.es) o dev (127.0.0.1) que DomPDF no resuelve.
     */
    private function replaceApplicationWebRootsWithPublicPath(string $html, string $publicPath): string
    {
        $fsBase = str_replace('\\', '/', rtrim($publicPath, '/'));

        foreach ($this->pdfApplicationUrlPrefixesForReplace() as $prefix) {
            if ($prefix !== '') {
                $html = str_replace($prefix.'/', $fsBase.'/', $html);
            }
        }

        $escaped = array_map(
            static fn (string $h): string => preg_quote($h, '#'),
            $this->pdfTrustedHostsForAssetPaths()
        );
        if ($escaped !== []) {
            $hostsPattern = implode('|', $escaped);
            $mapToFs = static function (array $m) use ($fsBase): string {
                $path = explode('?', rawurldecode($m[1]), 2)[0];

                return $fsBase.str_replace('\\', '/', $path);
            };
            $fixed = preg_replace_callback(
                '#https?://(?:'.$hostsPattern.')(?::\d+)?(/(?:uploads|storage)/[^\s\'"\)\>\#]+)#i',
                $mapToFs,
                $html
            );
            $html = $fixed ?? $html;

            // //host/uploads/... (sin esquema http/https)
            $fixed2 = preg_replace_callback(
                '#//(?:'.$hostsPattern.')(?::\d+)?(/(?:uploads|storage)/[^\s\'"\)\>\#]+)#i',
                $mapToFs,
                $html
            );
            $html = $fixed2 ?? $html;
        }

        return $html;
    }

    /**
     * Convierte URLs relativas de imágenes (uploads/...) a ruta absoluta del sistema de archivos para DomPDF.
     */
    public function ensureLocalPathsForPdf(string $html, string $publicPath): string
    {
        $normBase = str_replace('\\', '/', rtrim($publicPath, '/'));

        // url('uploads/...') relativos sin barra inicial
        $html = preg_replace_callback(
            '/url\s*\(\s*[\'"]?(?!\/|[a-z]:)(\/?)(uploads\/[^\'")\s]+)/i',
            function ($m) use ($normBase) {
                $path = $normBase.'/'.str_replace('\\', '/', $m[2]);

                return 'url(\''.$path.'\')';
            },
            $html
        );

        // background-image etc.: url(/uploads/...) o url("/storage/...")
        $html = preg_replace_callback(
            '/url\s*\(\s*[\'"]?(\/(?:uploads|storage)\/[^\'")\s]+)/i',
            function ($m) use ($normBase) {
                return 'url(\''.$normBase.str_replace('\\', '/', $m[1]).'\')';
            },
            $html
        );

        // <img src="/uploads/..."> y enlaces equivalentes
        $html = preg_replace_callback(
            '#\b(src|href)\s*=\s*([\'"])(/(?:uploads|storage)/[^\'"]+)\2#i',
            function ($m) use ($normBase) {
                return $m[1].'='.$m[2].$normBase.str_replace('\\', '/', $m[3]).$m[2];
            },
            $html
        );

        // src="uploads/..." sin barra inicial (subidas desde el editor)
        $html = preg_replace_callback(
            '#\b(src|href)\s*=\s*([\'"])((?:uploads|storage)/[^\'"]+)\2#i',
            function ($m) use ($normBase) {
                return $m[1].'='.$m[2].$normBase.'/'.str_replace('\\', '/', $m[3]).$m[2];
            },
            $html
        );

        return $html;
    }

    // Ajusta el width y height de los elementos con width, height y padding para DomPDF, sin importar el orden en el style
    /**
     * Preservar estilos inline correctamente para DomPDF
     * Convierte todos los formatos de color a hexadecimal para mejor compatibilidad
     */
    private function preserveInlineStyles($html) {
        // Primero convertir HSL a hex (DomPDF tiene problemas con HSL)
        $html = preg_replace_callback(
            '/color:\s*hsl\((\d+),\s*(\d+)%,\s*(\d+)%\)/i',
            function($matches) {
                $h = $matches[1] / 360;
                $s = $matches[2] / 100;
                $l = $matches[3] / 100;
                
                if ($s == 0) {
                    $r = $g = $b = round($l * 255);
                } else {
                    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
                    $p = 2 * $l - $q;
                    $r = round($this->hue2rgb($p, $q, $h + 1/3) * 255);
                    $g = round($this->hue2rgb($p, $q, $h) * 255);
                    $b = round($this->hue2rgb($p, $q, $h - 1/3) * 255);
                }
                
                return 'color: #' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                              str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                              str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
            },
            $html
        );
        
        // Convertir colores nombrados a hex
        $colorMap = [
            'yellow' => '#ffff00',
            'black' => '#000000',
            'white' => '#ffffff',
            'red' => '#ff0000',
            'green' => '#008000',
            'blue' => '#0000ff',
            'orange' => '#ffa500',
        ];
        
        foreach ($colorMap as $name => $hex) {
            // Reemplazar en estilos inline: color: yellow -> color: #ffff00
            // Usar lookahead negativo para no reemplazar dentro de palabras
            $html = preg_replace(
                '/(style="[^"]*color:\s*)' . preg_quote($name, '/') . '(?![a-z0-9#-])/i',
                '$1' . $hex,
                $html
            );
            // También en background-color
            $html = preg_replace(
                '/(style="[^"]*background-color:\s*)' . preg_quote($name, '/') . '(?![a-z0-9#-])/i',
                '$1' . $hex,
                $html
            );
        }
        
        // Convertir HSL a hex (DomPDF tiene problemas con HSL)
        $html = preg_replace_callback(
            '/color:\s*hsl\((\d+),\s*(\d+)%,\s*(\d+)%\)/i',
            function($matches) {
                $h = $matches[1] / 360;
                $s = $matches[2] / 100;
                $l = $matches[3] / 100;
                
                if ($s == 0) {
                    $r = $g = $b = round($l * 255);
                } else {
                    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
                    $p = 2 * $l - $q;
                    $r = round($this->hue2rgb($p, $q, $h + 1/3) * 255);
                    $g = round($this->hue2rgb($p, $q, $h) * 255);
                    $b = round($this->hue2rgb($p, $q, $h - 1/3) * 255);
                }
                
                return 'color: #' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . 
                              str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . 
                              str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
            },
            $html
        );
        
        // Convertir RGB a hex
        $html = preg_replace_callback(
            '/color:\s*rgb\((\d+),\s*(\d+),\s*(\d+)\)/i',
            function($matches) {
                $r = str_pad(dechex($matches[1]), 2, '0', STR_PAD_LEFT);
                $g = str_pad(dechex($matches[2]), 2, '0', STR_PAD_LEFT);
                $b = str_pad(dechex($matches[3]), 2, '0', STR_PAD_LEFT);
                return 'color: #' . $r . $g . $b;
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Helper para convertir HSL a RGB
     */
    private function hue2rgb($p, $q, $t) {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1/6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1/2) return $q;
        if ($t < 2/3) return $p + ($q - $p) * (2/3 - $t) * 6;
        return $p;
    }

    public function adjustWidthsForDomPdf($html) {
        return preg_replace_callback(
            '/style="([^"]*)"/i',
            function ($matches) {
                $style = $matches[1];
                $originalStyle = $style; // Preservar el estilo original

                // Buscar width, height y padding (en cualquier orden)
                if (
                    preg_match('/width:\s*(\d+)px;?/i', $style, $widthMatch) &&
                    preg_match('/height:\s*(\d+)px;?/i', $style, $heightMatch) &&
                    preg_match('/padding:\s*(\d+)px;?/i', $style, $paddingMatch)
                ) {
                    $width = (int)$widthMatch[1];
                    $height = (int)$heightMatch[1];
                    $padding = (int)$paddingMatch[1];
                    $newWidth = $width - ($padding * 2);
                    $newHeight = $height - ($padding * 2);

                    // Reemplazar SOLO width y height, preservando TODOS los demás estilos (incluidos colores)
                    $style = preg_replace('/width:\s*\d+px;?/i', "width: {$newWidth}px;", $style);
                    $style = preg_replace('/height:\s*\d+px;?/i', "height: {$newHeight}px;", $style);
                }
                
                // Asegurar que el estilo se devuelva completo sin perder nada
                return 'style="' . $style . '"';
            },
            $html
        );
    }

    public function exportParticipationPdf(Request $request, $id)
    {
        // Aumentar límites para PDFs grandes
        ini_set('max_execution_time', 300); // 5 minutos
        ini_set('memory_limit', '1024M');   // 1GB
        
        $design = DesignFormat::findOrFail($id);
        $this->authorizeParticipationQrExport($design);

        try {
            [$from, $to] = $this->resolveParticipationPdfRange($request, $design);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
        
        // Cache del HTML procesado (clave versionada; mismo pipeline que el Job en cola)
        $cacheKey = 'participation_html_pdf_v9_' . $id;
        $participation_html = cache()->remember($cacheKey, 3600, function () use ($design) {
            return $this->prepareParticipationHtmlForPdf($design->participation_html ?? '');
        });

        // Determinar tamaño y orientación
        $page = $design->page ?? 'a3';
        $orientation = $design->orientation ?? 'h';
        $pdfOrientation = ($orientation === 'h') ? 'landscape' : 'portrait';

        // Obtener tickets del set con eager loading optimizado
        $set = $design->set_id ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id) : null;
        $tickets = $set && $set->tickets ? $set->tickets : [];
        
        // Calcular filas y columnas
        $rows = $design->rows ?? 1;
        $cols = $design->cols ?? 1;
        $per_page = $rows * $cols;
        $total = max(0, $to - $from + 1);
        $total_pages = $per_page > 0 ? (int) ceil($total / $per_page) : 0;

        // Obtener tickets a imprimir
        $tickets_to_print = $total > 0 ? array_slice($tickets, $from - 1, $total) : [];

        // Optimizar HTML de participación (configurable)
        if (config('qr_optimization.optimize_images', false)) {
            $participation_html = $this->optimizeParticipationHtml($participation_html, $tickets_to_print);
        }

        // Generar QR codes en lote para todas las referencias únicas (usando Endroid - ultra-optimizado)
        $qrService = new \App\Services\EndroidQrCodeService();
        $uniqueReferences = [];
        foreach ($tickets_to_print as $ticket) {
            if (isset($ticket['r']) && !in_array($ticket['r'], $uniqueReferences)) {
                $uniqueReferences[] = $ticket['r'];
            }
        }
        
        // Usar el método más eficiente según la cantidad
        // if (count($uniqueReferences) > 200) {
            $qrCodes = $qrService->generateUltraFastQrCodes($uniqueReferences);
        /*} else {
            $qrCodes = $qrService->generateMultipleQrCodes($uniqueReferences);
        }*/

        if ($this->designPdfHtmlPreviewEnabled()) {
            $previewTickets = $tickets_to_print;
            if ($total > 500) {
                $previewTickets = array_slice($tickets_to_print, 0, $per_page);
            }
            $previewTotal = count($previewTickets);
            $previewTotalPages = $previewTotal > 0 ? (int) ceil($previewTotal / $per_page) : 1;
            $pages = $this->generatePagesOptimized($previewTickets, max(1, $previewTotalPages), $per_page);
            $this->cleanupTempQrCodes();

            return response()
                ->view('design.pdf_participation', [
                    'pages' => $pages,
                    'participation_html' => $participation_html,
                    'rows' => $rows,
                    'cols' => $cols,
                    'qrCodes' => $qrCodes,
                    'pdfDocumentTitle' => 'Participación PDF (vista previa)',
                    'pdfHtmlPreviewBanner' => true,
                ])
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        // Para PDFs muy grandes (>500 participaciones), usar procesamiento por lotes
        //if ($total > 500) {
        if ($total > 500) {
            return $this->generatePdfInChunks($design, $participation_html, $tickets, $from, $to, $rows, $cols, $page, $pdfOrientation, $qrCodes);
        }
        
        // Ordenar tickets en modo guillotina (optimizado)
        $pages = $this->generatePagesOptimized($tickets_to_print, $total_pages, $per_page);

        /*return view('design.pdf_participation', [
            'pages' => $pages,
            'participation_html' => $participation_html,
            // 'rows' => $rows,
            'cols' => $cols,
            'qrCodes' => $qrCodes,
        ]);*/

        $pdf = Pdf::loadView('design.pdf_participation', [
            'pages' => $pages,
            'participation_html' => $participation_html,
            'rows' => $rows,
            'cols' => $cols,
            'qrCodes' => $qrCodes,
        ])->setPaper($page, $pdfOrientation);

        // Limpiar QR codes temporales después de generar el PDF
        $this->cleanupTempQrCodes();
        
        return $pdf->download('participacion.pdf');
    }

    public function exportCoverPdf($id)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);

        try {
            if ($this->designPdfHtmlPreviewEnabled()) {
                $payload = $this->buildGridPdfParticipationViewPayload($design, $this->buildCoverHtmlItems($design), 'Portadas PDF (vista previa)');
                $payload['data']['pdfHtmlPreviewBanner'] = true;

                return response()
                    ->view($payload['view'], $payload['data'])
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            }
            $pdf = $this->makeCoverPdfFacade($design);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        } catch (\RuntimeException $e) {
            abort(500, $e->getMessage());
        }

        return $pdf->download('portadas.pdf');
    }

    public function exportBackPdf(Request $request, $id)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);

        $copies = $this->normalizeBackPdfCopies($request->query('copies', 'all'));
        $exactCount = $this->parseBackPdfExactCount($request);

        try {
            if ($this->designPdfHtmlPreviewEnabled()) {
                if ($exactCount !== null) {
                    $title = 'Traseras PDF — '.$exactCount.' ejemplar(es) (vista previa)';
                } else {
                    $title = $copies === 'one' ? 'Traseras PDF — 1 ejemplar (vista previa)' : 'Traseras PDF (vista previa)';
                }
                $payload = $this->buildGridPdfParticipationViewPayload($design, $this->buildBackHtmlItems($design, $copies, $exactCount), $title);
                $payload['data']['pdfHtmlPreviewBanner'] = true;

                return response()
                    ->view($payload['view'], $payload['data'])
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            }
            $pdf = $this->makeBackPdfFacade($design, $copies, $exactCount);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        } catch (\RuntimeException $e) {
            abort(500, $e->getMessage());
        }

        if ($exactCount !== null) {
            $filename = 'traseras-'.$exactCount.'.pdf';
        } else {
            $filename = $copies === 'one' ? 'trasera.pdf' : 'traseras.pdf';
        }

        return $pdf->download($filename);
    }

    /**
     * PDF de portadas: una por taco en orden (1, 2, 3…), varias por hoja según filas×columnas del diseño.
     */
    public function makeCoverPdfFacade(DesignFormat $design)
    {
        $items = $this->buildCoverHtmlItems($design);

        return $this->makeGridPdfFromHtmlItems($design, $items, 'Portadas PDF');
    }

    /**
     * PDF de traseras separado. copies=one → 1 ejemplar; copies=all → total de participaciones del set.
     */
    public function makeBackPdfFacade(DesignFormat $design, string $copies = 'all', ?int $exactCount = null)
    {
        $copies = $this->normalizeBackPdfCopies($copies);
        $items = $this->buildBackHtmlItems($design, $copies, $exactCount);

        return $this->makeGridPdfFromHtmlItems($design, $items, 'Traseras PDF');
    }

    /**
     * @return string[]
     */
    public function buildCoverHtmlItems(DesignFormat $design): array
    {
        if (empty($design->cover_html)) {
            throw new \InvalidArgumentException('Portada no encontrada');
        }

        $coverTemplate = $this->prepareCoverOrBackHtmlForPdf($design, 'cover_html');

        $output = $design->output ?? [];
        if (!empty($output['participations_per_book']) && $design->set_id && empty($output['taco_qrs'])) {
            $output = DesignFormat::mergeTacoQrsIntoOutput($design->set_id, $output);
        }

        $tacoQrs = $output['taco_qrs'] ?? [];
        if (empty($tacoQrs)) {
            return [$coverTemplate];
        }

        usort($tacoQrs, fn ($a, $b) => ((int) ($a['book_number'] ?? 0)) <=> ((int) ($b['book_number'] ?? 0)));

        $qrService = new \App\Services\EndroidQrCodeService();
        $items = [];
        foreach ($tacoQrs as $taco) {
            $tacoRef = $taco['taco_ref'] ?? '';
            $bookNumber = (int) ($taco['book_number'] ?? 0);
            if ($tacoRef === '') {
                continue;
            }
            $qrBase64 = $qrService->generateQrFromTextBase64($tacoRef);
            $items[] = $this->replaceCoverQrWithTacoQr($coverTemplate, $qrBase64, $bookNumber);
        }

        if ($items === []) {
            throw new \RuntimeException('No se pudieron generar las portadas de tacos');
        }

        return $items;
    }

    /**
     * @return string[]
     */
    public function buildBackHtmlItems(DesignFormat $design, string $copies = 'all', ?int $exactCount = null): array
    {
        if (empty($design->back_html)) {
            throw new \InvalidArgumentException('Trasera no encontrada');
        }

        $backHtml = $this->prepareCoverOrBackHtmlForPdf($design, 'back_html');
        $copies = $this->normalizeBackPdfCopies($copies);

        if ($exactCount !== null) {
            $n = max(1, min(100000, (int) $exactCount));

            return array_fill(0, $n, $backHtml);
        }

        if ($copies === 'one') {
            return [$backHtml];
        }

        $set = $design->set_id
            ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id)
            : null;
        $total = (int) ($set->total_participations ?? 0);
        if ($total <= 0 && $set && $set->tickets) {
            $tickets = is_array($set->tickets) ? $set->tickets : [];
            $total = count($tickets);
        }
        $total = max(1, $total);

        return array_fill(0, $total, $backHtml);
    }

    public function prepareCoverOrBackHtmlForPdf(DesignFormat $design, string $htmlField): string
    {
        $html = $design->$htmlField ?? '';
        $imageService = new ImageOptimizationService();
        $html = $imageService->optimizeHtmlImages($html);
        $publicPath = public_path();
        $html = $this->replaceApplicationWebRootsWithPublicPath($html, $publicPath);
        $html = $this->ensureLocalPathsForPdf($html, $publicPath);
        $html = $this->preserveInlineStyles($html);
        $html = $this->adjustWidthsForDomPdf($html);

        return $html;
    }

    /**
     * Empaqueta ítems en páginas en orden secuencial (taco 1, 2, 3…), no modo talonario/guillotina.
     *
     * @param  string[]  $items
     * @return string[][][]
     */
    public function generatePagesSequential(array $items, int $per_page): array
    {
        $per_page = max(1, $per_page);

        return array_values(array_chunk($items, $per_page));
    }

    /**
     * Misma vista y datos que DomPDF usa para portadas/traseras en cuadrícula.
     *
     * @param  string[]  $items
     * @return array{view: string, data: array<string, mixed>, page: string, orientation: string}
     */
    private function buildGridPdfParticipationViewPayload(DesignFormat $design, array $items, string $documentTitle): array
    {
        if ($items === []) {
            throw new \RuntimeException('No hay elementos para generar el PDF');
        }

        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $per_page = $rows * $cols;
        $pages = $this->generatePagesSequential($items, $per_page);

        return [
            'view' => 'design.pdf_participation',
            'data' => [
                'pages' => $pages,
                'use_prebuilt_cells' => true,
                'participation_html' => '',
                'rows' => $rows,
                'cols' => $cols,
                'qrCodes' => [],
                'pdfDocumentTitle' => $documentTitle,
            ],
            'page' => $design->page ?? 'a3',
            'orientation' => $design->orientation ?? 'h',
        ];
    }

    /**
     * @param  string[]  $items
     */
    public function makeGridPdfFromHtmlItems(DesignFormat $design, array $items, string $documentTitle)
    {
        $payload = $this->buildGridPdfParticipationViewPayload($design, $items, $documentTitle);
        $pdfOrientation = ($payload['orientation'] === 'h') ? 'landscape' : 'portrait';
        $pdf = Pdf::loadView($payload['view'], $payload['data'])
            ->setPaper($payload['page'], $pdfOrientation);

        $this->applyDompdfOptions($pdf);

        return $pdf;
    }

    /**
     * Guarda PDF en cuadrícula; trocea si hay muchos ítems (p. ej. todas las traseras).
     *
     * @param  string[]  $items
     */
    public function saveGridPdfFacadeToPath(DesignFormat $design, array $items, string $finalPath, string $documentTitle): void
    {
        $chunkSize = 80;
        if (count($items) <= $chunkSize) {
            $this->makeGridPdfFromHtmlItems($design, $items, $documentTitle)->save($finalPath);

            return;
        }

        $temp_files = [];
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            $temp = storage_path('app/temp_pdf_'.uniqid('', true).'.pdf');
            $this->makeGridPdfFromHtmlItems($design, $chunk, $documentTitle)->save($temp);
            $temp_files[] = $temp;
        }

        FpdiPdfMerge::mergeTemporaryFiles($temp_files, $finalPath);
    }

    /**
     * Rango inclusive de participaciones a imprimir.
     * Query opcional: pdf_from, pdf_to (ambos) para sobrescribir el diseño solo en esta descarga.
     *
     * @return array{0: int, 1: int}
     */
    private function resolveParticipationPdfRange(Request $request, DesignFormat $design): array
    {
        $set = $design->set_id ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id) : null;
        $totalParticipations = (int) ($set->total_participations ?? 0);
        if ($totalParticipations <= 0 && $set && is_array($set->tickets)) {
            $totalParticipations = count($set->tickets);
        }

        $qf = $request->query('pdf_from');
        $qt = $request->query('pdf_to');
        $hasCustom = ($qf !== null && $qf !== '') && ($qt !== null && $qt !== '');

        if ($hasCustom) {
            if ($totalParticipations <= 0) {
                throw new \InvalidArgumentException('No hay participaciones en el set para imprimir.');
            }
            $from = (int) $qf;
            $to = (int) $qt;
            $from = max(1, min($totalParticipations, $from));
            $to = max(1, min($totalParticipations, $to));
            if ($from > $to) {
                throw new \InvalidArgumentException('La participación inicial no puede ser mayor que la final.');
            }

            return [$from, $to];
        }

        $generate_mode = $design->output['generate_mode'] ?? 1;
        if ($generate_mode == 1) {
            return [1, max(0, $totalParticipations)];
        }

        $from = (int) ($design->output['participation_from'] ?? 1);
        $to = (int) ($design->output['participation_to'] ?? $totalParticipations);
        if ($totalParticipations > 0) {
            $from = max(1, min($totalParticipations, $from));
            $to = max(1, min($totalParticipations, $to));
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
        }

        return [$from, $to];
    }

    /**
     * Número exacto de traseras idénticas (query count). Max 100000.
     */
    private function parseBackPdfExactCount(Request $request): ?int
    {
        $raw = $request->query('count');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (int) $raw;
        if ($n < 1) {
            return null;
        }

        return min(100000, $n);
    }

    private function normalizeBackPdfCopies(?string $copies): string
    {
        $copies = strtolower(trim((string) $copies));

        return in_array($copies, ['one', '1', 'single'], true) ? 'one' : 'all';
    }

    private function designPdfHtmlPreviewEnabled(): bool
    {
        return (bool) config('design.pdf_html_preview', false);
    }

    private function applyDompdfOptions($pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $options = $dompdf->getOptions();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('enable_remote', true);
        $options->set('enable_html5_parser', true);
        $options->set('enable_php', true);
        $options->set('enableCssFloat', true);
        $options->set('enableFontSubsetting', false);
    }

    /**
     * Construye el PDF combinado portada+trasera (incluye tacos/QRs).
     * Sin comprobación de usuario: el llamador debe validar permisos antes.
     */
    public function makeCoverBackPdfFacade(DesignFormat $design)
    {
        if (empty($design->cover_html) || empty($design->back_html)) {
            throw new \InvalidArgumentException('Portada o trasera no encontradas');
        }

        $imageService = new ImageOptimizationService();
        $publicPath = public_path();

        $backHtml = $design->back_html;
        $backHtml = $imageService->optimizeHtmlImages($backHtml);
        $backHtml = $this->replaceApplicationWebRootsWithPublicPath($backHtml, $publicPath);
        $backHtml = $this->ensureLocalPathsForPdf($backHtml, $publicPath);
        $backHtml = $this->preserveInlineStyles($backHtml);
        $backHtml = $this->adjustWidthsForDomPdf($backHtml);

        $output = $design->output ?? [];
        if (!empty($output['participations_per_book']) && $design->set_id && empty($output['taco_qrs'])) {
            $output = DesignFormat::mergeTacoQrsIntoOutput($design->set_id, $output);
        }
        $tacoQrs = $output['taco_qrs'] ?? [];

        if (!empty($tacoQrs)) {
            $coverPages = [];
            $coverTemplate = $design->cover_html;
            $coverTemplate = $imageService->optimizeHtmlImages($coverTemplate);
            $coverTemplate = $this->replaceApplicationWebRootsWithPublicPath($coverTemplate, $publicPath);
            $coverTemplate = $this->ensureLocalPathsForPdf($coverTemplate, $publicPath);
            $coverTemplate = $this->preserveInlineStyles($coverTemplate);
            $coverTemplate = $this->adjustWidthsForDomPdf($coverTemplate);

            foreach ($tacoQrs as $taco) {
                $tacoRef = $taco['taco_ref'] ?? '';
                $bookNumber = $taco['book_number'] ?? 0;
                if (empty($tacoRef)) {
                    continue;
                }
                $qrBase64 = (new \App\Services\EndroidQrCodeService())->generateQrFromTextBase64($tacoRef);
                $coverHtml = $this->replaceCoverQrWithTacoQr($coverTemplate, $qrBase64, $bookNumber);
                $coverPages[] = $coverHtml;
            }

            if (empty($coverPages)) {
                throw new \RuntimeException('No se pudieron generar las portadas de tacos');
            }

            $coverBackPairs = [];
            foreach ($coverPages as $coverHtml) {
                $coverBackPairs[] = ['cover' => $coverHtml, 'back' => $backHtml];
            }

            $viewData = [
                'coverBackPairs' => $coverBackPairs,
            ];
            $viewName = 'design.pdf_cover_back_multiple';
        } else {
            $coverHtml = $design->cover_html;
            $coverHtml = $imageService->optimizeHtmlImages($coverHtml);
            $coverHtml = $this->replaceApplicationWebRootsWithPublicPath($coverHtml, $publicPath);
            $coverHtml = $this->ensureLocalPathsForPdf($coverHtml, $publicPath);
            $coverHtml = $this->preserveInlineStyles($coverHtml);
            $coverHtml = $this->adjustWidthsForDomPdf($coverHtml);

            $viewData = [
                'coverHtml' => $coverHtml,
                'backHtml' => $backHtml,
            ];
            $viewName = 'design.pdf_cover_back';
        }

        $page = $design->page ?? 'a3';
        $orientation = $design->orientation ?? 'h';
        $pdfOrientation = ($orientation === 'h') ? 'landscape' : 'portrait';

        $pdf = Pdf::loadView($viewName, $viewData);
        $pdf->setPaper($page, $pdfOrientation);
        $this->applyDompdfOptions($pdf);

        return $pdf;
    }

    /**
     * Exportar portada y trasera en un solo PDF.
     * Tarea 3 tacos: si hay taco_qrs en output, genera una portada por taco, cada una con su QR taco_ref.
     */
    public function exportCoverAndBackPdf($id)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);

        try {
            $pdf = $this->makeCoverBackPdfFacade($design);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        } catch (\RuntimeException $e) {
            abort(500, $e->getMessage());
        }

        return $pdf->download('portada-trasera.pdf');
    }

    /**
     * Reemplaza o inyecta el elemento QR de la portada con el QR del taco.
     * Prueba varios patrones (como participaciones) y si no hay QR, lo inyecta.
     */
    private function replaceCoverQrWithTacoQr(string $coverHtml, string $qrBase64, int $bookNumber): string
    {
        $qrImg = '<img src="' . $qrBase64 . '" class="qr-code" style="width:100%;height:100%;display:block;" alt="QR Taco ' . (int) $bookNumber . '" />';
        $replaced = false;

        // 1. Igual que participaciones: div.qr con span ui-draggable-handle vacío
        $before1 = $coverHtml;
        $coverHtml = preg_replace(
            '/<div([^>]*class="[^"]*qr[^"]*"[^>]*)>\s*<span class="ui-draggable-handle"><\/span>\s*<\/div>/s',
            '<div$1>' . $qrImg . '</div>',
            $coverHtml,
            1
        );
        if ($coverHtml !== $before1) {
            $replaced = true;
        }

        // 2. Div qr con span que contiene img (placeholder basicqr, etc.)
        if (!$replaced && preg_match('/<div[^>]*class="[^"]*qr[^"]*"[^>]*>/i', $coverHtml)) {
            $before = $coverHtml;
            $coverHtml = preg_replace_callback(
                '/(<div[^>]*class="[^"]*qr[^"]*"[^>]*>)\s*<span[^>]*>.*?<\/span>\s*(<\/div>)/s',
                function ($m) use ($qrImg) {
                    return $m[1] . $qrImg . $m[2];
                },
                $coverHtml,
                1
            );
            if ($coverHtml !== $before) {
                $replaced = true;
            }
        }

        // 3. Reemplazar img con basicqr.jpg por nuestro QR (cualquier ubicación)
        if (!$replaced && (stripos($coverHtml, 'basicqr') !== false || preg_match('/<img[^>]+src="[^"]*basicqr[^"]*"/i', $coverHtml))) {
            $coverHtml = preg_replace(
                '/<img([^>]*)src="[^"]*basicqr[^"]*"([^>]*)>/i',
                '<img$1src="' . $qrBase64 . '"$2 class="qr-code" style="width:100%;height:100%;display:block;">',
                $coverHtml,
                1
            );
            $replaced = true;
        }

        // 4. Si no hay elemento QR: inyectar uno en la portada (esquina inferior derecha, más grande)
        if (!$replaced) {
            $qrDiv = '<div class="elements qr" style="position:absolute;bottom:3mm;right:3mm;width:75px;height:75px;z-index:9999;padding:3px;background:#fff;border-radius:6px;">' . $qrImg . '</div>';
            if (preg_match('/<div[^>]*containment-wrapper[^>]*>/i', $coverHtml)) {
                $coverHtml = preg_replace(
                    '/(<div[^>]*containment-wrapper[^>]*>)/i',
                    '$1' . $qrDiv,
                    $coverHtml,
                    1
                );
            } else {
                $coverHtml = preg_replace('/(<div[^>]*format-box[^>]*>)/i', '$1' . $qrDiv, $coverHtml, 1);
            }
        }

        $coverHtml = preg_replace('/\{\{taco_number\}\}/i', (string) $bookNumber, $coverHtml);

        // Posicionar QR existente en esquina inferior derecha y tamaño mayor (75px)
        $coverHtml = preg_replace_callback(
            '/(<div[^>]*class="[^"]*qr[^"]*"[^>]*)style="([^"]*)"/i',
            function ($m) {
                $style = preg_replace('/\b(top|left):[^;]+;?/i', '', $m[2]);
                $style = preg_replace('/\bwidth:\s*[\d.]+px/i', 'width:75px', $style);
                $style = preg_replace('/\bheight:\s*[\d.]+px/i', 'height:75px', $style);
                $style = trim(preg_replace('/;+/', ';', $style), '; ') . '; bottom:3mm; right:3mm;';
                return $m[1] . 'style="' . $style . '"';
            },
            $coverHtml,
            1
        );

        return $coverHtml;
    }

    /**
     * Portada o trasera: instancia Pdf lista para descargar o guardar en disco (sin chequeo de permisos).
     */
    public function prepareOptimizedPdfFacade(DesignFormat $design, string $htmlField)
    {
        $html = $this->prepareCoverOrBackHtmlForPdf($design, $htmlField);

        $page = $design->page ?? 'a3';
        $orientation = $design->orientation ?? 'h';
        $pdfOrientation = ($orientation === 'h') ? 'landscape' : 'portrait';

        $viewName = 'design.pdf_base';
        if ($htmlField === 'cover_html') {
            $viewName = 'design.pdf_cover';
        } elseif ($htmlField === 'back_html') {
            $viewName = 'design.pdf_back';
        }

        $pdf = Pdf::loadView($viewName, ['html' => $html]);
        $pdf->setPaper($page, $pdfOrientation);
        $this->applyDompdfOptions($pdf);

        return $pdf;
    }

    /**
     * Método genérico optimizado para generar PDFs
     */
    private function generateOptimizedPdf($id, $htmlField, $filename)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);

        return $this->prepareOptimizedPdfFacade($design, $htmlField)->download($filename);
    }

    /**
     * Convierte rutas relativas de imágenes en HTML a URLs absolutas (para vista/editor).
     */
    private function ensureAbsoluteUrlsInHtml(string $html): string
    {
        if ($html === '') {
            return $html;
        }
        $base = rtrim(config('app.url'), '/');
        // url('/uploads/...') o url("uploads/...") o url('uploads/...') -> url(base/uploads/...)
        $html = preg_replace_callback(
            '/url\s*\(\s*[\'"]?(?!https?:\/\/)(\/?)(uploads\/[^\'")\s]+)/i',
            function ($m) use ($base) {
                return 'url(\'' . $base . '/' . $m[2] . '\')';
            },
            $html
        );
        // <img src="path" o src='path'>: si path no es absoluto (http/https) ni empieza por /, prefijar base
        $html = preg_replace_callback(
            '/<img(\s[^>]*)\ssrc=[\'"](?!https?:\\/\\/)([^\'"]+)[\'"]/i',
            function ($m) use ($base) {
                $path = $m[2];
                if (strpos($path, '/') === 0) {
                    return $m[0];
                }
                return '<img' . $m[1] . ' src="' . $base . '/' . $path . '"';
            },
            $html
        );
        return $html;
    }

    public function digitalParticipationImage($id)
    {
        $design = DesignFormat::with(['set.entity', 'lottery', 'entity'])->findOrFail($id);
        if (! auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para ver este diseño.');
        }
        $this->authorizeParticipationQrExport($design);

        $set = $design->set;
        $isDigitalSet = $set && $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
        if (! $isDigitalSet) {
            abort(404, 'Este diseño no es de participaciones digitales.');
        }

        $reservation_numbers = $set && $set->reserve ? $set->reserve->reservation_numbers : [];
        $html = $this->ensureAbsoluteUrlsInHtml($design->participation_html ?? '');

        return view('design.digital_participation_image', [
            'design' => $design,
            'set' => $set,
            'reservation_numbers' => $reservation_numbers,
            'html' => $html,
        ]);
    }

    /**
     * Imagen de participación para marketing (sin QR, referencia ni nº de participación).
     */
    public function marketingParticipationImage($id)
    {
        $design = DesignFormat::with(['set.entity', 'lottery', 'entity'])->findOrFail($id);
        if (! auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para ver este diseño.');
        }

        $set = $design->set;
        $reservation_numbers = $set && $set->reserve ? $set->reserve->reservation_numbers : [];
        $html = $this->ensureAbsoluteUrlsInHtml($design->participation_html ?? '');

        return view('design.marketing_participation_image', [
            'design' => $design,
            'set' => $set,
            'reservation_numbers' => $reservation_numbers,
            'html' => $html,
        ]);
    }

    /**
     * Muestra el formulario para editar un formato existente.
     */
    public function editFormat($id)
    {
        $format = DesignFormat::findOrFail($id);
        if (!auth()->user()->canAccessEntity((int) $format->entity_id)) {
            abort(403, 'No tienes permisos para editar este diseño.');
        }
        $approvalService = app(DesignApprovalService::class);
        if (! $approvalService->canEntityEditDesign(auth()->user(), $format)) {
            if ($approvalService->canReviewApproval(auth()->user(), $format)) {
                return redirect()->route('design.approval.review', $format->id);
            }
            if ($approvalService->isVisibleToEntityViewer($format)) {
                return redirect()->route('design.summary', $format->id);
            }

            abort(403, 'Este diseño está en preparación por la administración.');
        }
        $setForLock = $format->set_id ? Set::find($format->set_id) : null;
        if ($setForLock) {
            $feeService = app(ManagementFeeService::class);
            if ($feeService->blocksAdminDesignUntilEntityPays($setForLock)) {
                if (app(DesignApprovalService::class)->isAdministrationSideUser(auth()->user())) {
                    return redirect()->route('design.summary', $format->id)
                        ->with('warning', 'La entidad debe pagar la cuota de gestión PARTILOT antes de continuar con el diseño.');
                }

                return redirect()->route('design.managementFee.pay', $setForLock->id)
                    ->with('info', 'Debe confirmar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
            }
        }
        $printOrderLock = $this->getPrintOrderLockContext($format->id);
        $setLock = $setForLock ? $this->getSetDesignLockContext($setForLock) : ['locked' => false];
        if ($approvalService->operationalDesignLockApplies(auth()->user(), $format, $setLock, $printOrderLock)) {
            $message = ! empty($printOrderLock['locked'])
                ? ($printOrderLock['message'] ?? 'Este diseño tiene una orden de imprenta activa y no puede editarse. Puede visualizarlo desde el resumen.')
                : ($setLock['message'] ?? 'Este diseño está bloqueado por el estado operativo del set (ventas/asignaciones).');

            return redirect()->route('design.summary', $id)
                ->with('warning', $message);
        }
        $format = $this->hydrateDesignHtmlFromBlocks($format);
        if (! $approvalService->designHasParticipationContent($format)) {
            return $this->redirectToFormatWizardForEmptyDesign($format);
        }
        $set = $format->set_id ? Set::find($format->set_id) : null;
        $reservation_numbers = $set && $set->reserve ? $set->reserve->reservation_numbers : [];
        $isDigitalSet = $set && $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
        return view('design.edit_format', compact('format', 'set', 'reservation_numbers', 'isDigitalSet'));
    }

    /**
     * Vista de resumen tras guardar el diseño (paso 5): descarga de PDFs y volver al listado.
     */
    public function summary($id)
    {
        $design = DesignFormat::with(['set.entity', 'lottery', 'entity'])->findOrFail($id);
        if (!auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para ver este diseño.');
        }
        $approvalService = app(DesignApprovalService::class);
        if (auth()->user()->isEntity()
            && ! auth()->user()->isAdministration()
            && ! $approvalService->userActsAsAdministration(auth()->user())
            && ! $approvalService->isVisibleToEntityViewer($design)) {
            if ($this->entityAwaitingAdminDesignAfterFeePayment($design)) {
                return redirect()->route('design.index')
                    ->with('success', 'Cuota de gestión PARTILOT pagada. La administración continuará con el diseño y le avisará cuando pueda revisarlo.');
            }

            abort(403, 'Este diseño está en preparación por la administración. La entidad podrá revisarlo cuando se envíe a aprobación.');
        }
        $latestPrintOrder = PrintOrder::where('design_format_id', $design->id)
            ->orderByDesc('id')
            ->first();
        $printOrderLock = $this->getPrintOrderLockContext($design->id);

        $managementFee = null;
        $designApproval = null;
        if ($design->set) {
            $design->set->loadMissing('entity.administration');
            $managementFee = app(ManagementFeeService::class)->buildSummaryContext($design->set, auth()->user(), $design);
            $design->set->refresh();
        }
        $designApproval = app(DesignApprovalService::class)->buildSummaryContext($design, auth()->user());
        $awaitingEntityFeeBeforeDesign = app(DesignApprovalService::class)
            ->isAwaitingEntityManagementFeeBeforeAdminDesign($design);
        $hasDesignContent = app(DesignApprovalService::class)->designHasParticipationContent($design);
        $blocksQrExport = app(DesignApprovalService::class)->blocksQrExport($design);
        $sendToPrintBlockReason = $this->printOrderSubmissionBlockMessage($design);
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContext($design, auth()->user());
        $canSendToPrint = $sendToPrintBlockReason === null
            && ! $this->designSetIsDigitalOnly($design->set)
            && ! empty($printPayment['user_may_submit']);
        if ($sendToPrintBlockReason === null && empty($printPayment['user_may_submit']) && ! $this->designSetIsDigitalOnly($design->set)) {
            $sendToPrintBlockReason = $printPayment['user_submit_block_reason'];
        }
        $setLock = $design->set ? $this->getSetDesignLockContext($design->set) : ['locked' => false];
        $canOpenEditor = $approvalService->canOpenDesignEditor(auth()->user(), $design, $setLock['locked'], $printOrderLock['locked']);
        $canPreviewDesign = $hasDesignContent;
        $entityFeeDue = app(ManagementFeeService::class)->entityOwesManagementFee($design);
        [$stripePublishableKey, ] = app(ManagementFeePaymentService::class)->resolveStripeKeys();
        $stripePaymentEnabled = app(ManagementFeePaymentService::class)->hasStripeConfigured();

        return view('design.summary', compact(
            'design',
            'latestPrintOrder',
            'printOrderLock',
            'managementFee',
            'designApproval',
            'awaitingEntityFeeBeforeDesign',
            'hasDesignContent',
            'blocksQrExport',
            'canSendToPrint',
            'sendToPrintBlockReason',
            'canOpenEditor',
            'canPreviewDesign',
            'entityFeeDue',
            'stripePublishableKey',
            'stripePaymentEnabled',
            'printPayment'
        ));
    }

    public function approvalsIndex()
    {
        $entityIds = auth()->user()->accessibleEntityIds();
        $designs = DesignFormat::with(['entity', 'lottery', 'set'])
            ->whereIn('entity_id', $entityIds)
            ->where('approval_status', DesignApprovalService::STATUS_PENDING)
            ->orderByDesc('submitted_for_approval_at')
            ->get()
            ->filter(fn (DesignFormat $design) => app(DesignApprovalService::class)->canReviewApproval(auth()->user(), $design))
            ->values();

        return view('design.approvals_index', compact('designs'));
    }

    public function approvalReview($id)
    {
        $design = DesignFormat::with(['set.entity', 'lottery', 'entity'])->findOrFail($id);
        if (! app(DesignApprovalService::class)->canReviewApproval(auth()->user(), $design)) {
            abort(403, 'No tienes permisos para revisar este diseño.');
        }

        $html = $this->ensureAbsoluteUrlsInHtml($design->participation_html ?? '');

        return view('design.approval_review', compact('design', 'html'));
    }

    /**
     * Vista previa de solo lectura (p. ej. diseño en imprenta o set bloqueado operativamente).
     */
    public function participationPreview($id)
    {
        $design = DesignFormat::with(['set.entity', 'lottery', 'entity'])->findOrFail($id);
        if (! auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para ver este diseño.');
        }

        $approvalService = app(DesignApprovalService::class);
        if (! $approvalService->designHasParticipationContent($design)) {
            return redirect()->route('design.summary', $design->id)
                ->with('warning', 'El diseño de participación aún no tiene contenido para previsualizar.');
        }

        $html = $this->ensureAbsoluteUrlsInHtml($design->participation_html ?? '');
        $printOrderLock = $this->getPrintOrderLockContext($design->id);
        $latestPrintOrder = PrintOrder::where('design_format_id', $design->id)->orderByDesc('id')->first();

        return view('design.participation_preview', compact('design', 'html', 'printOrderLock', 'latestPrintOrder'));
    }

    public function submitForApproval($id)
    {
        $design = DesignFormat::with('set')->findOrFail($id);
        if (! auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        app(DesignApprovalService::class)->submitForApproval($design, auth()->user());

        return redirect()->route('design.summary', $design->id)
            ->with('success', 'Diseño enviado a la entidad para su aprobación.');
    }

    public function approveDesign($id)
    {
        $design = DesignFormat::with('set')->findOrFail($id);
        $user = auth()->user();
        app(DesignApprovalService::class)->approve($design, $user);

        if ($user->isEntity() && ! $user->isAdministration()) {
            return redirect()->route('design.index')
                ->with('success', 'Diseño aprobado correctamente. La administración podrá continuar con el proceso.');
        }

        return redirect()->route('design.summary', $design->id)
            ->with('success', 'Diseño aprobado. Puede procederse al pago de la cuota de gestión.');
    }

    public function rejectDesign(Request $request, $id)
    {
        $design = DesignFormat::with('set')->findOrFail($id);
        $reason = $request->input('reason');
        app(DesignApprovalService::class)->reject($design, auth()->user(), is_string($reason) ? $reason : null);

        return redirect()->route('design.approvals.index')
            ->with('success', 'Diseño rechazado. La administración deberá corregirlo y reenviarlo.');
    }

    public function payManagementFee(Set $set)
    {
        if (! auth()->user()->canAccessEntity((int) $set->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        $set->load('entity.administration');
        $design = DesignFormat::query()->where('set_id', $set->id)->orderByDesc('id')->first();
        $feeService = app(ManagementFeeService::class);

        if ($feeService->isManagementFeeSettled($set)) {
            if ($design) {
                return redirect()->route('design.summary', $design->id)
                    ->with('success', 'La cuota de gestión ya está confirmada.');
            }

            return redirect()->route('design.index');
        }

        if ($feeService->managementFeePaymentBlockedByApproval($design, $set)) {
            if ($design) {
                return redirect()->route('design.summary', $design->id)
                    ->with('warning', 'El diseño debe ser aprobado por la entidad antes del pago.');
            }

            return redirect()->route('design.index')
                ->with('warning', 'El diseño debe ser aprobado por la entidad antes del pago.');
        }

        $feeService->ensureSnapshot($set, $design);
        $managementFee = $feeService->buildSummaryContext($set, auth()->user(), $design);
        [$stripePublishableKey, ] = app(ManagementFeePaymentService::class)->resolveStripeKeys();
        $stripePaymentEnabled = app(ManagementFeePaymentService::class)->hasStripeConfigured();
        $paymentSuccessRedirectUrl = $this->managementFeePaymentSuccessUrl($set, $design);

        return view('design.pay_management_fee', compact(
            'set',
            'design',
            'managementFee',
            'stripePublishableKey',
            'stripePaymentEnabled',
            'paymentSuccessRedirectUrl'
        ));
    }

    public function createManagementFeePaymentIntent(Set $set)
    {
        if (! auth()->user()->canAccessEntity((int) $set->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        $set->load('entity');
        $design = DesignFormat::query()->where('set_id', $set->id)->orderByDesc('id')->first();
        $result = app(ManagementFeePaymentService::class)->createPaymentIntent($set, auth()->user(), $design);

        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    public function confirmManagementFeeStripe(Request $request, Set $set)
    {
        if (! auth()->user()->canAccessEntity((int) $set->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        $data = $request->validate([
            'stripe_payment_intent_id' => 'required|string',
        ]);

        $design = DesignFormat::query()->where('set_id', $set->id)->orderByDesc('id')->first();
        app(ManagementFeePaymentService::class)->confirmStripePayment(
            $set,
            $data['stripe_payment_intent_id'],
            auth()->user(),
            $design
        );

        return $this->redirectAfterManagementFeePayment(
            $set->fresh(),
            $design,
            'Cuota de gestión PARTILOT pagada correctamente.'
        );
    }

    public function confirmManagementFeeRemittance(Set $set)
    {
        if (! auth()->user()->canAccessEntity((int) $set->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        $set->load('entity.administration');
        $design = DesignFormat::query()->where('set_id', $set->id)->orderByDesc('id')->first();

        app(AdministrationBillingService::class)->queueManagementFeeCharge(
            $set,
            auth()->user(),
            $design
        );

        return $this->redirectAfterManagementFeePayment(
            $set->fresh(),
            $design,
            'Cuota de gestión registrada en remesa.'
        );
    }

    public function openEditor(Set $set)
    {
        if (! auth()->user()->canAccessEntity((int) $set->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        $set->load(['reserve', 'entity']);
        if ($redirect = $this->redirectIfEntityCannotDesign($set->entity)) {
            return $redirect;
        }

        $feeService = app(ManagementFeeService::class);
        $approvalService = app(DesignApprovalService::class);
        if ($feeService->blocksAdminDesignUntilEntityPays($set)) {
            $design = DesignFormat::query()->where('set_id', $set->id)->orderByDesc('id')->first();
            if ($approvalService->isAdministrationSideUser(auth()->user())) {
                if ($design) {
                    return redirect()->route('design.summary', $design->id)
                        ->with('warning', 'La entidad debe pagar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
                }

                return redirect()->route('design.index')
                    ->with('warning', 'La entidad debe pagar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
            }

            return redirect()->route('design.managementFee.pay', $set->id)
                ->with('info', 'Debe confirmar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
        }

        $lotteryId = $set->reserve?->lottery_id ?? session('design_lottery_id');
        if (! $lotteryId) {
            return redirect()->route('design.index')->with('error', 'No se pudo determinar el sorteo del set.');
        }

        session([
            'design_entity_id' => $set->entity_id,
            'design_lottery_id' => $lotteryId,
            'design_set_id' => $set->id,
        ]);

        $request = Request::create(route('design.format'), 'POST', [
            'set_id' => $set->id,
            'new_design' => 1,
        ]);
        $request->setLaravelSession(session());

        return $this->format($request);
    }

    public function markManagementFeePaid(Set $set)
    {
        if (! auth()->user()->canAccessEntity((int) $set->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        $set = app(ManagementFeeService::class)->markAsPaid($set, auth()->user());

        $design = DesignFormat::query()
            ->where('set_id', $set->id)
            ->orderByDesc('id')
            ->first();

        return $this->redirectAfterManagementFeePayment(
            $set->fresh(),
            $design,
            'Cuota de gestión PARTILOT confirmada.'
        );
    }

    public function sendToPrint($id)
    {
        $design = DesignFormat::with(['set', 'lottery', 'entity'])->findOrFail($id);
        if (!auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }
        if ($redirect = $this->redirectIfPrintOrderSubmissionBlocked($design)) {
            return $redirect;
        }
        if ($this->designSetIsDigitalOnly($design->set)) {
            return redirect()->route('design.summary', $design->id)
                ->with('warning', 'Los sets de participaciones digitales no se envían a imprenta.');
        }
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContext($design, auth()->user());
        if (empty($printPayment['user_may_submit'])) {
            return redirect()->route('design.summary', $design->id)
                ->with('warning', $printPayment['user_submit_block_reason'] ?? 'No puede gestionar el pago de impresión de este diseño.');
        }
        $printOrderLock = $this->getPrintOrderLockContext($design->id);
        if ($printOrderLock['locked']) {
            return redirect()->route('design.summary', $design->id)
                ->with('warning', $printOrderLock['message']);
        }

        $selectedPrintShop = PrintConfiguration::resolveDefault();
        $output = is_array($design->output ?? null) ? $design->output : [];
        $defaults = [
            'print_size' => (string) ($output['format'] ?? 'custom'),
            'participations_per_book' => (int) ($output['participations_per_book'] ?? 50),
            'back_mode' => 'bw',
            'print_configuration_id' => (int) $selectedPrintShop->id,
        ];
        $quote = $this->calculatePrintOrderQuote($design->set, $defaults, chargeDesignFee: false);
        [$stripePublishableKey, $stripeSecretKey] = $this->resolveStripeKeys($selectedPrintShop);
        $stripePaymentEnabled = $selectedPrintShop->hasStripeConfigured() && ! empty($printPayment['can_pay_stripe']);

        return view('design.send_to_print', compact(
            'design',
            'defaults',
            'quote',
            'printPayment',
            'stripePublishableKey',
            'stripePaymentEnabled',
            'selectedPrintShop'
        ));
    }

    public function previewPrintOrderQuote(Request $request, $id)
    {
        $design = DesignFormat::with('set')->findOrFail($id);
        if (! auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }
        if ($blockMessage = $this->printOrderSubmissionBlockMessage($design)) {
            return response()->json(['ok' => false, 'message' => $blockMessage], 422);
        }

        $data = $request->validate($this->printOrderSubmissionRules());
        $cfg = PrintConfiguration::resolveDefault();
        $data['print_configuration_id'] = $cfg->id;
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false);
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContext($design, auth()->user());
        [$publishableKey, $secretKey] = $this->resolveStripeKeys($cfg);

        return response()->json([
            'ok' => true,
            'quote' => $quote,
            'print_payment' => $printPayment,
            'stripe_payment_enabled' => $cfg->hasStripeConfigured() && ! empty($printPayment['can_pay_stripe']),
            'stripe_publishable_key' => $publishableKey,
        ]);
    }

    /**
     * Crea PaymentIntent de Stripe para enviar a imprenta un diseño ya elaborado.
     */
    public function createPrintOrderPaymentIntent(Request $request, $id)
    {
        $design = DesignFormat::with(['set', 'lottery', 'entity'])->findOrFail($id);
        if (! auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }
        if ($blockMessage = $this->printOrderSubmissionBlockMessage($design)) {
            return response()->json(['ok' => false, 'message' => $blockMessage], 422);
        }

        $design->loadMissing('entity');
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContext($design, auth()->user());
        if (empty($printPayment['can_pay_stripe'])) {
            return response()->json([
                'ok' => false,
                'message' => $printPayment['user_submit_block_reason'] ?? 'Este pedido no admite pago con tarjeta para su perfil.',
            ], 422);
        }

        $data = $request->validate($this->printOrderSubmissionRules());
        $cfg = PrintConfiguration::resolveDefault();
        $data['print_configuration_id'] = $cfg->id;
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false);
        $total = (float) ($quote['total'] ?? 0);
        if ($total <= 0) {
            return response()->json(['ok' => false, 'message' => 'El importe del pedido debe ser mayor que cero.'], 422);
        }

        [$publishableKey, $secretKey] = $this->resolveStripeKeys($cfg);
        if ($secretKey === '' || $publishableKey === '') {
            return response()->json(['ok' => false, 'message' => 'Stripe no está configurado para esta imprenta. Revisa Ajustes → Imprenta.'], 500);
        }

        try {
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $response = $client->post('payment_intents', [
                'auth' => [$secretKey, ''],
                'form_params' => [
                    'amount' => (int) round($total * 100),
                    'currency' => 'eur',
                    'description' => 'Imprenta — diseño #'.$design->id,
                    'metadata[design_format_id]' => (string) $design->id,
                    'metadata[set_id]' => (string) $design->set_id,
                    'metadata[entity_id]' => (string) $design->entity_id,
                    'metadata[print_configuration_id]' => (string) $cfg->id,
                    'automatic_payment_methods[enabled]' => 'true',
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            if (! is_array($payload) || empty($payload['client_secret']) || empty($payload['id'])) {
                return response()->json(['ok' => false, 'message' => 'No se pudo crear el PaymentIntent.'], 500);
            }

            return response()->json([
                'ok' => true,
                'client_secret' => (string) $payload['client_secret'],
                'publishable_key' => $publishableKey,
                'amount' => $total,
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe PaymentIntent (send to print) error', ['error' => $e->getMessage(), 'design_id' => $design->id]);

            return response()->json(['ok' => false, 'message' => 'Error creando el pago con Stripe.'], 500);
        }
    }

    public function submitPrintOrder(Request $request, $id)
    {
        $design = DesignFormat::with(['set', 'lottery', 'entity'])->findOrFail($id);
        if (! auth()->user()->canAccessEntity((int) $design->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }
        if ($redirect = $this->redirectIfPrintOrderSubmissionBlocked($design)) {
            return $redirect;
        }

        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContext($design, auth()->user());
        if (empty($printPayment['user_may_submit'])) {
            return redirect()->route('design.summary', $design->id)
                ->with('warning', $printPayment['user_submit_block_reason'] ?? 'No puede gestionar el pago de impresión de este diseño.');
        }

        $usesRemittance = ! empty($printPayment['can_queue_remittance']);

        $data = $request->validate(array_merge($this->printOrderSubmissionRules(), [
            'payment_method' => 'nullable|in:stripe,remittance',
            'stripe_payment_intent_id' => 'nullable|required_if:payment_method,stripe|string',
        ]), [
            'stripe_payment_intent_id.required_if' => 'No se encontró el pago de Stripe confirmado.',
        ]);

        if ($usesRemittance) {
            return $this->submitPrintOrderViaRemittance($request, $design, $data);
        }

        if (empty($printPayment['can_pay_stripe'])) {
            return redirect()->route('design.sendToPrint', $design->id)
                ->withInput()
                ->with('error', $printPayment['user_submit_block_reason'] ?? 'No hay un medio de pago disponible para su perfil.');
        }

        if (empty($data['stripe_payment_intent_id'])) {
            return redirect()->route('design.sendToPrint', $design->id)
                ->withInput()
                ->with('error', 'No se encontró el pago de Stripe confirmado.');
        }

        $cfg = PrintConfiguration::resolveDefault();
        $data['print_configuration_id'] = $cfg->id;
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false);
        $expectedTotal = round((float) ($quote['total'] ?? 0), 2);
        if ($expectedTotal <= 0) {
            return redirect()->route('design.sendToPrint', $design->id)
                ->withInput()
                ->with('error', 'El importe del pedido debe ser mayor que cero.');
        }

        $paymentIntentId = (string) $data['stripe_payment_intent_id'];
        $piPayload = $this->fetchStripePaymentIntent($paymentIntentId, $cfg);
        if (! is_array($piPayload) || ($piPayload['status'] ?? '') !== 'succeeded') {
            return redirect()->route('design.sendToPrint', $design->id)
                ->withInput()
                ->with('error', 'El pago no está confirmado en Stripe. Intenta de nuevo.');
        }

        $piAmount = (int) ($piPayload['amount'] ?? 0);
        if ($piAmount !== (int) round($expectedTotal * 100)) {
            return redirect()->route('design.sendToPrint', $design->id)
                ->withInput()
                ->with('error', 'El importe pagado no coincide con el presupuesto actual. Vuelve a intentar el pago.');
        }

        $metadata = is_array($piPayload['metadata'] ?? null) ? $piPayload['metadata'] : [];
        if ((string) ($metadata['design_format_id'] ?? '') !== (string) $design->id) {
            return redirect()->route('design.sendToPrint', $design->id)
                ->withInput()
                ->with('error', 'El pago no corresponde a este diseño.');
        }

        $duplicateOrder = null;
        $createdOrder = null;
        $lock = Cache::lock('print-order-stripe-pi:'.sha1($paymentIntentId), 25);
        $lock->block(12);
        try {
            DB::transaction(function () use ($paymentIntentId, $design, $data, $quote, $cfg, &$duplicateOrder, &$createdOrder) {
                $existing = PrintOrder::query()
                    ->where('payment_intent_id', $paymentIntentId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    $duplicateOrder = $existing;
                    $this->insertPrintOrderAuditRow(
                        printOrder: $existing,
                        action: 'duplicate_payment_intent_blocked',
                        message: 'Intento de registrar otra orden con el mismo PaymentIntent Stripe ('.$paymentIntentId.').',
                        userId: auth()->id()
                    );

                    return;
                }

                $orderCode = 'OPI'.str_pad((string) (PrintOrder::max('id') + 1), 6, '0', STR_PAD_LEFT);
                $createdOrder = PrintOrder::create([
                    'print_configuration_id' => $cfg->id,
                    'order_code' => $orderCode,
                    'design_format_id' => $design->id,
                    'set_id' => $design->set_id,
                    'entity_id' => $design->entity_id,
                    'lottery_id' => $design->lottery_id,
                    'created_by_user_id' => auth()->id(),
                    'status' => PrintOrder::STATUS_PENDING_REVIEW,
                    'payment_provider' => 'stripe',
                    'payment_intent_id' => $paymentIntentId,
                    'payment_status' => PrintOrder::PAYMENT_STATUS_PAID,
                    'print_size' => $data['print_size'],
                    'participations_per_book' => (int) $data['participations_per_book'],
                    'back_mode' => $data['back_mode'],
                    'quoted_amount' => $quote['total'],
                    'quote_breakdown' => $quote,
                    'notes' => $data['notes'] ?? null,
                    'sent_at' => null,
                    'paid_at' => now(),
                ]);

                $this->insertPrintOrderAuditRow(
                    printOrder: $createdOrder,
                    action: 'order_created_stripe',
                    message: 'Orden creada con pago Stripe (diseño existente). PI: '.$paymentIntentId,
                    userId: auth()->id()
                );

                $this->afterPrintOrderCreated($createdOrder, $design);
            });
        } finally {
            $lock->release();
        }

        if ($duplicateOrder) {
            return redirect()->route('design.summary', $design->id)
                ->with('warning', 'Este pago ya tiene una orden de imprenta registrada ('.$duplicateOrder->order_code.').');
        }

        $successMessage = 'Pago confirmado y orden de imprenta enviada correctamente.';
        if ($createdOrder && ! $createdOrder->fresh()->isVisibleToPrintShop()) {
            $successMessage = 'Pago confirmado. La imprenta recibirá el pedido cuando la entidad abone la cuota de gestión PARTILOT.';
        }

        return redirect()->route('design.summary', $design->id)
            ->with('success', $successMessage);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return \Illuminate\Http\RedirectResponse
     */
    private function submitPrintOrderViaRemittance(Request $request, DesignFormat $design, array $data)
    {
        if (! app(AdministrationBillingService::class)->canSubmitPrintOrderViaRemittance(auth()->user(), $design)) {
            abort(403, 'No puedes enviar este pedido a imprenta por remesa.');
        }

        $cfg = PrintConfiguration::resolveDefault();
        $data['print_configuration_id'] = $cfg->id;
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false);
        $expectedTotal = round((float) ($quote['total'] ?? 0), 2);
        if ($expectedTotal <= 0) {
            return redirect()->route('design.sendToPrint', $design->id)
                ->withInput()
                ->with('error', 'El importe del pedido debe ser mayor que cero.');
        }

        $order = null;
        DB::transaction(function () use ($design, $data, $quote, $cfg, &$order) {
            $orderCode = 'OPI'.str_pad((string) (PrintOrder::max('id') + 1), 6, '0', STR_PAD_LEFT);
            $order = PrintOrder::create([
                'print_configuration_id' => $cfg->id,
                'order_code' => $orderCode,
                'design_format_id' => $design->id,
                'set_id' => $design->set_id,
                'entity_id' => $design->entity_id,
                'lottery_id' => $design->lottery_id,
                'created_by_user_id' => auth()->id(),
                'status' => PrintOrder::STATUS_PENDING_REVIEW,
                'payment_provider' => PrintOrder::PAYMENT_PROVIDER_REMITTANCE,
                'payment_intent_id' => null,
                'payment_status' => PrintOrder::PAYMENT_STATUS_PAID,
                'print_size' => $data['print_size'],
                'participations_per_book' => (int) $data['participations_per_book'],
                'back_mode' => $data['back_mode'],
                'quoted_amount' => $quote['total'],
                'quote_breakdown' => $quote,
                'notes' => $data['notes'] ?? null,
                'sent_at' => null,
                'paid_at' => now(),
            ]);

            app(AdministrationBillingService::class)->queuePrintFeeCharge($order, auth()->user());

            $this->insertPrintOrderAuditRow(
                printOrder: $order,
                action: 'order_created_remittance',
                message: 'Orden creada con cargo en remesa periódica.',
                userId: auth()->id()
            );

            $this->afterPrintOrderCreated($order, $design);
        });

        $successMessage = 'Pedido enviado a imprenta. El importe quedará pendiente de adeudo en la próxima remesa.';
        if ($order && ! $order->fresh()->isVisibleToPrintShop()) {
            $successMessage = 'Pedido registrado. La imprenta lo recibirá cuando la entidad abone la cuota de gestión PARTILOT.';
        }

        return redirect()->route('design.summary', $design->id)
            ->with('success', $successMessage);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function submitExternalPartilotViaRemittance(
        DesignExternalInvitation $invitation,
        array $quote,
        PrintConfiguration $cfg
    ) {
        $invitation->loadMissing('entity.administration');
        $design = DesignFormat::query()
            ->where('entity_id', (int) $invitation->entity_id)
            ->where('set_id', (int) $invitation->set_id)
            ->orderByDesc('id')
            ->first();

        if (! $design) {
            $design = DesignFormat::create([
                'entity_id' => (int) $invitation->entity_id,
                'lottery_id' => (int) $invitation->lottery_id,
                'set_id' => (int) $invitation->set_id,
                'output' => [
                    'participations_per_book' => (int) ($invitation->participations_per_book ?? 50),
                ],
            ]);
        }

        $expectedTotal = round((float) ($quote['total'] ?? 0), 2);
        if ($expectedTotal <= 0) {
            return redirect()->route('design.external.step3')
                ->with('error', 'El importe del pedido debe ser mayor que cero.');
        }

        $order = null;
        DB::transaction(function () use ($invitation, $quote, $cfg, $design, &$order) {
            $orderCode = 'OPI'.str_pad((string) (PrintOrder::max('id') + 1), 6, '0', STR_PAD_LEFT);
            $order = PrintOrder::create([
                'print_configuration_id' => $cfg->id,
                'order_code' => $orderCode,
                'design_format_id' => (int) $design->id,
                'set_id' => $invitation->set_id,
                'entity_id' => $invitation->entity_id,
                'lottery_id' => $invitation->lottery_id,
                'created_by_user_id' => auth()->id(),
                'status' => PrintOrder::STATUS_PENDING_REVIEW,
                'payment_provider' => PrintOrder::PAYMENT_PROVIDER_REMITTANCE,
                'payment_intent_id' => null,
                'payment_status' => PrintOrder::PAYMENT_STATUS_PAID,
                'print_size' => $invitation->print_size,
                'participations_per_book' => $invitation->participations_per_book,
                'back_mode' => $invitation->back_mode,
                'quoted_amount' => $quote['total'],
                'quote_breakdown' => $quote,
                'notes' => trim((string) ($invitation->comment ?? ''))."\n[PAGO REMESA] Flujo Diseño e Impresión PARTILOT.",
                'sent_at' => null,
                'paid_at' => now(),
            ]);

            app(AdministrationBillingService::class)->queuePrintFeeCharge($order, auth()->user());

            $this->insertPrintOrderAuditRow(
                printOrder: $order,
                action: 'order_created_remittance',
                message: 'Orden PARTILOT creada con cargo en remesa periódica.',
                userId: auth()->id()
            );

            $this->afterPrintOrderCreated($order, $design);
        });

        $invitation->update(['status' => DesignExternalInvitation::STATUS_SENT, 'sent_at' => now()]);
        session()->forget(['design_external_invitation_id', 'design_external_mode']);

        $successMessage = 'Pedido enviado a imprenta. El importe quedará pendiente de adeudo en la próxima remesa.';
        if ($order && ! $order->fresh()->isVisibleToPrintShop()) {
            $successMessage = 'Pedido registrado. La imprenta lo recibirá cuando la entidad abone la cuota de gestión PARTILOT.';
        }

        return redirect()->route('design.external.list')
            ->with('success', $successMessage);
    }

    /**
     * @return array<string, string>
     */
    private function printOrderSubmissionRules(): array
    {
        return [
            'print_configuration_id' => ['nullable', 'integer', 'exists:print_configurations,id'],
            'print_size' => 'required|string|in:a3_6,a3_8,custom',
            'participations_per_book' => 'required|integer|min:1|max:1000',
            'back_mode' => 'required|string|in:bw,color',
            'notes' => 'nullable|string|max:4000',
        ];
    }

    private function entityAwaitingAdminDesignAfterFeePayment(DesignFormat $design): bool
    {
        $design->loadMissing('set.entity');
        if (! $design->set?->entity) {
            return false;
        }

        $feeService = app(ManagementFeeService::class);
        $approvalService = app(DesignApprovalService::class);

        return $feeService->requiresEntityPaymentBeforeAdminDesign($design->set->entity)
            && $feeService->isManagementFeeSettled($design->set)
            && $approvalService->requiresEntityApproval($design)
            && $approvalService->normalizedApprovalStatus($design->approval_status) === DesignApprovalService::STATUS_DRAFT;
    }

    private function managementFeePaymentSuccessUrl(Set $set, ?DesignFormat $design): string
    {
        $user = auth()->user();
        $approvalService = app(DesignApprovalService::class);

        if ($design && $user->isEntity()
            && ! $approvalService->userActsAsAdministration($user)
            && ! $approvalService->isVisibleToEntityViewer($design)) {
            return route('design.index');
        }

        if ($design) {
            return route('design.summary', $design->id);
        }

        return route('design.index');
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    private function redirectAfterManagementFeePayment(Set $set, ?DesignFormat $design, string $baseMessage)
    {
        $user = auth()->user();
        $approvalService = app(DesignApprovalService::class);
        $feeService = app(ManagementFeeService::class);
        $set->loadMissing('entity');

        if ($design && $approvalService->userActsAsAdministration($user)) {
            $entity = $set->entity;
            if ($entity
                && $feeService->requiresEntityPaymentBeforeAdminDesign($entity)
                && $feeService->isManagementFeeSettled($set)
                && $approvalService->canOpenDesignEditor($user, $design)) {
                return redirect()->route('design.editFormat', $design->id)
                    ->with('success', $baseMessage.' Ya puede continuar con el diseño.');
            }
        }

        if ($design && $user->isEntity()
            && ! $approvalService->userActsAsAdministration($user)
            && ! $approvalService->isVisibleToEntityViewer($design)) {
            return redirect()->route('design.index')
                ->with('success', $baseMessage.' La administración continuará con el diseño y le avisará cuando pueda revisarlo.');
        }

        if ($feeService->isManagementFeeSettled($set)
            && $approvalService->requiresPreEditorPayment($user, $set->entity)) {
            if ($design) {
                return redirect()->route('design.editFormat', $design->id)
                    ->with('success', $baseMessage.' Ya puede continuar con el diseño.');
            }

            return redirect()->route('design.openEditor', $set->id)
                ->with('success', $baseMessage.' Ya puede acceder al editor.');
        }

        if ($design) {
            return redirect()->route('design.summary', $design->id)
                ->with('success', $baseMessage.' Ya puede generar los PDF con códigos QR.');
        }

        if ($approvalService->userActsAsAdministration($user)) {
            return redirect()->route('design.openEditor', $set->id)
                ->with('success', $baseMessage.' Ya puede acceder al editor.');
        }

        return redirect()->route('design.index')->with('success', $baseMessage);
    }

    private function printOrderSubmissionBlockMessage(DesignFormat $design): ?string
    {
        if ($this->designSetIsDigitalOnly($design->set)) {
            return 'Los sets de participaciones digitales no se envían a imprenta.';
        }

        $approvalService = app(DesignApprovalService::class);
        if ($approvalService->isAwaitingEntityManagementFeeBeforeAdminDesign($design)) {
            return 'La entidad debe pagar la cuota de gestión PARTILOT antes de continuar con el diseño y el envío a imprenta.';
        }

        if (! $approvalService->designHasParticipationContent($design)) {
            return 'El diseño de participación aún no está creado. Complete el diseño antes de enviar a imprenta.';
        }

        if ($approvalService->blocksQrExport($design)) {
            return $approvalService->blockMessage($design);
        }

        $printOrderLock = $this->getPrintOrderLockContext($design->id);
        if ($printOrderLock['locked']) {
            return (string) ($printOrderLock['message'] ?? 'Este diseño no puede enviarse a imprenta.');
        }

        $design->loadMissing('entity');
        $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContext($design, auth()->user());
        if (empty($printPayment['user_may_submit'])) {
            return $printPayment['user_submit_block_reason'];
        }

        return null;
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|null
     */
    private function redirectIfPrintOrderSubmissionBlocked(DesignFormat $design)
    {
        $message = $this->printOrderSubmissionBlockMessage($design);
        if ($message === null) {
            return null;
        }

        return redirect()->route('design.summary', $design->id)->with('warning', $message);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchStripePaymentIntent(string $paymentIntentId, ?PrintConfiguration $cfg = null): ?array
    {
        if ($paymentIntentId === '') {
            return null;
        }

        [, $secretKey] = $this->resolveStripeKeys($cfg);
        if ($secretKey === '') {
            return null;
        }

        try {
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $response = $client->get('payment_intents/'.$paymentIntentId, [
                'auth' => [$secretKey, ''],
            ]);
            $payload = json_decode((string) $response->getBody(), true);

            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            Log::error('Stripe fetch payment intent error', ['error' => $e->getMessage(), 'pi' => $paymentIntentId]);

            return null;
        }
    }

    /**
     * Presupuesto de envío a imprenta desde el panel.
     *
     * @param  bool  $chargeDesignFee  Si es false (por defecto), el usuario ya elaboró el diseño en PARTILOT y no se factura la tarifa de diseño.
     *                                El flujo externo (invitación / sin editor) usa {@see calculateExternalInvitationQuote} e incluye diseño.
     */
    private function calculatePrintOrderQuote(Set $set, array $input, bool $chargeDesignFee = false): array
    {
        $cfg = $this->resolveActivePrintConfiguration(
            isset($input['print_configuration_id']) ? (int) $input['print_configuration_id'] : null
        );

        return app(PrintQuoteService::class)->calculateForSet($set, $cfg, $input, $chargeDesignFee);
    }

    /**
     * Actualiza el formato en la base de datos.
     */
    public function updateFormat(Request $request, $id)
    {
        // return $request->all();

        $format = DesignFormat::findOrFail($id);
        if (! $this->userCanAccessDesignFormat(auth()->user(), $format)) {
            abort(403, 'No tienes permisos para actualizar este diseño.');
        }
        if ($format->set) {
            $printOrderLock = $this->getPrintOrderLockContext($format->id);
            if ($printOrderLock['locked']) {
                return response()->json([
                    'success' => false,
                    'message' => $printOrderLock['message'],
                    'code' => 'SET_DESIGN_LOCKED',
                ], 422);
            }
            $designLock = $this->getSetDesignLockContext($format->set);
            if ($designLock['locked']) {
                $this->logDesignLockAudit($format->set, 'update_format_blocked', $designLock, $format->id);
                return response()->json([
                    'success' => false,
                    'message' => $designLock['message'],
                    'code' => 'SET_DESIGN_LOCKED',
                ], 422);
            }
        }
        // Procesar el JSON enviado desde el frontend (campo 'data')
        // if ($request->has('data')) {
            // $data = json_decode($request->input('data'), true);
            $data = $request->all();
            if (is_array($data)) {
                // Asignar los campos principales
                $format->format = $data['format'] ?? $format->format;
                $format->page = $data['page'] ?? $format->page;
                $format->rows = $data['rows'] ?? $format->rows;
                $format->cols = $data['cols'] ?? $format->cols;
                $format->orientation = $data['orientation'] ?? $format->orientation;
                $format->identation = $data['identation'] ?? $format->identation;
                $format->matrix_box = $data['matrix_box'] ?? $format->matrix_box;
                $format->horizontal_space = $data['horizontal_space'] ?? $format->horizontal_space;
                $format->vertical_space = $data['vertical_space'] ?? $format->vertical_space;
                $format->margin_custom = $data['margin_custom'] ?? $format->margin_custom;
                $format->participation_html = $data['participation_html'] ?? $format->participation_html;
                $format->cover_html = $data['cover_html'] ?? $format->cover_html;
                $format->back_html = $data['back_html'] ?? $format->back_html;
                if (array_key_exists('back_skipped', $data)) {
                    $format->back_skipped = (bool) $data['back_skipped'];
                }
                if (! empty($data['design_name'])) {
                    $format->design_name = $data['design_name'];
                }
                $format->snapshot_path = $data['snapshot_path'] ?? $format->snapshot_path;
                // Guardar los campos JSON como string si corresponde
                if (isset($data['margins'])) $format->margins = $data['margins'];
                if (isset($data['backgrounds'])) $format->backgrounds = $data['backgrounds'];
                if (isset($data['output'])) {
                    $output = $data['output'];
                    // Sets digitales: un solo taco (serie 1..N)
                    $set = $format->set;
                    if ($set && $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0) {
                        $output['participations_per_book'] = (int) $set->total_participations;
                    }
                    // Tarea 1 tacos: regenerar taco_qrs al guardar output (participations_per_book puede haber cambiado)
                    $format->output = DesignFormat::mergeTacoQrsIntoOutput($format->set_id, $output ?? []);
                }
                $format->save();

                if (isset($data['from_step_5']) && $data['from_step_5'] === true) {
                    app(DesignApprovalService::class)->assignDesignerTypeIfMissing($format, $this->resolveDesignSaveUser());
                } else {
                    app(DesignApprovalService::class)->invalidateApprovalAfterEdit($format->refresh());
                }
                
                // Si viene del paso 5 (configurar salida), redirigir a la vista de resumen
                if (isset($data['from_step_5']) && $data['from_step_5'] === true) {
                    return response()->json([
                        'success' => true,
                        'redirect' => session('print_shop_order_id')
                            ? route('print-shop.orders.show', session('print_shop_order_id'))
                            : route('design.summary', $id),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'redirect' => session('print_shop_order_id')
                        ? route('print-shop.orders.show', session('print_shop_order_id'))
                        : route('design.editFormat', $id),
                ]);
            }

        return response()->json([
            'success' => false,
            'message' => 'No se recibieron datos válidos para guardar el diseño.',
        ], 422);
    }


    /**
     * Generar páginas optimizado para evitar bucles anidados costosos
     */
    private function generatePagesOptimized($tickets_to_print, $total_pages, $per_page)
    {
        $pages = [];
        $ticket_count = count($tickets_to_print);
        
        for ($p = 0; $p < $total_pages; $p++) {
            $pages[$p] = [];
            for ($i = 0; $i < $per_page; $i++) {
                $ticket_index = $p + ($i * $total_pages);
                if ($ticket_index < $ticket_count) {
                    $pages[$p][$i] = $tickets_to_print[$ticket_index];
                }
            }
        }
        
        return $pages;
    }

    /**
     * Generar PDF en lotes para PDFs muy grandes
     */
    private function generatePdfInChunks($design, $participation_html, $tickets, $from, $to, $rows, $cols, $page, $pdfOrientation, $qrCodes = [])
    {
        $per_page = $rows * $cols;
        $chunk_size = 100; // Procesar de 100 en 100
        $total = $to - $from + 1;
        $total_pages = ceil($total / $per_page);
        
        // Crear archivo temporal para combinar PDFs
        $temp_files = [];
        
        for ($chunk_start = $from - 1; $chunk_start < $to; $chunk_start += $chunk_size) {
            $chunk_end = min($chunk_start + $chunk_size, $to);
            $chunk_tickets = array_slice($tickets, $chunk_start, $chunk_end - $chunk_start);
            
            // Calcular páginas para este chunk
            $chunk_pages = ceil(count($chunk_tickets) / $per_page);
            $pages = $this->generatePagesOptimized($chunk_tickets, $chunk_pages, $per_page);
            
            // Generar PDF para este chunk
            $pdf = Pdf::loadView('design.pdf_participation', [
                'pages' => $pages,
                'participation_html' => $participation_html,
                'rows' => $rows,
                'cols' => $cols,
                'qrCodes' => $qrCodes,
            ])->setPaper($page, $pdfOrientation);

            // Guardar en archivo temporal

            $temp_file = storage_path('app/temp_pdf_' . $chunk_start . '.pdf');
            $pdf->save($temp_file);
            $temp_files[] = $temp_file;
        }
        
        // Combinar PDFs usando una librería como TCPDF o FPDI
        $binary = FpdiPdfMerge::mergeTemporaryFiles($temp_files, false);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="participacion.pdf"'
        ]);
    }

    /**
     * Método alternativo para PDFs muy grandes usando colas
     */
    public function exportParticipationPdfAsync(Request $request, $id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeParticipationQrExport($design);

        try {
            [$from, $to] = $this->resolveParticipationPdfRange($request, $design);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $job_id = 'pdf_part_'.$id.'_'.$from.'_'.$to.'_'.time();
        Queue::push(new \App\Jobs\GenerateParticipationPdfJob($id, $job_id, $from, $to));

        return response()->json([
            'status' => 'processing',
            'job_id' => $job_id,
            'message' => 'El PDF se está generando en segundo plano. Cuando esté listo podrá descargarlo desde el aviso.',
            'check_url' => route('design.checkPdfStatus', $job_id),
        ]);
    }

    /**
     * Portada + trasera asíncronas (mismo pipeline que la descarga directa, archivo en disco).
     */
    public function exportCoverBackPdfAsync($id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);
        if (empty($design->cover_html) || empty($design->back_html)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Portada o trasera no encontradas',
            ], 404);
        }

        $job_id = 'pdf_cover_back_' . $id . '_' . time();
        Queue::push(new \App\Jobs\GenerateCoverBackPdfJob($id, $job_id));

        return response()->json([
            'status' => 'processing',
            'job_id' => $job_id,
            'message' => 'El PDF de portada y trasera se está generando. Cuando esté listo podrá descargarlo desde el aviso.',
            'check_url' => route('design.checkPdfStatus', $job_id),
        ]);
    }

    /**
     * Verificar el estado de un PDF en procesamiento
     */
    public function checkPdfStatus($job_id)
    {
        $file_path = storage_path('app/generated_pdfs/' . $job_id . '.pdf');
        
        if (file_exists($file_path)) {
            return response()->json([
                'status' => 'completed',
                'download_url' => route('design.downloadPdf', $job_id)
            ]);
        }
        
        return response()->json([
            'status' => 'processing',
            'message' => 'El PDF aún se está generando...'
        ]);
    }

    /**
     * Descargar PDF generado
     */
    public function downloadPdf($job_id)
    {
        $file_path = storage_path('app/generated_pdfs/' . $job_id . '.pdf');

        if (!file_exists($file_path)) {
            abort(404, 'PDF no encontrado');
        }

        $meta = GeneratedPdfCatalog::readMeta($job_id);
        if ($meta === null || ! isset($meta['design_format_id'])) {
            abort(403, 'No se puede descargar este archivo.');
        }

        $design = DesignFormat::find($meta['design_format_id']);
        if (! $design || ! auth()->user()?->canExportDesignPdf($design)) {
            abort(403, 'No tienes permisos para descargar este PDF.');
        }

        $downloadName = $meta['download_name'] ?? 'documento.pdf';
        GeneratedPdfCatalog::deleteMeta($job_id);

        return response()->download($file_path, $downloadName)->deleteFileAfterSend(true);
    }


    /**
     * Optimizar imágenes reutilizables en el HTML
     */
    private function optimizeReusableImages($html)
    {
        // Detectar todas las imágenes en el HTML
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        $images = $matches[1];
        
        if (empty($images)) {
            return $html;
        }

        // Agrupar imágenes por hash de contenido (imágenes idénticas)
        $imageGroups = [];
        $optimizedImages = [];
        
        foreach ($images as $imagePath) {
            $fullPath = $this->getImageFullPath($imagePath);
            if (file_exists($fullPath)) {
                $imageHash = md5_file($fullPath);
                if (!isset($imageGroups[$imageHash])) {
                    $imageGroups[$imageHash] = [
                        'original_path' => $imagePath,
                        'full_path' => $fullPath,
                        'optimized_path' => $this->optimizeImage($fullPath, $imageHash),
                        'count' => 0
                    ];
                }
                $imageGroups[$imageHash]['count']++;
                $optimizedImages[$imagePath] = $imageGroups[$imageHash]['optimized_path'];
            }
        }

        // Reemplazar todas las referencias a imágenes con las optimizadas
        foreach ($optimizedImages as $originalPath => $optimizedPath) {
            $html = str_replace($originalPath, $optimizedPath, $html);
        }

        return $html;
    }

    /**
     * Obtener la ruta completa de una imagen
     */
    private function getImageFullPath($imagePath)
    {
        // Si ya es una ruta absoluta
        if (strpos($imagePath, public_path()) === 0) {
            return $imagePath;
        }
        
        // Si es una URL relativa
        if (strpos($imagePath, '/') === 0) {
            return public_path() . $imagePath;
        }
        
        // Si es una URL completa
        if (strpos($imagePath, 'http') === 0) {
            return $imagePath;
        }
        
        // Ruta relativa desde public
        return public_path() . '/' . ltrim($imagePath, '/');
    }

    /**
     * Optimizar una imagen individual
     */
    private function optimizeImage($imagePath, $imageHash)
    {
        $cacheDir = storage_path('app/optimized_images');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $optimizedPath = $cacheDir . '/' . $imageHash . '.jpg';
        
        // Si ya existe la imagen optimizada, devolverla
        if (file_exists($optimizedPath)) {
            return $optimizedPath;
        }

        // Optimizar la imagen
        $this->compressImage($imagePath, $optimizedPath);
        
        return $optimizedPath;
    }

    /**
     * Comprimir imagen para reducir tamaño
     */
    private function compressImage($sourcePath, $destinationPath)
    {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            copy($sourcePath, $destinationPath);
            return;
        }

        $mimeType = $imageInfo['mime'];
        
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                copy($sourcePath, $destinationPath);
                return;
        }

        if (!$sourceImage) {
            copy($sourcePath, $destinationPath);
            return;
        }

        // Comprimir a JPEG con calidad 85% (balance entre calidad y tamaño)
        imagejpeg($sourceImage, $destinationPath, 85);
        imagedestroy($sourceImage);
    }

    /**
     * Optimizar HTML de participación (simplificado - solo si es necesario)
     */
    public function optimizeParticipationHtml($html, $tickets)
    {
        // Solo optimizar imágenes si hay muchas (para evitar ralentizar)
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
        $baseImages = $matches[1];
        
        // Solo optimizar si hay pocas imágenes (para no ralentizar)
        if (count($baseImages) <= 5) {
            $imageService = new ImageOptimizationService();
            
            foreach ($baseImages as $imagePath) {
                $optimizedPath = $imageService->optimizeImage($imagePath);
                if ($optimizedPath) {
                    $html = str_replace($imagePath, $optimizedPath, $html);
                }
            }
        }

        return $html;
    }

    /**
     * Preparar QR codes para todas las participaciones (simplificado)
     */
    private function prepareQrCodesForTickets($tickets)
    {
        if (empty($tickets)) {
            return;
        }

        // Solo generar QR codes únicos para evitar duplicados
        $uniqueReferences = [];
        foreach ($tickets as $ticket) {
            if (isset($ticket['r']) && !in_array($ticket['r'], $uniqueReferences)) {
                $uniqueReferences[] = $ticket['r'];
            }
        }

        // Pre-generar QR codes únicos en lote (mucho más eficiente)
        $qrService = new QrCodeService();
        $qrService->generateMultipleQrCodes($uniqueReferences);
    }

    /**
     * Limpiar QR codes temporales después de generar PDF (deshabilitado)
     */
    private function cleanupTempQrCodes()
    {
        // Los QR codes se mantienen para reutilización
        // Solo se limpian manualmente con el comando
        // $qrService = new QrCodeService();
        // $qrService->clearOldQrCodes(0);
    }

    /**
     * Versiones asíncronas para cover y back PDFs
     */
    private function authorizeDesignPdfExport(DesignFormat $design): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canExportDesignPdf($design)) {
            abort(403, 'No tienes permisos para exportar este diseño.');
        }
    }

    private function authorizeParticipationQrExport(DesignFormat $design): void
    {
        $this->authorizeDesignPdfExport($design);

        $approvalService = app(DesignApprovalService::class);
        if ($approvalService->blocksQrExport($design)) {
            abort(403, $approvalService->blockMessage($design));
        }
    }

    private function redirectIfEntityCannotDesign(Entity $entity)
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $approvalService = app(DesignApprovalService::class);
        if ($user->isEntity()
            && ! $user->isAdministration()
            && $approvalService->administrationDesignOnly($entity)) {
            return redirect()->route('design.index')
                ->with('warning', 'Para esta entidad el diseño lo realiza la administración. Podrá revisarlo cuando se envíe a aprobación.');
        }

        return null;
    }

    private function redirectIfEntityDesignerMustPayManagementFee(Set $set, ?DesignFormat $design = null)
    {
        $feeService = app(ManagementFeeService::class);
        if (! $feeService->blocksAdminDesignUntilEntityPays($set)) {
            return null;
        }

        $approvalService = app(DesignApprovalService::class);
        if ($approvalService->isAdministrationSideUser(auth()->user())) {
            if ($design) {
                return redirect()->route('design.summary', $design->id)
                    ->with('warning', 'La entidad debe pagar la cuota de gestión PARTILOT antes de continuar con el diseño.');
            }

            return null;
        }

        return redirect()->route('design.managementFee.pay', $set->id)
            ->with('info', 'Debe confirmar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
    }

    public function exportCoverPdfAsync($id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);
        if (empty($design->cover_html)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Portada no encontrada',
            ], 404);
        }

        $job_id = 'pdf_cover_grid_'.$id.'_'.time();
        Queue::push(new \App\Jobs\GenerateCoverPdfJob($id, $job_id));

        return response()->json([
            'status' => 'processing',
            'job_id' => $job_id,
            'message' => 'El PDF de portadas se está generando. Cuando esté listo podrá descargarlo desde el aviso.',
            'check_url' => route('design.checkPdfStatus', $job_id),
        ]);
    }

    public function exportBackPdfAsync(Request $request, $id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);
        if (empty($design->back_html)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Trasera no encontrada',
            ], 404);
        }

        $copies = $this->normalizeBackPdfCopies($request->query('copies', 'all'));
        $exactCount = $this->parseBackPdfExactCount($request);

        $job_id = 'pdf_back_'.$id.'_'.time();
        $filename = $exactCount !== null ? 'traseras-'.$exactCount.'.pdf' : ($copies === 'one' ? 'trasera.pdf' : 'traseras.pdf');

        Queue::push(new \App\Jobs\GenerateBackPdfJob($id, $job_id, $copies, $filename, $exactCount));

        $msg = $exactCount !== null
            ? 'El PDF con '.$exactCount.' traseras se está generando.'
            : ($copies === 'one'
                ? 'El PDF con 1 trasera se está generando.'
                : 'El PDF con todas las traseras se está generando.');

        return response()->json([
            'status' => 'processing',
            'job_id' => $job_id,
            'message' => $msg,
            'check_url' => route('design.checkPdfStatus', $job_id),
        ]);
    }

    /**
     * Método genérico para PDFs asíncronos
     */
    private function generateOptimizedPdfAsync($id, $htmlField, $filename)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);

        $job_id = 'pdf_' . preg_replace('/[^a-z0-9_]/i', '', $htmlField) . '_' . $id . '_' . time();
        Queue::push(new \App\Jobs\GenerateSimplePdfJob($id, $htmlField, $job_id, $filename));

        return response()->json([
            'status' => 'processing',
            'job_id' => $job_id,
            'message' => 'El PDF se está generando en segundo plano. Cuando esté listo podrá descargarlo desde el aviso.',
            'check_url' => route('design.checkPdfStatus', $job_id),
        ]);
    }

    public function saveSnapshot(Request $request) {
        try {
            $validated = $request->validate([
                'design_id' => 'required|exists:sets,id',
                'snapshot' => 'required|string',
            ]);
            
            $set = \App\Models\Set::findOrFail($validated['design_id']);
            $imgData = $validated['snapshot'];
            
            \Log::info('Recibido snapshot para set ID: ' . $set->id . ', longitud del string: ' . strlen($imgData));
            
            // Limpiar el string base64 - manejar diferentes formatos
            $img = $imgData;
            if (strpos($img, 'data:image/png;base64,') === 0) {
                $img = str_replace('data:image/png;base64,', '', $img);
            } elseif (strpos($img, 'data:image/jpeg;base64,') === 0) {
                $img = str_replace('data:image/jpeg;base64,', '', $img);
            }
            $img = str_replace(' ', '+', $img);
            $img = trim($img);
            
            // Decodificar base64
            $decodedImage = base64_decode($img, true);
            
            if ($decodedImage === false || empty($decodedImage)) {
                \Log::error('Error al decodificar imagen base64 para set ID: ' . $set->id . '. String recibido (primeros 100 chars): ' . substr($imgData, 0, 100));
                return response()->json([
                    'success' => false,
                    'message' => 'Error al procesar la imagen: datos base64 inválidos'
                ], 422);
            }
            
            \Log::info('Imagen decodificada correctamente, tamaño: ' . strlen($decodedImage) . ' bytes');
            
            // Asegurar que el directorio existe
            $directory = 'design_snapshots';
            
            // Verificar permisos de escritura en storage/public
            $storagePath = storage_path('app/public');
            if (!is_dir($storagePath)) {
                \Log::error('El directorio storage/app/public no existe: ' . $storagePath);
                return response()->json([
                    'success' => false,
                    'message' => 'Error: El directorio de storage no existe. Ejecute: php artisan storage:link'
                ], 500);
            }
            
            if (!is_writable($storagePath)) {
                \Log::error('El directorio storage/app/public no tiene permisos de escritura: ' . $storagePath);
                return response()->json([
                    'success' => false,
                    'message' => 'Error: Sin permisos de escritura en storage'
                ], 500);
            }
            
            // Crear directorio usando Storage facade primero
            try {
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory, 0755, true);
                    \Log::info('Directorio creado usando Storage: ' . $directory);
                }
            } catch (\Exception $e) {
                \Log::warning('Error al crear directorio con Storage, intentando método alternativo: ' . $e->getMessage());
            }
            
            $fileName = $directory . '/design_set_' . $set->id . '.png';
            
            // IMPORTANTE: Obtener el DesignFormat ANTES de guardar para poder eliminar el snapshot anterior
            $format = DesignFormat::where('set_id', $set->id)->first();
            $oldSnapshotPath = null;
            if ($format && $format->snapshot_path) {
                $oldSnapshotPath = $format->snapshot_path;
            }
            
            // Eliminar el snapshot anterior ANTES de guardar el nuevo (si existe y es diferente)
            if ($oldSnapshotPath && $oldSnapshotPath !== $fileName) {
                try {
                    if (Storage::disk('public')->exists($oldSnapshotPath)) {
                        Storage::disk('public')->delete($oldSnapshotPath);
                        \Log::info('Snapshot anterior eliminado ANTES de guardar nuevo: ' . $oldSnapshotPath);
                    }
                } catch (\Exception $e) {
                    \Log::warning('No se pudo eliminar snapshot anterior: ' . $e->getMessage());
                }
            }
            
            // Obtener la ruta completa del sistema de archivos
            try {
                $fullPath = Storage::disk('public')->path($fileName);
            } catch (\Exception $e) {
                // Fallback: construir la ruta manualmente
                $fullPath = storage_path('app/public/' . $fileName);
                \Log::info('Usando ruta manual para snapshot: ' . $fullPath);
            }
            
            $directoryPath = dirname($fullPath);
            
            // Asegurar que el directorio existe a nivel del sistema de archivos con permisos correctos
            if (!is_dir($directoryPath)) {
                if (!mkdir($directoryPath, 0755, true)) {
                    \Log::error('No se pudo crear el directorio: ' . $directoryPath);
                    return response()->json([
                        'success' => false,
                        'message' => 'Error al crear el directorio de snapshots'
                    ], 500);
                }
                \Log::info('Directorio creado a nivel de sistema de archivos: ' . $directoryPath);
            }
            
            // Verificar permisos de escritura en el directorio
            if (!is_writable($directoryPath)) {
                \Log::error('El directorio no tiene permisos de escritura: ' . $directoryPath);
                // Intentar cambiar permisos
                @chmod($directoryPath, 0755);
                if (!is_writable($directoryPath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Error: Sin permisos de escritura en el directorio de snapshots'
                    ], 500);
                }
            }
            
            // Guardar el archivo directamente usando file_put_contents con flags de escritura
            $saved = @file_put_contents($fullPath, $decodedImage, LOCK_EX);
            
            if ($saved === false || $saved === 0) {
                \Log::error('Error al guardar snapshot en storage para set ID: ' . $set->id . '. Ruta completa: ' . $fullPath . ', permisos dir: ' . substr(sprintf('%o', fileperms($directoryPath)), -4));
                return response()->json([
                    'success' => false,
                    'message' => 'Error al guardar la imagen en storage. Verifique permisos del servidor.'
                ], 500);
            }
            
            \Log::info('Archivo guardado usando file_put_contents: ' . $fullPath . ', bytes escritos: ' . $saved);
            
            // Verificar que el archivo se guardó correctamente
            if (!file_exists($fullPath)) {
                \Log::error('El archivo no existe después de guardar para set ID: ' . $set->id . '. Ruta completa: ' . $fullPath);
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo no se guardó correctamente'
                ], 500);
            }
            
            // Verificar también con Storage facade
            if (!Storage::disk('public')->exists($fileName)) {
                \Log::warning('El archivo no existe en Storage después de guardar para set ID: ' . $set->id . '. Ruta: ' . $fileName . ', pero existe en filesystem: ' . $fullPath);
            }
            
            $fileSize = filesize($fullPath);
            if ($fileSize === false || $fileSize === 0) {
                \Log::error('El archivo guardado tiene tamaño 0 o no se puede leer para set ID: ' . $set->id);
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo se guardó pero está vacío'
                ], 500);
            }
            
            \Log::info('Archivo guardado exitosamente: ' . $fileName . ' (ruta completa: ' . $fullPath . '), tamaño: ' . $fileSize . ' bytes');
            
            // Guardar la ruta en el DesignFormat del set para que listados/API puedan mostrar la imagen
            if ($format) {
                $format->snapshot_path = $fileName;
                $savedFormat = $format->save();
                
                if ($savedFormat) {
                    \Log::info('Snapshot_path guardado en DesignFormat para set ID: ' . $set->id . ' en: ' . $fileName);
                } else {
                    \Log::error('Error al guardar snapshot_path en DesignFormat para set ID: ' . $set->id);
                }
            } else {
                \Log::warning('No se encontró DesignFormat para set ID: ' . $set->id);
            }
            
            return response()->json([
                'success' => true,
                'path' => $fileName,
                'url' => asset('storage/' . $fileName),
                'file_size' => $fileSize
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error en saveSnapshot: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('Request data: ' . json_encode($request->all()));
            
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar snapshot: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar todos los formatos de diseño.
     * Filtra por entidades accesibles del usuario (respeta rol contexto: gestor administración / gestor entidad).
     */
    public function index()
    {
        $entityIds = auth()->user()->accessibleEntityIds();
        $designs = DesignFormat::with(['entity', 'lottery', 'set'])
            ->whereIn('entity_id', $entityIds)
            ->orderByDesc('id')
            ->get();

        $approvalService = app(DesignApprovalService::class);
        $user = auth()->user();
        if ($user->isEntity() && ! $user->isAdministration()) {
            $designs = $designs
                ->filter(fn (DesignFormat $design) => $approvalService->isVisibleToEntityViewer($design))
                ->values();
        }

        $lockBySetId = $this->batchDesignLockContextsForSetIds(
            $designs->pluck('set_id')->filter()->unique()->values()->all()
        );
        $designLockByDesignId = [];
        foreach ($designs as $d) {
            if ($d->set_id && isset($lockBySetId[$d->set_id])) {
                $designLockByDesignId[$d->id] = $lockBySetId[$d->set_id];
            }
        }
        $printOrderLockByDesignId = [];
        $designIds = $designs->pluck('id')->all();
        if (!empty($designIds)) {
            $latestOrdersByDesign = PrintOrder::query()
                ->whereIn('design_format_id', $designIds)
                ->orderByDesc('id')
                ->get()
                ->groupBy('design_format_id')
                ->map(fn ($rows) => $rows->first());

            foreach ($latestOrdersByDesign as $designId => $order) {
                $ctx = $this->buildPrintOrderLockContext($order);
                if ($ctx['locked'] || $ctx['completed']) {
                    $printOrderLockByDesignId[(int) $designId] = $ctx;
                }
            }
        }

        $approvalContextByDesignId = [];
        $feeService = app(ManagementFeeService::class);
        foreach ($designs as $d) {
            $setLocked = ! empty($designLockByDesignId[$d->id]['locked']);
            $printLocked = ! empty($printOrderLockByDesignId[$d->id]['locked']);
            $awaitingEntityFee = $d->set && $feeService->blocksAdminDesignUntilEntityPays($d->set);
            $printPayment = app(AdministrationBillingService::class)->buildPrintPaymentContext($d, $user);
            $blocksExport = $approvalService->blocksQrExport($d);
            $approvalContextByDesignId[$d->id] = [
                'label' => $approvalService->statusLabel($d->approval_status),
                'status' => $approvalService->normalizedApprovalStatus($d->approval_status),
                'requires_approval' => $approvalService->requiresEntityApproval($d),
                'can_submit' => $approvalService->canSubmitForApproval($user, $d) && ! $awaitingEntityFee,
                'can_edit' => $approvalService->canEntityEditDesign($user, $d),
                'can_open_editor' => ! $awaitingEntityFee
                    && $approvalService->canOpenDesignEditor($user, $d, $setLocked, $printLocked),
                'can_review' => $approvalService->canReviewApproval($user, $d),
                'awaiting_entity_fee' => $awaitingEntityFee,
                'management_fee_pending' => $approvalService->managementFeePendingAfterApproval($d)
                    || $feeService->entityOwesManagementFee($d),
                'entity_fee_due' => $feeService->entityOwesManagementFee($d),
                'acts_as_administration' => $approvalService->userActsAsAdministration($user),
                'blocks_export' => $blocksExport,
                'block_message' => $approvalService->blockMessage($d),
                'can_send_to_print' => ! $awaitingEntityFee
                    && ! ($d->set && $this->designSetIsDigitalOnly($d->set))
                    && $this->printOrderSubmissionBlockMessage($d) === null
                    && ! empty($printPayment['user_may_submit']),
                'send_to_print_block_reason' => $this->printOrderSubmissionBlockMessage($d)
                    ?: ($printPayment['user_submit_block_reason'] ?? null),
                'print_payer_label' => $printPayment['payer_label'] ?? null,
            ];
        }

        $canStartNewDesign = $approvalService->userCanStartNewDesign($user);

        return view('design.index', compact('designs', 'designLockByDesignId', 'printOrderLockByDesignId', 'approvalContextByDesignId', 'canStartNewDesign'));
    }

    /**
     * Eliminar un formato de diseño.
     */
    public function destroy($id)
    {
        try {
            $design = DesignFormat::with(['participations', 'set'])->findOrFail($id);
            
            // Verificar permisos: el usuario debe tener acceso a la entidad del diseño
            if (!auth()->user()->canAccessEntity($design->entity_id)) {
                abort(403, 'No tienes permisos para eliminar este diseño.');
            }
            
            if ($design->set) {
                $lock = $this->getSetDesignLockContext($design->set);
                if ($lock['locked']) {
                    return redirect()->route('design.index')->with('error', $lock['message']);
                }
            }
            $printOrderLock = $this->getPrintOrderLockContext($design->id);
            if ($printOrderLock['locked']) {
                return redirect()->route('design.index')->with('error', $printOrderLock['message']);
            }

            // El modelo DesignFormat tiene un evento boot que elimina automáticamente las participaciones
            // cuando se elimina el diseño, así que solo necesitamos eliminar el diseño
            $design->delete();
            
            return redirect()->route('design.index')
                ->with('success', 'El trabajo de diseño ha sido eliminado correctamente. Las participaciones asociadas también han sido eliminadas.');
                
        } catch (\Exception $e) {
            \Log::error('Error al eliminar diseño: ' . $e->getMessage());
            return redirect()->route('design.index')
                ->with('error', 'Error al eliminar el diseño: ' . $e->getMessage());
        }
    }

    /**
     * Determina si el set permite edición de diseño.
     * Regla operativa: si hay participaciones vendidas, reservadas, pagadas, perdidas
     * o asignadas a vendedor (seller_id), el diseño queda bloqueado.
     */
    private function getSetDesignLockContext(Set $set): array
    {
        $assignedCount = Participation::where('set_id', $set->id)->whereNotNull('seller_id')->count();
        $statusLockedCount = Participation::where('set_id', $set->id)
            ->whereIn('status', ['vendida', 'reservada', 'pagada', 'perdida'])
            ->count();

        return $this->buildDesignLockContext($assignedCount, $statusLockedCount);
    }

    /**
     * @param  array<int>  $setIds
     * @return array<int, array<string, mixed>>
     */
    private function batchDesignLockContextsForSetIds(array $setIds): array
    {
        $setIds = array_values(array_unique(array_filter($setIds)));
        if ($setIds === []) {
            return [];
        }

        $assignedRows = Participation::query()
            ->whereIn('set_id', $setIds)
            ->whereNotNull('seller_id')
            ->groupBy('set_id')
            ->selectRaw('set_id, COUNT(*) as c')
            ->pluck('c', 'set_id');

        $statusRows = Participation::query()
            ->whereIn('set_id', $setIds)
            ->whereIn('status', ['vendida', 'reservada', 'pagada', 'perdida'])
            ->groupBy('set_id')
            ->selectRaw('set_id, COUNT(*) as c')
            ->pluck('c', 'set_id');

        $out = [];
        foreach ($setIds as $sid) {
            $ac = (int) ($assignedRows[$sid] ?? 0);
            $sc = (int) ($statusRows[$sid] ?? 0);
            $out[$sid] = $this->buildDesignLockContext($ac, $sc);
        }

        return $out;
    }

    /**
     * Disponibilidad de participaciones para crear o ampliar diseños por set.
     *
     * @param  \Illuminate\Support\Collection<int, Set>|array<int, Set>  $sets
     * @return array<int, array{total:int, allocated_to_design:int, available_for_new_design:int, has_design:bool}>
     */
    private function batchSetDesignAvailabilityForSetIds(array $setIds, $sets): array
    {
        $setIds = array_values(array_unique(array_filter($setIds)));
        if ($setIds === []) {
            return [];
        }

        $setsById = collect($sets)->keyBy('id');

        $allocatedRows = Participation::query()
            ->whereIn('set_id', $setIds)
            ->whereNotNull('design_format_id')
            ->where('status', '!=', 'anulada')
            ->groupBy('set_id')
            ->selectRaw('set_id, COUNT(*) as c')
            ->pluck('c', 'set_id');

        $designRows = DesignFormat::query()
            ->whereIn('set_id', $setIds)
            ->groupBy('set_id')
            ->selectRaw('set_id, COUNT(*) as c')
            ->pluck('c', 'set_id');

        $out = [];
        foreach ($setIds as $sid) {
            $set = $setsById->get($sid);
            $total = $set ? (int) $set->total_participations : 0;
            $allocated = (int) ($allocatedRows[$sid] ?? 0);
            $out[$sid] = [
                'total' => $total,
                'allocated_to_design' => $allocated,
                'available_for_new_design' => max(0, $total - $allocated),
                'has_design' => (int) ($designRows[$sid] ?? 0) > 0,
            ];
        }

        return $out;
    }

    /**
     * @return array{locked:bool, message:?string, assigned_count:int, status_locked_count:int}
     */
    private function buildDesignLockContext(int $assignedCount, int $statusLockedCount): array
    {
        $locked = ($assignedCount + $statusLockedCount) > 0;

        if (! $locked) {
            return [
                'locked' => false,
                'message' => null,
                'assigned_count' => 0,
                'status_locked_count' => 0,
            ];
        }

        $message = 'Este set tiene participaciones comprometidas por operación (venta/asignación/reserva) y el diseño está bloqueado.';
        if ($assignedCount > 0 && $statusLockedCount > 0) {
            $message = "Diseño bloqueado: hay {$assignedCount} participaciones asignadas y {$statusLockedCount} en estado operativo no editable.";
        } elseif ($assignedCount > 0) {
            $message = "Diseño bloqueado: hay {$assignedCount} participaciones asignadas a vendedor.";
        } elseif ($statusLockedCount > 0) {
            $message = "Diseño bloqueado: hay {$statusLockedCount} participaciones en estado operativo no editable (vendida/reservada/pagada/perdida).";
        }

        return [
            'locked' => true,
            'message' => $message,
            'assigned_count' => $assignedCount,
            'status_locked_count' => $statusLockedCount,
        ];
    }

    private function isPrintOrderEditingBlockedStatus(string $status): bool
    {
        return in_array($status, [
            PrintOrder::STATUS_PENDING_REVIEW,
            PrintOrder::STATUS_IN_PRODUCTION,
        ], true);
    }

    /**
     * @return array{locked:bool, completed:bool, message:?string, status:?string, order_code:?string}
     */
    private function buildPrintOrderLockContext(?PrintOrder $order): array
    {
        if (! $order) {
            return ['locked' => false, 'completed' => false, 'message' => null, 'status' => null, 'order_code' => null];
        }

        $status = (string) $order->status;

        if ($order->isWorkflowComplete()) {
            return [
                'locked' => false,
                'completed' => true,
                'message' => 'La imprenta marcó el pedido '.$order->order_code.' como enviado.',
                'status' => $status,
                'order_code' => (string) $order->order_code,
            ];
        }

        if ($this->isPrintOrderEditingBlockedStatus($status)) {
            return [
                'locked' => true,
                'completed' => false,
                'message' => 'Diseño en imprenta ('.$order->order_code.'): '.PrintOrder::statusLabel($status).'.',
                'status' => $status,
                'order_code' => (string) $order->order_code,
            ];
        }

        return ['locked' => false, 'completed' => false, 'message' => null, 'status' => $status, 'order_code' => (string) $order->order_code];
    }

    private function isPrintOrderBlockingStatus(string $status): bool
    {
        return $this->isPrintOrderEditingBlockedStatus($status);
    }

    private function resolveAuthorizedPrintShopOrder(): ?PrintOrder
    {
        $orderId = session('print_shop_order_id');
        $user = auth()->user();
        if (! $orderId || ! $user || ! $user->canManagePrintShopOrders()) {
            return null;
        }

        $order = PrintOrder::query()->find((int) $orderId);
        if (! $order) {
            return null;
        }

        if ($user->isPrintShop() && ! $user->isSuperAdmin()) {
            $panelShopId = (int) ($user->panel_account_id ?? 0);
            if ($panelShopId > 0 && (int) $order->print_configuration_id !== $panelShopId) {
                return null;
            }
        }

        return $order;
    }

    private function assertPrintShopMayDesignOrder(PrintOrder $printOrder): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canManagePrintShopOrders()) {
            abort(403, 'No tienes acceso al editor de diseño de imprenta.');
        }

        if ($user->isPrintShop() && ! $user->isSuperAdmin()) {
            $panelShopId = (int) ($user->panel_account_id ?? 0);
            if ($panelShopId > 0 && (int) $printOrder->print_configuration_id !== $panelShopId) {
                abort(403, 'Esta orden pertenece a otra imprenta.');
            }
        }

        if (! $printOrder->printShopCanEditDesign()) {
            abort(403, 'Este pedido ya no admite edición de diseño en su estado actual.');
        }

        $printOrder->loadMissing('set');
        if (! $printOrder->isVisibleToPrintShop()) {
            abort(403, 'Este pedido no está disponible hasta que la entidad confirme la cuota de gestión PARTILOT.');
        }
    }

    private function userCanAccessDesignFormat(?User $user, DesignFormat $format): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->canAccessEntity((int) $format->entity_id)) {
            return true;
        }

        if (! $user->canManagePrintShopOrders()) {
            return false;
        }

        $orderId = session('print_shop_order_id');
        if (! $orderId) {
            return false;
        }

        return PrintOrder::query()
            ->where('id', (int) $orderId)
            ->where('design_format_id', $format->id)
            ->exists();
    }

    /**
     * @return array{locked:bool, message:?string, status:?string, order_code:?string}
     */
    private function designSetIsDigitalOnly(?\App\Models\Set $set): bool
    {
        if (! $set) {
            return false;
        }

        return ($set->digital_participations ?? 0) > 0
            && (int) ($set->physical_participations ?? 0) === 0;
    }

    private function getPrintOrderLockContext(?int $designFormatId): array
    {
        if (! $designFormatId) {
            return ['locked' => false, 'completed' => false, 'message' => null, 'status' => null, 'order_code' => null];
        }

        $latest = PrintOrder::query()
            ->where('design_format_id', $designFormatId)
            ->orderByDesc('id')
            ->first();

        if (! $latest) {
            return ['locked' => false, 'completed' => false, 'message' => null, 'status' => null, 'order_code' => null];
        }

        $ctx = $this->buildPrintOrderLockContext($latest);

        $printShopOrderId = session('print_shop_order_id');
        if ($ctx['locked']
            && $printShopOrderId
            && (int) $latest->id === (int) $printShopOrderId
            && auth()->user()?->canManagePrintShopOrders()) {
            return [
                'locked' => false,
                'completed' => false,
                'message' => null,
                'status' => (string) $latest->status,
                'order_code' => (string) $latest->order_code,
            ];
        }

        if ($ctx['locked']) {
            $label = PrintOrder::statusLabel((string) $latest->status);

            return [
                'locked' => true,
                'completed' => false,
                'message' => "Este diseño está en imprenta ({$latest->order_code}, {$label}). No se puede editar hasta que finalice el trabajo.",
                'status' => (string) $latest->status,
                'order_code' => (string) $latest->order_code,
            ];
        }

        return $ctx;
    }

    private function logDesignLockAudit(Set $set, string $action, array $lockContext, ?int $designFormatId = null): void
    {
        try {
            DB::table('design_lock_audits')->insert([
                'set_id' => $set->id,
                'entity_id' => $set->entity_id,
                'design_format_id' => $designFormatId,
                'user_id' => auth()->id(),
                'action' => $action,
                'message' => $lockContext['message'] ?? null,
                'assigned_count' => (int) ($lockContext['assigned_count'] ?? 0),
                'status_locked_count' => (int) ($lockContext['status_locked_count'] ?? 0),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('No se pudo registrar auditoría de bloqueo de diseño: ' . $e->getMessage());
        }
    }

    private function hydrateDesignHtmlFromBlocks(DesignFormat $design): DesignFormat
    {
        $blocks = is_array($design->blocks ?? null) ? $design->blocks : [];

        if (empty(trim((string) ($design->participation_html ?? ''))) && ! empty($blocks['participation_html'])) {
            $design->participation_html = $blocks['participation_html'];
        }
        if (empty(trim((string) ($design->cover_html ?? ''))) && ! empty($blocks['cover_html'])) {
            $design->cover_html = $blocks['cover_html'];
        }
        if (empty(trim((string) ($design->back_html ?? ''))) && ! empty($blocks['back_html'])) {
            $design->back_html = $blocks['back_html'];
        }

        $design->participation_html = $this->ensureAbsoluteUrlsInHtml($design->participation_html ?? '');
        $design->cover_html = $this->ensureAbsoluteUrlsInHtml($design->cover_html ?? '');
        $design->back_html = $this->ensureAbsoluteUrlsInHtml($design->back_html ?? '');

        if (empty($design->backgrounds) && ! empty($blocks['backgrounds']) && is_array($blocks['backgrounds'])) {
            $design->backgrounds = $blocks['backgrounds'];
        }

        return $design;
    }

    /**
     * Placeholder sin HTML: abrir el asistente format con plantilla por defecto (mismo flujo que diseño nuevo).
     */
    private function redirectToFormatWizardForEmptyDesign(DesignFormat $format)
    {
        session([
            'design_entity_id' => $format->entity_id,
            'design_lottery_id' => $format->lottery_id,
            'design_set_id' => $format->set_id,
        ]);

        $request = Request::create(route('design.format'), 'POST', [
            'set_id' => $format->set_id,
            'new_design' => 1,
        ]);
        $request->setLaravelSession(session());

        return $this->format($request);
    }

    private function ensurePlaceholderDesign(Set $set, Entity $entity, int $lotteryId): DesignFormat
    {
        $design = DesignFormat::query()
            ->where('set_id', $set->id)
            ->orderByDesc('id')
            ->first();

        if ($design) {
            app(DesignApprovalService::class)->assignDesignerTypeIfMissing($design, auth()->user());
            app(ManagementFeeService::class)->ensureSnapshot($set, $design);

            return $design->refresh();
        }

        $design = DesignFormat::create([
            'entity_id' => $entity->id,
            'lottery_id' => $lotteryId,
            'set_id' => $set->id,
            'format' => 'A4',
            'designer_type' => DesignApprovalService::DESIGNER_ADMINISTRATION,
            'approval_status' => DesignApprovalService::STATUS_DRAFT,
            'participation_html' => '',
            'cover_html' => '',
            'back_html' => '',
            'backgrounds' => [],
            'output' => [],
            'margins' => [],
        ]);

        app(ManagementFeeService::class)->ensureSnapshot($set, $design);

        return $design;
    }

    private function afterPrintOrderCreated(PrintOrder $order, ?DesignFormat $design = null): bool
    {
        $order->loadMissing(['set.entity', 'design']);
        $design ??= $order->design;
        $set = $order->set;
        if (! $set) {
            return false;
        }

        $feeService = app(ManagementFeeService::class);
        if ($design) {
            $feeService->ensureSnapshot($set, $design);
            $set->refresh();
        }

        if (! $feeService->blocksPrintShopUntilEntityPaysManagementFee($set)) {
            return false;
        }

        $designForNotify = $design ?? DesignFormat::query()
            ->where('set_id', $set->id)
            ->orderByDesc('id')
            ->first();

        if ($designForNotify) {
            $this->notifyEntityManagementFeePaymentRequired($designForNotify);
        }

        $this->insertPrintOrderAuditRow(
            printOrder: $order,
            action: 'held_for_entity_management_fee',
            message: 'Orden retenida: la imprenta no la verá hasta que la entidad pague la cuota de gestión PARTILOT.',
            userId: auth()->id()
        );

        return true;
    }

    private function notifyEntityManagementFeePaymentRequired(DesignFormat $design): void
    {
        $design->loadMissing(['entity', 'set']);
        $entity = $design->entity;
        if (! $entity) {
            return;
        }

        $emailsSent = [];
        $communicationEmailService = app(CommunicationEmailService::class);

        $managers = Manager::query()
            ->where('entity_id', $entity->id)
            ->where('status', 1)
            ->with('user')
            ->get();

        foreach ($managers as $manager) {
            $email = trim((string) ($manager->user?->email ?? ''));
            if ($email === '' || isset($emailsSent[$email])) {
                continue;
            }

            $communicationEmailService->sendAndLog(
                recipientEmail: $email,
                recipientRole: 'entity',
                recipientUser: $manager->user,
                messageType: 'management_fee_payment_request',
                templateKey: 'management_fee_payment_request',
                mailClass: ManagementFeePaymentRequestMail::class,
                mailPayload: ['design_format_id' => $design->id],
                context: ['set_id' => $design->set_id, 'entity_id' => $entity->id],
            );

            $emailsSent[$email] = true;
        }

        $entityEmail = trim((string) ($entity->email ?? ''));
        if ($entityEmail !== '' && ! isset($emailsSent[$entityEmail])) {
            $communicationEmailService->sendAndLog(
                recipientEmail: $entityEmail,
                recipientRole: 'entity',
                recipientUser: null,
                messageType: 'management_fee_payment_request',
                templateKey: 'management_fee_payment_request',
                mailClass: ManagementFeePaymentRequestMail::class,
                mailPayload: ['design_format_id' => $design->id],
                context: ['set_id' => $design->set_id, 'entity_id' => $entity->id],
            );
        }
    }
}