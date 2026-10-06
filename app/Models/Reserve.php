<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserve extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'lottery_id',
        'reservation_numbers',
        'total_amount',
        'total_tickets',
        'reservation_amount',
        'reservation_tickets',
        'status',
        'reservation_date',
        'expiration_date'
    ];

    protected $casts = [
        'reservation_numbers' => 'array',
        'reservation_date' => 'datetime',
        'expiration_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'reservation_amount' => 'decimal:2'
    ];

    /**
     * Relación con la entidad
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Relación con el sorteo
     */
    public function lottery()
    {
        return $this->belongsTo(Lottery::class);
    }

    /**
     * Relación con los sets
     */
    public function sets()
    {
        return $this->hasMany(Set::class);
    }

    /**
     * Scope para reservas pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para reservas confirmadas
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para reservas canceladas
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Scope para reservas completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 3);
    }

    /**
     * Obtener el texto del status
     */
    public function getStatusTextAttribute()
    {
        return match($this->status) {
            0 => 'Pendiente',
            1 => 'Confirmada',
            2 => 'Cancelada',
            3 => 'Completada',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener la clase CSS del status
     */
    public function getStatusClassAttribute()
    {
        return match($this->status) {
            0 => 'bg-warning',
            1 => 'bg-info',
            2 => 'bg-danger',
            3 => 'bg-success',
            default => 'bg-secondary'
        };
    }

    /**
     * Importe total ya comprometido en sets de esta reserva.
     */
    public function usedAmountInSets(): float
    {
        return (float) $this->sets()->sum('total_amount');
    }

    /**
     * Scope para filtrar reservas accesibles por usuario.
     */
    public function scopeForUser($query, User $user)
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        $entityIds = $user->accessibleEntityIds();

        if (empty($entityIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('entity_id', $entityIds);
    }
}
