<?php

namespace App\Models;

use App\Support\PendingDigitalSaleLinkCode;
use App\Support\PendingDigitalSaleContactMask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PendingDigitalSale extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'email',
        'buyer_phone',
        'notify_channel',
        'seller_id',
        'entity_id',
        'lottery_id',
        'set_id',
        'quantity',
        'sale_amount',
        'payment_method',
        'registration_token',
        'link_code',
        'buyer_sms_sent_count',
        'status',
        'valid_until',
        'completed_at',
        'completed_user_id',
    ];

    protected $casts = [
        'valid_until' => 'datetime',
        'completed_at' => 'datetime',
        'sale_amount' => 'decimal:2',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    public function participations(): BelongsToMany
    {
        return $this->belongsToMany(
            Participation::class,
            'pending_digital_sale_participations',
            'pending_digital_sale_id',
            'participation_id'
        );
    }

    public function completedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_user_id');
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public function isExpired(): bool
    {
        if (! $this->valid_until) {
            return true;
        }

        return $this->valid_until->lte(now());
    }

    public function isStillValid(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->valid_until
            && ! $this->isExpired();
    }

    public function scopePendingNotExpired($query)
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where('valid_until', '>=', now());
    }

    public function registrationUrl(): string
    {
        $url = url(config('digital_sale.registration_path', 'registro-comprador').'/'.$this->registration_token);
        if ($this->link_code) {
            $url .= '?codigo='.urlencode((string) $this->link_code);
        }

        return $url;
    }

    /** Enlace para compartir con el comprador (sin exponer el código al vendedor). */
    public function registrationUrlForShare(): string
    {
        return url(config('digital_sale.registration_path', 'registro-comprador').'/'.$this->registration_token);
    }

    /** Garantiza código de vinculación (ventas anteriores a la migración). */
    public function ensureLinkCode(): self
    {
        if (! $this->link_code) {
            $this->forceFill([
                'link_code' => PendingDigitalSaleLinkCode::generateUnique(),
            ])->save();
            $this->refresh();
        }

        return $this;
    }

    public function maskedBuyerContact(): ?string
    {
        return PendingDigitalSaleContactMask::forPending(
            $this->email,
            $this->buyer_phone,
            $this->notify_channel
        );
    }

    public function usesPhoneChannel(): bool
    {
        return in_array($this->notify_channel, ['sms', 'whatsapp'], true);
    }

    public function usesEmailChannel(): bool
    {
        return $this->notify_channel === 'email' || ($this->email && ! $this->usesPhoneChannel());
    }
}
