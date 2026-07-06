<?php

namespace App\Mail;

use App\Models\Administration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdministrationContractSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Administration $administration,
        public string $pdfStoragePath
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Copia del contrato SaaS PARTILOT firmado');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.administration-contract-signed');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->pdfStoragePath)
                ->as('contrato-saas-partilot-'.$this->administration->contract_reference.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
