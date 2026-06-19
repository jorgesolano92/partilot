<?php

namespace App\Mail;

use App\Models\EntityLotteryPrizeSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrizePaymentContractMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $signUrl;

    public function __construct(
        public EntityLotteryPrizeSetting $setting,
        string $token
    ) {
        $this->signUrl = route('prize-contract.sign', ['token' => $token]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Contrato de cobro de premios - Partilot');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.prize-payment-contract');
    }
}
