<?php

namespace App\Models;

use App\Support\ParticipationTicketReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Set extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_id',
        'reserve_id',
        'set_name',
        'set_description',
        'set_number',
        'total_participations',
        'participation_price',
        'total_amount',
        'played_amount',
        'donation_amount',
        'total_participation_amount',
        'physical_participations',
        'digital_participations',
        'deadline_date',
        'tickets',
        'status',
        'management_fee_status',
        'management_fee_amount',
        'management_fee_unit_price',
        'management_fee_participation_count',
        'management_fee_payer',
        'management_fee_paid_at',
        'management_fee_paid_by_user_id',
        'management_fee_stripe_payment_intent_id',
        'management_fee_payment_provider',
        'management_fee_billing_charge_id',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'set_number' => 'integer',
        'total_participations' => 'integer',
        'participation_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'played_amount' => 'decimal:2',
        'donation_amount' => 'decimal:2',
        'total_participation_amount' => 'decimal:2',
        'physical_participations' => 'integer',
        'digital_participations' => 'integer',
        'deadline_date' => 'date',
        'tickets' => 'array',
        'management_fee_amount' => 'decimal:2',
        'management_fee_unit_price' => 'decimal:4',
        'management_fee_participation_count' => 'integer',
        'management_fee_paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relación con la entidad
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Relación con la reserva
     */
    public function reserve()
    {
        return $this->belongsTo(Reserve::class);
    }

    /**
     * Relación con las participaciones
     */
    public function participations()
    {
        return $this->hasMany(Participation::class);
    }

    /**
     * Relación con los design formats
     */
    public function designFormats()
    {
        return $this->hasMany(DesignFormat::class);
    }

    public function managementFeeBillingCharge()
    {
        return $this->belongsTo(BillingCharge::class, 'management_fee_billing_charge_id');
    }

    /**
     * Scope para sets activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope para sets inactivos
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 0);
    }

    /**
     * Scope para sets pausados
     */
    public function scopePaused($query)
    {
        return $query->where('status', 2);
    }

    /**
     * Obtener el texto del status
     */
    public function getStatusTextAttribute()
    {
        return match($this->status) {
            0 => 'Inactivo',
            1 => 'Activo',
            2 => 'Pausado',
            default => 'Desconocido'
        };
    }

    /**
     * Obtener la clase CSS del status
     */
    public function getStatusClassAttribute()
    {
        return match($this->status) {
            0 => 'bg-danger',
            1 => 'bg-success',
            2 => 'bg-warning',
            default => 'bg-secondary'
        };
    }

    /**
     * Genera el array de tickets con referencias únicas (21 dígitos + dígito de control).
     *
     * @param int $entityId
     * @param int $reserveId
     * @param \DateTime|string|null $createdAt Reservado por compatibilidad; ya no interviene en la referencia
     * @param int $totalParticipations
     * @param array $oldTickets (opcional, no usado)
     * @return array
     */
    public static function generateTickets($entityId, $reserveId, $createdAt, $totalParticipations, $oldTickets = [])
    {
        $tickets = [];
        $usedReferences = [];

        for ($i = 1; $i <= $totalParticipations; $i++) {
            do {
                $referencia = ParticipationTicketReference::generate((int) $entityId, (int) $reserveId);
            } while (isset($usedReferences[$referencia]));

            $usedReferences[$referencia] = true;
            $tickets[] = [
                'n' => $i,
                'r' => $referencia,
            ];
        }

        return $tickets;
    }

    /**
     * Boot del modelo para eventos automáticos
     */
    protected static function boot()
    {
        parent::boot();

        // Asignar set_number al crear: solo los sets FÍSICOS cuentan (1, 2, 3...). Los digitales siempre 1 (no cuentan).
        static::creating(function ($set) {
            if (empty($set->set_number)) {
                $isDigitalOnly = $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
                $set->set_number = $isDigitalOnly ? 1 : static::getNextSetNumberPhysical($set->reserve_id);
            }
        });

        // Renumerar sets físicos cuando se elimina uno (los digitales siguen con set_number = 1)
        static::deleted(function ($deletedSet) {
            self::renumberSetsInReserve($deletedSet->reserve_id);
        });
    }

    /**
     * Siguiente número de set para una reserva (solo sets FÍSICOS; los digitales no cuentan).
     */
    private static function getNextSetNumberPhysical($reserveId)
    {
        $lastPhysical = self::where('reserve_id', $reserveId)
            ->where('physical_participations', '>', 0)
            ->orderBy('set_number', 'desc')
            ->first();

        return ($lastPhysical ? $lastPhysical->set_number : 0) + 1;
    }

    /**
     * Renumerar sets en una reserva después de eliminar uno: solo físicos (1, 2, 3...); digitales quedan en 1.
     */
    private static function renumberSetsInReserve($reserveId)
    {
        $sets = self::where('reserve_id', $reserveId)->orderBy('id')->get();
        $physicalNumber = 0;
        foreach ($sets as $set) {
            $isDigitalOnly = $set->digital_participations > 0 && (int) ($set->physical_participations ?? 0) === 0;
            $newNumber = $isDigitalOnly ? 1 : ++$physicalNumber;
            if ($set->set_number != $newNumber) {
                $set->update(['set_number' => $newNumber]);
            }
        }
    }

    /**
     * Obtener el siguiente número de participación para una reserva (todos los sets).
     * Usado cuando se necesita la consecución global histórica.
     */
    public static function getNextParticipationNumberForReserve($reserveId)
    {
        $maxParticipationNumber = \App\Models\Participation::whereHas('set', function ($query) use ($reserveId) {
            $query->where('reserve_id', $reserveId);
        })->max('participation_number') ?? 0;

        return $maxParticipationNumber + 1;
    }

    /**
     * Obtener el siguiente número de participación solo entre sets FÍSICOS de la reserva.
     * Las participaciones digitales no comparten esta consecución; cada set digital empieza en 1.
     */
    public static function getNextParticipationNumberForReservePhysical($reserveId)
    {
        $maxParticipationNumber = \App\Models\Participation::whereHas('set', function ($query) use ($reserveId) {
            $query->where('reserve_id', $reserveId)->where('physical_participations', '>', 0);
        })->max('participation_number') ?? 0;

        return $maxParticipationNumber + 1;
    }

    /**
     * Obtener el rango de números de participación para un set.
     * - Sets DIGITALES (solo digital_participations): numeración siempre 1..N por set (sin consecución global).
     * - Sets FÍSICOS (o mixtos): consecución global solo entre sets físicos de la misma reserva (1-100, 101-200, ...).
     */
    public function getParticipationNumberRange()
    {
        $total = (int) ($this->total_participations ?? 0);
        $isDigitalOnly = $this->digital_participations > 0 && (int) ($this->physical_participations ?? 0) === 0;

        if ($isDigitalOnly) {
            // Digitales: siempre del 1 al total del set (cada set digital empieza en 1)
            return [
                'start' => 1,
                'end' => $total,
                'count' => $total,
            ];
        }

        // Físicos (o mixtos): siguiente rango disponible solo entre sets físicos de la reserva
        $startNumber = static::getNextParticipationNumberForReservePhysical($this->reserve_id);
        $endNumber = $startNumber + $total - 1;

        return [
            'start' => $startNumber,
            'end' => $endNumber,
            'count' => $total,
        ];
    }

    /**
     * Obtener el total de participaciones restando las anuladas
     */
    public function getTotalParticipationsAttribute()
    {
        $cancelledCount = $this->participations()->where('status', 'anulada')->count();
        return $this->attributes['total_participations'] - $cancelledCount;
    }

    /**
     * Obtener el importe total restando las participaciones anuladas
     */
    public function getTotalAmountAttribute()
    {
        $cancelledCount = $this->participations()->where('status', 'anulada')->count();
        $cancelledAmount = $cancelledCount * ($this->played_amount ?? 0);
        return $this->attributes['total_amount'] - $cancelledAmount;
    }

    /**
     * Scope para filtrar sets accesibles por usuario.
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
