<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserConsent extends Model
{
    public const TYPE_REGISTRATION_TERMS = 'registration_terms';

    public const TYPE_DIGITAL_SALE_TERMS = 'digital_sale_terms';

    protected $fillable = [
        'user_id',
        'type',
        'version',
        'text_hash',
        'ip',
        'user_agent',
        'context',
        'accepted_at',
    ];

    protected $casts = [
        'context' => 'array',
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
