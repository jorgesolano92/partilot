<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\PendingEntityManagerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitación a gestor sin cuenta: aceptar (registro) o rechazar desde el correo.
 */
class EntityManagerPreregisterInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;
    public string $rejectUrl;
    public string $invitedEmail;

    public function __construct(
        public Entity $entity,
        public PendingEntityManagerInvitation $pending,
    ) {
        $this->entity->loadMissing('administration');
        $this->invitedEmail = (string) $pending->email;
        $this->acceptUrl = route('entity-managers.pending.register', [
            'token' => $pending->confirmation_token,
        ], absolute: true);
        $this->rejectUrl = route('entity-managers.pending.reject', [
            'token' => $pending->confirmation_token,
        ], absolute: true);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación como gestor de entidad - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.entity-manager-preregister-invite');
    }
}
