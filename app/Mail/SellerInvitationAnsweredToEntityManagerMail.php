<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerInvitationAnsweredToEntityManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Seller $seller,
        public Entity $entity,
        public ?User $managerUser,
        public bool $accepted,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->accepted
                ? 'Un vendedor ha aceptado la invitación - Partilot'
                : 'Un vendedor ha rechazado la invitación - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.seller-invitation-answered-to-entity-manager',
        );
    }
}
