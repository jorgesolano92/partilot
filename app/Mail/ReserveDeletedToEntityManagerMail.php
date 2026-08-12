<?php

namespace App\Mail;

use App\Models\Reserve;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReserveDeletedToEntityManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public Reserve $reserve;

    public ?string $deletionReason;

    public function __construct(Reserve $reserve, ?string $deletionReason = null)
    {
        $this->reserve = $reserve->loadMissing([
            'entity',
            'entity.administration',
            'entity.manager.user',
            'lottery',
            'lottery.lotteryType',
        ]);
        $this->deletionReason = self::normalizeReason($deletionReason);
    }

    private static function normalizeReason(?string $reason): ?string
    {
        if (! is_string($reason)) {
            return null;
        }

        $reason = trim($reason);

        return $reason !== '' ? $reason : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reserva eliminada - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserve-deleted-to-entity-manager'
        );
    }
}

