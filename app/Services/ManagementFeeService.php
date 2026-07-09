<?php

namespace App\Services;

use App\Models\BillingCharge;
use App\Models\DesignFormat;
use App\Models\Entity;
use App\Models\PartilotBillingSetting;
use App\Models\Set;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ManagementFeeService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_QUEUED_REMITTANCE = 'queued_for_remittance';

    public const PAYER_ENTITY = 'entity';

    public const PAYER_ADMINISTRATION = 'administration';

    public function resolvePayer(Entity $entity): string
    {
        return $entity->entity_pays_management_fee
            ? self::PAYER_ENTITY
            : self::PAYER_ADMINISTRATION;
    }

    public function unitPriceForParticipationCount(int $participationCount, string $payer, ?PartilotBillingSetting $settings = null): float
    {
        $settings ??= PartilotBillingSetting::current();

        if ($payer === self::PAYER_ADMINISTRATION) {
            return (float) $settings->fee_administration_per_participation;
        }

        if ($participationCount <= 1000) {
            return (float) $settings->fee_per_participation_1000;
        }

        if ($participationCount <= 5000) {
            return (float) $settings->fee_per_participation_5000;
        }

        return (float) $settings->fee_per_participation_10000;
    }

    /**
     * @return array{payer: string, participation_count: int, unit_price: float, amount: float}
     */
    public function calculateForSet(Set $set, ?Entity $entity = null, ?PartilotBillingSetting $settings = null): array
    {
        $entity ??= $set->relationLoaded('entity') ? $set->entity : $set->entity()->first();
        $settings ??= PartilotBillingSetting::current();

        $participationCount = max(0, (int) ($set->total_participations ?? 0));
        $payer = $entity ? $this->resolvePayer($entity) : self::PAYER_ADMINISTRATION;
        $unitPrice = $this->unitPriceForParticipationCount($participationCount, $payer, $settings);
        $amount = round($participationCount * $unitPrice, 2);

        return [
            'payer' => $payer,
            'participation_count' => $participationCount,
            'unit_price' => $unitPrice,
            'amount' => $amount,
        ];
    }

    public function isPaid(Set $set): bool
    {
        return $set->management_fee_status === self::STATUS_PAID;
    }

    public function isQueuedForRemittance(Set $set): bool
    {
        return $set->management_fee_status === self::STATUS_QUEUED_REMITTANCE;
    }

    public function isManagementFeeSettled(Set $set): bool
    {
        return $this->isPaid($set) || $this->isQueuedForRemittance($set);
    }

    /**
     * Venta digital en app: permitida si la cuota está pagada (Stripe) o encolada en remesa.
     * Sets legacy sin estado de cuota se tratan como vendibles.
     */
    public function allowsDigitalSale(Set $set): bool
    {
        $status = $set->management_fee_status;
        if ($status === null || $status === '') {
            return true;
        }

        return $this->isManagementFeeSettled($set);
    }

    /**
     * @param  Builder<\App\Models\Set>  $query
     */
    public function applyDigitalSaleEligibleConstraint(Builder $query, string $column = 'management_fee_status'): Builder
    {
        return $query->where(function (Builder $q) use ($column) {
            $q->whereNull($column)
                ->orWhere($column, '')
                ->orWhereIn($column, [self::STATUS_PAID, self::STATUS_QUEUED_REMITTANCE]);
        });
    }

    public function digitalSaleBlockedMessage(): string
    {
        return 'Las participaciones digitales de este diseño no están disponibles para la venta hasta que se abone la cuota de gestión.';
    }

    public function blocksQrExport(Set $set, ?DesignFormat $design = null): bool
    {
        $approvalService = app(DesignApprovalService::class);

        if ($design && $approvalService->requiresEntityApproval($design)) {
            if ($design->approval_status !== DesignApprovalService::STATUS_APPROVED
                && ! $approvalService->hasCommittedPrintOrder($design)) {
                return true;
            }
        }

        if ($design && $approvalService->isAwaitingEntityManagementFeeBeforeAdminDesign($design)) {
            return true;
        }

        return ! $this->isManagementFeeSettled($set);
    }

    public function blocksPrintShopUntilEntityPaysManagementFee(Set $set): bool
    {
        $set->loadMissing('entity');
        $entity = $set->entity;

        if (! $entity || $this->resolvePayer($entity) !== self::PAYER_ENTITY) {
            return false;
        }

        return ! $this->isManagementFeeSettled($set);
    }

    /**
     * Alerta modal para gestores entidad con cuota de gestión pendiente.
     *
     * @return array<string, mixed>|null
     */
    public function getEntityManagementFeeModalAlert(User $user): ?array
    {
        if (! $user->isEntity() || app(DesignApprovalService::class)->userActsAsAdministration($user)) {
            return null;
        }

        foreach ($user->accessibleEntityIds() as $entityId) {
            $designs = DesignFormat::query()
                ->with(['set.entity', 'entity'])
                ->where('entity_id', $entityId)
                ->orderByDesc('id')
                ->get();

            foreach ($designs as $design) {
                if (! $this->entityOwesManagementFee($design)) {
                    continue;
                }

                $set = $this->ensureSnapshot($design->set, $design);

                return [
                    'design_id' => $design->id,
                    'set_id' => $set->id,
                    'entity_name' => $design->entity?->name,
                    'set_name' => $set->set_name ?? ('Set #'.$set->id),
                    'amount' => (float) ($set->management_fee_amount ?? 0),
                    'pay_url' => route('design.managementFee.pay', $set->id),
                    'summary_url' => route('design.summary', $design->id),
                ];
            }
        }

        return null;
    }

    /**
     * La entidad debe abonar la cuota de gestión (p. ej. tras impresión PARTILOT).
     */
    public function entityOwesManagementFee(DesignFormat $design): bool
    {
        $design->loadMissing(['set.entity']);
        if (! $design->set || ! $design->entity) {
            return false;
        }

        if ($this->resolvePayer($design->entity) !== self::PAYER_ENTITY) {
            return false;
        }

        if ($this->isManagementFeeSettled($design->set)) {
            return false;
        }

        if ($this->blocksAdminDesignUntilEntityPays($design->set)) {
            return true;
        }

        return app(DesignApprovalService::class)->designHasParticipationContent($design);
    }

    /**
     * El pago de cuota exige aprobación previa del diseño.
     * Solo cuando pagador es administración; la entidad paga primero y aprueba después.
     */
    public function managementFeePaymentBlockedByApproval(?DesignFormat $design, Set $set): bool
    {
        $set->loadMissing('entity');
        $entity = $set->entity;

        if (! $entity || $this->resolvePayer($entity) === self::PAYER_ENTITY) {
            return false;
        }

        if (! $design) {
            return false;
        }

        $approvalService = app(DesignApprovalService::class);
        if (! $approvalService->requiresEntityApproval($design)) {
            return false;
        }

        return $design->approval_status !== DesignApprovalService::STATUS_APPROVED;
    }

    public function entityDesigns(Entity $entity): bool
    {
        return (bool) ($entity->entity_pays_print_fee ?? false);
    }

    public function administrationDesigns(Entity $entity): bool
    {
        return ! $this->entityDesigns($entity);
    }

    public function requiresEntityPaymentBeforeAdminDesign(Entity $entity): bool
    {
        return (bool) ($entity->entity_pays_management_fee ?? false)
            && $this->administrationDesigns($entity);
    }

    public function blocksAdminDesignUntilEntityPays(Set $set): bool
    {
        $set->loadMissing('entity');
        $entity = $set->entity;

        if (! $entity || $this->resolvePayer($entity) !== self::PAYER_ENTITY) {
            return false;
        }

        if ($this->isManagementFeeSettled($set)) {
            return false;
        }

        $approvalService = app(DesignApprovalService::class);

        // Switch 2 ON: la entidad diseña → cuota antes de entrar al editor (cualquier usuario).
        if ($approvalService->entityDesignEnabled($entity)) {
            return true;
        }

        // Switch 1 ON + Switch 2 OFF: la administración diseña → cuota antes de que admin entre al editor.
        return $this->requiresEntityPaymentBeforeAdminDesign($entity);
    }

    public function ensureSnapshot(Set $set, ?DesignFormat $design = null): Set
    {
        if ($this->isManagementFeeSettled($set)) {
            return $set;
        }

        $set->loadMissing('entity');
        $entityForSnapshot = $set->entity;
        $paymentBeforeAdminDesign = $entityForSnapshot
            && $this->requiresEntityPaymentBeforeAdminDesign($entityForSnapshot);
        $entityPaysFee = $entityForSnapshot
            && $this->resolvePayer($entityForSnapshot) === self::PAYER_ENTITY;

        if ($design && app(DesignApprovalService::class)->requiresEntityApproval($design)) {
            if ($design->approval_status !== DesignApprovalService::STATUS_APPROVED
                && ! $paymentBeforeAdminDesign
                && ! $entityPaysFee) {
                return $set;
            }
        }

        $entity = $set->relationLoaded('entity') ? $set->entity : $set->entity()->first();
        if (! $entity) {
            return $set;
        }

        if ($set->management_fee_status === self::STATUS_PENDING
            && $set->management_fee_amount !== null
            && $set->management_fee_payer !== null) {
            return $set;
        }

        $quote = $this->calculateForSet($set, $entity);

        if ($quote['amount'] <= 0) {
            $set->forceFill([
                'management_fee_status' => self::STATUS_PAID,
                'management_fee_amount' => 0,
                'management_fee_unit_price' => $quote['unit_price'],
                'management_fee_participation_count' => $quote['participation_count'],
                'management_fee_payer' => $quote['payer'],
                'management_fee_paid_at' => now(),
                'management_fee_paid_by_user_id' => null,
                'management_fee_payment_provider' => 'zero_amount',
            ])->save();

            return $set->refresh();
        }

        $set->forceFill([
            'management_fee_status' => self::STATUS_PENDING,
            'management_fee_amount' => $quote['amount'],
            'management_fee_unit_price' => $quote['unit_price'],
            'management_fee_participation_count' => $quote['participation_count'],
            'management_fee_payer' => $quote['payer'],
        ])->save();

        return $set->refresh();
    }

    public function payerLabel(?string $payer): string
    {
        return match ($payer) {
            self::PAYER_ENTITY => 'Entidad',
            self::PAYER_ADMINISTRATION => 'Administración',
            default => '—',
        };
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_PAID => 'Pagada',
            self::STATUS_QUEUED_REMITTANCE => 'Encolada en remesa',
            self::STATUS_PENDING => 'Pendiente',
            default => 'Sin calcular',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummaryContext(Set $set, User $user, ?DesignFormat $design = null): array
    {
        $set->loadMissing('entity.administration');
        $set = $this->ensureSnapshot($set, $design);
        $paymentService = app(ManagementFeePaymentService::class);
        $billingService = app(AdministrationBillingService::class);
        $stripeEnabled = $paymentService->hasStripeConfigured();
        $canPay = $paymentService->canPay($user, $set, $design);
        $usesRemittance = $billingService->shouldQueueManagementFeeRemittance($set);
        $approvalService = app(DesignApprovalService::class);
        $awaitingApproval = $design
            && $approvalService->requiresEntityApproval($design)
            && $design->approval_status !== DesignApprovalService::STATUS_APPROVED
            && ! $approvalService->hasCommittedPrintOrder($design);

        $administration = $set->entity?->administration;
        $entity = $set->entity;
        $paymentBeforeAdminDesign = $entity
            && $this->requiresEntityPaymentBeforeAdminDesign($entity)
            && ! $this->isManagementFeeSettled($set);
        $paymentBeforeEditor = $entity
            && $this->resolvePayer($entity) === self::PAYER_ENTITY
            && app(DesignApprovalService::class)->entityDesignEnabled($entity)
            && ! $this->isManagementFeeSettled($set);
        $paymentBlockedByApproval = $this->managementFeePaymentBlockedByApproval($design, $set);

        return [
            'status' => $set->management_fee_status,
            'status_label' => $this->statusLabel($set->management_fee_status),
            'amount' => (float) ($set->management_fee_amount ?? 0),
            'unit_price' => (float) ($set->management_fee_unit_price ?? 0),
            'participation_count' => (int) ($set->management_fee_participation_count ?? 0),
            'payer' => $set->management_fee_payer,
            'payer_label' => $this->payerLabel($set->management_fee_payer),
            'paid_at' => $set->management_fee_paid_at,
            'blocks_export' => $this->blocksQrExport($set, $design),
            'can_pay_stripe' => $canPay && $stripeEnabled && ! $usesRemittance && ! $paymentBlockedByApproval,
            'can_queue_remittance' => $billingService->canQueueManagementFee($user, $set, $design),
            'uses_remittance' => $usesRemittance,
            'has_valid_iban' => $administration ? $billingService->hasValidBillingIban($administration) : false,
            'remittance_frequency_label' => $administration
                ? $billingService->remittanceFrequencyLabel($administration->billing_remittance_frequency)
                : null,
            'can_mark_paid' => ! $stripeEnabled && ! $usesRemittance && $this->canMarkAsPaid($user, $set) && ! $paymentBlockedByApproval,
            'stripe_enabled' => $stripeEnabled,
            'awaiting_approval' => $awaitingApproval,
            'payment_before_admin_design' => $paymentBeforeAdminDesign,
            'payment_before_editor' => $paymentBeforeEditor,
            'needs_payment_action' => ! $this->isManagementFeeSettled($set) && ! $paymentBlockedByApproval,
        ];
    }

    public function canMarkAsPaid(User $user, Set $set): bool
    {
        if ($this->isManagementFeeSettled($set)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $payer = $set->management_fee_payer ?? $this->calculateForSet($set)['payer'];

        if ($payer === self::PAYER_ENTITY) {
            if ($user->isEntityPanelAccount() && (int) $user->panel_account_id === (int) $set->entity_id) {
                return true;
            }

            return $user->isEntity() && $user->canAccessEntity((int) $set->entity_id);
        }

        if ($payer === self::PAYER_ADMINISTRATION) {
            if ($user->isAdministrationPanelAccount()) {
                $entity = $set->relationLoaded('entity') ? $set->entity : $set->entity()->first();

                return $entity && (int) $user->panel_account_id === (int) $entity->administration_id;
            }

            return $user->isAdministration() && $user->canAccessEntity((int) $set->entity_id);
        }

        return false;
    }

    public function markQueuedForRemittance(Set $set, BillingCharge $charge, User $user): Set
    {
        $set->forceFill([
            'management_fee_status' => self::STATUS_QUEUED_REMITTANCE,
            'management_fee_paid_at' => now(),
            'management_fee_paid_by_user_id' => $user->id,
            'management_fee_payment_provider' => 'remittance',
            'management_fee_billing_charge_id' => $charge->id,
        ])->save();

        return $set->refresh();
    }

    public function markAsPaid(Set $set, User $user): Set
    {
        if ($this->isManagementFeeSettled($set)) {
            return $set;
        }

        if (! $this->canMarkAsPaid($user, $set)) {
            abort(403, 'No tienes permisos para confirmar el pago de la cuota de gestión.');
        }

        $this->ensureSnapshot($set);

        $set->forceFill([
            'management_fee_status' => self::STATUS_PAID,
            'management_fee_paid_at' => now(),
            'management_fee_paid_by_user_id' => $user->id,
            'management_fee_payment_provider' => 'manual',
        ])->save();

        return $set->refresh();
    }
}
