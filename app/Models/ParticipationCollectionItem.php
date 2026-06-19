<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipationCollectionItem extends Model
{
    protected $fillable = [
        'collection_id',
        'participation_id',
        'entity_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(ParticipationCollection::class, 'collection_id');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(Participation::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
