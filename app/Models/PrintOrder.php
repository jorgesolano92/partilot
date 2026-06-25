<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintOrder extends Model
{
    public const STATUS_PENDING_REVIEW = 'pendiente_revision';
    public const STATUS_IN_PRODUCTION = 'en_produccion';
    public const STATUS_SENT = 'enviada';
    public const STATUS_REJECTED = 'rechazada';

    /** Cobro online no aplicado (pedido interno / envío sin pasarela). */
    public const PAYMENT_STATUS_NOT_REQUIRED = 'not_required';

    /** Pago Stripe confirmado y registrado en pedido. */
    public const PAYMENT_STATUS_PAID = 'paid';

    /** Estado inicial migración / pendiente de conciliar. */
    public const PAYMENT_STATUS_PENDING = 'pending';

    /** Pago Stripe fallido o cancelado. */
    public const PAYMENT_STATUS_FAILED = 'failed';

    public const PAYMENT_PROVIDER_STRIPE = 'stripe';

    public const PAYMENT_PROVIDER_REMITTANCE = 'remittance';

    protected $fillable = [
        'print_configuration_id',
        'order_code',
        'design_format_id',
        'set_id',
        'entity_id',
        'lottery_id',
        'created_by_user_id',
        'status',
        'payment_provider',
        'payment_intent_id',
        'payment_status',
        'print_size',
        'participations_per_book',
        'back_mode',
        'quoted_amount',
        'quote_breakdown',
        'notes',
        'sent_at',
        'paid_at',
        'billing_charge_id',
    ];

    protected $casts = [
        'quoted_amount' => 'decimal:2',
        'quote_breakdown' => 'array',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function printConfiguration()
    {
        return $this->belongsTo(PrintConfiguration::class);
    }

    public function design()
    {
        return $this->belongsTo(DesignFormat::class, 'design_format_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function set()
    {
        return $this->belongsTo(Set::class);
    }

    public function billingCharge()
    {
        return $this->belongsTo(BillingCharge::class);
    }

    public function lottery()
    {
        return $this->belongsTo(Lottery::class);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING_REVIEW => 'Pendiente revisión',
            self::STATUS_IN_PRODUCTION => 'En producción',
            self::STATUS_SENT => 'Enviada',
            self::STATUS_REJECTED => 'Rechazada',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function statusBadgeClass(string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING_REVIEW => 'bg-warning text-dark',
            self::STATUS_IN_PRODUCTION => 'bg-info text-dark',
            self::STATUS_SENT => 'bg-success',
            self::STATUS_REJECTED => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    /**
     * Etiqueta legible del estado de cobro para UI y trazabilidad.
     */
    public static function paymentStatusLabel(?string $paymentStatus, ?string $paymentProvider): string
    {
        $s = $paymentStatus ?: '';
        return match ($s) {
            self::PAYMENT_STATUS_PAID, 'succeeded' => match ($paymentProvider) {
                self::PAYMENT_PROVIDER_STRIPE => 'Cobrado (Stripe)',
                self::PAYMENT_PROVIDER_REMITTANCE => 'Encolado en remesa',
                default => 'Cobrado',
            },
            self::PAYMENT_STATUS_NOT_REQUIRED => 'Sin cobro online',
            self::PAYMENT_STATUS_PENDING => $paymentProvider ? 'Pago pendiente / revisar' : 'Pendiente',
            self::PAYMENT_STATUS_FAILED => 'Pago fallido',
            default => $s !== '' ? ucfirst(str_replace('_', ' ', $s)) : '—',
        };
    }

    public static function paymentStatusBadgeClass(?string $paymentStatus): string
    {
        $s = $paymentStatus ?: '';
        return match ($s) {
            self::PAYMENT_STATUS_PAID, 'succeeded' => 'bg-success',
            self::PAYMENT_STATUS_NOT_REQUIRED => 'bg-secondary',
            self::PAYMENT_STATUS_PENDING => 'bg-warning text-dark',
            self::PAYMENT_STATUS_FAILED => 'bg-danger',
            default => 'bg-light text-dark border',
        };
    }

    public function requiresOnlinePayment(): bool
    {
        return (string) ($this->payment_provider ?? '') === self::PAYMENT_PROVIDER_STRIPE;
    }

    public function isPaymentSettled(): bool
    {
        $provider = (string) ($this->payment_provider ?? '');

        if ($provider === self::PAYMENT_PROVIDER_REMITTANCE) {
            return (string) ($this->payment_status ?? '') === self::PAYMENT_STATUS_PAID
                && $this->billing_charge_id !== null;
        }

        if (! $this->requiresOnlinePayment()) {
            return in_array((string) ($this->payment_status ?? ''), [
                self::PAYMENT_STATUS_NOT_REQUIRED,
                self::PAYMENT_STATUS_PAID,
            ], true);
        }

        return (string) ($this->payment_status ?? '') === self::PAYMENT_STATUS_PAID
            && trim((string) ($this->payment_intent_id ?? '')) !== '';
    }

    public function canTransitionTo(string $targetStatus): bool
    {
        $transitions = [
            self::STATUS_PENDING_REVIEW => [self::STATUS_IN_PRODUCTION, self::STATUS_REJECTED],
            self::STATUS_IN_PRODUCTION => [self::STATUS_SENT, self::STATUS_REJECTED],
            self::STATUS_REJECTED => [self::STATUS_PENDING_REVIEW],
            self::STATUS_SENT => [],
        ];

        if (! in_array($targetStatus, $transitions[$this->status] ?? [], true)) {
            return false;
        }

        if (in_array($targetStatus, [self::STATUS_IN_PRODUCTION, self::STATUS_SENT], true) && ! $this->isPaymentSettled()) {
            return false;
        }

        return true;
    }

    public function paymentTransitionBlockReason(): ?string
    {
        if ($this->isPaymentSettled()) {
            return null;
        }

        if ((string) ($this->payment_provider ?? '') === self::PAYMENT_PROVIDER_REMITTANCE) {
            return 'No se puede avanzar: falta confirmar el cargo en remesa.';
        }

        if ($this->requiresOnlinePayment()) {
            return match ((string) ($this->payment_status ?? '')) {
                self::PAYMENT_STATUS_FAILED => 'No se puede avanzar: el pago Stripe falló.',
                self::PAYMENT_STATUS_PENDING => 'No se puede avanzar: el pago Stripe está pendiente.',
                default => 'No se puede avanzar: falta confirmar el cobro Stripe.',
            };
        }

        return 'No se puede avanzar: el estado de cobro no está resuelto.';
    }

    /**
     * Bloquea edición del diseño en panel admin/entidad mientras la imprenta trabaja.
     */
    public function isEditingBlockedForDesign(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_IN_PRODUCTION,
        ], true);
    }

    public function isWorkflowComplete(): bool
    {
        return (string) $this->status === self::STATUS_SENT;
    }

    /**
     * La imprenta puede abrir el editor mientras el pedido está activo.
     */
    public function printShopCanEditDesign(): bool
    {
        return in_array((string) $this->status, [
            self::STATUS_PENDING_REVIEW,
            self::STATUS_IN_PRODUCTION,
            self::STATUS_REJECTED,
        ], true);
    }

    /**
     * Pedido con diseño pendiente de elaborar por la imprenta.
     */
    public function requiresPrintShopDesign(): bool
    {
        if (! $this->design_format_id) {
            return true;
        }

        $this->loadMissing('design');
        if (! $this->design) {
            return true;
        }

        return ! app(\App\Services\DesignApprovalService::class)->designHasParticipationContent($this->design);
    }

    /**
     * La imprenta no debe ver ni trabajar pedidos retenidos por cuota de gestión pendiente de la entidad.
     */
    public function isVisibleToPrintShop(): bool
    {
        if (! $this->set_id) {
            return true;
        }

        $this->loadMissing('set');

        return ! app(\App\Services\ManagementFeeService::class)
            ->blocksPrintShopUntilEntityPaysManagementFee($this->set);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeVisibleToPrintShop($query)
    {
        $paid = \App\Services\ManagementFeeService::STATUS_PAID;
        $queued = \App\Services\ManagementFeeService::STATUS_QUEUED_REMITTANCE;

        return $query->whereHas('set', function ($setQuery) use ($paid, $queued) {
            $setQuery->where(function ($visibilityQuery) use ($paid, $queued) {
                // La administración paga la gestión → visible para imprenta.
                $visibilityQuery->whereHas('entity', function ($entityQuery) {
                    $entityQuery->where('entity_pays_management_fee', false);
                })
                // La entidad paga la gestión → solo si la cuota ya está liquidada.
                ->orWhere(function ($entityPaysQuery) use ($paid, $queued) {
                    $entityPaysQuery
                        ->whereHas('entity', function ($entityQuery) {
                            $entityQuery->where('entity_pays_management_fee', true);
                        })
                        ->whereIn('management_fee_status', [$paid, $queued]);
                });
            });
        });
    }
}

