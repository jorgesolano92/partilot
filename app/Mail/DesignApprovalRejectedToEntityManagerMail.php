<?php

namespace App\Mail;

use App\Models\DesignFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DesignApprovalRejectedToEntityManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public DesignFormat $design;

    public function __construct(DesignFormat $design)
    {
        $this->design = $design->loadMissing([
            'entity',
            'lottery',
            'set',
            'set.reserve.lottery',
        ]);
    }

    public function envelope(): Envelope
    {
        $entityName = trim((string) ($this->design->entity?->name ?? ''));

        return new Envelope(
            subject: 'Diseño rechazado - Partilot'
                . ($entityName !== '' ? ' — '.$entityName : '')
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.design-approval-rejected'
        );
    }
}

