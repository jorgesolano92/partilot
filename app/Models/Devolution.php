<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class Devolution extends Model
{
    use HasFactory;

    protected $table = 'devolutions';

    protected $fillable = [
        'entity_id',
        'lottery_id',
        'seller_id',
        'user_id',
        'total_participations',
        'total_liquidation',
        'return_reason',
        'devolution_date',
        'devolution_time',
        'status',
        'notes',
        'special_prize_settlement'
    ];

    protected $casts = [
        'devolution_date' => 'date',
        'devolution_time' => 'datetime:H:i:s',
        'total_liquidation' => 'decimal:2',
        'special_prize_settlement' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con Entity
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Relación con Lottery
     */
    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    /**
     * Relación con Seller (opcional)
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Relación con User (quien procesa la devolución)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con DevolutionDetail
     */
    public function details(): HasMany
    {
        return $this->hasMany(DevolutionDetail::class);
    }

    /**
     * Relación con DevolutionPayment
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DevolutionPayment::class);
    }

    /**
     * Scope para filtrar por entidad
     */
    public function scopeByEntity($query, $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    /**
     * Scope para filtrar por sorteo
     */
    public function scopeByLottery($query, $lotteryId)
    {
        return $query->where('lottery_id', $lotteryId);
    }

    /**
     * Scope para filtrar por vendedor
     */
    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    public function isAnnulment(): bool
    {
        if ($this->relationLoaded('details')) {
            return $this->details->contains(fn ($d) => (string) $d->action === 'anular');
        }

        return $this->details()->where('action', 'anular')->exists();
    }

    public function typeLabel(): string
    {
        if ($this->isAnnulment()) {
            return 'Anulación';
        }

        return $this->seller_id ? 'Vendedor → Entidad' : 'Entidad → Administración';
    }

    public function annulmentReason(): ?string
    {
        $reason = trim((string) ($this->return_reason ?? ''));
        if ($reason === '') {
            return null;
        }

        return preg_replace('/^Anulaci[oó]n:\s*/iu', '', $reason) ?: $reason;
    }

    /**
     * Accessor para obtener el nombre completo de la devolución
     */
    public function getFullNameAttribute()
    {
        $seller = $this->seller ? " - {$this->seller->name}" : "";
        $prefix = $this->isAnnulment() ? 'Anulación' : 'Devolución';

        return "{$prefix} #{$this->id} - {$this->entity->name}{$seller}";
    }

    /**
     * Scope para filtrar devoluciones accesibles por el usuario.
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
