<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCharge extends Model
{
    public const PAYER_ADMINISTRATION = 'administration';

    public const PAYER_ENTITY = 'entity';

    public const CONCEPT_MANAGEMENT_FEE = 'management_fee';

    public const CONCEPT_PRINT_FEE = 'print_fee';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_REMITTANCE = 'in_remittance';

    public const STATUS_INVOICED = 'invoiced';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'administration_id',
        'entity_id',
        'set_id',
        'payer_type',
        'concept',
        'source_type',
        'source_id',
        'amount',
        'currency',
        'description',
        'status',
        'created_by_user_id',
        'collected_at',
        'billing_direct_debit_order_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'collected_at' => 'datetime',
    ];

    public function administration(): BelongsTo
    {
        return $this->belongsTo(Administration::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function directDebitOrder(): BelongsTo
    {
        return $this->belongsTo(BillingDirectDebitOrder::class, 'billing_direct_debit_order_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente de remesa',
            self::STATUS_IN_REMITTANCE => 'Incluido en adeudo',
            self::STATUS_INVOICED => 'Facturado',
            self::STATUS_COLLECTED => 'Cobrado',
            self::STATUS_CANCELLED => 'Anulado',
            default => 'Desconocido',
        };
    }

    public function conceptLabel(): string
    {
        return match ($this->concept) {
            self::CONCEPT_MANAGEMENT_FEE => 'Cuota gestión PARTILOT',
            self::CONCEPT_PRINT_FEE => 'Diseño e impresión',
            default => $this->concept,
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-warning text-dark',
            self::STATUS_IN_REMITTANCE => 'bg-info text-dark',
            self::STATUS_INVOICED => 'bg-primary',
            self::STATUS_COLLECTED => 'bg-success',
            self::STATUS_CANCELLED => 'bg-secondary',
            default => 'bg-light text-dark border',
        };
    }
}
