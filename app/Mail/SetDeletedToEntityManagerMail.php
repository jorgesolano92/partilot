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

    public ?string $deletionReason;

    public function __construct(Set $set, ?string $deletionReason = null)
    {
        $this->set = $set->loadMissing([
            'entity',
            'entity.manager.user',
            'reserve',
            'reserve.lottery',
            'reserve.lottery.lotteryType',
        ]);
        $this->deletionReason = self::normalizeReason($deletionReason);
    }

    private static function normalizeReason(?string $reason): ?string
    {
        if (! is_string($reason)) {
            return null;
        }

        $reason = trim($reason);

        return $reason !== '' ? $reason : null;
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

