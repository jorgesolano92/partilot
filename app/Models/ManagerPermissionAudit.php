<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManagerPermissionAudit extends Model
{
    protected $fillable = [
        'entity_id',
        'manager_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
        'ip',
        'user_agent',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
