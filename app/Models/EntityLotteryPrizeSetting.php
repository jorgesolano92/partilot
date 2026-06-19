<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityLotteryPrizeSetting extends Model
{
    public const MODE_PRESENCIAL = 'presencial';

    public const MODE_ONLINE = 'online';

    public const FUNDS_NOT_REQUIRED = 'not_required';

    public const FUNDS_PENDING = 'pending';

    public const FUNDS_CONFIRMED = 'confirmed';

    public const CONTRACT_NOT_REQUIRED = 'not_required';

    public const CONTRACT_PENDING = 'pending';

    public const CONTRACT_SIGNED = 'signed';

    public const DEFAULT_BLOCKED_MESSAGE = 'Enhorabuena!! Tu participación tiene un premio de {amount}€. Estamos en contacto con la entidad para habilitar el cobro lo antes posible.';

    public const DEFAULT_UNLOCKED_MESSAGE = 'Enhorabuena!! Tu participación tiene un premio de {amount}€.';

    /** Mensaje genérico LOPD cuando el premio ya fue gestionado por otra vía. */
    public const LOPD_ALREADY_MANAGED_MESSAGE = 'Esta participación ya ha sido gestionada.';

    protected $fillable = [
        'entity_id',
        'lottery_id',
        'prize_payment_mode',
        'mode_locked_at',
        'mode_locked_by_user_id',
        'has_sold_digital_participations',
        'funds_required_amount',
        'funds_deposited_amount',
        'funds_status',
        'funds_confirmed_at',
        'funds_confirmed_by_user_id',
        'contract_status',
        'contract_signed_at',
        'contract_signed_by_user_id',
        'contract_signer_name',
        'contract_token',
        'contract_sent_at',
        'online_payments_enabled',
        'presencial_payments_enabled',
        'blocked_user_message',
        'unlocked_user_message',
        'presencial_contact_text',
        'presencial_contact_address',
        'presencial_contact_city',
        'presencial_contact_province',
        'presencial_contact_schedule',
        'presencial_contact_phone',
        'presencial_contact_email',
        'presencial_contact_notes',
    ];

    protected $casts = [
        'mode_locked_at' => 'datetime',
        'funds_confirmed_at' => 'datetime',
        'contract_signed_at' => 'datetime',
        'contract_sent_at' => 'datetime',
        'has_sold_digital_participations' => 'boolean',
        'online_payments_enabled' => 'boolean',
        'presencial_payments_enabled' => 'boolean',
        'funds_required_amount' => 'decimal:2',
        'funds_deposited_amount' => 'decimal:2',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    public function modeLockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mode_locked_by_user_id');
    }

    public function contractSignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contract_signed_by_user_id');
    }

    public function activationLogs(): HasMany
    {
        return $this->hasMany(EntityLotteryPrizeActivationLog::class);
    }

    public function isModePresencial(): bool
    {
        return $this->prize_payment_mode === self::MODE_PRESENCIAL;
    }

    public function isModeOnline(): bool
    {
        return $this->prize_payment_mode === self::MODE_ONLINE;
    }

    public function fundsAreConfirmed(): bool
    {
        return $this->funds_status === self::FUNDS_CONFIRMED
            || $this->funds_status === self::FUNDS_NOT_REQUIRED;
    }

    public function contractIsSatisfied(): bool
    {
        return in_array($this->contract_status, [self::CONTRACT_NOT_REQUIRED, self::CONTRACT_SIGNED], true);
    }

    public function fundsStatusLabel(): string
    {
        return match ($this->funds_status) {
            self::FUNDS_PENDING => 'Pendiente',
            self::FUNDS_CONFIRMED => 'Confirmado',
            default => 'No requerido',
        };
    }

    public function modeLabel(): string
    {
        return match ($this->prize_payment_mode) {
            self::MODE_ONLINE => 'Online (PARTILOT)',
            self::MODE_PRESENCIAL => 'Presencial',
            default => '—',
        };
    }

    public function activationSummary(): string
    {
        if ($this->isModeOnline()) {
            return $this->online_payments_enabled ? 'Cobro online activo' : 'Cobro online bloqueado';
        }

        return $this->presencial_payments_enabled ? 'Presencial activo' : 'Presencial bloqueado';
    }
}
