<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesLotteryDrawDateGuard;
use Illuminate\Http\Request;
use App\Models\Entity;
use App\Models\Lottery;
use App\Models\Set;
use App\Models\Participation;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\HtmlText;
use App\Support\ParticipationPdfLayout;
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
use App\Support\SecureImageUpload;
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
                        $design = DesignFormat::create(array_merge(DesignFormat::defaultLayoutAttributes(), [
                            'entity_id' => (int) $invitation->entity_id,
                            'lottery_id' => (int) $invitation->lottery_id,
                            'set_id' => (int) $invitation->set_id,
                            'output' => [
                                'participations_per_book' => (int) ($invitation->participations_per_book ?? 50),
                            ],
                        ]));
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
            'design_upload_url' => route('design.external.uploadImage'),
            'design_snapshot_url' => route('design.external.saveSnapshot'),
            'design_qr_url' => route('design.external.generateQr'),
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
            'design_upload_url' => route('design.uploadImage'),
            'design_snapshot_url' => route('design.saveSnapshot'),
            'design_qr_url' => route('design.generateQr'),
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
            'cut_lines' => 'nullable|numeric',
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
            'cut_lines' => 'nullable|numeric',
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
        if (! empty($data['back_skipped'])) {
            $data['back_html'] = '';
            $data['blocks']['back_html'] = '';
        }
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
            if (app(DesignApprovalService::class)->isLockedAfterParticipationExport($existing)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El diseño quedó bloqueado tras descargar el PDF de participaciones y ya no se puede editar.',
                    'code' => 'DESIGN_EXPORT_LOCKED',
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
        if (array_key_exists('identation', $data)) {
            $existing->identation = $data['identation'];
        }
        if (array_key_exists('cut_lines', $data)) {
            $existing->cut_lines = $data['cut_lines'];
        }
        if (array_key_exists('matrix_box', $data)) {
            $existing->matrix_box = $data['matrix_box'];
        }
        if (array_key_exists('margin_custom', $data)) {
            $existing->margin_custom = $data['margin_custom'];
        }
        if (array_key_exists('horizontal_space', $data)) {
            $existing->horizontal_space = $data['horizontal_space'];
        }
        if (array_key_exists('vertical_space', $data)) {
            $existing->vertical_space = $data['vertical_space'];
        }
        if (isset($data['margins'])) {
            $existing->margins = $data['margins'];
        }
        $existing->blocks = $data['blocks'];
        $existing->participation_html = $data['participation_html'];
        $existing->cover_html = $data['cover_html'];
        $existing->back_skipped = (bool) ($data['back_skipped'] ?? false);
        $existing->back_html = $existing->back_skipped ? '' : ($data['back_html'] ?? '');
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
        app(DesignApprovalService::class)->markParticipationExportLock($design);
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
     * Convierte url(&quot;...&quot;) / url(&apos;...&apos;) a url('...') antes de procesar CSS.
     * El ";" de &quot; rompe regex que usan [^;]+ sobre declaraciones style.
     */
    public function decodeCssUrlHtmlEntities(string $html): string
    {
        if ($html === '' || (stripos($html, 'url(') === false && stripos($html, 'url (') === false)) {
            return $html;
        }

        $fixed = preg_replace_callback(
            '/url\s*\(\s*&quot;(.*?)&quot;\s*\)/is',
            static fn (array $m): string => "url('".str_replace("'", '%27', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))."')",
            $html
        );
        $html = $fixed ?? $html;

        $fixed = preg_replace_callback(
            '/url\s*\(\s*&apos;(.*?)&apos;\s*\)/is',
            static fn (array $m): string => "url('".str_replace("'", '%27', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))."')",
            $html
        );
        $html = $fixed ?? $html;

        $fixed = preg_replace_callback(
            '/url\s*\(\s*&#0*34;(.*?)&#0*34;\s*\)/is',
            static fn (array $m): string => "url('".str_replace("'", '%27', html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'))."')",
            $html
        );

        return $fixed ?? $html;
    }

    /**
     * HTML ligero para coordenadas/tipografía de estampado FPDI
     * (sin scale de fuente ni box-model DomPDF, que desalinea overlays).
     */
    public function prepareStampSlotHtml(string $html, float $identationMm = 2.5): string
    {
        $html = $this->decodeCssUrlHtmlEntities($html);
        $html = $this->flattenParticipationHtmlForPdf($html);
        $html = $this->stripFormatBoxBorderForPdf($html);
        $html = $this->normalizeParticipationElementsForPdf($html);
        $html = $this->enforceQrMinPrintSizeInHtml($html);
        $publicPath = public_path();
        $html = $this->replaceApplicationWebRootsWithPublicPath($html, $publicPath);
        $html = $this->ensureLocalPathsForPdf($html, $publicPath);

        return $html;
    }

    /**
     * HTML de participación listo para DomPDF (misma lógica en web y colas).
     * Mantiene el flujo histórico: base de la app -> ruta de public/ y url(uploads/...) en CSS.
     */
    public function prepareParticipationHtmlForPdf(string $html, float $identationMm = 2.5): string
    {
        $html = $this->decodeCssUrlHtmlEntities($html);
        $html = $this->insetBackgroundWithinMargins(
            $html,
            $identationMm,
            'containment-wrapper2',
            'design-participation-bg'
        );
        $html = $this->flattenParticipationHtmlForPdf($html);
        $html = $this->stripFormatBoxBorderForPdf($html);
        $html = $this->normalizeParticipationElementsForPdf($html);
        $html = $this->applyVerticalTextForDomPdf($html);
        $html = $this->enforceQrMinPrintSizeInHtml($html);
        $html = $this->adjustElementBoxModelForDomPdf($html);
        $html = $this->scaleFontSizesForDomPdf($html);
        $publicPath = public_path();
        $html = $this->replaceApplicationWebRootsWithPublicPath($html, $publicPath);
        $html = $this->ensureLocalPathsForPdf($html, $publicPath);
        $html = $this->ensurePdfBackgroundCoverStyles($html, 'design-participation-bg');
        $html = $this->materializePdfBackgroundCoverBitmap(
            $html,
            'design-participation-bg',
            0,
            (float) config('pdf_optimization.bg_pixel_scale', 1.0)
        );
        // Fondo como <img> una sola vez en la plantilla (misma ruta → DomPDF reutiliza XObject).
        $html = $this->promotePdfBackgroundLayerToImg($html, 'design-participation-bg');
        $html = $this->preserveInlineStyles($html);

        return $html;
    }

    /**
     * Quita nodos del editor que DomPDF no necesita (guías, botones, handles vacíos).
     * Menos nodos en el árbol = parse/layout más rápido (anecdota clásica de wrappers inútiles).
     */
    public function flattenParticipationHtmlForPdf(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        // Controles / guías del editor
        $html = preg_replace('/<button\b[^>]*>.*?<\/button>/is', '', $html) ?? $html;
        $html = preg_replace('/<[^>]*\bclass="[^"]*\bedit-btn\b[^"]*"[^>]*>.*?<\/[^>]+>/is', '', $html) ?? $html;
        $html = preg_replace(
            '/<div[^>]*\bclass="[^"]*\b(margen-izquierdo|margen-arriba|margen-derecho|margen-abajo|caja-matriz|caja-matriz-2|guide2|guide3|guide4)\b[^"]*"[^>]*>.*?<\/div>/is',
            '',
            $html
        ) ?? $html;

        // Handles jQuery UI vacíos fuera del QR (el QR se rellena luego)
        $html = preg_replace(
            '/(<div[^>]*\bclass="[^"]*\bqr\b[^"]*"[^>]*>)\s*<span[^>]*class="[^"]*ui-draggable-handle[^"]*"[^>]*>\s*<\/span>\s*(<\/div>)/is',
            '$1$2',
            $html
        ) ?? $html;
        $html = preg_replace('/<span[^>]*class="[^"]*ui-draggable-handle[^"]*"[^>]*>\s*<\/span>/is', '', $html) ?? $html;

        // Comentarios HTML
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;

        return $html;
    }

    /**
     * Elimina bordes del contenedor de participación (no deben imprimirse).
     */
    public function stripFormatBoxBorderForPdf(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $html = preg_replace_callback(
            '/(<div[^>]*\bclass="[^"]*\bformat-box\b[^"]*"[^>]*\bstyle=(["\']))(.*?)\2/is',
            static function (array $m): string {
                $style = preg_replace('/\bborder(?:-\w+)?\s*:[^;]+;?/i', '', $m[3]) ?? $m[3];
                $style = trim($style, '; ').'; border:none !important; outline:none !important;';

                return $m[1].$style.$m[2];
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/(<div[^>]*\bstyle=(["\']))(.*?)\2([^>]*\bclass="[^"]*\bformat-box\b[^"]*"[^>]*>)/is',
            static function (array $m): string {
                $style = preg_replace('/\bborder(?:-\w+)?\s*:[^;]+;?/i', '', $m[3]) ?? $m[3];
                $style = trim($style, '; ').'; border:none !important; outline:none !important;';

                return $m[1].$style.$m[2].$m[4];
            },
            $html
        ) ?? $html;
    }

    /**
     * Escala el HTML del diseño al tamaño de corte calculado para llenar la hoja.
     */
    public function scaleParticipationHtmlDimensionsForPdf(
        string $html,
        ParticipationPdfLayout $layout
    ): string {
        $scaleX = $layout->trimScaleX();
        $scaleY = $layout->trimScaleY();

        if (abs($scaleX - 1.0) < 0.0005 && abs($scaleY - 1.0) < 0.0005) {
            return $html;
        }

        $targetW = $layout->trimWidthMm;
        $targetH = $layout->trimHeightMm;

        $html = preg_replace_callback(
            '/(<div[^>]*\bclass="[^"]*\bformat-box\b[^"]*"[^>]*\bstyle=(["\']))(.*?)\2/is',
            static function (array $m) use ($targetW, $targetH): string {
                $style = $m[3];
                $style = preg_replace('/\bwidth\s*:\s*[\d.]+mm/i', 'width:'.$targetW.'mm', $style) ?? $style;
                $style = preg_replace('/\bheight\s*:\s*[\d.]+mm/i', 'height:'.$targetH.'mm', $style) ?? $style;

                return $m[1].$style.$m[2];
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/(<div[^>]*\bstyle=(["\']))(.*?)\2([^>]*\bclass="[^"]*\bformat-box\b[^"]*"[^>]*>)/is',
            static function (array $m) use ($targetW, $targetH): string {
                $style = $m[3];
                $style = preg_replace('/\bwidth\s*:\s*[\d.]+mm/i', 'width:'.$targetW.'mm', $style) ?? $style;
                $style = preg_replace('/\bheight\s*:\s*[\d.]+mm/i', 'height:'.$targetH.'mm', $style) ?? $style;

                return $m[1].$style.$m[2].$m[4];
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/(<div[^>]*\bclass="[^"]*\belements\b[^"]*"[^>]*\bstyle=(["\']))(.*?)\2/is',
            function (array $m) use ($scaleX, $scaleY): string {
                return $m[1].$this->scaleCssPxBoxModel($m[3], $scaleX, $scaleY).$m[2];
            },
            $html
        ) ?? $html;
    }

    private function scaleCssPxBoxModel(string $style, float $scaleX, float $scaleY): string
    {
        $style = preg_replace_callback(
            '/\b(left|width|padding-left|padding-right|margin-left|margin-right|right)\s*:\s*([\d.]+)\s*px/i',
            static fn (array $m): string => $m[1].':'.round((float) $m[2] * $scaleX, 2).'px',
            $style
        ) ?? $style;

        $style = preg_replace_callback(
            '/\b(top|height|padding-top|padding-bottom|margin-top|margin-bottom|bottom)\s*:\s*([\d.]+)\s*px/i',
            static fn (array $m): string => $m[1].':'.round((float) $m[2] * $scaleY, 2).'px',
            $style
        ) ?? $style;

        $fontScale = ($scaleX + $scaleY) / 2.0;

        return preg_replace_callback(
            '/\bfont-size\s*:\s*([\d.]+)\s*px/i',
            static fn (array $m): string => 'font-size:'.round((float) $m[1] * $fontScale, 2).'px',
            $style
        ) ?? $style;
    }

    /**
     * Convierte background-image de la capa de márgenes en <img> (mejor fidelidad DomPDF).
     * Debe llamarse UNA vez sobre la plantilla, no por cada ticket.
     */
    public function promotePdfBackgroundLayerToImg(string $html, string $bgId = 'design-participation-bg'): string
    {
        if ($html === '' || stripos($html, $bgId) === false) {
            return $html;
        }

        return preg_replace_callback(
            '/(<div[^>]*\bid=(["\'])'.preg_quote($bgId, '/').'\2[^>]*>)(.*?)(<\/div>)/is',
            static function (array $m): string {
                $open = $m[1];
                $inner = $m[3];
                $close = $m[4];

                if (stripos($inner, 'design-pdf-bg-img') !== false) {
                    return $m[0];
                }

                if (! preg_match('/\bstyle=(["\'])(.*?)\1/is', $open, $sm)) {
                    return $m[0];
                }
                $style = $sm[2];
                if (! preg_match('/\bbackground-image\s*:\s*url\s*\(\s*[\'"]?([^\'")\s]+)[\'"]?\s*\)/i', $style, $um)) {
                    return $m[0];
                }
                $src = trim(html_entity_decode($um[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r'\"");
                if ($src === '' || strcasecmp($src, 'none') === 0) {
                    return $m[0];
                }
                if (! preg_match('#^(https?:|data:)#i', $src) && ! is_file($src)) {
                    return $m[0];
                }

                $newStyle = preg_replace('/\bbackground-image\s*:[^;]+;?/i', '', $style) ?? $style;
                $newStyle = preg_replace('/\bbackground-size\s*:[^;]+;?/i', '', $newStyle) ?? $newStyle;
                $newStyle = preg_replace('/\bbackground-position\s*:[^;]+;?/i', '', $newStyle) ?? $newStyle;
                $newStyle = preg_replace('/\bbackground-repeat\s*:[^;]+;?/i', '', $newStyle) ?? $newStyle;
                $newStyle = trim(preg_replace('/;+/', ';', $newStyle) ?? $newStyle, "; \t\n\r");
                if ($newStyle !== '' && substr($newStyle, -1) !== ';') {
                    $newStyle .= ';';
                }
                if (! preg_match('/\boverflow\s*:/i', $newStyle)) {
                    $newStyle .= 'overflow:hidden;';
                }

                $open = preg_replace(
                    '/\bstyle=(["\'])(.*?)\1/is',
                    'style=$1'.$newStyle.'$1',
                    $open,
                    1
                ) ?? $open;

                $img = '<img class="design-pdf-bg-img" src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8')
                    .'" alt="" style="position:absolute;left:0;top:0;width:100%;height:100%;border:0;margin:0;padding:0;display:block;" />';

                return $open.$img.$inner.$close;
            },
            $html,
            1
        ) ?? $html;
    }

    /**
     * Fuerza background-size/position como en el editor (cover + center) en la capa de márgenes.
     */
    public function ensurePdfBackgroundCoverStyles(string $html, string $bgId): string
    {
        if ($html === '' || stripos($html, $bgId) === false) {
            return $html;
        }

        return preg_replace_callback(
            '/(<div[^>]*\bid=(["\'])'.preg_quote($bgId, '/').'\2[^>]*?\bstyle=(["\']))(.*?)(\3)/is',
            static function (array $m): string {
                $style = $m[4];
                if (! preg_match('/\bbackground-image\s*:\s*url\s*\(/i', $style)) {
                    return $m[0];
                }
                $style = preg_replace('/\bbackground-size\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\bbackground-position\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\bbackground-repeat\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = trim(preg_replace('/;+/', ';', $style) ?? $style, "; \t\n\r");
                if ($style !== '' && substr($style, -1) !== ';') {
                    $style .= ';';
                }
                $style .= 'background-size:cover;background-position:center center;background-repeat:no-repeat;';

                return $m[1].$style.$m[3];
            },
            $html,
            1
        ) ?? $html;
    }

    /**
     * Genera un PNG cover-crop al tamaño de la capa (2× CSS @96dpi) y lo usa como fondo
     * con background-size:100% 100%. Misma geometría que el canvas a sangre completa.
     */
    public function materializePdfBackgroundCoverBitmap(
        string $html,
        string $bgId,
        float $identationMm = 0,
        float $pixelScale = 1.0,
        ?float $innerWidthMm = null,
        ?float $innerHeightMm = null
    ): string {
        if ($html === '' || stripos($html, $bgId) === false || ! function_exists('imagecreatetruecolor')) {
            return $html;
        }

        [$boxWmm, $boxHmm] = $this->resolveFormatBoxSizeMmFromHtml($html);
        // Bitmap = tamaño real de la capa de fondo (canvas completo, o canvas−matriz en trasera).
        unset($identationMm);
        $innerWmm = $innerWidthMm ?? max(1.0, $boxWmm);
        $innerHmm = $innerHeightMm ?? max(1.0, $boxHmm);
        $scale = max(1.0, min(3.0, $pixelScale));
        $targetW = max(32, (int) round(($innerWmm / 25.4) * 96 * $scale));
        $targetH = max(32, (int) round(($innerHmm / 25.4) * 96 * $scale));
        $jpegQuality = max(50, min(95, (int) config('pdf_optimization.bg_jpeg_quality', 82)));

        return preg_replace_callback(
            '/(<div[^>]*\bid=(["\'])'.preg_quote($bgId, '/').'\2[^>]*?\bstyle=(["\']))(.*?)(\3)/is',
            function (array $m) use ($targetW, $targetH, $jpegQuality): string {
                $style = $m[4];
                if (! preg_match('/\bbackground-image\s*:\s*url\s*\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $style, $um)) {
                    return $m[0];
                }
                $src = trim(html_entity_decode($um[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r'\"");
                if ($src === '' || ! is_file($src)) {
                    return $m[0];
                }

                $outPath = $this->renderCoverCropBitmap($src, $targetW, $targetH, $jpegQuality);
                if ($outPath === null) {
                    return $m[0];
                }

                $style = preg_replace('/\bbackground-image\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\bbackground-size\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\bbackground-position\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\bbackground-repeat\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = trim(preg_replace('/;+/', ';', $style) ?? $style, "; \t\n\r");
                if ($style !== '' && substr($style, -1) !== ';') {
                    $style .= ';';
                }
                $style .= "background-image:url('".$outPath."');background-size:100% 100%;background-position:center center;background-repeat:no-repeat;";

                return $m[1].$style.$m[3];
            },
            $html,
            1
        ) ?? $html;
    }

    /**
     * @return array{0: float, 1: float} widthMm, heightMm
     */
    private function resolveFormatBoxSizeMmFromHtml(string $html): array
    {
        $w = 200.0;
        $h = 92.0;
        if (preg_match('/class=(["\'])[^"\']*format-box[^"\']*\1[^>]*\bstyle=(["\'])(.*?)\2/is', $html, $m)
            || preg_match('/\bstyle=(["\'])(.*?)\1[^>]*class=(["\'])[^"\']*format-box/is', $html, $mAlt)) {
            $style = $m[3] ?? ($mAlt[2] ?? '');
            if (preg_match('/\bwidth\s*:\s*([\d.]+)\s*mm/i', $style, $wm)) {
                $w = (float) $wm[1];
            }
            if (preg_match('/\bheight\s*:\s*([\d.]+)\s*mm/i', $style, $hm)) {
                $h = (float) $hm[1];
            }
        }

        return [max(10.0, $w), max(10.0, $h)];
    }

    private function renderCoverCropBitmap(string $sourcePath, int $targetW, int $targetH, int $jpegQuality = 82): ?string
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            return null;
        }
        $srcW = (int) $info[0];
        $srcH = (int) $info[1];
        if ($srcW < 1 || $srcH < 1) {
            return null;
        }

        $mime = $info['mime'] ?? '';
        $src = match ($mime) {
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/gif' => @imagecreatefromgif($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };
        if ($src === false) {
            return null;
        }

        $srcRatio = $srcW / $srcH;
        $dstRatio = $targetW / $targetH;
        if ($srcRatio > $dstRatio) {
            $cropH = $srcH;
            $cropW = (int) round($srcH * $dstRatio);
            $srcX = (int) max(0, round(($srcW - $cropW) / 2));
            $srcY = 0;
        } else {
            $cropW = $srcW;
            $cropH = (int) round($srcW / $dstRatio);
            $srcX = 0;
            $srcY = (int) max(0, round(($srcH - $cropH) / 2));
        }
        $cropW = max(1, min($srcW, $cropW));
        $cropH = max(1, min($srcH, $cropH));

        $dst = imagecreatetruecolor($targetW, $targetH);
        if ($dst === false) {
            imagedestroy($src);

            return null;
        }
        // Opaco (sin alfa): DomPDF con PNG+alpha genera “puntitos”/dither en fotos.
        imagealphablending($dst, true);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $white);

        imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, $targetW, $targetH, $cropW, $cropH);
        imagedestroy($src);

        $dir = storage_path('app/pdf_bg_cache');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $jpegQuality = max(50, min(95, $jpegQuality));
        $out = $dir.'/'.md5($sourcePath.'|'.$targetW.'x'.$targetH.'|cover-jpg'.$jpegQuality).'.jpg';
        $ok = imagejpeg($dst, $out, $jpegQuality);
        imagedestroy($dst);

        return $ok ? str_replace('\\', '/', $out) : null;
    }

    /**
     * El editor guarda width/height con box-sizing:border-box (incluyen padding).
     * DomPDF suele tratar absolute como content-box → las cajas crecen 2×padding.
     * Compensación: width/height -= 2×padding, mantener padding y left/top, forzar content-box.
     * Así el borde exterior coincide con el diseño.
     */
    public function adjustElementBoxModelForDomPdf(string $html): string
    {
        if ($html === '' || stripos($html, 'elements') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/(<div[^>]*\bclass="[^"]*\belements\b[^"]*"[^>]*\bstyle=")([^"]*)(")/i',
            function (array $m): string {
                $style = $m[2];

                if (! preg_match('/\bpadding\s*:\s*([\d.]+)\s*px\b/i', $style, $pm)) {
                    // Sin padding: DomPDF sigue pintando el texto alto; bajar un poco el contenido.
                    $isTextish = (bool) preg_match(
                        '/\b(text|number|reference|participation)\b/i',
                        $m[1]
                    );
                    if ($isTextish && preg_match('/\bheight\s*:\s*([\d.]+)\s*px\b/i', $style, $hm)) {
                        $h = (float) $hm[1];
                        $nudgePx = 3.0;
                        if ($h > ($nudgePx + 4)) {
                            $style = preg_replace(
                                '/\bheight\s*:[^;]+;?/i',
                                'height:'.$this->formatPdfCssPx($h - $nudgePx).';',
                                $style
                            ) ?? $style;
                            $style = 'padding-top:'.$this->formatPdfCssPx($nudgePx).';'.$style;
                        }
                    }
                    if (! preg_match('/\bbox-sizing\s*:/i', $style)) {
                        $style = 'box-sizing:content-box !important;'.$style;
                    }

                    return $m[1].$style.$m[3];
                }

                $pad = (float) $pm[1];
                if ($pad <= 0 || ! preg_match('/\bpadding\s*:\s*[\d.]+\s*px\s*;?/i', $style)) {
                    return $m[0];
                }

                if (
                    ! preg_match('/\bwidth\s*:\s*([\d.]+)\s*px\b/i', $style, $wm)
                    || ! preg_match('/\bheight\s*:\s*([\d.]+)\s*px\b/i', $style, $hm)
                ) {
                    return $m[0];
                }

                $outerW = (float) $wm[1];
                $outerH = (float) $hm[1];
                $innerW = $outerW - (2 * $pad);
                $innerH = $outerH - (2 * $pad);
                if ($innerW < 1 || $innerH < 1) {
                    return $m[0];
                }

                $style = preg_replace('/\bwidth\s*:[^;]+;?/i', 'width:'.$this->formatPdfCssPx($innerW).';', $style) ?? $style;
                $style = preg_replace('/\bheight\s*:[^;]+;?/i', 'height:'.$this->formatPdfCssPx($innerH).';', $style) ?? $style;
                // DomPDF alinea el texto más arriba que el navegador (ascent DejaVu).
                // Más padding-top / menos bottom (misma suma) baja el bloque visualmente.
                $nudge = max(2, (int) round($pad * 0.65));
                if ($nudge >= $pad) {
                    $nudge = max(0, (int) floor($pad) - 1);
                }
                $padTop = $pad + $nudge;
                $padBottom = max(0, $pad - $nudge);
                $style = preg_replace(
                    '/\bpadding\s*:[^;]+;?/i',
                    'padding:'.$this->formatPdfCssPx($padTop).' '.$this->formatPdfCssPx($pad).' '.$this->formatPdfCssPx($padBottom).' '.$this->formatPdfCssPx($pad).';',
                    $style
                ) ?? $style;
                $style = preg_replace('/\bbox-sizing\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = 'box-sizing:content-box !important;'.$style;
                if (! preg_match('/\bmax-width\s*:/i', $style)) {
                    $style .= 'max-width:'.$this->formatPdfCssPx($innerW).';';
                }
                if (! preg_match('/\bmax-height\s*:/i', $style)) {
                    $style .= 'max-height:'.$this->formatPdfCssPx($innerH).';';
                }

                return $m[1].$style.$m[3];
            },
            $html
        ) ?? $html;
    }

    private function formatPdfCssPx(float $value): string
    {
        $rounded = round($value, 3);
        if (abs($rounded - round($rounded)) < 0.0001) {
            return ((int) round($rounded)).'px';
        }

        return rtrim(rtrim(number_format($rounded, 3, '.', ''), '0'), '.').'px';
    }

    /**
     * @deprecated Use adjustElementBoxModelForDomPdf
     */
    public function flattenElementPaddingForDomPdf(string $html): string
    {
        return $this->adjustElementBoxModelForDomPdf($html);
    }

    /**
     * DomPDF (DejaVu) pinta el mismo font-size px más grande que el navegador.
     * Escala tipografía para acercar el relleno de las cajas al editor.
     */
    public function scaleFontSizesForDomPdf(string $html, float $factor = 0.85): string
    {
        if ($html === '' || $factor <= 0 || abs($factor - 1.0) < 0.001) {
            return $html;
        }

        return preg_replace_callback(
            '/font-size\s*:\s*([\d.]+)\s*(px|pt|em|rem)/i',
            function (array $m) use ($factor): string {
                $scaled = round(((float) $m[1]) * $factor, 2);

                return 'font-size:'.$scaled.$m[2];
            },
            $html
        ) ?? $html;
    }

    /**
     * Limpia HTML del editor para DomPDF sin alterar coordenadas ni tamaños guardados.
     * Fuerza position:absolute (en el editor algunos quedan relative y en PDF se apilan).
     */
    public function normalizeParticipationElementsForPdf(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $html = preg_replace('/<button\b[^>]*>.*?<\/button>/is', '', $html) ?? $html;

        $html = preg_replace_callback(
            '/(id=(["\'])containment-wrapper\d+\2[^>]*\bstyle=(["\']))(.*?)(\3)/is',
            function (array $m): string {
                // Solo sustituir calc(); no forzar width/height 100% (rompe la rejilla DomPDF)
                $style = preg_replace('/\bheight\s*:\s*calc\([^)]+\)\s*;?/i', 'height:100%;', $m[4]) ?? $m[4];

                return $m[1].$style.$m[5];
            },
            $html
        ) ?? $html;

        if (stripos($html, 'elements') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/(<div[^>]*\bclass="[^"]*\belements\b[^"]*"[^>]*\bstyle=")([^"]*)(")/i',
            function (array $m): string {
                $style = $m[2];
                if (preg_match('/\bposition\s*:\s*relative\b/i', $style)) {
                    $style = preg_replace('/\bposition\s*:\s*relative\b/i', 'position:absolute', $style) ?? $style;
                } elseif (! preg_match('/\bposition\s*:/i', $style)) {
                    $style = 'position:absolute;'.$style;
                }
                if (
                    preg_match('/\btop\s*:/i', $style)
                    && preg_match('/\bleft\s*:/i', $style)
                ) {
                    $style = preg_replace('/\b(bottom|right|inset)\s*:[^;]+;?/i', '', $style) ?? $style;
                }

                return $m[1].$style.$m[3];
            },
            $html
        ) ?? $html;
    }

    /**
     * DomPDF no aplica writing-mode ni transform:rotate.
     * - Texto normal: PNG rotado -90° (letras de lado) vía GD.
     * - reference/participation/number: FPDI estampa imagen rotada si data-text-vertical.
     */
    public function applyVerticalTextForDomPdf(string $html): string
    {
        if ($html === '') {
            return $html;
        }
        if (stripos($html, 'text-vertical') === false && stripos($html, 'data-text-vertical') === false) {
            return $html;
        }

        return preg_replace_callback(
            '/<div\b([^>]*?(?:class\s*=\s*["\'][^"\']*\btext-vertical\b[^"\']*["\']|data-text-vertical\s*=\s*["\']1["\'])[^>]*)>(.*?)<\/div>/is',
            function (array $m): string {
                $attrs = $m[1];
                $inner = $m[2];
                $isCritical = (bool) preg_match(
                    '/\bclass\s*=\s*["\'][^"\']*\b(reference|participation|number)\b[^"\']*["\']/i',
                    $attrs
                );

                $style = '';
                if (preg_match('/\bstyle\s*=\s*("|\')(.*?)\1/is', $attrs, $sm)) {
                    $style = $sm[2];
                }

                $style = preg_replace('/\bwriting-mode\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\btext-orientation\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\btransform(?:-origin)?\s*:[^;]+;?/i', '', $style) ?? $style;
                if (preg_match('/\boverflow\s*:/i', $style)) {
                    $style = preg_replace('/\boverflow\s*:[^;]+;?/i', 'overflow:visible;', $style) ?? $style;
                } else {
                    $style .= 'overflow:visible;';
                }
                $style = trim($style);
                if ($style === '') {
                    $style = 'overflow:visible;';
                }

                if (! preg_match('/\bdata-text-vertical\s*=/i', $attrs)) {
                    $attrs .= ' data-text-vertical="1"';
                }

                if (! $isCritical) {
                    $plain = $this->extractPlainTextFromHtmlFragment($inner);
                    $boxW = null;
                    $boxH = null;
                    if (preg_match('/\bwidth\s*:\s*([\d.]+)\s*px/i', $style, $wm)) {
                        $boxW = (float) $wm[1];
                    }
                    if (preg_match('/\bheight\s*:\s*([\d.]+)\s*px/i', $style, $hm)) {
                        $boxH = (float) $hm[1];
                    }
                    // Restar padding aproximado del área útil de la imagen.
                    $padPx = 0.0;
                    if (preg_match('/\bpadding\s*:\s*([\d.]+)\s*px/i', $style, $pm)) {
                        $padPx = (float) $pm[1];
                    }
                    if ($boxW !== null) {
                        $boxW = max(8.0, $boxW - (2 * $padPx));
                    }
                    if ($boxH !== null) {
                        $boxH = max(8.0, $boxH - (2 * $padPx));
                    }

                    $file = $this->renderRotatedTextPngTempFile(
                        $plain,
                        $this->parseFontSizePxFromCssHint($style.' '.$inner),
                        $this->parseRgbFromCssHint($style.' '.$inner),
                        (bool) preg_match('/<(strong|b)\b|\bfont-weight\s*:\s*(bold|[6-9]00)\b/i', $style.' '.$inner),
                        $boxW,
                        $boxH
                    );
                    if ($file !== null) {
                        $wAttr = $boxW !== null ? round($boxW).'px' : '100%';
                        $hAttr = $boxH !== null ? round($boxH).'px' : '100%';
                        // Ruta absoluta (chroot incluye storage/); evitar data-URI (DomPDF a menudo no la pinta).
                        $src = str_replace('\\', '/', $file);
                        $inner = '<img src="'.$src.'" width="'.(int) round($boxW ?? 40).'" height="'.(int) round($boxH ?? 120).'" alt="" style="width:'.$wAttr.';height:'.$hAttr.';display:block;border:0;max-width:100%;max-height:100%;">';
                    }
                }

                if (preg_match('/\bstyle\s*=\s*("|\')(.*?)\1/is', $attrs)) {
                    $attrs = preg_replace(
                        '/\bstyle\s*=\s*("|\')(.*?)\1/is',
                        'style="'.$style.'"',
                        $attrs,
                        1
                    ) ?? $attrs;
                } else {
                    $attrs .= ' style="'.$style.'"';
                }

                return '<div'.$attrs.'>'.$inner.'</div>';
            },
            $html
        ) ?? $html;
    }

    protected function extractPlainTextFromHtmlFragment(string $htmlFragment): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $htmlFragment) ?? $htmlFragment;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * PNG con texto rotado -90° (CSS) / +90° GD, data-URI para DomPDF.
     */
    public function renderVerticalTextPngDataUri(string $text, string $cssHint): ?string
    {
        $png = $this->renderRotatedTextPngBinary(
            $text,
            $this->parseFontSizePxFromCssHint($cssHint),
            $this->parseRgbFromCssHint($cssHint),
            (bool) preg_match('/\bfont-weight\s*:\s*(bold|[6-9]00)\b/i', $cssHint),
            null,
            null,
            $cssHint
        );
        if ($png === null) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /**
     * Escribe PNG rotado en archivo temporal (para DomPDF / FPDI::Image). Caller puede borrar.
     *
     * @param  array{0?:int,1?:int,2?:int}|null  $rgb
     */
    public function renderRotatedTextPngTempFile(
        string $text,
        float $fontSizePx,
        ?array $rgb = null,
        bool $bold = false,
        ?float $targetWidthPx = null,
        ?float $targetHeightPx = null,
        string $fontHint = 'font-family:DejaVu Sans,sans-serif;'
    ): ?string {
        $rgb = $rgb ?? [0, 0, 0];
        $png = $this->renderRotatedTextPngBinary(
            $text,
            $fontSizePx,
            $rgb,
            $bold,
            $targetWidthPx,
            $targetHeightPx,
            $fontHint.($bold ? 'font-weight:bold;' : '')
        );
        if ($png === null) {
            return null;
        }
        $dir = storage_path('app/pdf_vertical_text');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.'rot_'.uniqid('', true).'.png';
        if (file_put_contents($path, $png) === false) {
            return null;
        }

        return $path;
    }

    public function parseFontSizePxFromCssHint(string $cssHint): float
    {
        if (preg_match('/\bfont-size\s*:\s*([\d.]+)\s*px/i', $cssHint, $m)) {
            return max(8.0, (float) $m[1]);
        }
        if (preg_match('/\bfont-size\s*:\s*([\d.]+)\s*pt/i', $cssHint, $m)) {
            return max(8.0, (float) $m[1] * 96.0 / 72.0);
        }

        return 16.0;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    public function parseRgbFromCssHint(string $cssHint): array
    {
        if (preg_match('/(?<![-\w])color\s*:\s*#([0-9a-f]{3}|[0-9a-f]{6})\b/i', $cssHint, $m)) {
            $hex = strtolower($m[1]);
            if (strlen($hex) === 3) {
                $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
            }

            return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }
        if (preg_match('/(?<![-\w])color\s*:\s*rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i', $cssHint, $m)) {
            return [
                max(0, min(255, (int) round((float) $m[1]))),
                max(0, min(255, (int) round((float) $m[2]))),
                max(0, min(255, (int) round((float) $m[3]))),
            ];
        }
        if (preg_match('/(?<![-\w])color\s*:\s*hsl\(\s*([\d.]+)\s*,\s*([\d.]+)%\s*,\s*([\d.]+)%/i', $cssHint, $m)) {
            return $this->hslToRgb((float) $m[1], (float) $m[2] / 100, (float) $m[3] / 100);
        }

        return [0, 0, 0];
    }

    /**
     * Texto a 90° (antihorario = CSS rotate(-90deg)) sin imagerotate (rompe alpha).
     *
     * @param  array{0?:int,1?:int,2?:int}  $rgb
     */
    protected function renderRotatedTextPngBinary(
        string $text,
        float $fontSizePx,
        array $rgb,
        bool $bold = false,
        ?float $targetWidthPx = null,
        ?float $targetHeightPx = null,
        string $cssHint = ''
    ): ?string {
        if ($text === '' || ! function_exists('imagettftext')) {
            return null;
        }

        $fontFile = $this->resolveTtfForVerticalText(
            $cssHint.($bold ? 'font-weight:bold;' : '').'font-family:DejaVu Sans,sans-serif;'
        );
        if ($fontFile === null) {
            return null;
        }

        $fontSize = (int) max(8, round($fontSizePx));
        $pad = (int) max(4, round($fontSize * 0.25));
        $angle = 90; // CCW → letras de lado, lectura de abajo hacia arriba

        $lines = preg_split("/\n/u", $text) ?: [$text];
        $lineGap = (int) max($fontSize + 4, round($fontSize * 1.3));

        // Medir bounding box de cada línea a 90°.
        $boxes = [];
        $maxW = 0;
        $totalH = 0;
        foreach ($lines as $i => $line) {
            $measure = $line === '' ? ' ' : $line;
            $bbox = @imagettfbbox($fontSize, $angle, $fontFile, $measure);
            if (! is_array($bbox)) {
                return null;
            }
            $minX = min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $maxX = max($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
            $minY = min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $maxY = max($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
            $bw = (int) ceil($maxX - $minX);
            $bh = (int) ceil($maxY - $minY);
            $boxes[] = compact('minX', 'maxX', 'minY', 'maxY', 'bw', 'bh', 'line');
            $maxW = max($maxW, $bw);
            $totalH = max($totalH, $bh);
        }

        // Varias líneas: desplazar en X (tras rotar 90°, el “salto de línea” es horizontal).
        $width = max(1, $maxW + ((count($lines) - 1) * $lineGap) + (2 * $pad));
        $height = max(1, $totalH + (2 * $pad));

        if ($targetWidthPx !== null && $targetWidthPx > 0) {
            $width = max($width, (int) ceil($targetWidthPx));
        }
        if ($targetHeightPx !== null && $targetHeightPx > 0) {
            $height = max($height, (int) ceil($targetHeightPx));
        }

        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            return null;
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        imagefilledrectangle($img, 0, 0, $width, $height, $transparent);
        imagealphablending($img, true);

        $col = imagecolorallocate(
            $img,
            max(0, min(255, (int) ($rgb[0] ?? 0))),
            max(0, min(255, (int) ($rgb[1] ?? 0))),
            max(0, min(255, (int) ($rgb[2] ?? 0)))
        );
        if ($col === false) {
            imagedestroy($img);

            return null;
        }

        foreach ($boxes as $i => $box) {
            $line = $box['line'] === '' ? ' ' : $box['line'];
            // Origen: padding compensando el bbox negativo de TTF a 90°.
            $x = $pad - (int) $box['minX'] + ($i * $lineGap);
            $y = $pad - (int) $box['minY'];
            // Centrar en el alto del canvas si la caja es más alta.
            if ($height > ($box['bh'] + 2 * $pad)) {
                $y += (int) round(($height - $box['bh'] - 2 * $pad) / 2);
            }
            @imagettftext($img, $fontSize, $angle, $x, $y, $col, $fontFile, $line);
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);
        if ($png === false || $png === '') {
            return null;
        }

        return $png;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    protected function hslToRgb(float $h, float $s, float $l): array
    {
        $h = fmod(($h % 360) + 360, 360) / 360;
        $r = $l;
        $g = $l;
        $b = $l;
        if ($s > 0) {
            $q = $l < 0.5 ? $l * (1 + $s) : ($l + $s - $l * $s);
            $p = 2 * $l - $q;
            $r = $this->hueToRgbChannel($p, $q, $h + 1 / 3);
            $g = $this->hueToRgbChannel($p, $q, $h);
            $b = $this->hueToRgbChannel($p, $q, $h - 1 / 3);
        }

        return [
            (int) round($r * 255),
            (int) round($g * 255),
            (int) round($b * 255),
        ];
    }

    protected function hueToRgbChannel(float $p, float $q, float $t): float
    {
        if ($t < 0) {
            $t += 1;
        }
        if ($t > 1) {
            $t -= 1;
        }
        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }
        if ($t < 1 / 2) {
            return $q;
        }
        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    protected function resolveTtfForVerticalText(string $cssHint): ?string
    {
        $candidates = [];
        $wantBold = (bool) preg_match('/\bfont-weight\s*:\s*(bold|[6-9]00)\b/i', $cssHint);
        if (preg_match('/\bfont-family\s*:\s*([^;]+)/i', $cssHint, $m)) {
            $families = preg_split('/\s*,\s*/', strtolower($m[1])) ?: [];
            foreach ($families as $fam) {
                $fam = trim($fam, " \t\"'");
                if ($fam === '' || $fam === 'sans-serif' || $fam === 'serif' || $fam === 'monospace') {
                    continue;
                }
                if (str_contains($fam, 'asgonlae')) {
                    $candidates[] = public_path('Asgonlae.ttf');
                }
                if (str_contains($fam, 'dejavu')) {
                    $candidates[] = $wantBold
                        ? base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf')
                        : base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
                }
            }
        }
        if ($wantBold) {
            $candidates[] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
        }
        $candidates[] = public_path('Asgonlae.ttf');
        $candidates[] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
        $candidates[] = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @deprecated Ya no se usa (se rota el bloque entero). Se mantiene por compatibilidad.
     */
    protected function stackPlainTextVerticallyForDomPdf(string $htmlFragment): string
    {
        $text = $this->extractPlainTextFromHtmlFragment($htmlFragment);
        if ($text === '') {
            return '';
        }

        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Garantiza cajas .qr ≥ min_print_size_mm (por defecto 9 mm / 0,9×0,9 cm).
     * Centra el recuadro al ampliar para no desplazar el diseño.
     */
    public function enforceQrMinPrintSizeInHtml(string $html, ?float $minMm = null): string
    {
        if ($html === '' || stripos($html, 'qr') === false) {
            return $html;
        }

        $minMm = $minMm ?? (float) config('qr_optimization.qr_code.min_print_size_mm', 9);
        $minMm = max(5.0, $minMm);
        $minPx = $minMm * 96.0 / 25.4;

        return preg_replace_callback(
            '/(<div[^>]*\bclass="[^"]*\bqr\b[^"]*"[^>]*\bstyle=")([^"]*)(")/i',
            function (array $m) use ($minPx): string {
                $style = $m[2];
                $w = null;
                $h = null;
                if (preg_match('/\bwidth\s*:\s*([\d.]+)\s*px/i', $style, $wm)) {
                    $w = (float) $wm[1];
                }
                if (preg_match('/\bheight\s*:\s*([\d.]+)\s*px/i', $style, $hm)) {
                    $h = (float) $hm[1];
                }
                if ($w === null && $h === null) {
                    $side = $minPx;
                } else {
                    $side = max($w ?? 0.0, $h ?? 0.0, $minPx);
                }
                if ($w !== null && $h !== null && $w >= $minPx - 0.01 && $h >= $minPx - 0.01
                    && abs($w - $h) < 0.5) {
                    // Ya cumple: solo fijar min-* por si el editor lo pierde
                    if (! preg_match('/\bmin-width\s*:/i', $style)) {
                        $style .= 'min-width:'.$this->formatPdfCssPx($minPx).';';
                    }
                    if (! preg_match('/\bmin-height\s*:/i', $style)) {
                        $style .= 'min-height:'.$this->formatPdfCssPx($minPx).';';
                    }

                    return $m[1].$style.$m[3];
                }

                // Ampliar al mínimo/cuadrado sin recentrar (mismo ancla top-left que el editor).
                if (preg_match('/\bwidth\s*:/i', $style)) {
                    $style = preg_replace('/\bwidth\s*:[^;]+;?/i', 'width:'.$this->formatPdfCssPx($side).';', $style) ?? $style;
                } else {
                    $style .= 'width:'.$this->formatPdfCssPx($side).';';
                }
                if (preg_match('/\bheight\s*:/i', $style)) {
                    $style = preg_replace('/\bheight\s*:[^;]+;?/i', 'height:'.$this->formatPdfCssPx($side).';', $style) ?? $style;
                } else {
                    $style .= 'height:'.$this->formatPdfCssPx($side).';';
                }
                $style = preg_replace('/\bmin-width\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = preg_replace('/\bmin-height\s*:[^;]+;?/i', '', $style) ?? $style;
                $style .= 'min-width:'.$this->formatPdfCssPx($minPx).';min-height:'.$this->formatPdfCssPx($minPx).';';

                return $m[1].$style.$m[3];
            },
            $html
        ) ?? $html;
    }

    /**
     * Inyecta el QR de la participación respetando width/height del recuadro del diseño
     * (misma lógica que portadas de taco; no forzar 60×60).
     */
    public function injectTicketQrIntoParticipationHtml(string $html, string $qrBase64): string
    {
        if (config('qr_optimization.skip_in_pdf', false)) {
            return $html;
        }
        if ($html === '' || $qrBase64 === '') {
            return $html;
        }

        $src = $qrBase64;
        // Ruta de fichero → DomPDF embebe mejor que data-URI
        if (! str_starts_with($src, 'data:') && ! preg_match('#^https?://#i', $src)) {
            $src = str_replace('\\', '/', $src);
        }

        $qrImg = '<img src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'" class="qr-code" style="width:100%;height:100%;display:block;margin:0;padding:0;border:0;" alt="QR Code" />';
        $replaced = false;

        // Caja .qr vacía o con handle
        $before = $html;
        $html = preg_replace(
            '/(<div[^>]*class="[^"]*\bqr\b[^"]*"[^>]*>)\s*(?:<span[^>]*>.*?<\/span>\s*)?(<\/div>)/is',
            '$1'.$qrImg.'$2',
            $html,
            1
        );
        if ($html !== $before) {
            $replaced = true;
        }

        if (! $replaced && (stripos($html, 'basicqr') !== false || preg_match('/<img[^>]+src="[^"]*basicqr[^"]*"/i', $html))) {
            $html = preg_replace(
                '/<img([^>]*)src="[^"]*basicqr[^"]*"([^>]*)>/i',
                '<img$1src="'.htmlspecialchars($src, ENT_QUOTES, 'UTF-8').'"$2 class="qr-code" style="width:100%;height:100%;display:block;">',
                $html,
                1
            ) ?? $html;
            $replaced = true;
        }

        if (! $replaced) {
            $html = $html.$qrImg;
        }

        return $html;
    }

    /**
     * Mapa ref => data-URI o ruta de fichero QR para DomPDF.
     *
     * @param  string[]  $uniqueReferences
     * @return array<string, string>
     */
    public function buildParticipationQrMap(array $uniqueReferences): array
    {
        if ($uniqueReferences === [] || config('qr_optimization.skip_in_pdf', false)) {
            return [];
        }

        $qrService = new \App\Services\EndroidQrCodeService();
        if (config('pdf_optimization.qr_as_files', true)) {
            return $qrService->generateUltraFastQrCodeFilePaths($uniqueReferences);
        }

        return $qrService->generateUltraFastQrCodes($uniqueReferences);
    }

    /**
     * Fondo a sangre completa (todo el canvas de la participación).
     * Las sangres (identation) quedan como guía visual; no recortan el fondo.
     * Evita background-size:calc() (poco fiable en DomPDF).
     */
    public function insetBackgroundWithinMargins(
        string $html,
        float $identationMm,
        string $wrapperId = 'containment-wrapper2',
        string $bgId = 'design-participation-bg'
    ): string {
        if ($html === '' || ! preg_match('/id=(["\'])'.preg_quote($wrapperId, '/').'\1/i', $html)) {
            return $html;
        }

        // Firma conservada; el fondo ocupa el canvas completo.
        unset($identationMm);
        $insetCss = 'left:0;top:0;width:100%;height:100%;right:auto;bottom:auto';

        if (preg_match('/id=(["\'])'.preg_quote($bgId, '/').'\1/i', $html)) {
            $html = preg_replace_callback(
                '/(<div[^>]*\bid=(["\'])'.preg_quote($bgId, '/').'\2[^>]*?\bstyle=(["\']))(.*?)(\3)/is',
                function ($m) use ($insetCss) {
                    $style = preg_replace(
                        '/\b(left|top|right|bottom|inset|width|height)\s*:[^;]+;?/i',
                        '',
                        $m[4]
                    ) ?? $m[4];
                    if (! preg_match('/\bposition\s*:/i', $style)) {
                        $style = 'position:absolute;'.$style;
                    }
                    if (! preg_match('/\bz-index\s*:/i', $style)) {
                        $style .= 'z-index:0;';
                    }
                    if (! preg_match('/\bpointer-events\s*:/i', $style)) {
                        $style .= 'pointer-events:none;';
                    }
                    if (! preg_match('/\bbackground-size\s*:/i', $style)) {
                        $style .= 'background-size:cover;';
                    }
                    if (! preg_match('/\bbackground-position\s*:/i', $style)) {
                        $style .= 'background-position:center;';
                    }
                    $style = trim(preg_replace('/;+/', ';', $style) ?? $style, "; \t\n\r").';'.$insetCss;

                    return $m[1].$style.$m[3];
                },
                $html,
                1
            ) ?? $html;

            return $this->clearWrapperBackgroundStyles($html, $wrapperId);
        }

        $bgColor = null;
        $bgImage = null;
        if (preg_match(
            '/<div([^>]*\bid=(["\'])'.preg_quote($wrapperId, '/').'\2[^>]*)>/i',
            $html,
            $wm
        )) {
            $attrs = $wm[1];
            if (preg_match('/\bstyle=(["\'])(.*?)\1/is', $attrs, $sm)) {
                $style = $sm[2];
                if (preg_match('/\bbackground-color\s*:\s*([^;]+)/i', $style, $cm)) {
                    $bgColor = trim($cm[1]);
                }
                if (preg_match('/\bbackground-image\s*:\s*([^;]+)/i', $style, $im)) {
                    $candidate = trim($im[1]);
                    if (strcasecmp($candidate, 'none') !== 0) {
                        $bgImage = $candidate;
                    }
                } elseif (preg_match('/\bbackground\s*:\s*([^;]+)/i', $style, $bm)
                    && stripos($bm[1], 'url(') !== false) {
                    $bgImage = trim($bm[1]);
                }
            }
        }

        if ($bgColor === null && $bgImage === null) {
            // Sin fondo en wrapper: aún así crear capa vacía no hace falta
            return $html;
        }

        $layerStyle = 'position:absolute;'.$insetCss.';z-index:0;pointer-events:none;background-size:cover;background-position:center;background-repeat:no-repeat;';
        if ($bgColor !== null && $bgColor !== '') {
            $layerStyle .= 'background-color:'.$bgColor.';';
        }
        if ($bgImage !== null && $bgImage !== '') {
            $layerStyle .= 'background-image:'.$bgImage.';';
        }
        $layer = '<div id="'.$bgId.'" class="design-margin-bg" style="'.$layerStyle.'"></div>';

        $html = preg_replace(
            '/(<div[^>]*\bid=(["\'])'.preg_quote($wrapperId, '/').'\2[^>]*>)/i',
            '$1'.$layer,
            $html,
            1
        ) ?? $html;

        return $this->clearWrapperBackgroundStyles($html, $wrapperId);
    }

    /**
     * Fondo de trasera: todo el canvas salvo la franja derecha de matriz (matrix_box),
     * igual que el editor (left/top/bottom 0, right = matriz).
     */
    public function insetBackBackgroundLeavingMatrix(
        string $html,
        float $identationMm,
        float $matrixMm,
        string $wrapperId = 'containment-wrapper4',
        string $bgId = 'design-back-bg'
    ): string {
        if ($html === '' || ! preg_match('/id=(["\'])'.preg_quote($wrapperId, '/').'\1/i', $html)) {
            return $html;
        }

        unset($identationMm);
        $rightMm = max(0, round($matrixMm, 2));
        $insetCss = "left:0;top:0;width:calc(100% - {$rightMm}mm);height:100%;right:auto;bottom:auto";

        if (preg_match('/id=(["\'])'.preg_quote($bgId, '/').'\1/i', $html)) {
            $html = preg_replace_callback(
                '/(<div[^>]*\bid=(["\'])'.preg_quote($bgId, '/').'\2[^>]*?\bstyle=(["\']))(.*?)(\3)/is',
                function ($m) use ($insetCss) {
                    $style = preg_replace(
                        '/\b(left|top|right|bottom|inset|width|height)\s*:[^;]+;?/i',
                        '',
                        $m[4]
                    ) ?? $m[4];
                    if (! preg_match('/\bposition\s*:/i', $style)) {
                        $style = 'position:absolute;'.$style;
                    }
                    if (! preg_match('/\bz-index\s*:/i', $style)) {
                        $style .= 'z-index:0;';
                    }
                    if (! preg_match('/\bpointer-events\s*:/i', $style)) {
                        $style .= 'pointer-events:none;';
                    }
                    if (! preg_match('/\bbackground-size\s*:/i', $style)) {
                        $style .= 'background-size:cover;';
                    }
                    if (! preg_match('/\bbackground-position\s*:/i', $style)) {
                        $style .= 'background-position:center;';
                    }
                    $style = trim(preg_replace('/;+/', ';', $style) ?? $style, "; \t\n\r").';'.$insetCss;

                    return $m[1].$style.$m[3];
                },
                $html,
                1
            ) ?? $html;

            return $this->clearWrapperBackgroundStyles($html, $wrapperId);
        }

        $bgColor = '#dfdfdf';
        $bgImage = null;
        if (preg_match(
            '/<div([^>]*\bid=(["\'])'.preg_quote($wrapperId, '/').'\2[^>]*)>/i',
            $html,
            $wm
        )) {
            $attrs = $wm[1];
            if (preg_match('/\bstyle=(["\'])(.*?)\1/is', $attrs, $sm)) {
                $style = $sm[2];
                if (preg_match('/\bbackground-color\s*:\s*([^;]+)/i', $style, $cm)) {
                    $bgColor = trim($cm[1]);
                }
                if (preg_match('/\bbackground-image\s*:\s*([^;]+)/i', $style, $im)) {
                    $candidate = trim($im[1]);
                    if (strcasecmp($candidate, 'none') !== 0) {
                        $bgImage = $candidate;
                    }
                }
            }
        }

        $layerStyle = 'position:absolute;'.$insetCss.';z-index:0;pointer-events:none;background-size:cover;background-position:center;background-repeat:no-repeat;background-color:'.$bgColor.';';
        if ($bgImage !== null && $bgImage !== '') {
            $layerStyle .= 'background-image:'.$bgImage.';';
        }
        $layer = '<div id="'.$bgId.'" class="design-margin-bg" style="'.$layerStyle.'"></div>';

        $html = preg_replace(
            '/(<div[^>]*\bid=(["\'])'.preg_quote($wrapperId, '/').'\2[^>]*>)/i',
            '$1'.$layer,
            $html,
            1
        ) ?? $html;

        return $this->clearWrapperBackgroundStyles($html, $wrapperId);
    }

    private function clearWrapperBackgroundStyles(string $html, string $wrapperId): string
    {
        return preg_replace_callback(
            '/(<div[^>]*\bid=(["\'])'.preg_quote($wrapperId, '/').'\2[^>]*?\bstyle=(["\']))(.*?)(\3)/is',
            function ($m) {
                $style = $m[4];
                $style = preg_replace('/\bbackground(-image|-color|-size|-position|-repeat)?\s*:[^;]+;?/i', '', $style) ?? $style;
                $style = trim(preg_replace('/;+/', ';', $style) ?? $style, "; \t\n\r");
                if ($style === '') {
                    $style = 'background-color:#ffffff';
                } elseif (! preg_match('/\bbackground-color\s*:/i', $style)) {
                    $style .= ';background-color:#ffffff';
                }

                return $m[1].$style.$m[3];
            },
            $html,
            1
        ) ?? $html;
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
            // uploads/storage y también assets de public root (p.ej. /default.jpg)
            $fixed = preg_replace_callback(
                '#https?://(?:'.$hostsPattern.')(?::\d+)?(/[^\s\'"\)\>\#]+)#i',
                static function (array $m) use ($fsBase, $mapToFs): string {
                    $path = explode('?', rawurldecode($m[1]), 2)[0];
                    $candidate = $fsBase.str_replace('\\', '/', $path);
                    if (is_file($candidate)) {
                        return $candidate;
                    }
                    // Solo forzar uploads/storage aunque no existan (comportamiento previo)
                    if (preg_match('#^/(?:uploads|storage)/#i', $path)) {
                        return $mapToFs($m);
                    }

                    return $m[0];
                },
                $html
            );
            $html = $fixed ?? $html;

            // //host/uploads/... (sin esquema http/https)
            $fixed2 = preg_replace_callback(
                '#//(?:'.$hostsPattern.')(?::\d+)?(/[^\s\'"\)\>\#]+)#i',
                static function (array $m) use ($fsBase, $mapToFs): string {
                    $path = explode('?', rawurldecode($m[1]), 2)[0];
                    $candidate = $fsBase.str_replace('\\', '/', $path);
                    if (is_file($candidate)) {
                        return $candidate;
                    }
                    if (preg_match('#^/(?:uploads|storage)/#i', $path)) {
                        return $mapToFs($m);
                    }

                    return $m[0];
                },
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

        // <img src="/uploads/...">, /storage/... y otros ficheros públicos (p.ej. /default.jpg)
        $html = preg_replace_callback(
            '#\b(src|href)\s*=\s*([\'"])(/[^\'"]+)\2#i',
            function ($m) use ($normBase) {
                $rel = str_replace('\\', '/', $m[3]);
                $candidate = $normBase.$rel;
                if (is_file($candidate) || preg_match('#^/(?:uploads|storage)/#i', $rel)) {
                    return $m[1].'='.$m[2].$candidate.$m[2];
                }

                return $m[0];
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
        
        // Convertir RGB a hex (solo propiedad color, no background-color)
        $html = preg_replace_callback(
            '/(?<![-\w])color:\s*rgb\((\d+),\s*(\d+),\s*(\d+)\)/i',
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
        
        // Sin caché del HTML: cada export reprocesa (iteración rápida de CSS/PDF).
        $participation_html = $this->prepareParticipationHtmlForPdf(
            $design->participation_html ?? '',
            (float) ($design->identation ?? 2.5)
        );

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

        // Generar QR codes en lote (ficheros o data-URI según config)
        $uniqueReferences = [];
        foreach ($tickets_to_print as $ticket) {
            if (isset($ticket['r']) && ! in_array($ticket['r'], $uniqueReferences, true)) {
                $uniqueReferences[] = $ticket['r'];
            }
        }
        $qrCodes = $this->buildParticipationQrMap($uniqueReferences);
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
                ->view('design.pdf_participation', $this->participationPdfViewData(
                    $design,
                    $pages,
                    $participation_html,
                    $qrCodes,
                    [
                        'pdfDocumentTitle' => 'Participación PDF (vista previa)',
                        'pdfHtmlPreviewBanner' => true,
                    ]
                ))
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        // Varios documentos (ZIP) o stamp: misma ruta de artefacto.
        $docsOpts = $this->resolvePrintDocumentsOptions($request, $design);
        $docRanges = $this->participationPdfDocumentRanges(
            $design,
            $from,
            $to,
            $docsOpts['documents_mode'],
            $docsOpts['pages_per_document']
        );
        if (count($docRanges) > 1 || config('pdf_optimization.use_stamp_template', false)) {
            $jobId = 'pdf_sync_part_'.$id.'_'.$from.'_'.$to.'_'.uniqid('', true);
            try {
                $artifact = $this->writeParticipationExportArtifact(
                    $design,
                    $from,
                    $to,
                    $jobId,
                    $docsOpts['documents_mode'],
                    $docsOpts['pages_per_document']
                );
                $this->cleanupTempQrCodes();
                app(DesignApprovalService::class)->markParticipationExportLock($design);

                return response()->download(
                    $artifact['path'],
                    $artifact['download_name']
                )->deleteFileAfterSend(true);
            } catch (\Throwable $e) {
                GeneratedPdfCatalog::deleteArtifacts($jobId);
                throw $e;
            }
        }

        // Para PDFs muy grandes (>500 participaciones), usar procesamiento por lotes
        if ($total > 500) {
            return $this->generatePdfInChunks($design, $participation_html, $tickets, $from, $to, $rows, $cols, $page, $pdfOrientation, $qrCodes);
        }
        
        // Ordenar tickets en modo guillotina (optimizado)
        $pages = $this->generatePagesOptimized($tickets_to_print, $total_pages, $per_page);

        $pdf = Pdf::loadView('design.pdf_participation', $this->participationPdfViewData(
            $design,
            $pages,
            $participation_html,
            $qrCodes
        ))->setPaper($page, $pdfOrientation);
        $this->applyDompdfOptions($pdf);

        // Limpiar QR codes temporales después de generar el PDF
        $this->cleanupTempQrCodes();
        app(DesignApprovalService::class)->markParticipationExportLock($design);

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
                $payload = $this->buildGridPdfParticipationViewPayload(
                    $design,
                    $this->buildCoverHtmlItems($design),
                    'Portadas PDF (vista previa)',
                    false,
                    $design->cover_html ?? ''
                );
                $payload['data']['pdfHtmlPreviewBanner'] = true;

                return response()
                    ->view($payload['view'], $payload['data'])
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            }
            if (config('pdf_optimization.use_stamp_template', false)) {
                $tmp = storage_path('app/temp_cover_dl_'.uniqid('', true).'.pdf');
                $this->writeCoverPdfToFile($design, $tmp);

                return response()->download($tmp, 'portadas.pdf')->deleteFileAfterSend(true);
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
        if (! $design->hasBackDesign()) {
            abort(404, 'Este diseño no incluye trasera.');
        }

        $copies = $this->normalizeBackPdfCopies($request->query('copies', 'all'));
        $exactCount = $this->parseBackPdfExactCount($request);

        try {
            if ($this->designPdfHtmlPreviewEnabled()) {
                if ($exactCount !== null) {
                    $title = 'Traseras PDF — '.$exactCount.' ejemplar(es) (vista previa)';
                } else {
                    $title = $copies === 'one' ? 'Traseras PDF — 1 ejemplar (vista previa)' : 'Traseras PDF (vista previa)';
                }
                $payload = $this->buildGridPdfParticipationViewPayload(
                    $design,
                    $this->buildBackHtmlItems($design, $copies, $exactCount),
                    $title,
                    true,
                    $design->back_html ?? ''
                );
                $payload['data']['pdfHtmlPreviewBanner'] = true;

                return response()
                    ->view($payload['view'], $payload['data'])
                    ->header('Content-Type', 'text/html; charset=UTF-8');
            }
            if (config('pdf_optimization.use_stamp_template', false)) {
                $tmp = storage_path('app/temp_back_dl_'.uniqid('', true).'.pdf');
                $this->writeBackPdfToFile($design, $tmp, $copies, $exactCount);
                if ($exactCount !== null) {
                    $filename = 'traseras-'.$exactCount.'.pdf';
                } else {
                    $filename = $copies === 'one' ? 'trasera.pdf' : 'traseras.pdf';
                }

                return response()->download($tmp, $filename)->deleteFileAfterSend(true);
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

        return $this->makeGridPdfFromHtmlItems($design, $items, 'Traseras PDF', true);
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

        $perBook = max(1, (int) ($output['participations_per_book'] ?? 50));
        $totalParticipations = $this->resolveSetTotalParticipations($design);
        $totalBooks = max(1, count($tacoQrs));

        $qrService = new \App\Services\EndroidQrCodeService();
        $items = [];
        foreach ($tacoQrs as $taco) {
            $tacoRef = $taco['taco_ref'] ?? '';
            $bookNumber = (int) ($taco['book_number'] ?? 0);
            if ($tacoRef === '') {
                continue;
            }
            $qrBase64 = $qrService->generateQrFromTextBase64($tacoRef);
            $items[] = $this->replaceCoverQrWithTacoQr(
                $coverTemplate,
                $qrBase64,
                $bookNumber,
                $totalBooks,
                $perBook,
                $totalParticipations
            );
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

    public function prepareCoverOrBackHtmlForPdf(DesignFormat $design, string $htmlField, ?string $htmlOverride = null): string
    {
        $html = $htmlOverride ?? ($design->$htmlField ?? '');
        if ($html === '') {
            return $html;
        }

        $identationMm = (float) ($design->identation ?? 2.5);
        $matrixMm = (float) ($design->matrix_box ?? 40);
        $isCover = $htmlField === 'cover_html';
        $wrapperId = $isCover ? 'containment-wrapper3' : 'containment-wrapper4';
        $bgId = $isCover ? 'design-cover-bg' : 'design-back-bg';

        $html = $this->decodeCssUrlHtmlEntities($html);
        if ($isCover) {
            $html = $this->insetBackgroundWithinMargins($html, $identationMm, $wrapperId, $bgId);
        } else {
            // Trasera: franja derecha = identation + matriz (como el editor).
            $html = $this->insetBackBackgroundLeavingMatrix($html, $identationMm, $matrixMm, $wrapperId, $bgId);
        }
        $html = $this->flattenParticipationHtmlForPdf($html);
        $html = $this->stripFormatBoxBorderForPdf($html);
        $html = $this->normalizeParticipationElementsForPdf($html);
        $html = $this->applyVerticalTextForDomPdf($html);
        $html = $this->enforceQrMinPrintSizeInHtml($html);
        $html = $this->adjustElementBoxModelForDomPdf($html);
        $html = $this->scaleFontSizesForDomPdf($html);
        $publicPath = public_path();
        $html = $this->replaceApplicationWebRootsWithPublicPath($html, $publicPath);
        $html = $this->ensureLocalPathsForPdf($html, $publicPath);
        $html = $this->ensurePdfBackgroundCoverStyles($html, $bgId);

        $bgPixelScale = (float) config('pdf_optimization.bg_pixel_scale', 1.0);
        if ($isCover) {
            $html = $this->materializePdfBackgroundCoverBitmap(
                $html,
                $bgId,
                0,
                $bgPixelScale
            );
        } else {
            [$boxWmm, $boxHmm] = $this->resolveFormatBoxSizeMmFromHtml($html);
            $rightMm = max(0, $matrixMm);
            $innerWmm = max(1.0, $boxWmm - $rightMm);
            $html = $this->materializePdfBackgroundCoverBitmap(
                $html,
                $bgId,
                0,
                $bgPixelScale,
                $innerWmm,
                $boxHmm
            );
        }
        $html = $this->promotePdfBackgroundLayerToImg($html, $bgId);
        $html = $this->preserveInlineStyles($html);

        return $html;
    }

    /**
     * HTML ligero de portada/trasera para coords de stamp (sin hacks DomPDF de caja/fuente).
     */
    public function prepareCoverOrBackStampSlotHtml(DesignFormat $design, string $htmlField, ?string $htmlOverride = null): string
    {
        $html = $htmlOverride ?? ($design->$htmlField ?? '');
        if ($html === '') {
            return $html;
        }

        $html = $this->decodeCssUrlHtmlEntities($html);
        $html = $this->flattenParticipationHtmlForPdf($html);
        $html = $this->stripFormatBoxBorderForPdf($html);
        $html = $this->normalizeParticipationElementsForPdf($html);
        $html = $this->enforceQrMinPrintSizeInHtml($html);
        $publicPath = public_path();
        $html = $this->replaceApplicationWebRootsWithPublicPath($html, $publicPath);
        $html = $this->ensureLocalPathsForPdf($html, $publicPath);

        return $html;
    }

    /**
     * PDF de portadas en disco (stamp si PDF_USE_STAMP_TEMPLATE).
     * $itemFrom/$itemTo opcionales (1-based, inclusive) para trocear tacos/ítems.
     */
    public function writeCoverPdfToFile(
        DesignFormat $design,
        string $finalPath,
        ?int $itemFrom = null,
        ?int $itemTo = null
    ): void {
        $dir = dirname($finalPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! config('pdf_optimization.use_stamp_template', false)) {
            $items = $this->buildCoverHtmlItems($design);
            if ($itemFrom !== null && $itemTo !== null) {
                $items = array_values(array_slice(
                    $items,
                    max(0, $itemFrom - 1),
                    max(0, $itemTo - $itemFrom + 1)
                ));
            }
            if ($items === []) {
                throw new \InvalidArgumentException('No hay portadas en el rango solicitado.');
            }
            $this->saveGridPdfFacadeToPath($design, $items, $finalPath, 'Portadas PDF');

            return;
        }

        $prepared = $this->prepareCoverOrBackHtmlForPdf($design, 'cover_html');
        $slotsHtml = $this->prepareCoverOrBackStampSlotHtml($design, 'cover_html');
        $books = $this->buildCoverStampBookItems($design);
        if ($itemFrom !== null && $itemTo !== null) {
            $books = array_values(array_slice(
                $books,
                max(0, $itemFrom - 1),
                max(0, $itemTo - $itemFrom + 1)
            ));
        }
        if ($books === []) {
            throw new \InvalidArgumentException('No hay portadas en el rango solicitado.');
        }
        app(\App\Services\CoverBackPdfStampExporter::class)->exportCoversToFile(
            $design,
            $prepared,
            $books,
            $finalPath,
            $slotsHtml
        );
    }

    /**
     * PDF de traseras en disco (stamp si PDF_USE_STAMP_TEMPLATE).
     */
    public function writeBackPdfToFile(
        DesignFormat $design,
        string $finalPath,
        string $copies = 'all',
        ?int $exactCount = null
    ): void {
        $dir = dirname($finalPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (! config('pdf_optimization.use_stamp_template', false)) {
            $items = $this->buildBackHtmlItems($design, $copies, $exactCount);
            $this->saveGridPdfFacadeToPath($design, $items, $finalPath, 'Traseras PDF', true);

            return;
        }

        $prepared = $this->prepareCoverOrBackHtmlForPdf($design, 'back_html');
        $copies = $this->normalizeBackPdfCopies($copies);
        if ($exactCount !== null) {
            $n = max(1, min(100000, (int) $exactCount));
        } elseif ($copies === 'one') {
            $n = 1;
        } else {
            $set = $design->set_id
                ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id)
                : null;
            $total = (int) ($set->total_participations ?? 0);
            if ($total <= 0 && $set && $set->tickets) {
                $tickets = is_array($set->tickets) ? $set->tickets : [];
                $total = count($tickets);
            }
            $n = max(1, $total);
        }

        app(\App\Services\CoverBackPdfStampExporter::class)->exportBackCopiesToFile(
            $design,
            $prepared,
            $n,
            $finalPath
        );
    }

    /**
     * @return list<array{taco_ref:string,book_number:int,label:string}>
     */
    public function buildCoverStampBookItems(DesignFormat $design): array
    {
        if (empty($design->cover_html)) {
            throw new \InvalidArgumentException('Portada no encontrada');
        }

        $output = $design->output ?? [];
        if (! empty($output['participations_per_book']) && $design->set_id && empty($output['taco_qrs'])) {
            $output = DesignFormat::mergeTacoQrsIntoOutput($design->set_id, $output);
        }

        $tacoQrs = $output['taco_qrs'] ?? [];
        $perBook = max(1, (int) ($output['participations_per_book'] ?? 50));
        $totalParticipations = $this->resolveSetTotalParticipations($design);

        if ($tacoQrs === []) {
            return [[
                'taco_ref' => 'PREVIEW-TACO',
                'book_number' => 1,
                'label' => $this->buildTacoCoverLabel(1, 1, $perBook, max(1, $totalParticipations)),
            ]];
        }

        usort($tacoQrs, fn ($a, $b) => ((int) ($a['book_number'] ?? 0)) <=> ((int) ($b['book_number'] ?? 0)));
        $totalBooks = max(1, count($tacoQrs));
        $items = [];
        foreach ($tacoQrs as $taco) {
            $tacoRef = (string) ($taco['taco_ref'] ?? '');
            $bookNumber = (int) ($taco['book_number'] ?? 0);
            if ($tacoRef === '') {
                continue;
            }
            $items[] = [
                'taco_ref' => $tacoRef,
                'book_number' => $bookNumber,
                'label' => $this->buildTacoCoverLabel($bookNumber, $totalBooks, $perBook, $totalParticipations),
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('No se pudieron generar las portadas de tacos');
        }

        return $items;
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
     * Misma ordenación talonario/guillotina que participaciones.
     *
     * @param  list<mixed>  $items
     * @return list<list<mixed>>
     */
    public function generatePagesGuillotine(array $items, int $perPage): array
    {
        $count = count($items);
        $perPage = max(1, $perPage);
        if ($count === 0) {
            return [];
        }

        $totalPages = (int) ceil($count / $perPage);
        $pages = [];
        for ($p = 0; $p < $totalPages; $p++) {
            $pages[$p] = [];
            for ($i = 0; $i < $perPage; $i++) {
                $index = $p + ($i * $totalPages);
                if ($index < $count) {
                    $pages[$p][$i] = $items[$index];
                }
            }
        }

        return $pages;
    }

    /**
     * Misma vista y datos que DomPDF usa para portadas/traseras en cuadrícula.
     *
     * @param  string[]  $items
     * @return array{view: string, data: array<string, mixed>, page: string, orientation: string}
     */
    private function participationPdfLayout(DesignFormat $design, ?string $participationHtml = null): ParticipationPdfLayout
    {
        return ParticipationPdfLayout::fromDesign(
            $design,
            $participationHtml ?? $design->participation_html ?? ''
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function participationPdfViewData(
        DesignFormat $design,
        array $pages,
        string $participationHtml,
        array $qrCodes,
        array $extra = [],
        ?string $layoutHtml = null
    ): array {
        $layout = $this->participationPdfLayout($design, $layoutHtml ?? $participationHtml);
        if ($participationHtml !== '' && empty($extra['use_prebuilt_cells'])) {
            $participationHtml = $this->scaleParticipationHtmlDimensionsForPdf($participationHtml, $layout);
        }

        return array_merge([
            'pages' => $pages,
            'participation_html' => $participationHtml,
            'rows' => max(1, (int) ($design->rows ?? 1)),
            'cols' => max(1, (int) ($design->cols ?? 1)),
            'qrCodes' => $qrCodes,
            'layout' => $layout,
        ], $extra);
    }

    private function buildGridPdfParticipationViewPayload(
        DesignFormat $design,
        array $items,
        string $documentTitle,
        bool $talonario = false,
        ?string $layoutHtml = null
    ): array {
        if ($items === []) {
            throw new \RuntimeException('No hay elementos para generar el PDF');
        }

        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $per_page = $rows * $cols;
        $layoutSource = $layoutHtml ?? $items[0] ?? $design->participation_html ?? '';
        $layout = $this->participationPdfLayout($design, $layoutSource);
        $scaledItems = array_map(
            fn (string $item): string => $this->scaleParticipationHtmlDimensionsForPdf($item, $layout),
            $items
        );
        $pages = $talonario
            ? $this->generatePagesGuillotine($scaledItems, $per_page)
            : $this->generatePagesSequential($scaledItems, $per_page);

        return [
            'view' => 'design.pdf_participation',
            'data' => $this->participationPdfViewData($design, $pages, '', [], [
                'use_prebuilt_cells' => true,
                'pdfDocumentTitle' => $documentTitle,
            ], $layoutSource),
            'page' => $design->page ?? 'a3',
            'orientation' => $design->orientation ?? 'h',
        ];
    }

    /**
     * @param  string[]  $items
     */
    public function makeGridPdfFromHtmlItems(
        DesignFormat $design,
        array $items,
        string $documentTitle,
        bool $talonario = false
    ) {
        $payload = $this->buildGridPdfParticipationViewPayload($design, $items, $documentTitle, $talonario);
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
    public function saveGridPdfFacadeToPath(
        DesignFormat $design,
        array $items,
        string $finalPath,
        string $documentTitle,
        bool $talonario = false
    ): void {
        $chunkSize = 80;
        if ($talonario || count($items) <= $chunkSize) {
            $this->makeGridPdfFromHtmlItems($design, $items, $documentTitle, $talonario)->save($finalPath);

            return;
        }

        $temp_files = [];
        foreach (array_chunk($items, $chunkSize) as $chunk) {
            $temp = storage_path('app/temp_pdf_'.uniqid('', true).'.pdf');
            $this->makeGridPdfFromHtmlItems($design, $chunk, $documentTitle, false)->save($temp);
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

    public function applyDompdfOptions($pdf): void
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
        $options->set('enableFontSubsetting', (bool) config('pdf_optimization.font_subsetting', true));
        // Mantener DPI 96: px del diseño están calibrados al ticket en mm.
        // Subir DPI encoge los elementos en px respecto al format-box y destroza el layout.
        $options->set('dpi', (int) config('pdf_optimization.dpi', 96));

        $fontDir = $this->ensureDompdfFontDir();

        // Permitir cargar tipografías locales (public/, storage/)
        $chroot = array_values(array_unique(array_filter([
            base_path(),
            public_path(),
            storage_path(),
            $fontDir,
        ])));
        $options->setChroot($chroot);
        if ($fontDir) {
            $options->setFontDir($fontDir);
            $options->setFontCache($fontDir);
        }

        $this->registerDesignDompdfFonts($dompdf, $fontDir);
    }

    /**
     * Crea storage/fonts si hace falta y comprueba escritura de métricas DomPDF (.ufm).
     */
    protected function ensureDompdfFontDir(): ?string
    {
        $dir = storage_path('fonts');

        if (! is_dir($dir)) {
            try {
                if (! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                    Log::warning('No se pudo crear storage/fonts para DomPDF', ['path' => $dir]);

                    return null;
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo crear storage/fonts para DomPDF: '.$e->getMessage(), [
                    'path' => $dir,
                ]);

                return null;
            }
        }

        if (! is_writable($dir)) {
            @chmod($dir, 0775);
        }

        if (! is_writable($dir)) {
            Log::warning('storage/fonts no es escribible; Asgonlae no se registrará en DomPDF', [
                'path' => $dir,
            ]);

            return null;
        }

        return $dir;
    }

    /**
     * Registra fuentes TTF del diseño en DomPDF (p. ej. Asgonlae).
     * No emitir @font-face data-URI en vistas PDF: DomPDF escribe .ufm al parsear CSS.
     */
    protected function registerDesignDompdfFonts($dompdf, ?string $fontDir = null): void
    {
        $fontDir = $fontDir ?? $this->ensureDompdfFontDir();
        if ($fontDir === null) {
            return;
        }

        $fontFile = public_path('Asgonlae.ttf');
        if (! is_readable($fontFile)) {
            Log::warning('Asgonlae.ttf no legible', ['path' => $fontFile]);

            return;
        }

        try {
            $fontMetrics = $dompdf->getFontMetrics();
            if (! method_exists($fontMetrics, 'registerFont')) {
                return;
            }

            // DomPDF espera URI con protocolo (file://), no ruta Windows cruda.
            $fontUri = 'file:///' . ltrim(str_replace('\\', '/', $fontFile), '/');

            $okNormal = $fontMetrics->registerFont(
                [
                    'family' => 'Asgonlae',
                    'style' => 'normal',
                    'weight' => 'normal',
                ],
                $fontUri
            );
            $okBold = $fontMetrics->registerFont(
                [
                    'family' => 'Asgonlae',
                    'style' => 'normal',
                    'weight' => 'bold',
                ],
                $fontUri
            );

            if (! $okNormal && ! $okBold) {
                Log::warning('DomPDF no registró Asgonlae (registerFont=false)', [
                    'uri' => $fontUri,
                    'fontDir' => $fontDir,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar Asgonlae en DomPDF: '.$e->getMessage(), [
                'fontDir' => $fontDir,
            ]);
        }
    }

    /**
     * Vista previa rápida (1 participación / 1 portada / 1 trasera) del HTML actual del editor.
     * Abre inline en popup; no encola.
     */
    public function previewDesignStepPdf(Request $request)
    {
        if (! auth()->check() && ! session()->has('design_external_invitation_id') && ! session()->has('print_shop_order_id')) {
            abort(403);
        }

        ini_set('max_execution_time', 120);
        ini_set('memory_limit', '512M');

        $data = $request->validate([
            'type' => 'required|in:participation,cover,back',
            'html' => 'required|string',
            'design_id' => 'nullable|integer',
            'page' => 'nullable|string|max:20',
            'orientation' => 'nullable|in:h,v',
            'rows' => 'nullable|integer|min:1|max:12',
            'cols' => 'nullable|integer|min:1|max:12',
            'identation' => 'nullable|numeric|min:0|max:50',
            'cut_lines' => 'nullable|numeric|min:0|max:50',
        ]);

        $type = $data['type'];
        $html = $data['html'];
        $design = ! empty($data['design_id']) ? DesignFormat::find($data['design_id']) : null;

        $page = $data['page'] ?? ($design->page ?? 'a3');
        $orientation = $data['orientation'] ?? ($design->orientation ?? 'h');
        $rows = (int) ($data['rows'] ?? ($design->rows ?? 3));
        $cols = (int) ($data['cols'] ?? ($design->cols ?? 2));
        $identation = (float) ($data['identation'] ?? ($design->identation ?? 2.5));
        $cutLines = array_key_exists('cut_lines', $data)
            ? (float) $data['cut_lines']
            : (float) ($design->cut_lines ?? $identation);
        $pdfOrientation = ($orientation === 'h') ? 'landscape' : 'portrait';
        $perPage = max(1, $rows * $cols);

        if ($type === 'participation') {
            $prepared = $this->prepareParticipationHtmlForPdf($html, $identation);
            // Hoja completa (rows×cols) con refs/QR en ceros — igual que la muestra de aprobación.
            $tickets = $this->buildParticipationSampleTicketsForPreview($perPage);
            $qrCodes = $this->buildParticipationQrMap(array_values(array_unique(array_map(
                static fn (array $t): string => (string) $t['r'],
                $tickets
            ))));

            // Misma ruta que la exportación real cuando el stamp está activo.
            if (config('pdf_optimization.use_stamp_template', false) && $design) {
                $shell = $design->replicate();
                $shell->page = $page;
                $shell->orientation = $orientation;
                $shell->rows = $rows;
                $shell->cols = $cols;
                $shell->identation = $identation;
                $shell->cut_lines = $cutLines;
                $tmp = storage_path('app/temp_preview_stamp_'.uniqid('', true).'.pdf');
                try {
                    $slotsHtml = $this->prepareStampSlotHtml($html, $identation);
                    app(\App\Services\ParticipationPdfStampExporter::class)->exportToFile(
                        $shell,
                        $prepared,
                        $tickets,
                        $qrCodes,
                        $tmp,
                        $slotsHtml
                    );
                    $this->cleanupTempQrCodes();

                    return response()->file($tmp, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="preview-participacion.pdf"',
                    ])->deleteFileAfterSend(true);
                } catch (\Throwable $e) {
                    @unlink($tmp);
                    Log::warning('previewDesignStepPdf stamp fallback', ['error' => $e->getMessage()]);
                }
            }

            $layoutDesign = $design ? $design->replicate() : new DesignFormat();
            $layoutDesign->page = $page;
            $layoutDesign->orientation = $orientation;
            $layoutDesign->rows = $rows;
            $layoutDesign->cols = $cols;
            $layoutDesign->identation = $identation;
            $layoutDesign->cut_lines = $cutLines;

            $pages = $this->generatePagesOptimized($tickets, 1, $perPage);
            $pdf = Pdf::loadView('design.pdf_participation', $this->participationPdfViewData(
                $layoutDesign,
                $pages,
                $prepared,
                $qrCodes,
                ['pdfDocumentTitle' => 'Vista previa participación'],
                $html
            ))->setPaper($page, $pdfOrientation);
            $this->applyDompdfOptions($pdf);
            $this->cleanupTempQrCodes();

            return $pdf->stream('preview-participacion.pdf');
        }

        $shell = $design ? $design->replicate() : new DesignFormat();
        $shell->page = $page;
        $shell->orientation = $orientation;
        $shell->rows = $rows;
        $shell->cols = $cols;
        $shell->identation = $identation;
        $shell->cut_lines = $cutLines;
        $shell->set_id = $design->set_id ?? null;
        $shell->output = $design->output ?? [];

        if ($type === 'cover') {
            $shell->cover_html = $html;
            if (config('pdf_optimization.use_stamp_template', false)) {
                $tmp = storage_path('app/temp_preview_cover_stamp_'.uniqid('', true).'.pdf');
                try {
                    $shell->output = $design->output ?? ['taco_qrs' => [[
                        'taco_ref' => 'PREVIEW-TACO',
                        'book_number' => 1,
                    ]], 'participations_per_book' => 50];
                    $this->writeCoverPdfToFile($shell, $tmp);

                    return response()->file($tmp, [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="preview-portada.pdf"',
                    ])->deleteFileAfterSend(true);
                } catch (\Throwable $e) {
                    Log::warning('previewDesignStepPdf cover stamp fallback', ['error' => $e->getMessage()]);
                    @unlink($tmp);
                }
            }
            $prepared = $this->prepareCoverOrBackHtmlForPdf($shell, 'cover_html', $html);
            $qrService = new \App\Services\EndroidQrCodeService();
            $qrBase64 = $qrService->generateQrFromTextBase64('PREVIEW-TACO');
            $item = $this->replaceCoverQrWithTacoQr($prepared, $qrBase64, 1, 1, 50, 50);
            $pdf = $this->makeGridPdfFromHtmlItems($shell, [$item], 'Vista previa portada');

            return $pdf->stream('preview-portada.pdf');
        }

        $shell->back_html = $html;
        if (config('pdf_optimization.use_stamp_template', false)) {
            $tmp = storage_path('app/temp_preview_back_stamp_'.uniqid('', true).'.pdf');
            try {
                $this->writeBackPdfToFile($shell, $tmp, 'one', 1);

                return response()->file($tmp, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="preview-trasera.pdf"',
                ])->deleteFileAfterSend(true);
            } catch (\Throwable $e) {
                Log::warning('previewDesignStepPdf back stamp fallback', ['error' => $e->getMessage()]);
                @unlink($tmp);
            }
        }
        $prepared = $this->prepareCoverOrBackHtmlForPdf($shell, 'back_html', $html);
        $pdf = $this->makeGridPdfFromHtmlItems($shell, [$prepared], 'Vista previa trasera');

        return $pdf->stream('preview-trasera.pdf');
    }

    /**
     * Tickets ficticios para rellenar 1 hoja completa en «Previsualizar PDF»
     * (refs/QR en ceros, como la muestra de aprobación).
     *
     * @return list<array{r: string, n: int}>
     */
    private function buildParticipationSampleTicketsForPreview(int $perPage): array
    {
        $perPage = max(1, $perPage);
        $zeroRef = str_repeat('0', \App\Support\ParticipationTicketReference::LENGTH);
        $tickets = [];
        for ($i = 1; $i <= $perPage; $i++) {
            $tickets[] = [
                'r' => $zeroRef,
                'n' => $i,
            ];
        }

        return $tickets;
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

        $backHtml = $this->prepareCoverOrBackHtmlForPdf($design, 'back_html');

        $output = $design->output ?? [];
        if (!empty($output['participations_per_book']) && $design->set_id && empty($output['taco_qrs'])) {
            $output = DesignFormat::mergeTacoQrsIntoOutput($design->set_id, $output);
        }
        $tacoQrs = $output['taco_qrs'] ?? [];

        if (!empty($tacoQrs)) {
            $coverPages = [];
            $coverTemplate = $this->prepareCoverOrBackHtmlForPdf($design, 'cover_html');

            $perBook = max(1, (int) ($output['participations_per_book'] ?? 50));
            $totalParticipations = $this->resolveSetTotalParticipations($design);
            $totalBooks = max(1, count($tacoQrs));

            foreach ($tacoQrs as $taco) {
                $tacoRef = $taco['taco_ref'] ?? '';
                $bookNumber = (int) ($taco['book_number'] ?? 0);
                if (empty($tacoRef)) {
                    continue;
                }
                $qrBase64 = (new \App\Services\EndroidQrCodeService())->generateQrFromTextBase64($tacoRef);
                $coverHtml = $this->replaceCoverQrWithTacoQr(
                    $coverTemplate,
                    $qrBase64,
                    $bookNumber,
                    $totalBooks,
                    $perBook,
                    $totalParticipations
                );
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
            $coverHtml = $this->prepareCoverOrBackHtmlForPdf($design, 'cover_html');

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
     * Total de participaciones del set asociado al diseño (tickets como fallback).
     */
    private function resolveSetTotalParticipations(DesignFormat $design): int
    {
        $set = $design->relationLoaded('set')
            ? $design->set
            : ($design->set_id ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id) : null);

        $total = (int) ($set->total_participations ?? 0);
        if ($total <= 0 && $set && ! empty($set->tickets)) {
            $tickets = is_array($set->tickets) ? $set->tickets : [];
            $total = count($tickets);
        }

        return max(0, $total);
    }

    /**
     * Texto del recuadro inferior de la portada de taco.
     * Ejemplo: "Taco 001/050 - Participaciones 00001/00050"
     */
    private function buildTacoCoverLabel(int $bookNumber, int $totalBooks, int $perBook, int $totalParticipations): string
    {
        $bookNumber = max(1, $bookNumber);
        $totalBooks = max(1, $totalBooks);
        $perBook = max(1, $perBook);
        $from = (($bookNumber - 1) * $perBook) + 1;
        $to = $bookNumber * $perBook;
        if ($totalParticipations > 0) {
            $to = min($to, $totalParticipations);
        }
        $to = max($from, $to);

        $tacoPad = max(3, strlen((string) $totalBooks));
        $partPad = max(5, strlen((string) max($totalParticipations, $to)));

        return sprintf(
            'Taco %s/%s - Participaciones %s/%s',
            str_pad((string) $bookNumber, $tacoPad, '0', STR_PAD_LEFT),
            str_pad((string) $totalBooks, $tacoPad, '0', STR_PAD_LEFT),
            str_pad((string) $from, $partPad, '0', STR_PAD_LEFT),
            str_pad((string) $to, $partPad, '0', STR_PAD_LEFT)
        );
    }

    /**
     * Reemplaza o inyecta el elemento QR de la portada con el QR del taco.
     * Prueba varios patrones (como participaciones) y si no hay QR, lo inyecta.
     * También rellena el recuadro inferior (barra context) con número de taco y rango.
     */
    private function replaceCoverQrWithTacoQr(
        string $coverHtml,
        string $qrBase64,
        int $bookNumber,
        int $totalBooks = 1,
        int $perBook = 50,
        int $totalParticipations = 0
    ): string {
        $qrImg = '<img src="' . $qrBase64 . '" class="qr-code" style="width:100%;height:100%;display:block;" alt="QR Taco ' . (int) $bookNumber . '" />';
        $replaced = false;
        $tacoLabel = $this->buildTacoCoverLabel($bookNumber, $totalBooks, $perBook, $totalParticipations);
        $from = (($bookNumber - 1) * max(1, $perBook)) + 1;
        $to = $bookNumber * max(1, $perBook);
        if ($totalParticipations > 0) {
            $to = min($to, $totalParticipations);
        }
        $to = max($from, $to);

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

        $escapedLabel = htmlspecialchars($tacoLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        // Letra legible y completa; padding inferior extra centra mejor en DomPDF
        $buildTacoLabelHtml = function (int $fontPx) use ($escapedLabel): string {
            $fontPx = max(13, min(24, $fontPx));

            return '<table style="width:100%;height:100%;border-collapse:collapse;border:0;margin:0;padding:0;">'
                .'<tr><td style="text-align:center;vertical-align:middle;font-size:'.$fontPx.'px;font-weight:bold;line-height:1;padding:0 6px 4px 6px;margin:0;border:0;font-family:DejaVu Sans, sans-serif;white-space:nowrap;">'
                .$escapedLabel
                .'</td></tr></table>';
        };

        $estimateBoxHeightPx = function () use ($coverHtml): float {
            if (preg_match('/(?:format-box|containment-wrapper)[^>]*style=(["\'])(.*?)\1/is', $coverHtml, $box)
                && preg_match('/\bheight\s*:\s*([\d.]+)\s*(px|mm)/i', $box[2], $bh)) {
                $h = (float) $bh[1];

                return strtolower($bh[2]) === 'mm' ? $h * 3.779527559 : $h;
            }

            return 350.0;
        };

        $estimateBoxWidthPx = function () use ($coverHtml): float {
            if (preg_match('/(?:format-box|containment-wrapper)[^>]*style=(["\'])(.*?)\1/is', $coverHtml, $box)
                && preg_match('/\bwidth\s*:\s*([\d.]+)\s*(px|mm)/i', $box[2], $bw)) {
                $w = (float) $bw[1];

                return strtolower($bw[2]) === 'mm' ? $w * 3.779527559 : $w;
            }

            return 750.0;
        };

        $resolveTacoFontPx = function (string $attrs) use ($estimateBoxHeightPx, $estimateBoxWidthPx, $tacoLabel): int {
            $boxH = $estimateBoxHeightPx();
            $boxW = $estimateBoxWidthPx();
            $barH = 36.0;
            $barW = max(120.0, $boxW - 80.0);

            if (preg_match('/\bheight\s*:\s*([\d.]+)\s*px/i', $attrs, $hm)) {
                $barH = (float) $hm[1];
            } elseif (preg_match('/\bheight\s*:\s*([\d.]+)\s*%/i', $attrs, $hm)) {
                $barH = $boxH * ((float) $hm[1] / 100);
            } elseif (preg_match('/\binset\s*:\s*([\d.]+)px\s+[\d.]+px\s+([\d.]+)px/i', $attrs, $im)) {
                $barH = max(12.0, $boxH - (float) $im[1] - (float) $im[2]);
            }

            if (preg_match('/\bwidth\s*:\s*([\d.]+)\s*px/i', $attrs, $wm)) {
                $barW = (float) $wm[1];
            } elseif (preg_match('/\bwidth\s*:\s*calc\(\s*100%\s*-\s*([\d.]+)px\s*\)/i', $attrs, $wm)) {
                $barW = max(120.0, $boxW - (float) $wm[1]);
            }

            // ~58% del alto; limitar también por longitud para no cortar el texto
            $byHeight = (int) round($barH * 0.58);
            $chars = max(1, mb_strlen($tacoLabel));
            $byWidth = (int) floor(($barW * 0.94) / ($chars * 0.55));

            return max(13, min(22, $byHeight, $byWidth));
        };

        $labelHtml = $buildTacoLabelHtml(18);

        // Normalizar marcadores escapados / variantes antes de sustituir
        $coverHtml = str_ireplace(
            [
                '&#123;&#123;taco_label&#125;&#125;',
                '&#123;&#123; taco_label &#125;&#125;',
                '&lcub;&lcub;taco_label&rcub;&rcub;',
                '@{{taco_label}}',
                '{{ taco_label }}',
                '{{taco_label}}',
                '__TACO_LABEL__',
            ],
            '%%TACO_LABEL%%',
            $coverHtml
        );
        $coverHtml = preg_replace('/\{\{\s*taco[_\-\s]*label\s*\}\}/iu', '%%TACO_LABEL%%', $coverHtml) ?? $coverHtml;

        // Rellenar barras .context (comillas simples/dobles, class en cualquier orden)
        $contextFilled = false;
        if (preg_match_all('/<div([^>]*class=(["\'])[^"\']*\bcontext\b[^"\']*\2[^>]*)>\s*<span([^>]*)>(.*?)<\/span>\s*<\/div>/is', $coverHtml, $allCtx, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            $targetIndex = 0;
            foreach ($allCtx as $i => $match) {
                $chunk = $match[0][0];
                if (preg_match('/\bbottom\s*:/i', $chunk) || preg_match('/\binset\s*:/i', $chunk) || stripos($chunk, '%%TACO_LABEL%%') !== false || stripos($chunk, 'taco_label') !== false) {
                    $targetIndex = $i;
                    break;
                }
                $targetIndex = $i;
            }

            $full = $allCtx[$targetIndex][0][0];
            $pos = $allCtx[$targetIndex][0][1];
            $attrs = $allCtx[$targetIndex][1][0];
            $spanAttrs = $allCtx[$targetIndex][3][0];

            $labelHtml = $buildTacoLabelHtml($resolveTacoFontPx($attrs));

            // Contenedor a altura completa para centrar el texto del taco en DomPDF
            if (! preg_match('/\bstyle=/i', $spanAttrs)) {
                $spanAttrs .= ' style="display:block;height:100%;padding:0;margin:0;text-align:center;"';
            } else {
                $spanAttrs = preg_replace(
                    '/style=(["\'])(.*?)(\1)/is',
                    'style=$1display:block;height:100%;padding:0;margin:0;text-align:center;$2$1',
                    $spanAttrs,
                    1
                );
            }
            $filled = '<div'.$attrs.'><span'.$spanAttrs.'>'.$labelHtml.'</span></div>';
            $coverHtml = substr($coverHtml, 0, $pos).$filled.substr($coverHtml, $pos + strlen($full));
            $contextFilled = true;
        }

        if (! $contextFilled) {
            $contextDiv = '<div class="elements context" style="width:calc(100% - 60px);border-radius:10px;height:12%;position:absolute;bottom:8px;left:0;right:0;margin:auto;background-color:#dfdfdf;border:2px solid #333;overflow:hidden;z-index:6;">'
                .'<span style="display:block;height:100%;padding:0;margin:0;">'.$labelHtml.'</span></div>';
            if (preg_match('/<div[^>]*containment-wrapper[^>]*>/i', $coverHtml)) {
                $coverHtml = preg_replace(
                    '/(<div[^>]*containment-wrapper[^>]*>)/i',
                    '$1'.$contextDiv,
                    $coverHtml,
                    1
                ) ?? $coverHtml;
            } else {
                $coverHtml = preg_replace('/(<div[^>]*format-box[^>]*>)/i', '$1'.$contextDiv, $coverHtml, 1) ?? $coverHtml;
            }
        }

        $coverHtml = str_replace('%%TACO_LABEL%%', $escapedLabel, $coverHtml);
        $coverHtml = preg_replace('/\{\{\s*taco_number\s*\}\}/i', (string) $bookNumber, $coverHtml) ?? $coverHtml;
        $coverHtml = preg_replace('/\{\{\s*taco_total\s*\}\}/i', (string) max(1, $totalBooks), $coverHtml) ?? $coverHtml;
        $coverHtml = preg_replace('/\{\{\s*participation_from\s*\}\}/i', (string) $from, $coverHtml) ?? $coverHtml;
        $coverHtml = preg_replace('/\{\{\s*participation_to\s*\}\}/i', (string) $to, $coverHtml) ?? $coverHtml;

        // Por si quedó el marcador partido por etiquetas HTML
        if (stripos($coverHtml, 'taco_label') !== false || stripos($coverHtml, '%%TACO_LABEL%%') !== false) {
            $coverHtml = str_ireplace('%%TACO_LABEL%%', $escapedLabel, $coverHtml);
            $coverHtml = preg_replace('/\{\{\s*taco[_\-\s]*label\s*\}\}/iu', $escapedLabel, $coverHtml) ?? $coverHtml;
            $coverHtml = preg_replace('/__\s*TACO_LABEL\s*__/i', $escapedLabel, $coverHtml) ?? $coverHtml;
            // Último recurso: token suelto (sin romper clases tipo cover-taco-qr)
            $coverHtml = preg_replace('/(?<![\w-])taco_label(?![\w-])/i', $escapedLabel, $coverHtml) ?? $coverHtml;
        }

        // Si el QR tiene top+left (posición del editor), quitar bottom/right/inset residuales
        // del placeholder para que DomPDF no lo deje anclado abajo a la derecha.
        $coverHtml = preg_replace_callback(
            '/(<div[^>]*class=(["\'])[^"\']*\bqr\b[^"\']*\2[^>]*?)style=(["\'])(.*?)(\3)/i',
            function ($m) {
                $style = $m[4];
                $hasTopLeft = preg_match('/\btop\s*:/i', $style) && preg_match('/\bleft\s*:/i', $style);
                if (! $hasTopLeft) {
                    return $m[0];
                }
                $style = preg_replace('/\b(bottom|right|inset)\s*:[^;]+;?/i', '', $style);
                $style = trim(preg_replace('/;+/', ';', $style), '; ');

                return $m[1].'style='.$m[3].$style.$m[3];
            },
            $coverHtml,
            1
        ) ?? $coverHtml;

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
    public function ensureAbsoluteUrlsInHtml(string $html): string
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
        app(DesignApprovalService::class)->markParticipationExportLock($design);

        $reservation_numbers = $set && $set->reserve ? $set->reserve->reservation_numbers : [];
        $html = $this->ensureAbsoluteUrlsInHtml($design->participation_html ?? '');
        $html = $this->insetBackgroundWithinMargins(
            $html,
            (float) ($design->identation ?? 2.5),
            'containment-wrapper2',
            'design-participation-bg'
        );

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
        $html = $this->insetBackgroundWithinMargins(
            $html,
            (float) ($design->identation ?? 2.5),
            'containment-wrapper2',
            'design-participation-bg'
        );

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
        session([
            'design_entity_id' => $format->entity_id,
            'design_lottery_id' => $format->lottery_id,
            'design_set_id' => $format->set_id,
        ]);
        $approvalService = app(DesignApprovalService::class);
        if ($approvalService->isLockedAfterParticipationExport($format)) {
            return redirect()->route('design.summary', $format->id)
                ->with('warning', 'El diseño quedó bloqueado tras descargar el PDF de participaciones. Puede consultarlo desde el resumen, sin editar.');
        }
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
        $canDownloadPendingSample = app(DesignApprovalService::class)
            ->canDownloadPendingParticipationSample(auth()->user(), $design);
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
        $exportLocked = $approvalService->isLockedAfterParticipationExport($design);
        $canPreviewDesign = $hasDesignContent;
        $entityFeeDue = app(ManagementFeeService::class)->entityOwesManagementFee($design);
        $summaryBlockMessage = $approvalService->blockMessage($design);
        $summaryStatus = $this->buildDesignSummaryStatus(
            $design,
            auth()->user(),
            $awaitingEntityFeeBeforeDesign,
            $hasDesignContent,
            $managementFee,
            $entityFeeDue,
            $blocksQrExport,
            $summaryBlockMessage
        );
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
            'canDownloadPendingSample',
            'canSendToPrint',
            'sendToPrintBlockReason',
            'canOpenEditor',
            'exportLocked',
            'canPreviewDesign',
            'entityFeeDue',
            'summaryStatus',
            'summaryBlockMessage',
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
            $message = app(DesignApprovalService::class)->isPrintShopDesign($design)
                ? 'Diseño aprobado correctamente. La imprenta podrá continuar con la impresión.'
                : 'Diseño aprobado correctamente. La administración podrá continuar con el proceso.';

            return redirect()->route('design.index')
                ->with('success', $message);
        }

        return redirect()->route('design.summary', $design->id)
            ->with('success', 'Diseño aprobado. Puede procederse al pago de la cuota de gestión.');
    }

    public function rejectDesign(Request $request, $id)
    {
        $design = DesignFormat::with('set')->findOrFail($id);
        $reason = $request->input('reason');
        app(DesignApprovalService::class)->reject($design, auth()->user(), is_string($reason) ? $reason : null);

        $message = app(DesignApprovalService::class)->isPrintShopDesign($design)
            ? 'Diseño rechazado. La imprenta deberá corregirlo y reenviarlo a la entidad.'
            : 'Diseño rechazado. La administración deberá corregirlo y reenviarlo.';

        return redirect()->route('design.approvals.index')
            ->with('success', $message);
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

    public function openChooseType(Set $set)
    {
        if ($redirect = $this->prepareDesignSessionForSet($set)) {
            return $redirect;
        }

        $user = auth()->user();
        $approvalService = app(DesignApprovalService::class);
        $feeService = app(ManagementFeeService::class);

        $design = DesignFormat::query()
            ->where('set_id', $set->id)
            ->orderByDesc('id')
            ->first();

        if (! $design) {
            return redirect()->route('design.showChooseType');
        }

        if ($feeService->blocksAdminDesignUntilEntityPays($set)) {
            if ($approvalService->isAdministrationSideUser($user)) {
                return redirect()->route('design.summary', $design->id)
                    ->with('warning', 'La entidad debe pagar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
            }

            return redirect()->route('design.managementFee.pay', $set->id)
                ->with('info', 'Debe confirmar la cuota de gestión PARTILOT antes de acceder al editor de diseño.');
        }

        $setLock = $this->getSetDesignLockContext($set);
        $printOrderLock = $this->getPrintOrderLockContext($design->id);

        if ($approvalService->canOpenDesignEditor($user, $design, $setLock['locked'], $printOrderLock['locked'])) {
            if (! $approvalService->designHasParticipationContent($design)) {
                return $this->redirectToFormatWizardForEmptyDesign($design);
            }

            return redirect()->route('design.editFormat', $design->id);
        }

        if ($user->isEntity()
            && ! $approvalService->userActsAsAdministration($user)
            && ! $approvalService->isVisibleToEntityViewer($design)) {
            return redirect()->route('design.index')
                ->with('info', 'La administración está preparando el diseño de este set.');
        }

        if ($approvalService->canReviewApproval($user, $design)) {
            return redirect()->route('design.approval.review', $design->id);
        }

        return redirect()->route('design.summary', $design->id);
    }

    public function openEditor(Set $set)
    {
        if ($redirect = $this->prepareDesignSessionForSet($set)) {
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

        $request = Request::create(route('design.format'), 'POST', [
            'set_id' => $set->id,
            'new_design' => 1,
        ]);
        $request->setLaravelSession(session());

        return $this->format($request);
    }

    private function prepareDesignSessionForSet(Set $set): ?\Illuminate\Http\RedirectResponse
    {
        if (! auth()->user()->canAccessEntity((int) $set->entity_id)) {
            abort(403, 'No tienes permisos para esta operación.');
        }

        $set->load(['reserve', 'entity']);
        if ($redirect = $this->redirectIfEntityCannotDesign($set->entity)) {
            return $redirect;
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

        return null;
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
            'back_mode' => $design->hasBackDesign() ? 'bw' : 'none',
            'print_configuration_id' => (int) $selectedPrintShop->id,
        ];
        $quote = $this->calculatePrintOrderQuote($design->set, $defaults, chargeDesignFee: false, design: $design);
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
        ))->with('includeBackInPrint', $design->hasBackDesign());
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

        $data = $request->validate($this->printOrderSubmissionRules($design));
        $cfg = PrintConfiguration::resolveDefault();
        $data['print_configuration_id'] = $cfg->id;
        if (! $design->hasBackDesign()) {
            $data['back_mode'] = 'none';
        }
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false, design: $design);
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

        $data = $request->validate($this->printOrderSubmissionRules($design));
        $cfg = PrintConfiguration::resolveDefault();
        $data['print_configuration_id'] = $cfg->id;
        if (! $design->hasBackDesign()) {
            $data['back_mode'] = 'none';
        }
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false, design: $design);
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

        $data = $request->validate(array_merge($this->printOrderSubmissionRules($design), [
            'payment_method' => 'nullable|in:stripe,remittance',
            'stripe_payment_intent_id' => 'nullable|required_if:payment_method,stripe|string',
        ]), [
            'stripe_payment_intent_id.required_if' => 'No se encontró el pago de Stripe confirmado.',
        ]);
        $data['notes'] = $this->sanitizePrintOrderNotes($data['notes'] ?? null);
        if (! $design->hasBackDesign()) {
            $data['back_mode'] = 'none';
        }

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
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false, design: $design);
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
        if (! $design->hasBackDesign()) {
            $data['back_mode'] = 'none';
        }
        $quote = $this->calculatePrintOrderQuote($design->set, $data, chargeDesignFee: false, design: $design);
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
            $design = DesignFormat::create(array_merge(DesignFormat::defaultLayoutAttributes(), [
                'entity_id' => (int) $invitation->entity_id,
                'lottery_id' => (int) $invitation->lottery_id,
                'set_id' => (int) $invitation->set_id,
                'output' => [
                    'participations_per_book' => (int) ($invitation->participations_per_book ?? 50),
                ],
            ]));
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
    private function printOrderSubmissionRules(?DesignFormat $design = null): array
    {
        $backModeRule = ($design !== null && ! $design->hasBackDesign())
            ? 'nullable|string|in:none,bw,color'
            : 'required|string|in:bw,color';

        return [
            'print_configuration_id' => ['nullable', 'integer', 'exists:print_configurations,id'],
            'print_size' => 'required|string|in:a3_6,a3_8,custom',
            'participations_per_book' => 'required|integer|min:1|max:1000',
            'back_mode' => $backModeRule,
            'notes' => 'nullable|string|max:4000',
        ];
    }

    private function sanitizePrintOrderNotes(?string $notes): ?string
    {
        return HtmlText::sanitizePlainText($notes);
    }

    private function buildDesignSummaryStatus(
        DesignFormat $design,
        User $user,
        bool $awaitingEntityFeeBeforeDesign,
        bool $hasDesignContent,
        ?array $managementFee,
        bool $entityFeeDue,
        bool $blocksQrExport,
        string $summaryBlockMessage
    ): array {
        $approvalService = app(DesignApprovalService::class);
        $adminUser = $approvalService->userActsAsAdministration($user);
        $entityViewer = $user->isEntity()
            && ! $user->isAdministration()
            && ! $adminUser;
        $amountLabel = isset($managementFee['amount'])
            ? number_format((float) $managementFee['amount'], 2, ',', '.').'€'
            : null;

        if ($awaitingEntityFeeBeforeDesign || ($entityFeeDue && ! $hasDesignContent)) {
            if ($entityViewer) {
                $message = ! empty($managementFee['payment_before_editor'])
                    ? 'Debe abonar la cuota de gestión PARTILOT'
                        .($amountLabel ? " ({$amountLabel})" : '')
                        .' antes de acceder al editor y continuar con el diseño.'
                    : 'Debe abonar la cuota de gestión PARTILOT'
                        .($amountLabel ? " ({$amountLabel})" : '')
                        .' para que la administración pueda continuar con el diseño de este set.';
            } else {
                $message = 'La entidad debe pagar la cuota de gestión PARTILOT'
                    .($amountLabel ? " ({$amountLabel})" : '')
                    .' para que pueda continuar editando el diseño de participación. '
                    .'Hasta entonces no es posible editar el diseño ni generar PDFs con códigos QR. '
                    .'El pago solo puede realizarlo la entidad desde su panel de Diseño e Impresión.';
            }

            return [
                'tone' => 'warning',
                'title' => 'Cuota de gestión pendiente (entidad)',
                'message' => $message,
            ];
        }

        if (! $hasDesignContent) {
            return [
                'tone' => 'warning',
                'title' => 'Diseño pendiente de crear',
                'message' => 'El diseño de participación aún no tiene contenido. Debe completarse en el editor antes de generar PDFs o enviar a imprenta.',
            ];
        }

        if ($blocksQrExport) {
            return [
                'tone' => 'warning',
                'title' => 'Acción pendiente',
                'message' => $summaryBlockMessage,
            ];
        }

        return [
            'tone' => 'success',
            'title' => 'Diseño guardado',
            'message' => 'La configuración del diseño se ha guardado correctamente. Puede descargar los PDF generados o volver al listado de diseños.',
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
    private function calculatePrintOrderQuote(Set $set, array $input, bool $chargeDesignFee = false, ?DesignFormat $design = null): array
    {
        if ($design !== null && ! $design->hasBackDesign()) {
            $input['include_back'] = false;
            $input['back_mode'] = 'none';
        }

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
                if (array_key_exists('cut_lines', $data)) {
                    $format->cut_lines = $data['cut_lines'];
                }
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
                if (isset($data['margins'])) {
                    $format->margins = $data['margins'];
                }
                if (isset($data['backgrounds'])) {
                    $format->backgrounds = $data['backgrounds'];
                }
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

                // Mantener blocks sincronizado con lo guardado (si no, al recargar se restaura el HTML antiguo)
                $blocks = is_array($format->blocks) ? $format->blocks : [];
                $blocks['participation_html'] = $format->participation_html ?? '';
                $blocks['cover_html'] = $format->cover_html ?? '';
                $blocks['back_html'] = $format->back_html ?? '';
                if (isset($data['backgrounds'])) {
                    $blocks['backgrounds'] = $data['backgrounds'];
                }
                if (isset($data['output'])) {
                    $blocks['output'] = is_array($format->output) ? $format->output : ($data['output'] ?? []);
                }
                if (isset($data['margins'])) {
                    $blocks['margins'] = $data['margins'];
                }
                $format->blocks = $blocks;

                $format->save();

                if (isset($data['from_step_5']) && $data['from_step_5'] === true) {
                    app(DesignApprovalService::class)->assignDesignerTypeIfMissing($format, $this->resolveDesignSaveUser());
                    $this->autoSubmitPrintShopDesignForEntityApproval($format);
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
        $chunk_size = max(50, (int) config('pdf_optimization.chunk_size', 250)); // Procesar por lotes
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
            $pdf = Pdf::loadView('design.pdf_participation', $this->participationPdfViewData(
                $design,
                $pages,
                $participation_html,
                $qrCodes
            ))->setPaper($page, $pdfOrientation);
            $this->applyDompdfOptions($pdf);

            // Guardar en archivo temporal

            $temp_file = storage_path('app/temp_pdf_' . $chunk_start . '.pdf');
            $pdf->save($temp_file);
            $temp_files[] = $temp_file;
        }
        
        // Combinar PDFs usando una librería como TCPDF o FPDI
        $binary = FpdiPdfMerge::mergeTemporaryFiles($temp_files, false);

        app(DesignApprovalService::class)->markParticipationExportLock($design);

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

        $docsOpts = $this->resolvePrintDocumentsOptions($request, $design);

        $job_id = 'pdf_part_'.$id.'_'.$from.'_'.$to.'_'.time();
        \App\Support\PdfJobStatus::markProcessing($job_id);
        \App\Support\PdfJobStatus::touchPresence($job_id);

        try {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', (string) config('pdf_optimization.memory_limit', '2048M'));

            $artifact = $this->writeParticipationExportArtifact(
                $design,
                $from,
                $to,
                $job_id,
                $docsOpts['documents_mode'],
                $docsOpts['pages_per_document']
            );
            $artifact['download_name'] = $this->resolveExportDownloadName(
                $request,
                $design,
                'participaciones',
                (bool) ($artifact['is_zip'] ?? false)
            );

            clearstatcache(true, $artifact['path']);
            if (! is_file($artifact['path']) || filesize($artifact['path']) < 1) {
                throw new \RuntimeException('El PDF se generó pero el archivo no está disponible en disco.');
            }

            \App\Support\GeneratedPdfCatalog::writeMeta(
                $job_id,
                $artifact['download_name'],
                (int) $id
            );
            \App\Support\PdfJobStatus::markCompleted($job_id);
            app(DesignApprovalService::class)->markParticipationExportLock($design);

            $notifyEmail = auth()->user()?->email;
            if (
                config('pdf_optimization.send_email', false)
                && is_string($notifyEmail)
                && $notifyEmail !== ''
            ) {
                try {
                    \Illuminate\Support\Facades\Mail::to($notifyEmail)->send(new \App\Mail\DesignPdfReadyMail(
                        route('design.downloadPdf', $job_id),
                        $artifact['is_zip'] ? 'Participaciones ZIP' : 'Participaciones PDF',
                        (int) $id
                    ));
                    \App\Support\PdfJobStatus::markEmailSent($job_id);
                } catch (\Throwable $mailEx) {
                    Log::warning('exportParticipationPdfAsync email failed', [
                        'job_id' => $job_id,
                        'message' => $mailEx->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'status' => 'completed',
                'job_id' => $job_id,
                'download_url' => route('design.downloadPdf', $job_id),
                'message' => $artifact['is_zip']
                    ? 'ZIP generado. La descarga debería iniciar automáticamente.'
                    : 'PDF generado. La descarga debería iniciar automáticamente.',
                'check_url' => route('design.checkPdfStatus', $job_id),
            ]);
        } catch (\Throwable $e) {
            \App\Support\PdfJobStatus::markFailed($job_id, $e->getMessage());
            Log::error('exportParticipationPdfAsync failed', [
                'job_id' => $job_id,
                'design_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'failed',
                'job_id' => $job_id,
                'message' => 'No se pudo generar el PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Empaquetado de impresión (1 PDF o ZIP). Prioridad: query/body de la petición;
     * si no vienen, se usan los valores guardados en el diseño como valor por defecto.
     * Cuando el modal envía los params, se persisten en design.output (sin reaprobar ni regenerar participaciones).
     *
     * @return array{documents_mode: string, pages_per_document: int}
     */
    private function resolvePrintDocumentsOptions(Request $request, DesignFormat $design): array
    {
        $output = is_array($design->output) ? $design->output : [];
        $hasExplicitMode = $request->query('documents_mode') !== null
            || $request->input('documents_mode') !== null;
        $hasExplicitPages = $request->query('pages_per_document') !== null
            || $request->input('pages_per_document') !== null;

        $modeRaw = $request->query(
            'documents_mode',
            $request->input('documents_mode', $output['documents_mode'] ?? '1')
        );
        $pagesRaw = $request->query(
            'pages_per_document',
            $request->input('pages_per_document', $output['pages_per_document'] ?? 150)
        );

        $opts = [
            'documents_mode' => ((string) $modeRaw === '2') ? '2' : '1',
            'pages_per_document' => max(1, (int) $pagesRaw),
        ];

        if ($hasExplicitMode || $hasExplicitPages) {
            $this->persistPrintDocumentsOptions($design, $opts);
        }

        return $opts;
    }

    /**
     * Nombre de descarga editable: design_name + sufijo (- participaciones / - portadas / - traseras).
     * Acepta `download_name` / `download_filename` en la query (sin o con extensión).
     */
    private function resolveExportDownloadName(
        Request $request,
        DesignFormat $design,
        string $kindSuffix,
        bool $isZip = false
    ): string {
        $requested = $request->query('download_name', $request->input('download_name'));
        if (! is_string($requested) || trim($requested) === '') {
            $requested = $request->query('download_filename', $request->input('download_filename'));
        }

        $base = is_string($requested) ? trim($requested) : '';
        if ($base === '') {
            $designLabel = trim((string) ($design->design_name ?? ''));
            if ($designLabel === '') {
                $designLabel = 'Diseño '.$design->id;
            }
            $base = $designLabel.' - '.$kindSuffix;
        }

        $base = preg_replace('/\.(pdf|zip)$/i', '', $base) ?? $base;
        $base = $this->sanitizeExportDownloadBasename($base);
        if ($base === '') {
            $base = 'diseno-'.$design->id.'-'.$kindSuffix;
        }

        return $base.($isZip ? '.zip' : '.pdf');
    }

    private function sanitizeExportDownloadBasename(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $name = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $name);
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name, " \t.-");
        if (function_exists('mb_substr')) {
            $name = mb_substr($name, 0, 160, 'UTF-8');
        } else {
            $name = substr($name, 0, 160);
        }

        return trim($name, " \t.-");
    }

    /**
     * Guarda documents_mode / pages_per_document en el diseño para la próxima impresión.
     *
     * @param  array{documents_mode: string, pages_per_document: int}  $opts
     */
    private function persistPrintDocumentsOptions(DesignFormat $design, array $opts): void
    {
        $output = is_array($design->output) ? $design->output : [];
        $mode = (string) ($opts['documents_mode'] ?? '1');
        $pages = max(1, (int) ($opts['pages_per_document'] ?? 150));

        if (
            (string) ($output['documents_mode'] ?? '1') === $mode
            && (int) ($output['pages_per_document'] ?? 150) === $pages
        ) {
            return;
        }

        $output['documents_mode'] = $mode;
        $output['pages_per_document'] = $pages;
        $design->output = $output;
        // saveQuietly: no dispara updateParticipations ni otros observers por un cambio de empaquetado.
        $design->saveQuietly();
    }

    /**
     * Rangos [from,to] según documents_mode y páginas por documento.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function participationPdfDocumentRanges(
        DesignFormat $design,
        int $from,
        int $to,
        ?string $documentsMode = null,
        ?int $pagesPerDocument = null
    ): array {
        if ($from > $to) {
            return [];
        }

        $output = is_array($design->output) ? $design->output : [];
        $mode = (string) ($documentsMode ?? ($output['documents_mode'] ?? '1'));
        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $perPage = $rows * $cols;
        $pagesPerDoc = max(1, (int) ($pagesPerDocument ?? ($output['pages_per_document'] ?? 150)));
        $ticketsPerDoc = $pagesPerDoc * $perPage;
        $total = $to - $from + 1;

        if ($mode !== '2' || $ticketsPerDoc <= 0 || $total <= $ticketsPerDoc) {
            return [[$from, $to]];
        }

        $ranges = [];
        for ($start = $from; $start <= $to; $start += $ticketsPerDoc) {
            $ranges[] = [$start, min($to, $start + $ticketsPerDoc - 1)];
        }

        return $ranges;
    }

    /**
     * Genera un PDF o un ZIP de partes en generated_pdfs/{jobId}.*
     * Público para jobs en cola.
     *
     * @return array{path: string, download_name: string, is_zip: bool}
     */
    public function writeParticipationExportArtifact(
        DesignFormat $design,
        int $from,
        int $to,
        string $jobId,
        ?string $documentsMode = null,
        ?int $pagesPerDocument = null
    ): array {
        $ranges = $this->participationPdfDocumentRanges(
            $design,
            $from,
            $to,
            $documentsMode,
            $pagesPerDocument
        );
        if ($ranges === []) {
            throw new \InvalidArgumentException('No hay participaciones en el rango solicitado.');
        }

        $dir = storage_path('app/generated_pdfs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $designId = (int) $design->id;

        if (count($ranges) === 1) {
            $path = $dir.DIRECTORY_SEPARATOR.$jobId.'.pdf';
            $this->writeParticipationPdfToFile($design, $ranges[0][0], $ranges[0][1], $path);

            return [
                'path' => $path,
                'download_name' => 'participacion-diseno-'.$designId.'.pdf',
                'is_zip' => false,
            ];
        }

        $zipPath = $dir.DIRECTORY_SEPARATOR.$jobId.'.zip';
        $tempParts = [];
        $zipEntries = [];

        try {
            foreach ($ranges as $i => [$rangeFrom, $rangeTo]) {
                $partIndex = $i + 1;
                $partName = sprintf('participacion-diseno-%d-parte-%02d.pdf', $designId, $partIndex);
                $partPath = $dir.DIRECTORY_SEPARATOR.$jobId.'_part_'.$partIndex.'.pdf';
                $this->writeParticipationPdfToFile($design, $rangeFrom, $rangeTo, $partPath);
                $tempParts[] = $partPath;
                $zipEntries[$partName] = $partPath;
            }

            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                    throw new \RuntimeException('No se pudo crear el archivo ZIP.');
                }
                foreach ($zipEntries as $partName => $partPath) {
                    if (! $zip->addFile($partPath, $partName)) {
                        $zip->close();
                        throw new \RuntimeException('No se pudo añadir '.$partName.' al ZIP.');
                    }
                }
                $zip->close();
            } else {
                \App\Support\SimpleZipStore::create($zipPath, $zipEntries);
            }
        } catch (\Throwable $e) {
            foreach ($tempParts as $partPath) {
                @unlink($partPath);
            }
            @unlink($zipPath);
            throw $e;
        }

        foreach ($tempParts as $partPath) {
            @unlink($partPath);
        }

        return [
            'path' => $zipPath,
            'download_name' => 'participacion-diseno-'.$designId.'.zip',
            'is_zip' => true,
        ];
    }

    /**
     * @return array{path: string, download_name: string, is_zip: bool}
     */
    public function writeCoverExportArtifact(
        DesignFormat $design,
        string $jobId,
        string $documentsMode = '1',
        int $pagesPerDocument = 150
    ): array {
        $designId = (int) $design->id;
        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $itemsPerDoc = max(1, $pagesPerDocument * $rows * $cols);

        $totalItems = config('pdf_optimization.use_stamp_template', false)
            ? count($this->buildCoverStampBookItems($design))
            : count($this->buildCoverHtmlItems($design));

        $dir = storage_path('app/generated_pdfs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($documentsMode !== '2' || $totalItems <= $itemsPerDoc) {
            $path = $dir.DIRECTORY_SEPARATOR.$jobId.'.pdf';
            $this->writeCoverPdfToFile($design, $path);

            return [
                'path' => $path,
                'download_name' => 'portadas-diseno-'.$designId.'.pdf',
                'is_zip' => false,
            ];
        }

        $zipPath = $dir.DIRECTORY_SEPARATOR.$jobId.'.zip';
        $tempParts = [];
        $zipEntries = [];

        try {
            $partIndex = 0;
            for ($start = 1; $start <= $totalItems; $start += $itemsPerDoc) {
                $partIndex++;
                $end = min($totalItems, $start + $itemsPerDoc - 1);
                $partName = sprintf('portadas-diseno-%d-parte-%02d.pdf', $designId, $partIndex);
                $partPath = $dir.DIRECTORY_SEPARATOR.$jobId.'_part_'.$partIndex.'.pdf';
                $this->writeCoverPdfToFile($design, $partPath, $start, $end);
                $tempParts[] = $partPath;
                $zipEntries[$partName] = $partPath;
            }
            $this->storePdfPartsZip($zipPath, $zipEntries);
        } catch (\Throwable $e) {
            foreach ($tempParts as $partPath) {
                @unlink($partPath);
            }
            @unlink($zipPath);
            throw $e;
        }

        foreach ($tempParts as $partPath) {
            @unlink($partPath);
        }

        return [
            'path' => $zipPath,
            'download_name' => 'portadas-diseno-'.$designId.'.zip',
            'is_zip' => true,
        ];
    }

    /**
     * @return array{path: string, download_name: string, is_zip: bool}
     */
    public function writeBackExportArtifact(
        DesignFormat $design,
        string $jobId,
        string $copies = 'all',
        ?int $exactCount = null,
        string $documentsMode = '1',
        int $pagesPerDocument = 150
    ): array {
        $designId = (int) $design->id;
        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $itemsPerDoc = max(1, $pagesPerDocument * $rows * $cols);

        $copies = $this->normalizeBackPdfCopies($copies);
        if ($exactCount !== null) {
            $total = max(1, min(100000, (int) $exactCount));
        } elseif ($copies === 'one') {
            $total = 1;
        } else {
            $set = $design->set_id
                ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id)
                : null;
            $total = (int) ($set->total_participations ?? 0);
            if ($total <= 0 && $set && $set->tickets) {
                $tickets = is_array($set->tickets) ? $set->tickets : [];
                $total = count($tickets);
            }
            $total = max(1, $total);
        }

        $dir = storage_path('app/generated_pdfs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if ($documentsMode !== '2' || $total <= $itemsPerDoc) {
            $path = $dir.DIRECTORY_SEPARATOR.$jobId.'.pdf';
            $this->writeBackPdfToFile($design, $path, $copies, $total);

            return [
                'path' => $path,
                'download_name' => 'traseras-diseno-'.$designId.'.pdf',
                'is_zip' => false,
            ];
        }

        $zipPath = $dir.DIRECTORY_SEPARATOR.$jobId.'.zip';
        $tempParts = [];
        $zipEntries = [];

        try {
            $partIndex = 0;
            for ($start = 0; $start < $total; $start += $itemsPerDoc) {
                $partIndex++;
                $chunk = min($itemsPerDoc, $total - $start);
                $partName = sprintf('traseras-diseno-%d-parte-%02d.pdf', $designId, $partIndex);
                $partPath = $dir.DIRECTORY_SEPARATOR.$jobId.'_part_'.$partIndex.'.pdf';
                $this->writeBackPdfToFile($design, $partPath, 'all', $chunk);
                $tempParts[] = $partPath;
                $zipEntries[$partName] = $partPath;
            }
            $this->storePdfPartsZip($zipPath, $zipEntries);
        } catch (\Throwable $e) {
            foreach ($tempParts as $partPath) {
                @unlink($partPath);
            }
            @unlink($zipPath);
            throw $e;
        }

        foreach ($tempParts as $partPath) {
            @unlink($partPath);
        }

        return [
            'path' => $zipPath,
            'download_name' => 'traseras-diseno-'.$designId.'.zip',
            'is_zip' => true,
        ];
    }

    /**
     * @param  array<string, string>  $zipEntries  entryName => absolutePath
     */
    private function storePdfPartsZip(string $zipPath, array $zipEntries): void
    {
        if (class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('No se pudo crear el archivo ZIP.');
            }
            foreach ($zipEntries as $partName => $partPath) {
                if (! $zip->addFile($partPath, $partName)) {
                    $zip->close();
                    throw new \RuntimeException('No se pudo añadir '.$partName.' al ZIP.');
                }
            }
            $zip->close();

            return;
        }

        \App\Support\SimpleZipStore::create($zipPath, $zipEntries);
    }

    /**
     * Genera el PDF de participaciones en disco (síncrono; usa stamp si está activo).
     */
    public function writeParticipationPdfToFile(DesignFormat $design, int $from, int $to, string $finalPath): void
    {
        $set = $design->set_id ? Set::select('id', 'tickets', 'total_participations')->find($design->set_id) : null;
        $tickets = $set && $set->tickets ? $set->tickets : [];

        $tickets_slice = [];
        if ($from <= $to && $to >= 1) {
            $tickets_slice = array_slice($tickets, $from - 1, max(0, $to - $from + 1));
        }

        $this->writeParticipationTicketsPdfToFile($design, $tickets_slice, $finalPath);
    }

    /**
     * PDF de participaciones a partir de una lista explícita de tickets (p. ej. muestra con refs en ceros).
     *
     * @param  list<array{r?: string, n?: int|string}>  $tickets
     */
    public function writeParticipationTicketsPdfToFile(DesignFormat $design, array $tickets, string $finalPath): void
    {
        $participation_html = $this->prepareParticipationHtmlForPdf(
            $design->participation_html ?? '',
            (float) ($design->identation ?? 2.5)
        );

        if (config('qr_optimization.optimize_images', false)) {
            $participation_html = $this->optimizeParticipationHtml($participation_html, $tickets);
        }

        $uniqueReferences = [];
        foreach ($tickets as $ticket) {
            if (isset($ticket['r']) && ! in_array($ticket['r'], $uniqueReferences, true)) {
                $uniqueReferences[] = $ticket['r'];
            }
        }
        $qrCodes = $this->buildParticipationQrMap($uniqueReferences);

        $dir = dirname($finalPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (config('pdf_optimization.use_stamp_template', false)) {
            $slotsHtml = $this->prepareStampSlotHtml(
                $design->participation_html ?? '',
                (float) ($design->identation ?? 2.5)
            );
            app(\App\Services\ParticipationPdfStampExporter::class)->exportToFile(
                $design,
                $participation_html,
                $tickets,
                $qrCodes,
                $finalPath,
                $slotsHtml
            );

            return;
        }

        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $per_page = $rows * $cols;
        $page = $design->page ?? 'a3';
        $orientation = $design->orientation ?? 'h';
        $pdfOrientation = ($orientation === 'h') ? 'landscape' : 'portrait';
        $total_pages = $per_page > 0 ? (int) ceil(count($tickets) / $per_page) : 0;
        $pages = $this->generatePagesOptimized($tickets, $total_pages, $per_page);

        $pdf = Pdf::loadView('design.pdf_participation', $this->participationPdfViewData(
            $design,
            $pages,
            $participation_html,
            $qrCodes
        ))->setPaper($page, $pdfOrientation);
        $this->applyDompdfOptions($pdf);
        $pdf->save($finalPath);
    }

    /**
     * Muestra de 1 hoja para administración mientras el diseño está pendiente de aprobación.
     * Referencias y QR con ceros (sin datos reales del set).
     */
    public function exportParticipationSamplePdf($id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);

        $approvalService = app(DesignApprovalService::class);
        if (! $approvalService->canDownloadPendingParticipationSample(auth()->user(), $design)) {
            abort(403, 'Solo la administración puede descargar la muestra mientras el diseño no esté aprobado por la entidad.');
        }

        ini_set('max_execution_time', '120');
        ini_set('memory_limit', (string) config('pdf_optimization.memory_limit', '2048M'));

        $rows = max(1, (int) ($design->rows ?? 1));
        $cols = max(1, (int) ($design->cols ?? 1));
        $perPage = $rows * $cols;
        $zeroRef = str_repeat('0', \App\Support\ParticipationTicketReference::LENGTH);
        $tickets = [];
        for ($i = 1; $i <= $perPage; $i++) {
            $tickets[] = [
                'r' => $zeroRef,
                'n' => $i,
            ];
        }

        $tmp = storage_path('app/generated_pdfs/sample_part_'.$id.'_'.uniqid('', true).'.pdf');
        $dir = dirname($tmp);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            $this->writeParticipationTicketsPdfToFile($design, $tickets, $tmp);
            $this->cleanupTempQrCodes();

            return response()->download(
                $tmp,
                'muestra-participaciones-diseno-'.$id.'.pdf'
            )->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            @unlink($tmp);
            Log::error('exportParticipationSamplePdf failed', [
                'design_id' => $id,
                'message' => $e->getMessage(),
            ]);
            abort(500, 'No se pudo generar la muestra: '.$e->getMessage());
        }
    }

    /**
     * Portada + trasera: generación síncrona (sin worker).
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

        $job_id = 'pdf_cover_back_'.$id.'_'.time();
        \App\Support\PdfJobStatus::markProcessing($job_id);
        \App\Support\PdfJobStatus::touchPresence($job_id);

        try {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', (string) config('pdf_optimization.memory_limit', '2048M'));

            $final_path = storage_path('app/generated_pdfs/'.$job_id.'.pdf');
            $dir = dirname($final_path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $this->makeCoverBackPdfFacade($design)->save($final_path);

            \App\Support\GeneratedPdfCatalog::writeMeta(
                $job_id,
                'portada-trasera-diseno-'.$id.'.pdf',
                (int) $id
            );
            \App\Support\PdfJobStatus::markCompleted($job_id);

            $this->maybeSendDesignPdfReadyEmail(
                $job_id,
                'Portada y trasera PDF',
                (int) $id
            );

            return response()->json([
                'status' => 'completed',
                'job_id' => $job_id,
                'download_url' => route('design.downloadPdf', $job_id),
                'message' => 'PDF generado. La descarga debería iniciar automáticamente.',
                'check_url' => route('design.checkPdfStatus', $job_id),
            ]);
        } catch (\Throwable $e) {
            \App\Support\PdfJobStatus::markFailed($job_id, $e->getMessage());
            Log::error('exportCoverBackPdfAsync failed', [
                'job_id' => $job_id,
                'design_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'failed',
                'job_id' => $job_id,
                'message' => 'No se pudo generar el PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verificar el estado de un PDF en procesamiento
     */
    public function checkPdfStatus($job_id)
    {
        \App\Support\PdfJobStatus::touchPresence($job_id);

        $tracked = \App\Support\PdfJobStatus::get($job_id);
        if (($tracked['status'] ?? null) === 'failed') {
            return response()->json([
                'status' => 'failed',
                'message' => $tracked['message'] ?? 'La generación del PDF falló.',
            ]);
        }

        $meta = GeneratedPdfCatalog::readMeta($job_id);
        $file_path = GeneratedPdfCatalog::artifactPath($job_id, (string) ($meta['download_name'] ?? ''));
        
        if (is_file($file_path)) {
            \App\Support\PdfJobStatus::markCompleted($job_id);

            return response()->json([
                'status' => 'completed',
                'download_url' => route('design.downloadPdf', $job_id)
            ]);
        }
        
        return response()->json([
            'status' => 'processing',
            'message' => 'El PDF aún se está generando.',
        ]);
    }

    /**
     * Descargar PDF generado
     */
    public function downloadPdf($job_id)
    {
        $meta = GeneratedPdfCatalog::readMeta($job_id);
        if ($meta === null || ! isset($meta['design_format_id'])) {
            abort(403, 'No se puede descargar este archivo.');
        }

        if (GeneratedPdfCatalog::isExpired($job_id, $meta)) {
            GeneratedPdfCatalog::deleteArtifacts($job_id);
            abort(410, 'El enlace de descarga ha caducado (máximo '.GeneratedPdfCatalog::TTL_DAYS.' días).');
        }

        $downloadName = (string) ($meta['download_name'] ?? '');
        $file_path = null;
        // En Windows el antivirus / indexador a veces bloquea el archivo justo tras escribirlo.
        for ($attempt = 0; $attempt < 8; $attempt++) {
            clearstatcache(true, GeneratedPdfCatalog::artifactPath($job_id, $downloadName));
            $candidate = GeneratedPdfCatalog::artifactPath($job_id, $downloadName);
            if (is_file($candidate) && filesize($candidate) > 0) {
                $file_path = $candidate;
                break;
            }
            usleep(150000);
        }

        if ($file_path === null || ! is_file($file_path)) {
            abort(404, 'PDF no encontrado o el enlace ha caducado.');
        }

        $design = DesignFormat::find($meta['design_format_id']);
        if (! $design || ! auth()->user()?->canExportDesignPdf($design)) {
            abort(403, 'No tienes permisos para descargar este PDF.');
        }

        $downloadName = $meta['download_name'] ?? basename($file_path);

        // Reutilizable: no borrar meta ni archivo al descargar (caduca a los TTL_DAYS).
        return response()->download($file_path, $downloadName);
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

    public function exportCoverPdfAsync(Request $request, $id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);
        if (empty($design->cover_html)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Portada no encontrada',
            ], 404);
        }

        $docsOpts = $this->resolvePrintDocumentsOptions($request, $design);

        $job_id = 'pdf_cover_grid_'.$id.'_'.time();
        \App\Support\PdfJobStatus::markProcessing($job_id);
        \App\Support\PdfJobStatus::touchPresence($job_id);

        try {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', (string) config('pdf_optimization.memory_limit', '2048M'));

            $artifact = $this->writeCoverExportArtifact(
                $design,
                $job_id,
                $docsOpts['documents_mode'],
                $docsOpts['pages_per_document']
            );
            $artifact['download_name'] = $this->resolveExportDownloadName(
                $request,
                $design,
                'portadas',
                (bool) ($artifact['is_zip'] ?? false)
            );

            \App\Support\GeneratedPdfCatalog::writeMeta(
                $job_id,
                $artifact['download_name'],
                (int) $id
            );
            \App\Support\PdfJobStatus::markCompleted($job_id);

            $this->maybeSendDesignPdfReadyEmail(
                $job_id,
                'Portadas PDF',
                (int) $id
            );

            return response()->json([
                'status' => 'completed',
                'job_id' => $job_id,
                'download_url' => route('design.downloadPdf', $job_id),
                'message' => 'PDF generado. La descarga debería iniciar automáticamente.',
                'check_url' => route('design.checkPdfStatus', $job_id),
            ]);
        } catch (\Throwable $e) {
            \App\Support\PdfJobStatus::markFailed($job_id, $e->getMessage());
            Log::error('exportCoverPdfAsync failed', [
                'job_id' => $job_id,
                'design_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'failed',
                'job_id' => $job_id,
                'message' => 'No se pudo generar el PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    public function exportBackPdfAsync(Request $request, $id)
    {
        $design = DesignFormat::findOrFail($id);
        $this->authorizeDesignPdfExport($design);
        if (! $design->hasBackDesign()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Este diseño no incluye trasera (se omitió en el editor).',
            ], 422);
        }

        $copies = $this->normalizeBackPdfCopies($request->query('copies', 'all'));
        $exactCount = $this->parseBackPdfExactCount($request);
        $docsOpts = $this->resolvePrintDocumentsOptions($request, $design);

        $job_id = 'pdf_back_'.$id.'_'.time();

        \App\Support\PdfJobStatus::markProcessing($job_id);
        \App\Support\PdfJobStatus::touchPresence($job_id);

        try {
            ini_set('max_execution_time', '300');
            ini_set('memory_limit', (string) config('pdf_optimization.memory_limit', '2048M'));

            $artifact = $this->writeBackExportArtifact(
                $design,
                $job_id,
                $copies,
                $exactCount,
                $docsOpts['documents_mode'],
                $docsOpts['pages_per_document']
            );
            $artifact['download_name'] = $this->resolveExportDownloadName(
                $request,
                $design,
                'traseras',
                (bool) ($artifact['is_zip'] ?? false)
            );

            \App\Support\GeneratedPdfCatalog::writeMeta(
                $job_id,
                $artifact['download_name'],
                (int) $id
            );
            \App\Support\PdfJobStatus::markCompleted($job_id);

            $this->maybeSendDesignPdfReadyEmail(
                $job_id,
                'Traseras PDF',
                (int) $id
            );

            return response()->json([
                'status' => 'completed',
                'job_id' => $job_id,
                'download_url' => route('design.downloadPdf', $job_id),
                'message' => 'PDF generado. La descarga debería iniciar automáticamente.',
                'check_url' => route('design.checkPdfStatus', $job_id),
            ]);
        } catch (\Throwable $e) {
            \App\Support\PdfJobStatus::markFailed($job_id, $e->getMessage());
            Log::error('exportBackPdfAsync failed', [
                'job_id' => $job_id,
                'design_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'failed',
                'job_id' => $job_id,
                'message' => 'No se pudo generar el PDF: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Email opcional al terminar un PDF (PDF_SEND_EMAIL).
     */
    private function maybeSendDesignPdfReadyEmail(string $jobId, string $title, int $designId): void
    {
        $notifyEmail = auth()->user()?->email;
        if (
            ! config('pdf_optimization.send_email', false)
            || ! is_string($notifyEmail)
            || $notifyEmail === ''
        ) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::to($notifyEmail)->send(new \App\Mail\DesignPdfReadyMail(
                route('design.downloadPdf', $jobId),
                $title,
                $designId
            ));
            \App\Support\PdfJobStatus::markEmailSent($jobId);
        } catch (\Throwable $mailEx) {
            Log::warning('maybeSendDesignPdfReadyEmail failed', [
                'job_id' => $jobId,
                'message' => $mailEx->getMessage(),
            ]);
        }
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
        $this->assertDesignEditorAccess($request);

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
    public function index(Request $request)
    {
        $entityFilterIdRaw = $request->query('entity_id');
        $entityFilterId = $entityFilterIdRaw !== null && $entityFilterIdRaw !== ''
            ? (int) $entityFilterIdRaw
            : null;

        $entityIds = auth()->user()->accessibleEntityIds();
        if ($entityFilterId !== null) {
            if (! auth()->user()->canAccessEntity((int) $entityFilterId)) {
                abort(403, 'No tienes permisos para gestionar esta entidad.');
            }

            $entityIds = [(int) $entityFilterId];
        }

        $designs = DesignFormat::with(['entity', 'lottery', 'set'])
            ->whereIn('entity_id', $entityIds)
            ->orderByDesc('id')
            ->get();

        $approvalService = app(DesignApprovalService::class);
        $user = auth()->user();
        $pendingApprovalsCount = 0;
        if ($user->isEntity() && ! $user->isAdministration()) {
            $designs = $designs
                ->filter(fn (DesignFormat $design) => $approvalService->isVisibleToEntityViewer($design))
                ->values();

            $pendingApprovalsCount = DesignFormat::query()
                ->whereIn('entity_id', $entityIds)
                ->where('approval_status', DesignApprovalService::STATUS_PENDING)
                ->get()
                ->filter(fn (DesignFormat $design) => $approvalService->canReviewApproval($user, $design))
                ->count();
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
                'export_locked' => $approvalService->isLockedAfterParticipationExport($d),
                'can_review' => $approvalService->canReviewApproval($user, $d),
                'awaiting_entity_fee' => $awaitingEntityFee,
                'management_fee_pending' => $approvalService->managementFeePendingAfterApproval($d)
                    || $feeService->entityOwesManagementFee($d),
                'entity_fee_due' => $feeService->entityOwesManagementFee($d),
                'acts_as_administration' => $approvalService->userActsAsAdministration($user),
                'blocks_export' => $blocksExport,
                'can_download_pending_sample' => $approvalService->canDownloadPendingParticipationSample($user, $d),
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

        return view('design.index', compact('designs', 'designLockByDesignId', 'printOrderLockByDesignId', 'approvalContextByDesignId', 'canStartNewDesign', 'pendingApprovalsCount'));
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

    private function autoSubmitPrintShopDesignForEntityApproval(DesignFormat $design): void
    {
        if (! session('print_shop_order_id')) {
            return;
        }

        $user = auth()->user();
        if (! $user instanceof User) {
            return;
        }

        $design->refresh();
        $approvalService = app(DesignApprovalService::class);
        if (! $approvalService->canSubmitForApproval($user, $design)) {
            return;
        }

        $approvalService->submitForApproval($design, $user);
        session()->flash('success', 'Diseño enviado a la entidad para su aprobación.');
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

        $printOrder->loadMissing('design');
        if ($printOrder->design
            && ! app(DesignApprovalService::class)->printShopCanEditDesign($printOrder->design)) {
            abort(403, 'El diseño está pendiente de aprobación por la entidad. No puede editarse hasta que la entidad responda.');
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

        // Solo rellenar columnas vacías desde blocks (nunca sobrescribir un HTML ya guardado)
        foreach (['participation_html', 'cover_html', 'back_html'] as $field) {
            $columnHtml = trim((string) ($design->$field ?? ''));
            $blocksHtml = (string) ($blocks[$field] ?? '');
            if ($columnHtml === '' && $blocksHtml !== '') {
                $design->$field = $blocksHtml;
            }
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

        $approvalService = app(DesignApprovalService::class);
        $designerType = auth()->user()
            ? $approvalService->resolveDesignerTypeForSave(auth()->user(), $entity)
            : DesignApprovalService::DESIGNER_ADMINISTRATION;

        $design = DesignFormat::create(array_merge(DesignFormat::defaultLayoutAttributes(), [
            'entity_id' => $entity->id,
            'lottery_id' => $lotteryId,
            'set_id' => $set->id,
            'format' => 'A4',
            'designer_type' => $designerType,
            'approval_status' => DesignApprovalService::STATUS_DRAFT,
            'participation_html' => '',
            'cover_html' => '',
            'back_html' => '',
            'backgrounds' => [],
            'output' => [],
        ]));

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

    public function uploadImage(Request $request)
    {
        $this->assertDesignEditorAccess($request);

        $request->validate(SecureImageUpload::rules('image', true));

        $filename = SecureImageUpload::store($request->file('image'), 'uploads');

        return response()->json(['url' => url('uploads/'.$filename)]);
    }

    /**
     * Assets del editor (imágenes, snapshot): permitido a quien puede diseñar
     * (admin, entidad, superadmin, imprenta, invitación externa).
     */
    private function assertDesignEditorAccess(Request $request): void
    {
        if (session('design_external_invitation_id') || session('print_shop_order_id')) {
            return;
        }

        $user = auth()->user();
        if (! $user) {
            abort(401, 'No autenticado.');
        }

        $canUseEditor = $user->isSuperAdmin()
            || $user->isPrintShop()
            || $user->isAdministration()
            || $user->isAdministrationPanelAccount()
            || $user->isEntity()
            || app(DesignApprovalService::class)->userActsAsAdministration($user)
            || $user->managers()->where('status', 1)->exists();

        if (! $canUseEditor) {
            abort(403, 'No tienes permisos para usar el editor de diseño.');
        }

        // Si hay entidad en contexto, exigir acceso (no aplica a superadmin / imprenta).
        $entityId = $this->resolveDesignEditorEntityId($request);
        if ($entityId
            && ! $user->isSuperAdmin()
            && ! $user->isPrintShop()
            && ! $user->canAccessEntity($entityId)) {
            abort(403, 'No tienes permisos para usar el editor de diseño.');
        }
    }

    private function resolveDesignEditorEntityId(Request $request): ?int
    {
        foreach ([
            session('design_entity_id'),
            $request->input('design_entity_id'),
            $request->input('entity_id'),
        ] as $candidate) {
            if ($candidate !== null && $candidate !== '' && (int) $candidate > 0) {
                return (int) $candidate;
            }
        }

        $designFormatId = $request->input('design_format_id');
        if ($designFormatId && ctype_digit((string) $designFormatId)) {
            $entityId = DesignFormat::query()->whereKey((int) $designFormatId)->value('entity_id');
            if ($entityId) {
                return (int) $entityId;
            }
        }

        // saveSnapshot envía design_id = set_id
        $setId = $request->input('design_id') ?? $request->input('set_id');
        if ($setId && ctype_digit((string) $setId)) {
            $entityId = Set::query()->whereKey((int) $setId)->value('entity_id');
            if ($entityId) {
                return (int) $entityId;
            }
        }

        return null;
    }
}