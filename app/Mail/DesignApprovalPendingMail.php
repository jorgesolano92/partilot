<?php

namespace App\Mail;

use App\Models\DesignFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DesignApprovalPendingMail extends Mailable
{
    use Queueable, SerializesModels;

    public DesignFormat $design;

    public string $reviewUrl;

    public function __construct(DesignFormat $design)
    {
        $this->design = $design->loadMissing([
            'entity',
            'lottery',
            'set',
            'set.reserve.lottery',
        ]);
        $this->reviewUrl = url(route('design.approval.review', $design->id, false));
    }

    public function envelope(): Envelope
    {
        $entityName = trim((string) ($this->design->entity?->name ?? ''));

        return new Envelope(
            subject: 'Diseño de participaciones pendiente de su aprobación'
                . ($entityName !== '' ? ' — '.$entityName : ''),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.design-approval-pending',
        );
    }
}
