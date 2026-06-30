<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScrutinyDetailedResult extends Model
{
    use HasFactory;

    protected $table = 'scrutiny_detailed_results';

    protected $fillable = [
        'scrutiny_id',
        'entity_id',
        'winning_number',
        'set_id',
        'premio_por_decimo',
        'premio_por_participacion',
        'total_decimos',
        'total_participations',
        'premio_total',
        'winning_categories'
    ];

    protected $casts = [
        'premio_por_decimo' => 'decimal:2',
        'premio_por_participacion' => 'decimal:2',
        'total_decimos' => 'decimal:2',
        'premio_total' => 'decimal:2',
        'winning_categories' => 'array',
    ];

    /**
     * Relación con el escrutinio
     */
    public function scrutiny()
    {
        return $this->belongsTo(AdministrationLotteryScrutiny::class, 'scrutiny_id');
    }

    /**
     * Relación con la entidad
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * Relación con el set
     */
    public function set()
    {
        return $this->belongsTo(Set::class);
    }

    /**
     * Scope para resultados por número ganador
     */
    public function scopeByWinningNumber($query, $number)
    {
        return $query->where('winning_number', $number);
    }

    /**
     * Scope para resultados por entidad
     */
    public function scopeByEntity($query, $entityId)
    {
        return $query->where('entity_id', $entityId);
    }

    /**
     * Scope para resultados por set
     */
    public function scopeBySet($query, $setId)
    {
        return $query->where('set_id', $setId);
    }
}