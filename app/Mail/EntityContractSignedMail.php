<?php

namespace App\Mail;

use App\Models\Entity;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EntityContractSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Entity $entity,
        public string $pdfStoragePath
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Copia del contrato marco PARTILOT firmado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.entity-contract-signed');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->pdfStoragePath)
                ->as('contrato-marco-partilot-'.$this->entity->contract_reference.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
