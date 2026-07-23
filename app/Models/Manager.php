<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manager extends Model
{
    use HasFactory;
    
    protected $fillable = [
        "user_id",
        "contact_email",
        "contact_name",
        "contact_last_name",
        "contact_last_name2",
        "contact_nif_cif",
        "contact_birthday",
        "contact_phone",
        "contact_comment",
        "contact_image",
        "entity_id",
        "administration_id",
        "is_primary",
        "pending_primary",
        "permission_sellers",
        "permission_design",
        "permission_statistics",
        "permission_payments",
        "confirmation_token",
        "confirmation_sent_at",
        "role_invitation_reminder_sent_at",
        "requires_password_setup",
        "user_created_for_invitation",
        "status",
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'pending_primary' => 'boolean',
        'permission_sellers' => 'boolean',
        'permission_design' => 'boolean',
        'permission_statistics' => 'boolean',
        'permission_payments' => 'boolean',
        'confirmation_sent_at' => 'datetime',
        'role_invitation_reminder_sent_at' => 'datetime',
        'requires_password_setup' => 'boolean',
        'user_created_for_invitation' => 'boolean',
        'contact_birthday' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function administration()
    {
        return $this->belongsTo(Administration::class);
    }

    public function isAdministrationContactProfile(): bool
    {
        return $this->administration_id !== null
            && $this->entity_id === null
            && $this->is_primary;
    }

    public function usesStoredContactProfile(): bool
    {
        return $this->isAdministrationContactProfile() && $this->user_id === null;
    }

    public function hasContactData(): bool
    {
        if ($this->usesStoredContactProfile()) {
            return trim((string) ($this->contact_name ?? '')) !== ''
                || trim((string) ($this->contact_email ?? '')) !== '';
        }

        return $this->user !== null;
    }

    public function contactField(string $field): mixed
    {
        $contactKey = 'contact_'.$field;
        $fromContact = $this->{$contactKey} ?? null;

        if ($fromContact !== null && $fromContact !== '') {
            return $fromContact;
        }

        return $this->user?->{$field};
    }

    public function resolvedContactEmail(): string
    {
        return trim((string) ($this->contactField('email') ?? ''));
    }

    public function resolvedContactImage(): ?string
    {
        $image = trim((string) ($this->contact_image ?? ''));
        if ($image !== '') {
            return $image;
        }

        $userImage = trim((string) ($this->user?->image ?? ''));

        return $userImage !== '' ? $userImage : null;
    }

    public function resolvedContactFullName(): string
    {
        return trim(implode(' ', array_filter([
            (string) ($this->contactField('name') ?? ''),
            (string) ($this->contactField('last_name') ?? ''),
            (string) ($this->contactField('last_name2') ?? ''),
        ])));
    }

    public function resolvedContactBirthdayInput(): string
    {
        $birthday = $this->contactField('birthday');
        if ($birthday instanceof \DateTimeInterface) {
            return $birthday->format('Y-m-d');
        }

        return is_string($birthday) ? $birthday : '';
    }

    /**
     * Pendiente de activación / aceptación de invitación o cargo.
     */
    public function isPendingActivation(): bool
    {
        if ($this->pending_primary) {
            return true;
        }

        if (! empty($this->confirmation_token)) {
            return true;
        }

        return $this->status === null || (int) $this->status === -1;
    }

    public function statusLabel(): string
    {
        if ($this->isPendingActivation()) {
            return 'Pendiente';
        }

        return (int) $this->status === 1 ? 'Activo' : 'Inactivo';
    }

    public function statusBadgeClass(): string
    {
        if ($this->isPendingActivation()) {
            return 'bg-secondary';
        }

        return (int) $this->status === 1 ? 'bg-success' : 'bg-danger';
    }

    /**
     * Ha registrado aceptación del rol (gestor / gestor responsable) en el marco legal.
     */
    public function hasAcceptedRoleLegal(): bool
    {
        if (! $this->user_id) {
            return false;
        }

        $actions = ($this->is_primary || $this->pending_primary)
            ? [
                LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR_RESPONSABLE,
                LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR,
            ]
            : [LegalAcceptance::ACTION_ACEPTACION_ROL_GESTOR];

        return LegalAcceptance::query()
            ->where('user_id', $this->user_id)
            ->whereIn('action', $actions)
            ->where('result', LegalAcceptance::RESULT_ACEPTADO)
            ->exists();
    }
}
