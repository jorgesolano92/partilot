<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\Lottery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrizePaymentActivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Entity $entity,
        public Lottery $lottery,
        public string $bodyMessage
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cobro de premio disponible - Partilot');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.prize-payment-activated');
    }
}
