<?php

namespace App\Models;

use App\Mail\TransferCollectionConfirmationMail;
use App\Services\AppInboxNotificationService;
use App\Services\CommunicationEmailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ParticipationCollection extends Model
{
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REVERTED = 'reverted';

    protected $fillable = [
        'user_id',
        'nombre',
        'apellidos',
        'nif',
        'iban',
        'importe_total',
        'status',
        'confirmation_token',
        'confirmation_sent_at',
        'verified_at',
        'expires_at',
        'collected_at',
        'sepa_payment_order_id',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'importe_total' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ParticipationCollectionItem::class, 'collection_id');
    }

    public function participations()
    {
        return $this->belongsToMany(Participation::class, 'participation_collection_items', 'collection_id', 'participation_id');
    }

    public function sepaPaymentOrder(): BelongsTo
    {
        return $this->belongsTo(SepaPaymentOrder::class);
    }

    public function scopePendingVerification($query)
    {
        return $query->where('status', self::STATUS_PENDING_VERIFICATION)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    /** Verificadas, sin orden SEPA asignada y no revertidas (pendientes de gestionar). */
    public function scopePending($query)
    {
        $query = $query->verified();
        if (Schema::hasColumn('participation_collections', 'sepa_payment_order_id')) {
            $query->whereNull('sepa_payment_order_id');
        }

        if (Schema::hasTable('sepa_payment_beneficiaries')) {
            $query->whereNotIn('participation_collections.id', function ($sub) {
                $sub->select('participation_collection_id')
                    ->from('sepa_payment_beneficiaries')
                    ->where('status', 'reverted')
                    ->whereNotNull('participation_collection_id');
            });
        }

        return $query;
    }

    public function isPendingVerification(): bool
    {
        return $this->status === self::STATUS_PENDING_VERIFICATION;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING_VERIFICATION => 'No verificada',
            self::STATUS_VERIFIED => 'Pendiente de gestionar',
            self::STATUS_EXPIRED => 'Expirada',
            self::STATUS_CANCELLED => 'Cancelada',
            self::STATUS_REVERTED => 'Revertida (cobrable)',
            default => $this->status ?? '—',
        };
    }

    public static function generateConfirmationToken(): string
    {
        return Str::random(64);
    }

    public static function verificationExpiryHours(): int
    {
        return (int) config('partilot.transfer_collection_verify_hours', 48);
    }

    /** IDs de participaciones reservadas en solicitudes pendientes de verificación. */
    public static function reservedParticipationIds(): array
    {
        return ParticipationCollectionItem::query()
            ->whereHas('collection', fn ($q) => $q->pendingVerification())
            ->pluck('participation_id')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    public function confirmVerification(): void
    {
        if (! $this->isPendingVerification() || $this->isExpired()) {
            throw new \RuntimeException('La solicitud no puede confirmarse.');
        }

        DB::transaction(function () {
            $collection = self::query()->whereKey($this->id)->lockForUpdate()->first();
            if (! $collection || ! $collection->isPendingVerification() || $collection->isExpired()) {
                throw new \RuntimeException('La solicitud no puede confirmarse.');
            }

            app(\App\Services\EntityLotteryPrizePaymentService::class)
                ->reserveDepositedFundsForVerifiedCollection($collection);

            $now = now();
            $participationIds = $collection->items()->pluck('participation_id')->unique()->filter()->values()->all();

            $collection->update([
                'status' => self::STATUS_VERIFIED,
                'verified_at' => $now,
                'collected_at' => $now,
                'confirmation_token' => null,
            ]);

            if ($participationIds !== []) {
                Participation::whereIn('id', $participationIds)->update(['collected_at' => $now]);
            }

            $this->refresh();
            $this->sendVerifiedNotifications();

            app(\App\Services\PaymentOperationAuditService::class)->log(
                operationType: \App\Models\PaymentOperationAuditLog::OP_COLLECTION_VERIFIED,
                userId: $collection->user_id ? (int) $collection->user_id : null,
                amount: (float) $collection->importe_total,
                referenceType: 'participation_collection',
                referenceId: (int) $collection->id,
                context: ['participation_ids' => $participationIds],
            );
        });
    }

    public function cancelVerification(): void
    {
        if (!$this->isPendingVerification()) {
            return;
        }

        $this->update(['status' => self::STATUS_CANCELLED, 'confirmation_token' => null]);
        $this->delete();
    }

    /**
     * Error bancario / revertir a cobrable: libera participaciones y cierra la solicitud
     * para que no vuelva a aparecer en pendientes de gestionar.
     */
    public function revertAsCobrable(): void
    {
        $participationIds = $this->items()
            ->pluck('participation_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($participationIds !== []) {
            $updates = ['collected_at' => null];
            if (Schema::hasColumn('participations', 'status')) {
                Participation::whereIn('id', $participationIds)->update(array_merge($updates, ['status' => 'vendida']));
            } else {
                Participation::whereIn('id', $participationIds)->update($updates);
            }
        }

        $this->update([
            'status' => self::STATUS_REVERTED,
            'sepa_payment_order_id' => null,
            'confirmation_token' => null,
        ]);
        $this->delete();
    }

    public function markAsExpired(): void
    {
        if (!$this->isPendingVerification()) {
            return;
        }

        $this->update(['status' => self::STATUS_EXPIRED, 'confirmation_token' => null]);
        $this->delete();
    }

    protected function sendVerifiedNotifications(): void
    {
        $this->load(['user', 'items.participation.set.entity']);
        $user = $this->user;
        if (!$user) {
            return;
        }

        try {
            $first = $this->items->first()?->participation;
            $entity = $first?->set?->entity;
            if ($entity) {
                $inbox = app(AppInboxNotificationService::class);
                $senderId = $inbox->resolveSenderIdForEntity((int) $entity->id) ?? (int) $user->id;
                $inbox->notifyUser(
                    (int) $user->id,
                    (int) $entity->id,
                    $entity->administration_id ? (int) $entity->administration_id : null,
                    $senderId,
                    'cobro_registrado',
                    $entity->name,
                    'Tu solicitud de cobro por transferencia ha sido confirmada. La entidad gestionará el pago.',
                    [
                        'collection_id' => $this->id,
                        'rol_context' => 'usuario',
                        'importe_total' => (float) $this->importe_total,
                    ]
                );
            }
        } catch (\Throwable $e) {
            \Log::warning('Inbox cobro confirmado: ' . $e->getMessage());
        }

        try {
            app(CommunicationEmailService::class)->sendAndLog(
                recipientEmail: (string) $user->email,
                recipientRole: 'usuario',
                recipientUser: $user,
                messageType: 'transfer_collection_confirmation',
                templateKey: null,
                mailClass: TransferCollectionConfirmationMail::class,
                mailPayload: ['collection_id' => $this->id],
                context: ['source' => 'verification', 'user_id' => $user->id],
            );
        } catch (\Throwable $e) {
            \Log::warning('Fallo enviando confirmación post-verificación: ' . $e->getMessage());
        }
    }

    /**
     * Al borrar la solicitud de cobro, poner collected_at en null en las participaciones vinculadas.
     */
    protected static function booted(): void
    {
        static::deleting(function (ParticipationCollection $collection) {
            $participationIds = $collection->items()->pluck('participation_id')->unique()->filter()->values()->all();
            if ($participationIds !== [] && Schema::hasColumn('participations', 'collected_at')) {
                Participation::whereIn('id', $participationIds)->update(['collected_at' => null]);
            }
        });
    }
}
