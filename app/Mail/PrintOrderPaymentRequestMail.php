<?php

namespace App\Mail;

use App\Models\PrintOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrintOrderPaymentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrintOrder $printOrder,
        public string $payUrl = '',
    ) {
        $this->printOrder = $printOrder->loadMissing([
            'entity',
            'set',
            'lottery',
            'printConfiguration',
            'design.set',
        ]);

        if ($this->payUrl === '') {
            $this->payUrl = route('design.payPrintOrder', $this->printOrder, absolute: true);
        }
    }

    public function envelope(): Envelope
    {
        $code = trim((string) ($this->printOrder->order_code ?? ''));

        return new Envelope(
            subject: 'Solicitud de pago impresión'.($code !== '' ? ' '.$code : '').' - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.print-order-payment-request');
    }
}
