<?php

namespace App\Services;

use App\Models\AdministrationLotteryScrutiny;
use App\Models\Entity;
use App\Models\Lottery;
use App\Models\Participation;
use App\Models\ScrutinyDetailedResult;
use App\Models\User;
use App\Support\ActiveEntityContext;

class EntityLotteryPrizeService
{
    public function resolveViewEntity(User $user): ?Entity
    {
        $entityIds = $user->accessibleEntityIds();
        if (empty($entityIds)) {
            return null;
        }

        $entityId = ActiveEntityContext::activeEntityId($user) ?? $entityIds[0];
        if (! in_array($entityId, $entityIds, true)) {
            $entityId = $entityIds[0];
        }

        return Entity::query()->find($entityId);
    }

    public function entityParticipatesInLottery(Entity $entity, Lottery $lottery): bool
    {
        return $entity->reserves()
            ->where('lottery_id', $lottery->id)
            ->exists();
    }

    public function getScrutinyForEntity(Entity $entity, Lottery $lottery): ?AdministrationLotteryScrutiny
    {
        return AdministrationLotteryScrutiny::query()
            ->where('lottery_id', $lottery->id)
            ->where('administration_id', $entity->administration_id)
            ->where('is_scrutinized', true)
            ->with([
                'detailedResults' => function ($query) use ($entity) {
                    $query->where('entity_id', $entity->id)
                        ->where('total_decimos', '>', 0)
                        ->with('set');
                },
            ])
            ->first();
    }

    /**
     * @return array{emitidas: int, vendidas: int, devueltas: int}
     */
    public function participationStats(Entity $entity, Lottery $lottery): array
    {
        $base = Participation::query()
            ->whereHas('set.reserve', fn ($query) => $query->where('lottery_id', $lottery->id))
            ->whereHas('entity', fn ($query) => $query->where('id', $entity->id));

        return [
            'emitidas' => (clone $base)->count(),
            'vendidas' => (clone $base)->soldForScrutiny()->count(),
            'devueltas' => (clone $base)->where('status', 'devuelta')->count(),
        ];
    }

    /**
     * @return array{decimos: int, premio_total: float}
     */
    public function recalculateDecimos(ScrutinyDetailedResult $result, Lottery $lottery): array
    {
        $ticketPrice = (float) ($lottery->ticket_price ?? 0);
        $pricePerParticipation = (float) ($result->set->played_amount ?? 0);
        $totalParticipations = (int) $result->total_participations;
        $participacionesPorDecimo = $pricePerParticipation > 0 ? $ticketPrice / $pricePerParticipation : 0;
        $decimos = $participacionesPorDecimo > 0
            ? (int) round($totalParticipations / $participacionesPorDecimo)
            : 0;
        $premioTotal = (float) $result->premio_por_decimo * $decimos;

        return [
            'decimos' => $decimos,
            'premio_total' => $premioTotal,
        ];
    }
}
