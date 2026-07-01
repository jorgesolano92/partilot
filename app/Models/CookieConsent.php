<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CookieConsent extends Model
{
    public const UPDATED_AT = null;

    public const CHOICE_ALL = 'all';

    public const CHOICE_NECESSARY = 'necessary';

    public const CHOICE_CUSTOM = 'custom';

    protected $fillable = [
        'user_id',
        'visitor_key',
        'cookies_tecnicas',
        'cookies_analiticas',
        'choice',
        'channel',
        'ip_address',
        'user_agent',
        'accepted_at',
    ];

    protected $casts = [
        'cookies_tecnicas' => 'boolean',
        'cookies_analiticas' => 'boolean',
        'accepted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
