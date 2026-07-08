<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PendingEntityManagerInvitation extends Model
{
    protected $fillable = [
        'email',
        'entity_id',
        'is_primary',
        'permission_sellers',
        'permission_design',
        'permission_statistics',
        'permission_payments',
        'confirmation_token',
        'confirmation_sent_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'permission_sellers' => 'boolean',
        'permission_design' => 'boolean',
        'permission_statistics' => 'boolean',
        'permission_payments' => 'boolean',
        'confirmation_sent_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function findByToken(string $token): ?self
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return static::query()
            ->where('confirmation_token', $token)
            ->first();
    }

    public static function issueToken(): string
    {
        return Str::random(64);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function storeInvitation(int $entityId, string $email, array $attributes = []): self
    {
        $normalizedEmail = static::normalizeEmail($email);

        return static::query()->updateOrCreate(
            [
                'entity_id' => $entityId,
                'email' => $normalizedEmail,
            ],
            array_merge($attributes, [
                'confirmation_token' => static::issueToken(),
                'confirmation_sent_at' => now(),
            ])
        );
    }
}
