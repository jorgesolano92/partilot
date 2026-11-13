<?php

namespace App\Mail;

use App\Models\Devolution;
use App\Models\DevolutionDetail;
use App\Models\SellerSettlement;
use App\Services\SellerSettlementRegistrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class DevolutionReturnedToEntityManagerMail extends Mailable
{
    use Queueable, SerializesModels;

    public Devolution $devolution;

    public ?\App\Models\User $entityManagerUser;

    /** @var Collection<int, \App\Models\Participation> */
    public Collection $returnedParticipations;

    /** @var Collection<int, \App\Models\Participation> */
    public Collection $soldParticipations;

    public int $returnedCount = 0;

    public float $returnedAmount = 0.0;

    public int $soldCount = 0;

    public float $soldAmount = 0.0;

    public float $paidAmount = 0.0;

    public float $pendingAmount = 0.0;

    public function __construct(Devolution $devolution)
    {
        $this->devolution = $devolution->loadMissing([
            'entity',
            'entity.manager.user',
            'entity.administration',
            'lottery',
            'payments',
            'seller',
        ]);

        $this->entityManagerUser = $this->devolution->entity?->manager?->user;

        $details = DevolutionDetail::query()
            ->where('devolution_id', $this->devolution->id)
            ->with('participation.set')
            ->get();

        $this->returnedParticipations = $details
            ->whereIn('action', ['devolver', 'devolver_vendedor'])
            ->pluck('participation')
            ->filter()
            ->values();

        $this->soldParticipations = $details
            ->where('action', 'vender')
            ->pluck('participation')
            ->filter()
            ->values();

        $price = fn ($p) => $p
            ? (float) SellerSettlementRegistrationService::participationPrice($p)
            : 0.0;

        $this->returnedCount = $this->returnedParticipations->count();
        $this->returnedAmount = round((float) $this->returnedParticipations->sum($price), 2);

        $this->soldCount = $this->soldParticipations->count();
        $this->soldAmount = round((float) $this->soldParticipations->sum($price), 2);

        $this->paidAmount = round((float) $this->devolution->payments->sum('amount'), 2);

        $settlement = SellerSettlement::query()
            ->where('notes', 'Pago en devolución #'.$this->devolution->id)
            ->orderByDesc('id')
            ->first();

        if ($settlement) {
            $this->pendingAmount = round(max(0, (float) $settlement->pending_amount), 2);
        } else {
            // Sin apunte de settlement: pendiente ≈ liquidación pedida − pagado en esta operación
            $this->pendingAmount = round(max(0, (float) $this->devolution->total_liquidation - $this->paidAmount), 2);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Devolución procesada - Partilot',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.devolution-transmission-to-entity-manager',
        );
    }
}
