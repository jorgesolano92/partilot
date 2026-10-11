<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParticipationActivityLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'participation_id',
        'activity_type',
        'user_id',
        'seller_id',
        'entity_id',
        'old_status',
        'new_status',
        'old_seller_id',
        'new_seller_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con la participación
     */
    public function participation()
    {
        return $this->belongsTo(Participation::class);
    }

    /**
     * Relación con el usuario que realizó la acción
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el vendedor involucrado
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    /**
     * Relación con la entidad
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Relación con el vendedor anterior (para cambios de asignación)
     */
    public function oldSeller()
    {
        return $this->belongsTo(Seller::class, 'old_seller_id');
    }

    /**
     * Relación con el nuevo vendedor (para cambios de asignación)
     */
    public function newSeller()
    {
        return $this->belongsTo(Seller::class, 'new_seller_id');
    }

    /**
     * Scopes para filtrar por tipo de actividad
     */
    public function scopeCreated($query)
    {
        return $query->where('activity_type', 'created');
    }

    public function scopeAssigned($query)
    {
        return $query->where('activity_type', 'assigned');
    }

    public function scopeReturnedBySeller($query)
    {
        return $query->where('activity_type', 'returned_by_seller');
    }

    public function scopeSold($query)
    {
        return $query->where('activity_type', 'sold');
    }

    public function scopeReturnedToAdministration($query)
    {
        return $query->where('activity_type', 'returned_to_administration');
    }

    public function scopeStatusChanged($query)
    {
        return $query->where('activity_type', 'status_changed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('activity_type', 'cancelled');
    }

    /**
     * Scope para filtrar por participación
     */
    public function scopeForParticipation($query, $participationId)
    {
        return $query->where('participation_id', $participationId);
    }

    /**
     * Scope para filtrar por vendedor
     */
    public function scopeBySeller($query, $sellerId)
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Scope para filtrar por entidad
     */
    public function scopeByEntity($query, $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    /**
     * Scope para filtrar por usuario
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para obtener actividades recientes
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Obtener la descripción legible del tipo de actividad
     */
    public function getActivityTypeTextAttribute()
    {
        $types = [
            'created' => 'Creada',
            'assigned' => 'Asignada a vendedor',
            'returned_by_seller' => 'Devuelta por vendedor',
            'sold' => 'Vendida',
            'returned_to_administration' => 'Devuelta a administración',
            'status_changed' => 'Cambio de estado',
            'cancelled' => 'Anulada',
            'modified' => 'Modificada',
            'paid' => 'Pagada',
            'gift_sent' => 'Regalo enviado',
            'gift_accepted' => 'Regalo aceptado',
            'gift_rejected' => 'Regalo rechazado',
            'gift_expired' => 'Regalo caducado',
            'gift_cancelled' => 'Regalo cancelado',
        ];

        return $types[$this->activity_type] ?? $this->activity_type;
    }

    /**
     * Obtener el badge CSS según el tipo de actividad
     */
    public function getActivityBadgeAttribute()
    {
        $badges = [
            'created' => 'bg-info',
            'assigned' => 'bg-primary',
            'returned_by_seller' => 'bg-warning',
            'sold' => 'bg-success',
            'returned_to_administration' => 'bg-secondary',
            'status_changed' => 'bg-info',
            'cancelled' => 'bg-danger',
            'modified' => 'bg-secondary',
            'paid' => 'bg-success',
            'gift_sent' => 'bg-info',
            'gift_accepted' => 'bg-success',
            'gift_rejected' => 'bg-warning',
            'gift_expired' => 'bg-secondary',
            'gift_cancelled' => 'bg-secondary',
        ];

        return $badges[$this->activity_type] ?? 'bg-secondary';
    }

    /**
     * Método estático para registrar una actividad
     */
    public static function log($participationId, $activityType, $data = [])
    {
        return static::create([
            'participation_id' => $participationId,
            'activity_type' => $activityType,
            'user_id' => $data['user_id'] ?? auth()->id(),
            'seller_id' => $data['seller_id'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'old_status' => $data['old_status'] ?? null,
            'new_status' => $data['new_status'] ?? null,
            'old_seller_id' => $data['old_seller_id'] ?? null,
            'new_seller_id' => $data['new_seller_id'] ?? null,
            'description' => $data['description'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
        ]);
    }
}
