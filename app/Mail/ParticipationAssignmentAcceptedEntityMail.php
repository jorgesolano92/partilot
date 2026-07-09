<?php

namespace App\Mail;

use App\Models\ParticipationAssignmentProposal;
use App\Models\Seller;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParticipationAssignmentAcceptedEntityMail extends Mailable
{
    use Queueable, SerializesModels;

    public Seller $seller;

    public ParticipationAssignmentProposal $proposal;

    public int $assignedCount;

    public function __construct(Seller $seller, ParticipationAssignmentProposal $proposal, int $assignedCount)
    {
        $this->seller = $seller;
        $this->proposal = $proposal;
        $this->assignedCount = $assignedCount;
    }

    public function envelope(): Envelope
    {
        $lotteryName = $this->proposal->lottery?->name ?? 'sorteo';

        return new Envelope(
            subject: 'Vendedor ha aceptado asignación — '.$lotteryName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.participation-assignment-accepted-entity',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
