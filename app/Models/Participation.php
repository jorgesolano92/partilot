<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Participation extends Model
{
    use HasFactory;

    public const WALLET_MODE_DIGITAL = 'digital';

    public const WALLET_MODE_STORAGE = 'storage';

    protected $fillable = [
        'entity_id',
        'set_id',
        'design_format_id',
        'participation_number',
        'participation_code',
        'book_number',
        'status',
        'seller_id',
        'sale_date',
        'sale_time',
        'sale_amount',
        'payment_method',
        'buyer_name',
        'wallet_mode',
        'buyer_phone',
        'buyer_email',
        'buyer_nif',
        'collected_at',
        'donated_at',
        'return_date',
        'return_time',
        'return_reason',
        'returned_by',
        'cancellation_date',
        'cancellation_reason',
        'cancelled_by',
        'notes',
        'metadata'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'sale_time' => 'datetime:H:i',
        'sale_amount' => 'decimal:2',
        'return_date' => 'date',
        'return_time' => 'datetime:H:i',
        'cancellation_date' => 'date',
        'collected_at' => 'datetime',
        'donated_at' => 'datetime',
        'metadata' => 'array'
    ];

    // Relaciones
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function set()
    {
        return $this->belongsTo(Set::class);
    }

    public function designFormat()
    {
        return $this->belongsTo(DesignFormat::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Usuario de cartera / comprador en app: buyer_name guarda su id (string), no el nombre.
     */
    public function walletOwner()
    {
        return $this->belongsTo(User::class, 'buyer_name', 'id');
    }

    public function buyerNameIsWalletUserId(): bool
    {
        $key = trim((string) ($this->buyer_name ?? ''));

        return $key !== '' && ctype_digit($key);
    }

    public function isWalletStorage(): bool
    {
        return $this->wallet_mode === self::WALLET_MODE_STORAGE;
    }

    public function isWalletDigital(): bool
    {
        return $this->wallet_mode === self::WALLET_MODE_DIGITAL
            || ($this->buyerNameIsWalletUserId() && $this->wallet_mode === null);
    }

    /**
     * Participaciones que deben cobrarse online (nativas 1D/ o físicas digitalizadas en cartera).
     */
    public function requiresOnlinePrizeCollection(): bool
    {
        $code = (string) ($this->participation_code ?? '');

        if (str_starts_with($code, '1D/')) {
            return true;
        }

        $this->loadMissing('set');

        if (($this->set?->digital_participations ?? 0) > 0
            && (int) ($this->set?->physical_participations ?? 0) <= 0) {
            return true;
        }

        return $this->wallet_mode === self::WALLET_MODE_DIGITAL;
    }

    public function returnedBy()
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function activityLogs()
    {
        return $this->hasMany(ParticipationActivityLog::class)->orderBy('created_at', 'desc');
    }

    public function gift()
    {
        return $this->hasOne(ParticipationGift::class);
    }

    public function pendingDigitalSales()
    {
        return $this->belongsToMany(
            PendingDigitalSale::class,
            'pending_digital_sale_participations',
            'participation_id',
            'pending_digital_sale_id'
        );
    }

    public function activePendingDigitalSale(): ?PendingDigitalSale
    {
        return $this->pendingDigitalSales()
            ->where('pending_digital_sales.status', PendingDigitalSale::STATUS_PENDING)
            ->orderByDesc('pending_digital_sales.id')
            ->first();
    }

    // Scopes para consultas comunes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'disponible');
    }

    /** Ventas registradas: vendida, pagada (cobrada) y donada (sigue siendo vendida en BD). */
    public function scopeSold($query)
    {
        return $query->whereIn('status', ['vendida', 'pagada']);
    }

    /**
     * Vendidas para escrutinio: definitivas + digitales pendientes de vinculación (venta ya hecha, comprador sin registrar).
     */
    public function scopeSoldForScrutiny($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'vendida')
                ->orWhere(function ($q2) {
                    $q2->where('status', 'reserva_venta_digital')
                        ->whereHas('pendingDigitalSales', function ($pds) {
                            $pds->where('pending_digital_sales.status', PendingDigitalSale::STATUS_PENDING);
                        });
                });
        });
    }

    /**
     * Participaciones que cuentan para liquidar al vendedor.
     * Incluye: asignada, vendida, pagada, reserva_venta_digital (venta digital pendiente).
     * Excluye: disponible, devuelta, anulada, perdida, reservada.
     * Las donadas siguen como vendida en BD.
     */
    public function scopeEligibleForSellerSettlement($query, int $sellerId)
    {
        return $query->where(function ($q) use ($sellerId) {
            $q->where(function ($q2) use ($sellerId) {
                $q2->where('seller_id', $sellerId)
                    ->whereIn('status', ['asignada', 'vendida', 'pagada']);
            })->orWhere(function ($q2) use ($sellerId) {
                $q2->where('status', 'reserva_venta_digital')
                    ->whereHas('pendingDigitalSales', function ($pds) use ($sellerId) {
                        $pds->where('pending_digital_sales.seller_id', $sellerId)
                            ->where('pending_digital_sales.status', PendingDigitalSale::STATUS_PENDING);
                    });
            });
        });
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'devuelta');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'anulada');
    }

    public function scopeBySet($query, $setId)
    {
        return $query->where('set_id', $setId);
    }

    public function scopeByBook($query, $bookNumber, $setId)
    {
        return $query->where('book_number', $bookNumber)
                    ->where('set_id', $setId);
    }

    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    // Métodos de utilidad
    public function isAvailable()
    {
        return $this->status === 'disponible';
    }

    public function isSold()
    {
        return in_array($this->status, ['vendida', 'pagada'], true);
    }

    public function isReturned()
    {
        return $this->status === 'devuelta';
    }

    public function isCancelled()
    {
        return $this->status === 'anulada';
    }

    public function isReserved()
    {
        return $this->status === 'reservada';
    }

    public function isLost()
    {
        return $this->status === 'perdida';
    }

    // Método para marcar como vendida (payment_method por participación para Tarea 3 QR)
    public function markAsSold($sellerId, $saleAmount = null, $buyerInfo = [], $paymentMethod = null)
    {
        $walletUser = null;
        if (! empty($buyerInfo['user_id'])) {
            $walletUser = User::find($buyerInfo['user_id']);
        } elseif (! empty($buyerInfo['user']) && $buyerInfo['user'] instanceof User) {
            $walletUser = $buyerInfo['user'];
        } elseif (! empty($buyerInfo['name']) && ctype_digit((string) $buyerInfo['name'])) {
            // Legacy: buyerInfo['name'] = (string) user_id en ventas digitales.
            $walletUser = User::find((int) $buyerInfo['name']);
        }

        $data = [
            'status' => 'vendida',
            'seller_id' => $sellerId,
            'sale_date' => now()->toDateString(),
            'sale_time' => now()->toTimeString(),
            'sale_amount' => $saleAmount,
            'buyer_nif' => $buyerInfo['nif'] ?? null,
        ];

        if ($walletUser) {
            \App\Services\ParticipationOwnerService::assignOwner($this, $walletUser);
            $data['buyer_name'] = $this->buyer_name;
            $data['buyer_email'] = $this->buyer_email;
            $data['buyer_phone'] = $this->buyer_phone;
        } else {
            // Sin usuario app: buyer_name puede ser texto libre (venta física).
            $data['buyer_name'] = $buyerInfo['name'] ?? null;
            $data['buyer_phone'] = $buyerInfo['phone'] ?? null;
            $data['buyer_email'] = $buyerInfo['email'] ?? null;
        }

        if ($paymentMethod !== null) {
            $data['payment_method'] = $paymentMethod;
        }
        $this->update($data);
    }

    // Método para marcar como devuelta
    public function markAsReturned($reason = null, $returnedBy = null)
    {
        $this->update([
            'status' => 'devuelta',
            'return_date' => now()->toDateString(),
            'return_time' => now()->toTimeString(),
            'return_reason' => $reason,
            'returned_by' => $returnedBy,
        ]);
    }

    // Método para marcar como anulada
    public function markAsCancelled($reason = null, $cancelledBy = null)
    {
        $this->update([
            'status' => 'anulada',
            'cancellation_date' => now()->toDateString(),
            'cancellation_reason' => $reason,
            'cancelled_by' => $cancelledBy,
        ]);
    }

    // Método para reservar
    public function reserve()
    {
        $this->update(['status' => 'reservada']);
    }

    // Método para liberar reserva
    public function release()
    {
        $this->update(['status' => 'disponible']);
    }

    // Método para marcar como perdida
    public function markAsLost()
    {
        $this->update(['status' => 'perdida']);
    }

    /**
     * Código de participación para mostrar en UI. Los digitales se guardan como 1D/00001 y se muestran como 1/00001.
     */
    public function getDisplayParticipationCodeAttribute()
    {
        $code = $this->participation_code ?? '';
        if (str_starts_with($code, '1D/')) {
            return '1/' . substr($code, 3);
        }
        return $code;
    }

    // Método para obtener el estado en español (si último log es returned_by_seller y status disponible → "DISPONIBLE DV")
    public function getStatusTextAttribute()
    {
        $statuses = [
            'disponible' => 'Disponible',
            'reservada' => 'Reservada',
            'vendida' => 'Vendida',
            'devuelta' => 'Devuelta',
            'anulada' => 'Anulada',
            'perdida' => 'Perdida',
            'asignada' => 'Asignada',
            'pagada' => 'Pagada',
            'reserva_venta_digital' => 'Reservada (venta digital)',
        ];

        if ($this->status === 'disponible') {
            $lastLog = $this->relationLoaded('activityLogs')
                ? $this->activityLogs->sortByDesc('created_at')->first()
                : $this->activityLogs()->orderBy('created_at', 'desc')->first();
            if ($lastLog && $lastLog->activity_type === 'returned_by_seller') {
                return 'DISPONIBLE DV';
            }
        }

        return $statuses[$this->status] ?? $this->status;
    }

    // Método para obtener el badge de estado
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'disponible' => 'bg-success',
            'reservada' => 'bg-warning',
            'vendida' => 'bg-primary',
            'devuelta' => 'bg-info',
            'anulada' => 'bg-danger',
            'perdida' => 'bg-secondary',
            'pagada' => 'bg-success',
        ];

        return $badges[$this->status] ?? 'bg-secondary';
    }

    /**
     * Buscar por código de participación (acepta código de visualización 1/00001 → busca 1D/00001 para digital).
     */
    public function scopeWhereCodeOrDisplayCode($query, string $code)
    {
        $code = trim($code);
        if (str_starts_with($code, '1/') && ! str_starts_with($code, '1D/')) {
            $digitalCode = '1D/' . substr($code, 2);
            return $query->where(function ($q) use ($code, $digitalCode) {
                $q->where('participation_code', $code)->orWhere('participation_code', $digitalCode);
            });
        }
        return $query->where('participation_code', $code);
    }

    /**
     * Scope para filtrar participaciones accesibles por usuario.
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $entityIds = $user->accessibleEntityIds();
        $sellerIds = $user->accessibleSellerIds();

        if (empty($entityIds) && empty($sellerIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($entityIds, $sellerIds) {
            if (!empty($entityIds)) {
                $q->whereIn('entity_id', $entityIds);
            }

            if (!empty($sellerIds)) {
                $q->orWhereIn('seller_id', $sellerIds);
            }
        });
    }
}
