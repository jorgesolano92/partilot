<?php

namespace App\Mail;

use App\Models\Set;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SetDeletedToEntityManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public Set $set;

    public function __construct(Set $set)
    {
        $this->set = $set->loadMissing([
            'entity',
            'entity.manager.user',
            'reserve',
            'reserve.lottery',
            'reserve.lottery.lotteryType',
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Set eliminado - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.set-deleted-to-entity-manager'
        );
    }
}

