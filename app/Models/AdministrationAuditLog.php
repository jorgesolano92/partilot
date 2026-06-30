<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrationAuditLog extends Model
{
    protected $fillable = [
        'administration_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
        'ip',
        'user_agent',
    ];

    public function administration(): BelongsTo
    {
        return $this->belongsTo(Administration::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
