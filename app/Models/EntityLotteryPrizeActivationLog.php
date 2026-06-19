<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityLotteryPrizeActivationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entity_lottery_prize_setting_id',
        'event',
        'payload',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(EntityLotteryPrizeSetting::class, 'entity_lottery_prize_setting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return match ($this->event) {
            'mode_selected' => 'Modalidad seleccionada',
            'mode_changed_by_superadmin' => 'Modalidad cambiada (superadmin)',
            'funds_confirmed' => 'Fondos confirmados',
            'contract_sent' => 'Contrato enviado por email',
            'contract_signed' => 'Contrato firmado',
            'online_activated' => 'Cobro online activado',
            'presencial_activated' => 'Pago presencial activado',
            'online_blocked' => 'Cobros bloqueados (superadmin)',
            'messages_updated' => 'Mensajes actualizados',
            'presencial_contact_updated' => 'Contacto presencial actualizado',
            'sync_after_scrutiny' => 'Sincronización tras escrutinio',
            'payment_registered_presencial' => 'Pago presencial registrado',
            default => $this->event,
        };
    }

    public function payloadSummary(): ?string
    {
        $payload = $this->payload ?? [];
        if ($payload === []) {
            return null;
        }

        $parts = [];
        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $parts[] = $key.': '.$value;
            }
        }

        return $parts !== [] ? implode(' · ', $parts) : json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
