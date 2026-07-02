<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagerResponsibleRejectedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $designateUrl;

    public function __construct(
        public Entity $entity,
        public ?User $rejectedUser,
    ) {
        $this->designateUrl = route('entities.show', ['entity' => $entity->id]);
    }

    public function envelope(): Envelope
    {
        $name = trim((string) ($this->rejectedUser?->name ?? 'El usuario designado'));

        return new Envelope(subject: "{$name} ha rechazado el cargo de Gestor Responsable");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.manager-responsible-rejected-admin');
    }
}
