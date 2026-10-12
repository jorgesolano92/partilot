<?php

namespace App\Mail;

use App\Models\Reserve;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReserveSavedToEntityManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public Reserve $reserve;

    public function __construct(Reserve $reserve)
    {
        $this->reserve = $reserve->loadMissing([
            'entity',
            'entity.administration',
            'entity.manager.user',
            'lottery',
            'lottery.lotteryType',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reserva confirmada - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reserve-saved-to-entity-manager',
        );
    }
}

