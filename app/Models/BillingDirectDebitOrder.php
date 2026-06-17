<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingDirectDebitOrder extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_CANCELLED = 'cancelled';

    public const SEQUENCE_FRST = 'FRST';

    public const SEQUENCE_RCUR = 'RCUR';

    protected $fillable = [
        'administration_id',
        'message_id',
        'payment_info_id',
        'creation_date',
        'collection_date',
        'number_of_transactions',
        'control_sum',
        'creditor_name',
        'creditor_nif_cif',
        'creditor_iban',
        'creditor_scheme_id',
        'debtor_name',
        'debtor_nif_cif',
        'debtor_iban',
        'debtor_mandate_id',
        'debtor_mandate_signed_at',
        'sequence_type',
        'xml_filename',
        'status',
        'notes',
        'created_by_user_id',
        'exported_at',
        'collected_at',
    ];

    protected $casts = [
        'creation_date' => 'datetime',
        'collection_date' => 'date',
        'control_sum' => 'decimal:2',
        'number_of_transactions' => 'integer',
        'debtor_mandate_signed_at' => 'date',
        'exported_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function administration(): BelongsTo
    {
        return $this->belongsTo(Administration::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(BillingCharge::class, 'billing_direct_debit_order_id');
    }

    public static function generateMessageId(): string
    {
        return 'BDD'.date('YmdHis').strtoupper(substr(uniqid(), -6));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Borrador',
            self::STATUS_EXPORTED => 'XML exportado',
            self::STATUS_COLLECTED => 'Cobrado',
            self::STATUS_CANCELLED => 'Anulado',
            default => ucfirst($this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'bg-warning text-dark',
            self::STATUS_EXPORTED => 'bg-info text-dark',
            self::STATUS_COLLECTED => 'bg-success',
            self::STATUS_CANCELLED => 'bg-secondary',
            default => 'bg-light text-dark border',
        };
    }
}
