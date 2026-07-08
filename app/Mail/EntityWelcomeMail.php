<?php

namespace App\Mail;

use App\Models\Entity;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntityWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Entity $entity,
        public User $user,
        public string $plainPassword = '',
        public string $loginUrl = '',
    ) {
        if ($this->loginUrl === '') {
            $this->loginUrl = route('login', absolute: true);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Acceso al panel de su entidad - Partilot');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.entity-welcome');
    }
}
