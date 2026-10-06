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
     * Etiqueta legible para décimos (p. ej. 2,5 sin ceros sobrantes).
     */
    public static function formatDecimosLabel(float $decimos): string
    {
        return rtrim(rtrim(number_format($decimos, 2, ',', '.'), '0'), ',');
    }

    /**
     * Datos de visualización desde el registro guardado (sin re-redondear décimos).
     *
     * @return array{decimos: float, decimos_label: string, premio_total: float}
     */
    public function recalculateDecimos(ScrutinyDetailedResult $result, Lottery $lottery): array
    {
        $decimos = (float) $result->total_decimos;

        return [
            'decimos' => $decimos,
            'decimos_label' => self::formatDecimosLabel($decimos),
            'premio_total' => (float) $result->premio_total,
        ];
    }
}
