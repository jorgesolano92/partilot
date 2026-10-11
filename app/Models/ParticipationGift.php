<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ParticipationGift extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'participation_id',
        'from_user_id',
        'to_user_id',
        'to_email',
        'status',
        'message',
        'claim_token',
        'accepted_at',
        'rejected_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    public function recipientEmail(): string
    {
        return (string) ($this->toUser?->email ?? $this->to_email ?? '');
    }

    public function registrationUrl(): ?string
    {
        if (! $this->claim_token) {
            return null;
        }

        return url('/registro-regalo/'.$this->claim_token);
    }

    public static function generateClaimToken(): string
    {
        return Str::random(48);
    }
}
