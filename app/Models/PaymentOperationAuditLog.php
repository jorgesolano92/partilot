<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentOperationAuditLog extends Model
{
    public const OP_COLLECTION_REQUESTED = 'collection_requested';

    public const OP_COLLECTION_VERIFIED = 'collection_verified';

    public const OP_COLLECTION_CANCELLED = 'collection_cancelled';

    public const OP_COLLECTION_FAILED = 'collection_failed';

    public const OP_DONATION = 'donation';

    protected $fillable = [
        'user_id',
        'operation_type',
        'amount',
        'entity_id',
        'administration_id',
        'reference_type',
        'reference_id',
        'ip_address',
        'user_agent',
        'context',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'context' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
