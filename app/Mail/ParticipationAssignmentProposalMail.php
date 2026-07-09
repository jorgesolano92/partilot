<?php

namespace App\Mail;

use App\Models\ParticipationAssignmentProposal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ParticipationAssignmentProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public ParticipationAssignmentProposal $proposal;

    public string $conditionsUrl;

    public string $acceptUrl;

    public string $rejectUrl;

    public function __construct(ParticipationAssignmentProposal $proposal)
    {
        $this->proposal = $proposal;
        $this->conditionsUrl = route('legal.terminos-y-condiciones');
        $this->acceptUrl = route('participation-assignment.accept', ['token' => $proposal->token]);
        $this->rejectUrl = route('participation-assignment.reject', ['token' => $proposal->token]);
    }

    public function envelope(): Envelope
    {
        $lotteryName = $this->proposal->lottery?->name ?? 'sorteo';

        return new Envelope(
            subject: 'Se te proponen participaciones — '.$lotteryName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.participation-assignment-proposal',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
