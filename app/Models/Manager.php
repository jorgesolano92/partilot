<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Manager extends Model
{
    use HasFactory;
    
    protected $fillable = [
        "user_id",
        "entity_id",
        "administration_id",
        "is_primary",
        "pending_primary",
        "permission_sellers",
        "permission_design",
        "permission_statistics",
        "permission_payments",
        "confirmation_token",
        "confirmation_sent_at",
        "role_invitation_reminder_sent_at",
        "requires_password_setup",
        "status",
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'pending_primary' => 'boolean',
        'permission_sellers' => 'boolean',
        'permission_design' => 'boolean',
        'permission_statistics' => 'boolean',
        'permission_payments' => 'boolean',
        'confirmation_sent_at' => 'datetime',
        'role_invitation_reminder_sent_at' => 'datetime',
        'requires_password_setup' => 'boolean',
    ];

    /**
     * Relación con User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con Entity
     */
    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function administration()
    {
        return $this->belongsTo(Administration::class);
    }
}
