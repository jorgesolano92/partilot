<?php

namespace App\Mail;

use App\Models\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitación a gestor que aún no tiene cuenta: debe registrarse con el mismo email.
 */
class EntityManagerPreregisterInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $registerHintUrl;

    public function __construct(
        public Entity $entity,
        public string $invitedEmail
    ) {
        $this->registerHintUrl = (string) (config('app.manager_registration_url') ?: route('login', absolute: true));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación como gestor de entidad (registro pendiente) - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.entity-manager-preregister-invite');
    }
}
