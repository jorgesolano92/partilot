<?php

namespace App\Mail;

use App\Models\PrintOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrintOrderCreatedToPrintShopMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrintOrder $printOrder,
        public bool $heldForManagementFee = false,
        public string $panelUrl = '',
    ) {
        $this->printOrder = $printOrder->loadMissing([
            'entity',
            'set',
            'lottery',
            'printConfiguration',
        ]);

        if ($this->panelUrl === '') {
            $this->panelUrl = route('print-shop.orders.show', $this->printOrder, absolute: true);
        }
    }

    public function envelope(): Envelope
    {
        $code = trim((string) ($this->printOrder->order_code ?? ''));

        return new Envelope(
            subject: 'Nuevo pedido de impresión'.($code !== '' ? ' '.$code : '').' - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.print-order-created-to-print-shop');
    }
}
