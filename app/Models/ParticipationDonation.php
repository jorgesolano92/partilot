<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParticipationDonation extends Model
{
    protected $fillable = [
        'user_id',
        'entity_id',
        'nombre',
        'apellidos',
        'nif',
        'importe_donacion',
        'importe_codigo',
        'codigo_recarga',
        'anonima',
        'certificado_fiscal',
    ];

    protected $casts = [
        'donated_at' => 'datetime',
        'importe_donacion' => 'decimal:2',
        'importe_codigo' => 'decimal:2',
        'anonima' => 'boolean',
        'certificado_fiscal' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Donaciones de una entidad: por entity_id o, en registros antiguos sin él, por la entidad de sus participaciones.
     */
    public function scopeForEntity($query, int $entityId)
    {
        return $query->where(function ($q) use ($entityId) {
            $q->where('participation_donations.entity_id', $entityId)
                ->orWhere(function ($legacy) use ($entityId) {
                    $legacy->whereNull('participation_donations.entity_id')
                        ->whereIn('participation_donations.id', function ($sub) use ($entityId) {
                            $sub->select('pdi.donation_id')
                                ->from('participation_donation_items as pdi')
                                ->join('participations as p', 'p.id', '=', 'pdi.participation_id')
                                ->where('p.entity_id', $entityId);
                        });
                });
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(ParticipationDonationItem::class, 'donation_id');
    }

    public function participations()
    {
        return $this->belongsToMany(Participation::class, 'participation_donation_items', 'donation_id', 'participation_id');
    }

    protected static function booted(): void
    {
        static::creating(function (ParticipationDonation $donation) {
            if ($donation->donated_at === null) {
                $donation->donated_at = now();
            }
        });
    }
}
