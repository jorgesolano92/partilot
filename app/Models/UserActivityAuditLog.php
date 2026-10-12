<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityAuditLog extends Model
{
    public const EVENT_LOGIN = 'login';

    public const EVENT_ENTITY_UPDATED = 'entity_updated';

    public const CHANNEL_PANEL = 'panel';

    public const CHANNEL_APP = 'app';

    protected $fillable = [
        'user_id',
        'administration_id',
        'entity_id',
        'event',
        'channel',
        'field',
        'old_value',
        'new_value',
        'ip',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
