<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
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
        'rejected_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'permission_sellers' => 'boolean',
        'permission_design' => 'boolean',
        'permission_statistics' => 'boolean',
        'permission_payments' => 'boolean',
        'confirmation_sent_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public static function ensureRejectedAtColumn(): void
    {
        if (! Schema::hasTable('pending_entity_manager_invitations')) {
            return;
        }

        if (! Schema::hasColumn('pending_entity_manager_invitations', 'rejected_at')) {
            Schema::table('pending_entity_manager_invitations', function ($table) {
                $table->timestamp('rejected_at')->nullable()->after('confirmation_sent_at');
            });
        }
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function findByToken(string $token): ?self
    {
        static::ensureRejectedAtColumn();

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        return static::query()
            ->where('confirmation_token', $token)
            ->whereNull('rejected_at')
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
        static::ensureRejectedAtColumn();

        $normalizedEmail = static::normalizeEmail($email);

        return static::query()->updateOrCreate(
            [
                'entity_id' => $entityId,
                'email' => $normalizedEmail,
            ],
            array_merge($attributes, [
                'confirmation_token' => static::issueToken(),
                'confirmation_sent_at' => now(),
                'rejected_at' => null,
            ])
        );
    }
}
