<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DesignPdfReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $downloadUrl;

    public string $pdfLabel;

    public int $designId;

    public function __construct(string $downloadUrl, string $pdfLabel, int $designId)
    {
        $this->downloadUrl = $downloadUrl;
        $this->pdfLabel = $pdfLabel;
        $this->designId = $designId;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Su PDF está listo — '.$this->pdfLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.design-pdf-ready',
        );
    }
}
