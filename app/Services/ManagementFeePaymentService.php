<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\Entity;
use App\Models\PartilotBillingSetting;
use App\Models\Set;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class ManagementFeePaymentService
{
    public function hasStripeConfigured(): bool
    {
        $settings = PartilotBillingSetting::current();

        return $settings->hasStripeConfigured();
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function resolveStripeKeys(): array
    {
        $settings = PartilotBillingSetting::current();

        if (! $settings->hasStripeConfigured()) {
            return ['', ''];
        }

        return [$settings->stripePublishableKey(), $settings->stripeSecretKey()];
    }

    public function canPay(User $user, Set $set, ?\App\Models\DesignFormat $design = null): bool
    {
        $feeService = app(ManagementFeeService::class);

        if ($feeService->isManagementFeeSettled($set)) {
            return false;
        }

        if ($feeService->managementFeePaymentBlockedByApproval($design, $set)) {
            return false;
        }

        $set->loadMissing('entity');
        $payer = $set->management_fee_payer ?? $feeService->calculateForSet($set)['payer'];

        if ($payer === ManagementFeeService::PAYER_ENTITY) {
            return $this->canPayAsEntityPayer($user, $set);
        }

        return $feeService->canMarkAsPaid($user, $set);
    }

    /**
     * Pago Stripe de cuota con pagador entidad: solo usuarios de la entidad (no administración ni super admin).
     */
    private function canPayAsEntityPayer(User $user, Set $set): bool
    {
        if (app(DesignApprovalService::class)->userActsAsAdministration($user)) {
            return false;
        }

        return app(ManagementFeeService::class)->canMarkAsPaid($user, $set);
    }

    /**
     * @return array{ok: bool, client_secret?: string, publishable_key?: string, amount?: float, message?: string}
     */
    public function createPaymentIntent(Set $set, User $user, ?\App\Models\DesignFormat $design = null): array
    {
        if (! $this->canPay($user, $set, $design)) {
            return ['ok' => false, 'message' => 'No puedes iniciar el pago de esta cuota.'];
        }

        if (app(AdministrationBillingService::class)->shouldQueueManagementFeeRemittance($set)) {
            return ['ok' => false, 'message' => 'Esta administración paga por remesa periódica. Confirme el cargo en remesa.'];
        }

        [$publishableKey, $secretKey] = $this->resolveStripeKeys();
        if ($secretKey === '' || $publishableKey === '') {
            return ['ok' => false, 'message' => 'Stripe no está configurado en Ajustes → Config. factura auto.'];
        }

        $feeService = app(ManagementFeeService::class);
        $set = $feeService->ensureSnapshot($set, $design);
        $amount = (float) ($set->management_fee_amount ?? 0);

        if ($amount <= 0) {
            $feeService->markAsPaid($set, $user);

            return ['ok' => true, 'amount' => 0, 'already_paid' => true];
        }

        try {
            $customerId = $this->getOrCreateStripeCustomer($set, $user);
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $formParams = [
                'amount' => (int) round($amount * 100),
                'currency' => 'eur',
                'description' => 'Cuota gestión PARTILOT — set #'.$set->id,
                'metadata[set_id]' => (string) $set->id,
                'metadata[entity_id]' => (string) $set->entity_id,
                'metadata[payer_user_id]' => (string) $user->id,
                'metadata[concept]' => 'management_fee',
                'automatic_payment_methods[enabled]' => 'true',
                'setup_future_usage' => 'off_session',
            ];

            if ($customerId !== '') {
                $formParams['customer'] = $customerId;
            }

            $response = $client->post('payment_intents', [
                'auth' => [$secretKey, ''],
                'form_params' => $formParams,
            ]);

            $payload = json_decode((string) $response->getBody(), true);
            if (! is_array($payload) || empty($payload['client_secret']) || empty($payload['id'])) {
                return ['ok' => false, 'message' => 'No se pudo crear el PaymentIntent.'];
            }

            $set->forceFill([
                'management_fee_stripe_payment_intent_id' => (string) $payload['id'],
            ])->save();

            return [
                'ok' => true,
                'client_secret' => (string) $payload['client_secret'],
                'publishable_key' => $publishableKey,
                'amount' => $amount,
            ];
        } catch (\Throwable $e) {
            Log::error('Stripe management fee PaymentIntent error', [
                'error' => $e->getMessage(),
                'set_id' => $set->id,
            ]);

            return ['ok' => false, 'message' => 'Error creando el pago con Stripe.'];
        }
    }

    public function confirmStripePayment(Set $set, string $paymentIntentId, User $user, ?\App\Models\DesignFormat $design = null): Set
    {
        $feeService = app(ManagementFeeService::class);
        if (! $this->canPay($user, $set, $design) && ! $feeService->isManagementFeeSettled($set)) {
            abort(403, 'No puedes confirmar el pago de esta cuota.');
        }

        if ($feeService->isManagementFeeSettled($set)) {
            return $set;
        }

        if (! $this->isStripePaymentSucceeded($paymentIntentId)) {
            abort(422, 'El pago no está confirmado en Stripe. Intenta de nuevo.');
        }

        $storedPi = (string) ($set->management_fee_stripe_payment_intent_id ?? '');
        if ($storedPi !== '' && $storedPi !== $paymentIntentId) {
            abort(422, 'El identificador de pago no coincide con la cuota pendiente.');
        }

        $this->persistStripeCustomerFromPaymentIntent($set, $paymentIntentId);

        $set->forceFill([
            'management_fee_status' => ManagementFeeService::STATUS_PAID,
            'management_fee_paid_at' => now(),
            'management_fee_paid_by_user_id' => $user->id,
            'management_fee_stripe_payment_intent_id' => $paymentIntentId,
            'management_fee_payment_provider' => 'stripe',
        ])->save();

        return $set->refresh();
    }

    public function isStripePaymentSucceeded(string $paymentIntentId): bool
    {
        $payload = $this->fetchStripePaymentIntent($paymentIntentId);

        return is_array($payload) && (($payload['status'] ?? '') === 'succeeded');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchStripePaymentIntent(string $paymentIntentId): ?array
    {
        [, $secretKey] = $this->resolveStripeKeys();
        if ($secretKey === '') {
            return null;
        }

        try {
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $response = $client->get('payment_intents/'.urlencode($paymentIntentId), [
                'auth' => [$secretKey, ''],
            ]);

            $payload = json_decode((string) $response->getBody(), true);

            return is_array($payload) ? $payload : null;
        } catch (\Throwable $e) {
            Log::error('Stripe fetch management fee PI error', [
                'error' => $e->getMessage(),
                'pi' => $paymentIntentId,
            ]);

            return null;
        }
    }

    private function getOrCreateStripeCustomer(Set $set, User $user): string
    {
        $entity = $set->relationLoaded('entity') ? $set->entity : $set->entity()->first();
        if (! $entity) {
            return '';
        }

        $payer = $set->management_fee_payer ?? app(ManagementFeeService::class)->calculateForSet($set, $entity)['payer'];

        if ($payer === ManagementFeeService::PAYER_ENTITY) {
            return $this->ensureEntityStripeCustomer($entity);
        }

        $administration = $entity->administration;
        if ($administration) {
            return $this->ensureAdministrationStripeCustomer($administration, $entity);
        }

        return '';
    }

    private function ensureEntityStripeCustomer(Entity $entity): string
    {
        if (! empty($entity->stripe_customer_id)) {
            return (string) $entity->stripe_customer_id;
        }

        [, $secretKey] = $this->resolveStripeKeys();
        if ($secretKey === '') {
            return '';
        }

        try {
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $response = $client->post('customers', [
                'auth' => [$secretKey, ''],
                'form_params' => array_filter([
                    'name' => $entity->name,
                    'email' => $entity->email,
                    'metadata[entity_id]' => (string) $entity->id,
                ]),
            ]);
            $payload = json_decode((string) $response->getBody(), true);
            $customerId = is_array($payload) ? (string) ($payload['id'] ?? '') : '';
            if ($customerId !== '') {
                $entity->forceFill(['stripe_customer_id' => $customerId])->save();
            }

            return $customerId;
        } catch (\Throwable $e) {
            Log::warning('Stripe customer create (entity) failed', ['entity_id' => $entity->id, 'error' => $e->getMessage()]);

            return '';
        }
    }

    private function ensureAdministrationStripeCustomer(Administration $administration, Entity $entity): string
    {
        if (! empty($administration->stripe_customer_id)) {
            return (string) $administration->stripe_customer_id;
        }

        [, $secretKey] = $this->resolveStripeKeys();
        if ($secretKey === '') {
            return '';
        }

        try {
            $client = new Client(['base_uri' => 'https://api.stripe.com/v1/']);
            $response = $client->post('customers', [
                'auth' => [$secretKey, ''],
                'form_params' => array_filter([
                    'name' => $administration->name ?: $administration->society,
                    'email' => $administration->email,
                    'metadata[administration_id]' => (string) $administration->id,
                    'metadata[entity_id]' => (string) $entity->id,
                ]),
            ]);
            $payload = json_decode((string) $response->getBody(), true);
            $customerId = is_array($payload) ? (string) ($payload['id'] ?? '') : '';
            if ($customerId !== '') {
                $administration->forceFill(['stripe_customer_id' => $customerId])->save();
            }

            return $customerId;
        } catch (\Throwable $e) {
            Log::warning('Stripe customer create (administration) failed', [
                'administration_id' => $administration->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function persistStripeCustomerFromPaymentIntent(Set $set, string $paymentIntentId): void
    {
        $payload = $this->fetchStripePaymentIntent($paymentIntentId);
        if (! is_array($payload)) {
            return;
        }

        $customerId = (string) ($payload['customer'] ?? '');
        if ($customerId === '') {
            return;
        }

        $entity = $set->entity;
        if (! $entity) {
            return;
        }

        $payer = $set->management_fee_payer ?? app(ManagementFeeService::class)->calculateForSet($set, $entity)['payer'];

        if ($payer === ManagementFeeService::PAYER_ENTITY && empty($entity->stripe_customer_id)) {
            $entity->forceFill(['stripe_customer_id' => $customerId])->save();

            return;
        }

        if ($payer === ManagementFeeService::PAYER_ADMINISTRATION && $entity->administration && empty($entity->administration->stripe_customer_id)) {
            $entity->administration->forceFill(['stripe_customer_id' => $customerId])->save();
        }
    }
}
