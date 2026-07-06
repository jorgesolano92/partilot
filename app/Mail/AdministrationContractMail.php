<?php

namespace App\Mail;

use App\Models\Administration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdministrationContractMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $signUrl;

    public function __construct(
        public Administration $administration,
        string $token
    ) {
        $this->signUrl = route('administration-contract.sign', ['token' => $token]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Contrato SaaS PARTILOT — Firma requerida');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.administration-contract');
    }
}
