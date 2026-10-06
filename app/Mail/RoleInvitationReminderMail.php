<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RoleInvitationReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?Entity $entity,
        public User $invitedUser,
        public string $roleType,
        public string $acceptUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recordatorio: tienes una solicitud pendiente en PARTILOT');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.role-invitation-reminder');
    }
}
