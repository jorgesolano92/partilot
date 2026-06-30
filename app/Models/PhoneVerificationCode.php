<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerificationCode extends Model
{
    protected $fillable = [
        'phone',
        'code_hash',
        'expires_at',
        'verified_at',
        'failed_attempts',
        'locked_until',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'locked_until' => 'datetime',
    ];
}
