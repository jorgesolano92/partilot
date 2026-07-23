<?php

namespace App\Services;

use App\Models\DesignFormat;
use App\Models\Entity;
use App\Models\Manager;
use App\Models\PrintOrder;
use App\Models\User;
use App\Mail\DesignApprovalPendingMail;

class DesignApprovalService
{
    public const DESIGNER_ADMINISTRATION = 'administration';

    public const DESIGNER_ENTITY = 'entity';

    public const DESIGNER_PRINT_SHOP = 'print_shop';

    public const DESIGNER_SUPERADMIN = 'superadmin';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function superadminSkipsEntityApproval(): bool
    {
        return (bool) config('design.superadmin_skip_entity_approval', false);
    }

    public function resolveDesignerType(User $user): string
    {
        if ($user->isSuperAdmin() && $this->superadminSkipsEntityApproval()) {
            return self::DESIGNER_SUPERADMIN;
        }

        return $user->isEntity() ? self::DESIGNER_ENTITY : self::DESIGNER_ADMINISTRATION;
    }

    /**
     * Switch 2 (entity_pays_print_fee): ON diseña la entidad; OFF diseña la administración.
     */
    public function entityDesignEnabled(Entity $entity): bool
    {
        return (bool) ($entity->entity_pays_print_fee ?? false);
    }

    public function administrationDesignOnly(Entity $entity): bool
    {
        return ! $this->entityDesignEnabled($entity);
    }

    public function canEntityActAsDesigner(User $user, Entity $entity): bool
    {
        if (! $this->entityDesignEnabled($entity)) {
            return false;
        }

        if (! $user->isEntity() || $this->isAdministrationSideUser($user)) {
            return false;
        }

        return $user->canAccessEntity((int) $entity->id);
    }

    public function resolveDesignerTypeForSave(?User $user, Entity $entity): string
    {
        if (session('print_shop_order_id') && $user?->canManagePrintShopOrders()) {
            return self::DESIGNER_PRINT_SHOP;
        }

        if ($user && $user->isSuperAdmin() && $this->superadminSkipsEntityApproval()) {
            return self::DESIGNER_SUPERADMIN;
        }

        if ($user && $this->canEntityActAsDesigner($user, $entity)) {
            return self::DESIGNER_ENTITY;
        }

        return self::DESIGNER_ADMINISTRATION;
    }

    public function userCanStartNewDesign(User $user): bool
    {
        if ($this->isAdministrationSideUser($user) || $user->isSuperAdmin()) {
            return true;
        }

        if (! $user->isEntity()) {
            return false;
        }

        foreach ($user->accessibleEntityIds() as $entityId) {
            $entity = Entity::query()->find($entityId);
            if ($entity && $this->canEntityActAsDesigner($user, $entity)) {
                return true;
            }
        }

        return false;
    }

    public function requiresEntityApproval(DesignFormat $design): bool
    {
        $type = $design->designer_type ?? self::DESIGNER_ADMINISTRATION;

        // Superadmin (con flag .env): no pasa por aprobación de entidad.
        // Administración e imprenta: sí requieren aprobación.
        return in_array($type, [self::DESIGNER_ADMINISTRATION, self::DESIGNER_PRINT_SHOP], true);
    }

    public function isPrintShopDesign(DesignFormat $design): bool
    {
        return ($design->designer_type ?? '') === self::DESIGNER_PRINT_SHOP;
    }

    /**
     * Diseños de administración en borrador/rechazado solo los ve la administración hasta el envío.
     */
    public function isVisibleToEntityViewer(DesignFormat $design): bool
    {
        if ($this->isAwaitingEntityManagementFeeBeforeAdminDesign($design)) {
            return true;
        }

        if (app(ManagementFeeService::class)->entityOwesManagementFee($design)) {
            return true;
        }

        if (! $this->requiresEntityApproval($design)) {
            return true;
        }

        $status = $this->normalizedApprovalStatus($design->approval_status);

        return in_array($status, [self::STATUS_PENDING, self::STATUS_APPROVED], true);
    }

    public function isAdministrationSideUser(User $user): bool
    {
        return $this->userActsAsAdministration($user);
    }

    /**
     * Perfil activo de administración (panel, gestor admin o contexto de sesión).
     */
    public function userActsAsAdministration(User $user): bool
    {
        if ($user->isSuperAdmin() || $user->isAdministration() || $user->isAdministrationPanelAccount()) {
            return true;
        }

        $contextRole = session('context_role');
        if ($contextRole === 'entity') {
            return false;
        }
        if ($contextRole === 'administration') {
            return $user->managers()->whereNotNull('administration_id')->exists();
        }

        return $user->managers()->whereNotNull('administration_id')->exists();
    }

    /**
     * Gestor entidad (sin perfil admin activo) solo edita diseños creados por la propia entidad.
     * Diseños de administración, superadmin o imprenta son de solo lectura para la entidad
     * (aprobar/rechazar si aplica), aunque el superadmin omita la aprobación.
     * La administración sí puede editar tras la aprobación (vuelve a borrador al guardar).
     */
    public function canEntityEditDesign(User $user, DesignFormat $design): bool
    {
        if ($this->userActsAsAdministration($user)) {
            return true;
        }

        if (! $user->isEntity()) {
            return true;
        }

        return ($design->designer_type ?? self::DESIGNER_ADMINISTRATION) === self::DESIGNER_ENTITY;
    }

    public function canOpenDesignEditor(User $user, DesignFormat $design, bool $setLocked = false, bool $printOrderLocked = false): bool
    {
        if (! $this->canEntityEditDesign($user, $design)) {
            return false;
        }

        return ! $this->operationalDesignLockApplies($user, $design, ['locked' => $setLocked], ['locked' => $printOrderLocked]);
    }

    /**
     * Bloqueo operativo del set (ventas/asignaciones). La administración puede seguir editando
     * diseños que requieren aprobación de entidad hasta que haya una orden activa en imprenta.
     */
    public function operationalDesignLockApplies(
        User $user,
        DesignFormat $design,
        array $setLockContext,
        array $printOrderLockContext = ['locked' => false]
    ): bool {
        if (! empty($printOrderLockContext['locked'])) {
            return true;
        }

        if (empty($setLockContext['locked'])) {
            return false;
        }

        if ($this->userActsAsAdministration($user) && $this->requiresEntityApproval($design)) {
            return false;
        }

        return true;
    }

    public function managementFeePendingAfterApproval(DesignFormat $design): bool
    {
        if (! $design->set_id || ! $this->requiresEntityApproval($design)) {
            return false;
        }

        if ($this->normalizedApprovalStatus($design->approval_status) !== self::STATUS_APPROVED) {
            return false;
        }

        $design->loadMissing('set');

        return $design->set
            && ! app(ManagementFeeService::class)->isManagementFeeSettled($design->set);
    }

    public function requiresPreEditorPayment(User $user, ?Entity $entity = null): bool
    {
        if (! $entity) {
            return false;
        }

        $feeService = app(ManagementFeeService::class);
        if ($feeService->resolvePayer($entity) !== ManagementFeeService::PAYER_ENTITY) {
            return false;
        }

        // Switch 2 ON: cobro antes del editor (documento §3).
        return $this->entityDesignEnabled($entity);
    }

    public function normalizedApprovalStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return self::STATUS_DRAFT;
        }

        return $status;
    }

    public function assignDesignerTypeIfMissing(DesignFormat $design, ?User $user = null): void
    {
        $dirty = false;
        $design->loadMissing('entity');
        $entity = $design->entity;

        if (! $design->designer_type) {
            $design->designer_type = session('print_shop_order_id') && $user?->canManagePrintShopOrders()
                ? self::DESIGNER_PRINT_SHOP
                : (($user && $entity)
                    ? $this->resolveDesignerTypeForSave($user, $entity)
                    : self::DESIGNER_ADMINISTRATION);
            $dirty = true;
        } elseif ($user?->canManagePrintShopOrders() && session('print_shop_order_id')) {
            $status = $this->normalizedApprovalStatus($design->approval_status);
            if (in_array($status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)
                && $design->designer_type !== self::DESIGNER_PRINT_SHOP) {
                $design->designer_type = self::DESIGNER_PRINT_SHOP;
                $dirty = true;
            }
        } elseif ($user && $user->isSuperAdmin() && $this->superadminSkipsEntityApproval()) {
            if ($design->designer_type !== self::DESIGNER_SUPERADMIN) {
                $design->designer_type = self::DESIGNER_SUPERADMIN;
                $dirty = true;
            }
            // Sale del circuito de aprobación de entidad (pendiente/rechazado → borrador).
            if (in_array($this->normalizedApprovalStatus($design->approval_status), [self::STATUS_PENDING, self::STATUS_REJECTED], true)) {
                $design->approval_status = self::STATUS_DRAFT;
                $design->submitted_for_approval_at = null;
                $design->approval_decided_at = null;
                $design->approved_by_user_id = null;
                $design->approval_rejection_reason = null;
                $dirty = true;
            }
        } elseif ($user && $entity && $this->isAdministrationSideUser($user) && ! ($user->isSuperAdmin() && $this->superadminSkipsEntityApproval())) {
            $status = $this->normalizedApprovalStatus($design->approval_status);
            if (in_array($status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)
                && $design->designer_type !== self::DESIGNER_ADMINISTRATION) {
                $design->designer_type = self::DESIGNER_ADMINISTRATION;
                $dirty = true;
            }
        } elseif ($user && $entity && $this->canEntityActAsDesigner($user, $entity)) {
            $status = $this->normalizedApprovalStatus($design->approval_status);
            if (in_array($status, [self::STATUS_DRAFT, self::STATUS_REJECTED], true)
                && $design->designer_type !== self::DESIGNER_ENTITY) {
                $design->designer_type = self::DESIGNER_ENTITY;
                $dirty = true;
            }
        }

        if ($this->requiresEntityApproval($design) && ($design->approval_status === null || $design->approval_status === '')) {
            $design->approval_status = self::STATUS_DRAFT;
            $dirty = true;
        }

        if ($dirty) {
            $design->save();
        }
    }

    public function canSubmitForApproval(User $user, DesignFormat $design): bool
    {
        if (! $this->requiresEntityApproval($design)) {
            return false;
        }

        if (! in_array($this->normalizedApprovalStatus($design->approval_status), [self::STATUS_DRAFT, self::STATUS_REJECTED], true)) {
            return false;
        }

        if ($this->isPrintShopDesign($design)) {
            return $this->userCanSubmitDesignForApproval($user, $design);
        }

        if (! $user->canAccessEntity((int) $design->entity_id)) {
            return false;
        }

        return $this->userCanSubmitDesignForApproval($user, $design);
    }

    public function userCanSubmitDesignForApproval(User $user, DesignFormat $design): bool
    {
        if ($user->isEntityPanelAccount()) {
            return false;
        }

        if ($this->isPrintShopDesign($design)) {
            if (! session('print_shop_order_id') && $this->userActsAsAdministration($user)) {
                return false;
            }

            return $user->canManagePrintShopOrders()
                && $this->printShopCanSubmitDesign($user, $design);
        }

        // Gestor o cuenta con perfil entidad nunca envía a aprobación (aunque también gestione administración).
        if ($user->isEntity() && ! $user->isAdministration()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return $user->canAccessEntity((int) $design->entity_id);
        }

        if (! $user->isAdministration() && ! $user->isAdministrationPanelAccount()) {
            return false;
        }

        if (! $user->canAccessEntity((int) $design->entity_id)) {
            return false;
        }

        $design->loadMissing('entity');
        if (! $design->entity?->administration_id) {
            return false;
        }

        return in_array((int) $design->entity->administration_id, $user->accessibleAdministrationIds(), true);
    }

    public function printShopCanSubmitDesign(User $user, DesignFormat $design): bool
    {
        $query = PrintOrder::query()->where('design_format_id', $design->id);

        if ($user->isPrintShop() && ! $user->isSuperAdmin()) {
            $panelShopId = (int) ($user->panel_account_id ?? 0);
            if ($panelShopId > 0) {
                $query->where('print_configuration_id', $panelShopId);
            }
        }

        return $query->exists();
    }

    public function printShopCanEditDesign(DesignFormat $design): bool
    {
        if (! $this->isPrintShopDesign($design)) {
            return true;
        }

        return in_array($this->normalizedApprovalStatus($design->approval_status), [
            self::STATUS_DRAFT,
            self::STATUS_REJECTED,
        ], true);
    }

    public function canReviewApproval(User $user, DesignFormat $design): bool
    {
        if (! $this->requiresEntityApproval($design)) {
            return false;
        }

        if ($design->approval_status !== self::STATUS_PENDING) {
            return false;
        }

        return $user->isEntity()
            && ! $user->isAdministration()
            && $user->canAccessEntity((int) $design->entity_id);
    }

    public function isAwaitingEntityManagementFeeBeforeAdminDesign(DesignFormat $design): bool
    {
        if (! $design->set_id) {
            return false;
        }

        $design->loadMissing('set.entity');

        return $design->set
            && app(ManagementFeeService::class)->blocksAdminDesignUntilEntityPays($design->set);
    }

    public function submitForApproval(DesignFormat $design, User $user): DesignFormat
    {
        if (! $this->canSubmitForApproval($user, $design)) {
            abort(403, 'No puedes enviar este diseño a aprobación.');
        }

        if (empty(trim(strip_tags($design->participation_html ?? '')))) {
            abort(422, 'El diseño debe tener contenido antes de enviarlo a la entidad.');
        }

        $design->forceFill([
            'approval_status' => self::STATUS_PENDING,
            'submitted_for_approval_at' => now(),
            'approval_decided_at' => null,
            'approved_by_user_id' => null,
            'approval_rejection_reason' => null,
        ])->save();

        $this->notifyEntityDesignApprovalRequired($design->refresh());

        return $design;
    }

    private function notifyEntityDesignApprovalRequired(DesignFormat $design): void
    {
        if (! $this->requiresEntityApproval($design)) {
            return;
        }

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
            ->where(function ($query) {
                $query->where('is_primary', true)
                    ->orWhere('permission_design', true);
            })
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
                messageType: 'design_approval_pending',
                templateKey: 'design_approval_pending',
                mailClass: DesignApprovalPendingMail::class,
                mailPayload: ['design_format_id' => $design->id],
                context: ['set_id' => $design->set_id, 'entity_id' => $entity->id, 'design_format_id' => $design->id],
            );

            $emailsSent[$email] = true;
        }

        $entityEmail = trim((string) ($entity->email ?? ''));
        if ($entityEmail !== '' && ! isset($emailsSent[$entityEmail])) {
            $communicationEmailService->sendAndLog(
                recipientEmail: $entityEmail,
                recipientRole: 'entity',
                recipientUser: null,
                messageType: 'design_approval_pending',
                templateKey: 'design_approval_pending',
                mailClass: DesignApprovalPendingMail::class,
                mailPayload: ['design_format_id' => $design->id],
                context: ['set_id' => $design->set_id, 'entity_id' => $entity->id, 'design_format_id' => $design->id],
            );

            $emailsSent[$entityEmail] = true;
        }

        $panelUsers = User::query()
            ->where('panel_account_type', 'entity')
            ->where('panel_account_id', $entity->id)
            ->where('status', true)
            ->get();

        foreach ($panelUsers as $panelUser) {
            $email = trim((string) ($panelUser->email ?? ''));
            if ($email === '' || isset($emailsSent[$email])) {
                continue;
            }

            $communicationEmailService->sendAndLog(
                recipientEmail: $email,
                recipientRole: 'entity',
                recipientUser: $panelUser,
                messageType: 'design_approval_pending',
                templateKey: 'design_approval_pending',
                mailClass: DesignApprovalPendingMail::class,
                mailPayload: ['design_format_id' => $design->id],
                context: ['set_id' => $design->set_id, 'entity_id' => $entity->id, 'design_format_id' => $design->id],
            );

            $emailsSent[$email] = true;
        }
    }

    public function approve(DesignFormat $design, User $user): DesignFormat
    {
        if (! $this->canReviewApproval($user, $design)) {
            abort(403, 'No puedes aprobar este diseño.');
        }

        $design->forceFill([
            'approval_status' => self::STATUS_APPROVED,
            'approval_decided_at' => now(),
            'approved_by_user_id' => $user->id,
            'approval_rejection_reason' => null,
        ])->save();

        if ($design->set) {
            app(ManagementFeeService::class)->ensureSnapshot($design->set, $design);
        }

        return $design->refresh();
    }

    public function reject(DesignFormat $design, User $user, ?string $reason = null): DesignFormat
    {
        if (! $this->canReviewApproval($user, $design)) {
            abort(403, 'No puedes rechazar este diseño.');
        }

        $design->forceFill([
            'approval_status' => self::STATUS_REJECTED,
            'approval_decided_at' => now(),
            'approved_by_user_id' => null,
            'approval_rejection_reason' => $reason,
        ])->save();

        $this->reopenPrintOrdersAfterDesignRejection($design->refresh());

        return $design;
    }

    public function reopenPrintOrdersAfterDesignRejection(DesignFormat $design): void
    {
        PrintOrder::query()
            ->where('design_format_id', $design->id)
            ->whereIn('status', [
                PrintOrder::STATUS_SENT,
                PrintOrder::STATUS_IN_PRODUCTION,
            ])
            ->get()
            ->each(fn (PrintOrder $order) => $order->reopenForDesignCorrection(
                auth()->id(),
                'Pedido reabierto automáticamente: la entidad rechazó el diseño'
            ));
    }

    public function invalidateApprovalAfterEdit(DesignFormat $design, ?User $user = null): void
    {
        $user = $user ?? auth()->user();
        if ($user instanceof User && $user->isSuperAdmin() && $this->superadminSkipsEntityApproval()) {
            $this->assignDesignerTypeIfMissing($design, $user);

            return;
        }

        if (! $this->requiresEntityApproval($design)) {
            return;
        }

        if ($design->approval_status !== self::STATUS_APPROVED) {
            return;
        }

        $design->forceFill([
            'approval_status' => self::STATUS_DRAFT,
            'submitted_for_approval_at' => null,
            'approval_decided_at' => null,
            'approved_by_user_id' => null,
            'approval_rejection_reason' => null,
        ])->save();
    }

    public function designHasParticipationContent(DesignFormat $design): bool
    {
        return ! empty(trim(strip_tags((string) ($design->participation_html ?? ''))));
    }

    /**
     * Pedido de imprenta pagado o confirmado: la entidad ya comprometió el trabajo de impresión.
     */
    public function hasCommittedPrintOrder(DesignFormat $design): bool
    {
        return PrintOrder::query()
            ->where('design_format_id', $design->id)
            ->whereIn('payment_status', [
                PrintOrder::PAYMENT_STATUS_PAID,
                PrintOrder::PAYMENT_STATUS_NOT_REQUIRED,
            ])
            ->whereIn('status', [
                PrintOrder::STATUS_PENDING_REVIEW,
                PrintOrder::STATUS_IN_PRODUCTION,
                PrintOrder::STATUS_SENT,
            ])
            ->exists();
    }

    public function blocksQrExport(DesignFormat $design): bool
    {
        if ($this->isAwaitingEntityManagementFeeBeforeAdminDesign($design)) {
            return true;
        }

        if (! $this->designHasParticipationContent($design)) {
            return true;
        }

        if ($this->requiresEntityApproval($design) && $design->approval_status !== self::STATUS_APPROVED) {
            if ($this->isPrintShopDesign($design) || ! $this->hasCommittedPrintOrder($design)) {
                return true;
            }
        }

        if (! $design->set) {
            return false;
        }

        return app(ManagementFeeService::class)->blocksQrExport($design->set, $design);
    }

    public function blockMessage(DesignFormat $design): string
    {
        if ($this->isAwaitingEntityManagementFeeBeforeAdminDesign($design)) {
            $design->loadMissing('set.entity');
            $entity = $design->set?->entity;
            if ($entity && $this->entityDesignEnabled($entity)) {
                return 'La entidad debe abonar la cuota de gestión PARTILOT antes de acceder al editor y continuar con el diseño.';
            }

            return 'La entidad debe abonar la cuota de gestión PARTILOT para que pueda continuar editando el diseño de participación.';
        }

        if (! $this->designHasParticipationContent($design)) {
            return 'El diseño de participación aún no está creado. Debe completarse antes de generar PDFs o enviar a imprenta.';
        }

        if ($this->requiresEntityApproval($design) && $design->approval_status !== self::STATUS_APPROVED) {
            if ($this->isPrintShopDesign($design) || ! $this->hasCommittedPrintOrder($design)) {
                return match ($this->normalizedApprovalStatus($design->approval_status)) {
                    self::STATUS_PENDING => 'El diseño está pendiente de aprobación por la entidad.',
                    self::STATUS_REJECTED => $this->isPrintShopDesign($design)
                        ? 'El diseño fue rechazado por la entidad. La imprenta debe corregirlo y reenviarlo.'
                        : 'El diseño fue rechazado por la entidad. Debe corregirse y volver a enviarse.',
                    default => 'El diseño debe ser aprobado por la entidad antes de generar archivos con códigos QR.',
                };
            }
        }

        $design->loadMissing('set.entity');
        if ($design->set && $design->set->entity) {
            $payer = app(ManagementFeeService::class)->resolvePayer($design->set->entity);
            if ($payer === ManagementFeeService::PAYER_ENTITY) {
                return 'La entidad debe abonar la cuota de gestión PARTILOT antes de generar archivos con códigos QR.';
            }
        }

        return 'La cuota de gestión PARTILOT debe estar pagada antes de generar archivos con códigos QR.';
    }

    /**
     * Administración (o superadmin): muestra de 1 hoja con refs/QR en ceros mientras espera aprobación de entidad.
     */
    public function canDownloadPendingParticipationSample(?User $user, DesignFormat $design): bool
    {
        if (! $user) {
            return false;
        }

        if (! $user->isSuperAdmin() && ! $this->isAdministrationSideUser($user)) {
            return false;
        }

        if (! $this->designHasParticipationContent($design)) {
            return false;
        }

        if ($this->normalizedApprovalStatus($design->approval_status) !== self::STATUS_PENDING) {
            return false;
        }

        $design->loadMissing('set');
        if ($design->set
            && (int) ($design->set->digital_participations ?? 0) > 0
            && (int) ($design->set->physical_participations ?? 0) === 0) {
            return false;
        }

        return true;
    }

    public function statusLabel(?string $status): string
    {
        return match ($this->normalizedApprovalStatus($status)) {
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING => 'Pendiente aprobación entidad',
            self::STATUS_APPROVED => 'Aprobado por entidad',
            self::STATUS_REJECTED => 'Rechazado por entidad',
            default => '—',
        };
    }

    public function designerTypeLabel(?string $type): string
    {
        return match ($type) {
            self::DESIGNER_PRINT_SHOP => 'Imprenta PARTILOT',
            self::DESIGNER_ENTITY => 'Entidad',
            self::DESIGNER_ADMINISTRATION => 'Administración',
            self::DESIGNER_SUPERADMIN => 'Superadministrador',
            default => '—',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function buildSummaryContext(DesignFormat $design, User $user): array
    {
        return [
            'required' => $this->requiresEntityApproval($design),
            'status' => $design->approval_status,
            'status_label' => $this->statusLabel($design->approval_status),
            'can_submit' => $this->canSubmitForApproval($user, $design),
            'can_review' => $this->canReviewApproval($user, $design),
            'submitted_at' => $design->submitted_for_approval_at,
            'decided_at' => $design->approval_decided_at,
            'rejection_reason' => $design->approval_rejection_reason,
            'blocks_export' => $this->blocksQrExport($design),
        ];
    }
}
