<?php

namespace App\Services;

use App\Models\Administration;
use App\Models\BillingCharge;
use App\Models\DesignFormat;
use App\Models\Entity;
use App\Models\PrintOrder;
use App\Models\Set;
use App\Models\User;

class AdministrationBillingService
{
    public const MODE_CARD = 'card';

    public const MODE_REMITTANCE = 'remittance';

    public const FREQUENCY_MONTHLY = 'monthly';

    public const FREQUENCY_BIWEEKLY = 'biweekly';

    public function usesRemittance(?Administration $administration): bool
    {
        return $administration
            && ($administration->billing_payment_mode ?? self::MODE_CARD) === self::MODE_REMITTANCE;
    }

    public function hasValidBillingIban(Administration $administration): bool
    {
        $digits = preg_replace('/\D/', '', (string) ($administration->account ?? ''));

        return strlen($digits) === 22;
    }

    public function paymentModeLabel(?string $mode): string
    {
        return match ($mode) {
            self::MODE_REMITTANCE => 'Remesa periódica',
            self::MODE_CARD => 'Tarjeta (Stripe)',
            default => 'Tarjeta (Stripe)',
        };
    }

    public function remittanceFrequencyLabel(?string $frequency): string
    {
        return match ($frequency) {
            self::FREQUENCY_BIWEEKLY => 'Quincenal',
            self::FREQUENCY_MONTHLY => 'Mensual',
            default => '—',
        };
    }

    public function shouldQueueManagementFeeRemittance(Set $set, ?Entity $entity = null): bool
    {
        $entity ??= $set->relationLoaded('entity') ? $set->entity : $set->entity()->first();
        if (! $entity) {
            return false;
        }

        $payer = app(ManagementFeeService::class)->resolvePayer($entity);
        if ($payer !== ManagementFeeService::PAYER_ADMINISTRATION) {
            return false;
        }

        $administration = $entity->relationLoaded('administration') ? $entity->administration : $entity->administration()->first();

        return $this->usesRemittance($administration);
    }

    public function canQueueManagementFee(User $user, Set $set, ?DesignFormat $design = null): bool
    {
        $feeService = app(ManagementFeeService::class);
        if ($feeService->isManagementFeeSettled($set)) {
            return false;
        }

        if ($design && app(DesignApprovalService::class)->requiresEntityApproval($design)) {
            if ($design->approval_status !== DesignApprovalService::STATUS_APPROVED) {
                return false;
            }
        }

        if (! $this->shouldQueueManagementFeeRemittance($set)) {
            return false;
        }

        return $feeService->canMarkAsPaid($user, $set);
    }

    public function queueManagementFeeCharge(Set $set, User $user, ?DesignFormat $design = null): BillingCharge
    {
        if (! $this->canQueueManagementFee($user, $set, $design)) {
            abort(403, 'No puedes registrar el cargo de cuota de gestión en remesa.');
        }

        $set->loadMissing('entity.administration');
        $entity = $set->entity;
        $administration = $entity?->administration;

        if (! $administration || ! $this->hasValidBillingIban($administration)) {
            abort(422, 'La administración debe tener un IBAN válido configurado para usar remesa.');
        }

        $feeService = app(ManagementFeeService::class);
        $set = $feeService->ensureSnapshot($set, $design);

        $existing = BillingCharge::query()
            ->where('set_id', $set->id)
            ->where('concept', BillingCharge::CONCEPT_MANAGEMENT_FEE)
            ->whereIn('status', [BillingCharge::STATUS_PENDING, BillingCharge::STATUS_INVOICED])
            ->first();

        if ($existing) {
            $feeService->markQueuedForRemittance($set, $existing, $user);

            return $existing;
        }

        $amount = (float) ($set->management_fee_amount ?? 0);
        if ($amount <= 0) {
            $feeService->markAsPaid($set, $user);

            abort(422, 'No hay importe de cuota de gestión que registrar.');
        }

        $charge = BillingCharge::create([
            'administration_id' => $administration->id,
            'entity_id' => $entity->id,
            'set_id' => $set->id,
            'payer_type' => BillingCharge::PAYER_ADMINISTRATION,
            'concept' => BillingCharge::CONCEPT_MANAGEMENT_FEE,
            'source_type' => 'set',
            'source_id' => $set->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'description' => 'Cuota gestión PARTILOT — set #'.$set->id.' ('.($set->set_name ?? 'sin nombre').')',
            'status' => BillingCharge::STATUS_PENDING,
            'created_by_user_id' => $user->id,
        ]);

        $feeService->markQueuedForRemittance($set, $charge, $user);

        return $charge;
    }

    public function resolvePrintPayer(Entity $entity): string
    {
        return ($entity->entity_pays_print_fee ?? false)
            ? BillingCharge::PAYER_ENTITY
            : BillingCharge::PAYER_ADMINISTRATION;
    }

    public function printPayerLabel(?string $payer): string
    {
        return match ($payer) {
            BillingCharge::PAYER_ENTITY => 'Entidad',
            BillingCharge::PAYER_ADMINISTRATION => 'Administración',
            default => '—',
        };
    }

    public function shouldQueuePrintFeeRemittance(?Entity $entity): bool
    {
        if (! $entity || $this->resolvePrintPayer($entity) !== BillingCharge::PAYER_ADMINISTRATION) {
            return false;
        }

        $administration = $entity->relationLoaded('administration')
            ? $entity->administration
            : $entity->administration()->first();

        return $this->usesRemittance($administration);
    }

    public function canSubmitPrintOrderViaRemittance(User $user, DesignFormat $design): bool
    {
        if (! $user->canAccessEntity((int) $design->entity_id)) {
            return false;
        }

        $design->loadMissing('entity.administration');

        return $this->shouldQueuePrintFeeRemittance($design->entity)
            && $this->hasValidBillingIban($design->entity->administration);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPrintPaymentContext(DesignFormat $design): array
    {
        $design->loadMissing('entity.administration');
        $entity = $design->entity;
        $payer = $entity ? $this->resolvePrintPayer($entity) : BillingCharge::PAYER_ADMINISTRATION;
        $usesRemittance = $this->shouldQueuePrintFeeRemittance($entity);
        $administration = $entity?->administration;

        return [
            'payer' => $payer,
            'payer_label' => $this->printPayerLabel($payer),
            'uses_remittance' => $usesRemittance,
            'can_pay_stripe' => ! $usesRemittance,
            'can_queue_remittance' => $usesRemittance && $administration && $this->hasValidBillingIban($administration),
            'remittance_frequency_label' => $administration
                ? $this->remittanceFrequencyLabel($administration->billing_remittance_frequency)
                : null,
        ];
    }

    public function queuePrintFeeCharge(PrintOrder $order, User $user): BillingCharge
    {
        $order->loadMissing(['entity.administration', 'design']);
        $entity = $order->entity;
        $administration = $entity?->administration;

        if (! $entity || ! $administration || ! $this->shouldQueuePrintFeeRemittance($entity)) {
            abort(403, 'Este pedido no puede registrarse en remesa.');
        }

        if (! $this->hasValidBillingIban($administration)) {
            abort(422, 'La administración debe tener un IBAN válido configurado para usar remesa.');
        }

        if ($order->billing_charge_id) {
            return $order->billingCharge;
        }

        $existing = BillingCharge::query()
            ->where('source_type', 'print_order')
            ->where('source_id', $order->id)
            ->where('concept', BillingCharge::CONCEPT_PRINT_FEE)
            ->whereIn('status', [
                BillingCharge::STATUS_PENDING,
                BillingCharge::STATUS_IN_REMITTANCE,
                BillingCharge::STATUS_INVOICED,
            ])
            ->first();

        if ($existing) {
            $order->forceFill(['billing_charge_id' => $existing->id])->save();

            return $existing;
        }

        $amount = (float) ($order->quoted_amount ?? 0);
        if ($amount <= 0) {
            abort(422, 'No hay importe de diseño e impresión que registrar.');
        }

        $charge = BillingCharge::create([
            'administration_id' => $administration->id,
            'entity_id' => $entity->id,
            'set_id' => $order->set_id,
            'payer_type' => BillingCharge::PAYER_ADMINISTRATION,
            'concept' => BillingCharge::CONCEPT_PRINT_FEE,
            'source_type' => 'print_order',
            'source_id' => $order->id,
            'amount' => $amount,
            'currency' => 'EUR',
            'description' => 'Diseño e impresión — '.$order->order_code,
            'status' => BillingCharge::STATUS_PENDING,
            'created_by_user_id' => $user->id,
        ]);

        $order->forceFill(['billing_charge_id' => $charge->id])->save();

        return $charge;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BillingCharge>
     */
    public function pendingChargesForAdministration(int $administrationId)
    {
        return BillingCharge::query()
            ->where('administration_id', $administrationId)
            ->where('status', BillingCharge::STATUS_PENDING)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BillingCharge>
     */
    public function chargesForAdministration(int $administrationId, ?string $status = null)
    {
        $query = BillingCharge::query()
            ->with(['entity', 'set', 'directDebitOrder'])
            ->where('administration_id', $administrationId)
            ->orderByDesc('id');

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query->get();
    }
}
