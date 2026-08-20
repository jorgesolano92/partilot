<?php

namespace App\Mail;

use App\Models\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntityContractSignRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $signUrl;

    public function __construct(
        public Entity $entity,
        string $token
    ) {
        $this->signUrl = route('entity-contract.sign', ['token' => $token]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Contrato marco PARTILOT — Firma del representante autorizada');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.entity-contract-sign-request');
    }
}
