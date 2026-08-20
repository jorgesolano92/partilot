<?php

namespace App\Mail;

use App\Models\PrintOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrintOrderRejectedByPrintShopMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrintOrder $printOrder,
        public string $summaryUrl = '',
    ) {
        $this->printOrder = $printOrder->loadMissing([
            'entity',
            'set',
            'lottery',
            'printConfiguration',
            'design',
        ]);

        if ($this->summaryUrl === '' && $this->printOrder->design_format_id) {
            $this->summaryUrl = route('design.summary', $this->printOrder->design_format_id, absolute: true);
        }
    }

    public function envelope(): Envelope
    {
        $code = trim((string) ($this->printOrder->order_code ?? ''));

        return new Envelope(
            subject: 'Pedido de impresión rechazado'.($code !== '' ? ' '.$code : '').' - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.print-order-rejected-by-print-shop');
    }
}
