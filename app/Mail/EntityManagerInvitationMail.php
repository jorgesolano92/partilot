<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\Manager;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntityManagerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $acceptUrl;
    public string $rejectUrl;

    public function __construct(
        public Entity $entity,
        public User $managerUser,
        public Manager $manager
    ) {
        $this->entity->loadMissing('administration');
        $this->acceptUrl = route('entity-managers.confirm-accept', ['token' => $manager->confirmation_token]);
        $this->rejectUrl = route('entity-managers.confirm-reject', ['token' => $manager->confirmation_token]);
    }

    public function envelope(): Envelope
    {
        $subject = $this->manager->pending_primary || $this->manager->is_primary
            ? 'Tienes una solicitud pendiente en PARTILOT'
            : 'Invitación para colaborar en PARTILOT';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.entity-manager-invitation');
    }
}

