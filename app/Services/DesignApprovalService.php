<?php

namespace App\Services;

use App\Models\DesignFormat;
use App\Models\User;

class DesignApprovalService
{
    public const DESIGNER_ADMINISTRATION = 'administration';

    public const DESIGNER_ENTITY = 'entity';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function resolveDesignerType(User $user): string
    {
        return $user->isEntity() ? self::DESIGNER_ENTITY : self::DESIGNER_ADMINISTRATION;
    }

    public function requiresEntityApproval(DesignFormat $design): bool
    {
        return ($design->designer_type ?? self::DESIGNER_ADMINISTRATION) === self::DESIGNER_ADMINISTRATION;
    }

    public function requiresPreEditorPayment(User $user): bool
    {
        return $this->resolveDesignerType($user) === self::DESIGNER_ENTITY;
    }

    public function assignDesignerTypeIfMissing(DesignFormat $design, ?User $user = null): void
    {
        if ($design->designer_type) {
            return;
        }

        $designerType = $user
            ? $this->resolveDesignerType($user)
            : self::DESIGNER_ADMINISTRATION;
        $design->designer_type = $designerType;
        if ($designerType === self::DESIGNER_ADMINISTRATION && ! $design->approval_status) {
            $design->approval_status = self::STATUS_DRAFT;
        }
        $design->save();
    }

    public function canSubmitForApproval(User $user, DesignFormat $design): bool
    {
        if (! $this->requiresEntityApproval($design)) {
            return false;
        }

        if (! in_array($design->approval_status, [self::STATUS_DRAFT, self::STATUS_REJECTED, null], true)) {
            return false;
        }

        return $user->isAdministration() && $user->canAccessEntity((int) $design->entity_id);
    }

    public function canReviewApproval(User $user, DesignFormat $design): bool
    {
        if ($design->approval_status !== self::STATUS_PENDING) {
            return false;
        }

        return $user->isEntity() && $user->canAccessEntity((int) $design->entity_id);
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

        return $design->refresh();
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

        return $design->refresh();
    }

    public function invalidateApprovalAfterEdit(DesignFormat $design): void
    {
        if (! $this->requiresEntityApproval($design)) {
            return;
        }

        if ($design->approval_status !== self::STATUS_APPROVED) {
            return;
        }

        $design->forceFill([
            'approval_status' => self::STATUS_PENDING,
            'submitted_for_approval_at' => now(),
            'approval_decided_at' => null,
            'approved_by_user_id' => null,
            'approval_rejection_reason' => null,
        ])->save();
    }

    public function blocksQrExport(DesignFormat $design): bool
    {
        if ($this->requiresEntityApproval($design) && $design->approval_status !== self::STATUS_APPROVED) {
            return true;
        }

        if (! $design->set) {
            return false;
        }

        return app(ManagementFeeService::class)->blocksQrExport($design->set, $design);
    }

    public function blockMessage(DesignFormat $design): string
    {
        if ($this->requiresEntityApproval($design) && $design->approval_status !== self::STATUS_APPROVED) {
            return match ($design->approval_status) {
                self::STATUS_PENDING => 'El diseño está pendiente de aprobación por la entidad.',
                self::STATUS_REJECTED => 'El diseño fue rechazado por la entidad. Debe corregirse y volver a enviarse.',
                default => 'El diseño debe ser aprobado por la entidad antes de generar archivos con códigos QR.',
            };
        }

        return 'La cuota de gestión PARTILOT debe estar pagada antes de generar archivos con códigos QR.';
    }

    public function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_PENDING => 'Pendiente aprobación entidad',
            self::STATUS_APPROVED => 'Aprobado por entidad',
            self::STATUS_REJECTED => 'Rechazado por entidad',
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
