<?php

namespace App\Services;

use App\Models\Entity;
use App\Models\Participation;
use Illuminate\Support\Collection;

class PrizeWalletOperationGuardService
{
    public function assertEntityActive(?Entity $entity): ?string
    {
        if (! $entity) {
            return 'Entidad no encontrada.';
        }

        if ((int) $entity->status !== 1) {
            return 'La entidad no está activa para cobros o donaciones.';
        }

        return null;
    }

    /**
     * @param  Collection<int, Participation>  $participations
     * @param  callable(Participation): array{has_won?: bool, prize_amount?: float|int}  $prizeInfoResolver
     */
    public function assertAllParticipationsHavePrize(Collection $participations, callable $prizeInfoResolver): ?string
    {
        foreach ($participations as $participation) {
            $info = $prizeInfoResolver($participation);
            if (! ($info['has_won'] ?? false) || (float) ($info['prize_amount'] ?? 0) <= 0) {
                return 'Solo se pueden operar participaciones premiadas con premio mayor que cero.';
            }
        }

        return null;
    }

    public function assertPositiveAmount(float $amount): ?string
    {
        if ($amount <= 0) {
            return 'El importe debe ser mayor que cero.';
        }

        return null;
    }

    public function assertWithinGlobalCap(float $amount): ?string
    {
        $max = (float) config('prize_wallet.max_operation_amount', 50000);
        if ($amount > $max) {
            return 'El importe supera el límite máximo permitido por operación.';
        }

        return null;
    }

    /**
     * @param  Collection<int, Participation>  $participations
     * @param  callable(Participation): float  $walletAmountResolver
     */
    public function assertAmountMatchesParticipations(
        float $importeTotal,
        Collection $participations,
        callable $walletAmountResolver,
        float $tolerance = 0.01
    ): ?string {
        $expected = round($participations->sum(fn (Participation $p) => $walletAmountResolver($p)), 2);
        if (abs($importeTotal - $expected) > $tolerance) {
            return 'El importe no coincide con el total de las participaciones.';
        }

        return null;
    }

    /**
     * @param  Collection<int, Participation>  $participations
     */
    public function assertEntitiesActive(Collection $participations): ?string
    {
        $participations->loadMissing('set.entity');

        foreach ($participations as $participation) {
            $entity = $participation->set?->entity;
            if ($message = $this->assertEntityActive($entity)) {
                return $message;
            }
        }

        return null;
    }
}
