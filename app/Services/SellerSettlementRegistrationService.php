<?php

namespace App\Services;

use App\Mail\SellerSettlementStatusMail;
use App\Models\Entity;
use App\Models\Lottery;
use App\Models\Participation;
use App\Models\Seller;
use App\Models\SellerSettlement;
use App\Models\SellerSettlementPayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Apuntes de dinero que el vendedor entrega a la entidad (panel y app gestor).
 * La deuda del vendedor es el precio de todas las participaciones que tiene (vendidas o no) menos lo entregado;
 * cada apunte debe cubrir participaciones completas y no puede superar lo pendiente.
 */
class SellerSettlementRegistrationService
{
    public const PAYMENT_METHODS = ['efectivo', 'bizum', 'transferencia'];

    public const DEFAULT_NOTES = ['Liquidación de vendedor', 'Liquidación de vendedor (app gestor)'];

    /**
     * @return Collection<int, Participation>
     */
    public function eligibleParticipations(int $sellerId, int $lotteryId): Collection
    {
        return Participation::query()
            ->eligibleForSellerSettlement($sellerId)
            ->whereHas('set.reserve', fn ($q) => $q->where('lottery_id', $lotteryId))
            ->with('set')
            ->get();
    }

    public function previousPaid(int $sellerId, int $lotteryId): float
    {
        return (float) SellerSettlement::query()
            ->where('seller_id', $sellerId)
            ->where('lottery_id', $lotteryId)
            ->sum('paid_amount');
    }

    public static function participationPrice(Participation $participation): float
    {
        return (float) ($participation->set->total_participation_amount ?? 0);
    }

    /**
     * Motivo por el que no se puede registrar el apunte, o null si es válido.
     *
     * @param  Collection<int, Participation>  $participations
     */
    public function blockReason(Collection $participations, float $previousPaid, float $newPayment): ?string
    {
        if ($participations->isEmpty()) {
            return 'Este vendedor no tiene participaciones asignadas pendientes de liquidar en este sorteo.';
        }

        $totalAmount = (float) $participations->sum(fn ($p) => self::participationPrice($p));
        $pending = round($totalAmount - $previousPaid, 2);
        if ($pending <= 0.009) {
            return 'No queda importe pendiente de liquidar para este vendedor en este sorteo.';
        }
        if ($newPayment > $pending + 0.009) {
            return 'El importe a liquidar ('.$this->money($newPayment).') supera el pendiente ('.$this->money($pending).').';
        }

        return $this->indivisibleReason($participations, $previousPaid, $newPayment);
    }

    /**
     * Las participaciones son indivisibles: lo entregado en total tras el apunte debe equivaler
     * a un número entero de participaciones del vendedor.
     *
     * @param  Collection<int, Participation>  $participations
     */
    private function indivisibleReason(Collection $participations, float $previousPaid, float $newPayment): ?string
    {
        $countsByPrice = [];
        foreach ($participations as $participation) {
            $cents = (int) round(self::participationPrice($participation) * 100);
            if ($cents > 0) {
                $countsByPrice[$cents] = ($countsByPrice[$cents] ?? 0) + 1;
            }
        }
        if ($countsByPrice === []) {
            return null;
        }

        $previousCents = (int) round($previousPaid * 100);
        $targetCents = $previousCents + (int) round($newPayment * 100);
        if ($this->isReachable($countsByPrice, $targetCents)) {
            return null;
        }

        $prices = array_keys($countsByPrice);
        if (count($prices) === 1) {
            $price = $prices[0];
            $lower = intdiv($targetCents, $price) * $price - $previousCents;
            $upper = $lower + $price;
            $maxNew = $price * $countsByPrice[$price] - $previousCents;
            $options = array_filter([$lower, $upper], fn ($c) => $c > 0 && $c <= $maxNew);
            $hint = $options === []
                ? ''
                : ' Puedes registrar '.implode(' o ', array_map(fn ($c) => $this->money($c / 100), $options)).'.';

            return 'Las participaciones no se pueden fraccionar: el importe debe cubrir participaciones completas de '
                .$this->money($price / 100).'.'.$hint;
        }

        sort($prices);

        return 'Las participaciones no se pueden fraccionar: el importe debe cubrir participaciones completas (precios: '
            .implode(', ', array_map(fn ($c) => $this->money($c / 100), $prices)).').';
    }

    /**
     * ¿Se puede formar $target sumando participaciones (precio => cantidad disponible)?
     *
     * @param  array<int, int>  $countsByPrice
     */
    private function isReachable(array $countsByPrice, int $target): bool
    {
        if ($target === 0) {
            return true;
        }

        $gcd = 0;
        foreach (array_keys($countsByPrice) as $price) {
            $gcd = $this->gcd($gcd, $price);
        }
        if ($target % $gcd !== 0) {
            return false;
        }

        $target = intdiv($target, $gcd);
        if ($target > 2_000_000) {
            return true;
        }
        $reachable = array_fill(0, $target + 1, false);
        $reachable[0] = true;
        foreach ($countsByPrice as $price => $count) {
            $step = intdiv($price, $gcd);
            $used = array_fill(0, $target + 1, 0);
            for ($amount = $step; $amount <= $target; $amount++) {
                if (! $reachable[$amount] && $reachable[$amount - $step] && $used[$amount - $step] < $count) {
                    $reachable[$amount] = true;
                    $used[$amount] = $used[$amount - $step] + 1;
                }
            }
            if ($reachable[$target]) {
                return true;
            }
        }

        return $reachable[$target];
    }

    private function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }

    /**
     * Registra el apunte y avisa al vendedor. Lanza \InvalidArgumentException si no es válido.
     *
     * @param  array<int, array{payment_method: string, amount: float|int|string}>  $payments
     */
    public function register(
        Seller $seller,
        int $lotteryId,
        array $payments,
        ?string $concept,
        User $actingUser,
        string $defaultNotes = 'Liquidación de vendedor'
    ): SellerSettlement {
        $payments = array_values(array_filter($payments, fn ($p) => (float) ($p['amount'] ?? 0) > 0));
        $newPayment = round((float) collect($payments)->sum(fn ($p) => (float) $p['amount']), 2);
        $concept = trim((string) $concept);

        $entityId = 0;
        $settlement = DB::transaction(function () use ($seller, $lotteryId, $payments, $newPayment, $concept, $actingUser, $defaultNotes, &$entityId) {
            $participations = $this->eligibleParticipations((int) $seller->id, $lotteryId);
            $previousPaid = $this->previousPaid((int) $seller->id, $lotteryId);

            if ($reason = $this->blockReason($participations, $previousPaid, $newPayment)) {
                throw new \InvalidArgumentException($reason);
            }

            $totalAmount = (float) $participations->sum(fn ($p) => self::participationPrice($p));
            $pricePerParticipation = self::participationPrice($participations->first());
            $now = now();

            $settlement = SellerSettlement::create([
                'seller_id' => $seller->id,
                'lottery_id' => $lotteryId,
                'user_id' => $actingUser->id,
                'total_amount' => $totalAmount,
                'paid_amount' => $newPayment,
                'pending_amount' => round($totalAmount - $previousPaid - $newPayment, 2),
                'total_participations' => $participations->count(),
                'calculated_participations' => $pricePerParticipation > 0 ? round($newPayment / $pricePerParticipation, 2) : 0,
                'settlement_date' => $now->format('Y-m-d'),
                'settlement_time' => $now->format('H:i:s'),
                'notes' => $concept !== '' ? $concept : $defaultNotes,
            ]);

            foreach ($payments as $payment) {
                SellerSettlementPayment::create([
                    'seller_settlement_id' => $settlement->id,
                    'amount' => $payment['amount'],
                    'payment_method' => $payment['payment_method'],
                    'notes' => 'Pago de liquidación - '.ucfirst($payment['payment_method']),
                    'payment_date' => $now,
                ]);
            }

            $entityId = (int) ($participations->first()->set->entity_id ?? 0);

            return $settlement;
        });

        $this->notifySeller($seller, $settlement, $actingUser, $entityId > 0 ? Entity::find($entityId) : null);

        return $settlement;
    }

    /**
     * Aviso al vendedor por email y en la app (buzón + push), con copia por email al gestor responsable.
     */
    public function notifySeller(Seller $seller, SellerSettlement $settlement, User $actingUser, ?Entity $entity = null): void
    {
        $seller->loadMissing(['user', 'entities.manager.user']);
        $settlement->loadMissing('payments');
        $isFullySettled = (float) $settlement->pending_amount <= 0.0001;
        $entity ??= $seller->entities->first();
        $lottery = Lottery::find($settlement->lottery_id);
        $mailPayload = [
            'seller_id' => $seller->id,
            'settlement_id' => $settlement->id,
            'is_fully_settled' => $isFullySettled,
        ];
        $context = ['seller_id' => $seller->id, 'lottery_id' => $settlement->lottery_id, 'entity_id' => $entity?->id];
        $communication = app(CommunicationEmailService::class);

        $sellerEmail = trim((string) ($seller->user->email ?? $seller->email ?? ''));
        if ($sellerEmail !== '') {
            try {
                $communication->sendAndLog(
                    recipientEmail: $sellerEmail,
                    recipientRole: 'vendedor',
                    recipientUser: $seller->user,
                    messageType: $isFullySettled ? 'seller_settlement_full' : 'seller_settlement_partial',
                    templateKey: null,
                    mailClass: SellerSettlementStatusMail::class,
                    mailPayload: $mailPayload,
                    context: $context,
                );
            } catch (\Throwable $e) {
                Log::warning('Fallo enviando email de liquidación al vendedor '.$seller->id.': '.$e->getMessage());
            }
        }

        if ($seller->user_id) {
            try {
                $methods = $settlement->payments
                    ->map(fn ($p) => ucfirst((string) $p->payment_method).' '.$this->money((float) $p->amount))
                    ->implode(', ');
                $message = ($entity?->name ?? 'La entidad').' ha registrado tu entrega de '.$this->money((float) $settlement->paid_amount)
                    .($methods !== '' ? ' ('.$methods.')' : '')
                    .($lottery ? ' del sorteo '.$lottery->name : '').'.'
                    .(in_array($settlement->notes, self::DEFAULT_NOTES, true) ? '' : ' Concepto: '.$settlement->notes.'.')
                    .' Pendiente: '.$this->money(max(0, (float) $settlement->pending_amount)).'.';

                app(AppInboxNotificationService::class)->notifyUser(
                    recipientUserId: (int) $seller->user_id,
                    entityId: $entity?->id,
                    administrationId: $entity?->administration_id ? (int) $entity->administration_id : null,
                    senderId: (int) $actingUser->id,
                    kind: 'liquidacion_vendedor',
                    title: $isFullySettled ? 'Liquidación completada' : 'Entrega registrada',
                    message: $message,
                    meta: ['seller_settlement_id' => $settlement->id, 'lottery_id' => $settlement->lottery_id],
                );
            } catch (\Throwable $e) {
                Log::warning('Fallo enviando aviso en app de liquidación al vendedor '.$seller->id.': '.$e->getMessage());
            }
        }

        $managerUser = $entity?->manager?->user;
        if ($managerUser && ! empty($managerUser->email) && (int) $managerUser->id !== (int) $actingUser->id) {
            try {
                $communication->sendAndLog(
                    recipientEmail: (string) $managerUser->email,
                    recipientRole: 'gestor_entidad',
                    recipientUser: $managerUser,
                    messageType: $isFullySettled ? 'seller_settlement_full_copy_entity' : 'seller_settlement_partial_copy_entity',
                    templateKey: null,
                    mailClass: SellerSettlementStatusMail::class,
                    mailPayload: $mailPayload,
                    context: $context,
                );
            } catch (\Throwable $e) {
                Log::warning('Fallo enviando copia de liquidación al gestor: '.$e->getMessage());
            }
        }
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
