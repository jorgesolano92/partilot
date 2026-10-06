<?php

namespace App\Services;

use App\Models\PendingDigitalSale;
use App\Models\Seller;

/**
 * Notificación al comprador en ventas digitales pendientes.
 * SMS vía httpSMS si está activo; si no, la app abre wa.me (manual).
 */
class DigitalSaleBuyerNotifyService
{
    public function __construct(
        private DigitalSaleSmsService $sms,
    ) {}

    public function smsEnabled(): bool
    {
        return $this->sms->isEnabled();
    }

    public function whatsappEnabled(): bool
    {
        return false;
    }

    public function preferredChannel(): string
    {
        return $this->sms->isEnabled() ? 'sms' : 'manual';
    }

    public function configPayload(): array
    {
        $channel = $this->preferredChannel();

        return [
            'sms_enabled' => $this->sms->isEnabled(),
            'whatsapp_enabled' => false,
            'buyer_notify_channel' => $channel,
            'notify_auto_enabled' => $channel === 'sms',
            'buyer_sms_max_resends' => $this->sms->maxResends(),
        ];
    }

    public function findPendingForSeller(Seller $seller, int $pendingId): ?PendingDigitalSale
    {
        return $this->sms->findPendingForSeller($seller, $pendingId);
    }

    /**
     * @return array{channel: string, message_sid: string}
     */
    public function sendToBuyer(Seller $seller, int $pendingId, string $buyerPhone): array
    {
        if ($this->preferredChannel() === 'manual') {
            throw new \RuntimeException(
                'SMS no configurado. Activa DIGITAL_SALE_SMS_ENABLED y httpSMS (HTTPSMS_API_KEY, HTTPSMS_FROM_NUMBER) en el servidor.'
            );
        }

        $pending = $this->sms->findPendingForSeller($seller, $pendingId);
        if (! $pending) {
            throw new \InvalidArgumentException(
                'Venta pendiente no encontrada, caducada o ya reclamada por el comprador.'
            );
        }

        if ($pending->buyer_phone) {
            $stored = $this->sms->normalizeSmsAddress($pending->buyer_phone);
            $requested = $this->sms->normalizeSmsAddress($buyerPhone);
            if ($stored && $requested && $stored !== $requested) {
                throw new \InvalidArgumentException(
                    'Solo se puede reenviar al teléfono registrado en la venta.'
                );
            }
            $buyerPhone = $pending->buyer_phone;
        }

        $messageSid = $this->sms->sendToBuyer($pending, $buyerPhone);
        $pending->refresh();

        return [
            'channel' => 'sms',
            'message_sid' => $messageSid,
            'buyer_sms_sent_count' => (int) $pending->buyer_sms_sent_count,
            'buyer_sms_sends_remaining' => $this->sms->sendsRemaining($pending),
        ];
    }
}
