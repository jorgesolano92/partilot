<?php

namespace App\Mail;

use App\Models\DesignFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManagementFeePaymentRequestMail extends Mailable
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
        return new Envelope(
            subject: 'Pago de cuota de gestión PARTILOT pendiente',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.management-fee-payment-request',
        );
    }
}
