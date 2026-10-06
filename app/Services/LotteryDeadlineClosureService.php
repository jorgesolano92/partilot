<?php

namespace App\Services;

use App\Models\Devolution;
use App\Models\DevolutionDetail;
use App\Models\Entity;
use App\Models\Lottery;
use App\Models\LotteryDeadlineClosureLog;
use App\Models\Participation;
use App\Models\Reserve;
use App\Models\Set;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LotteryDeadlineClosureService
{
    public function __construct(
        private LotteryDeadlineReminderService $reminderService
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('lottery.auto_deadline_closure.enabled', false);
    }

    /**
     * @return array{processed: int, completed: int, skipped: int, failed: int, details: array<int, array<string, mixed>>}
     */
    public function run(
        bool $dryRun = false,
        ?Carbon $asOf = null,
        ?int $entityId = null,
        ?int $lotteryId = null,
        bool $force = false
    ): array {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $stats = [
            'processed' => 0,
            'completed' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($this->collectDueClosures($asOf, $entityId, $lotteryId) as $candidate) {
            $stats['processed']++;
            $result = $this->processCandidate($candidate, $asOf, $dryRun, $force);
            $stats['details'][] = $result;
            $stats[$result['stat_key']]++;
        }

        return $stats;
    }

    /**
     * @return Collection<int, array{entity: Entity, lottery: Lottery, deadline: Carbon}>
     */
    public function collectDueClosures(?Carbon $asOf = null, ?int $entityId = null, ?int $lotteryId = null): Collection
    {
        $asOf = ($asOf ?? now())->copy()->startOfDay();
        $items = collect();

        $lotteriesQuery = Lottery::query()->whereNotNull('deadline_date');
        if ($lotteryId) {
            $lotteriesQuery->where('id', $lotteryId);
        }

        foreach ($lotteriesQuery->get() as $lottery) {
            $entitiesQuery = Entity::query()
                ->where('status', 1)
                ->whereHas('reserves', function ($query) use ($lottery) {
                    $query->where('lottery_id', $lottery->id)->where('status', 1);
                });

            if ($entityId) {
                $entitiesQuery->where('id', $entityId);
            }

            foreach ($entitiesQuery->get() as $entity) {
                $deadline = $this->reminderService->resolveEffectiveDeadline($entity, $lottery);
                if (! $deadline || ! $deadline->lt($asOf)) {
                    continue;
                }

                $items->push([
                    'entity' => $entity,
                    'lottery' => $lottery,
                    'deadline' => $deadline,
                ]);
            }
        }

        return $items->sortBy([
            ['deadline', 'asc'],
            fn ($item) => $item['entity']->name,
        ])->values();
    }

    /**
     * @param  array{entity: Entity, lottery: Lottery, deadline: Carbon}  $candidate
     * @return array<string, mixed>
     */
    public function processCandidate(array $candidate, Carbon $asOf, bool $dryRun = false, bool $force = false): array
    {
        /** @var Entity $entity */
        $entity = $candidate['entity'];
        /** @var Lottery $lottery */
        $lottery = $candidate['lottery'];
        /** @var Carbon $deadline */
        $deadline = $candidate['deadline'];

        $label = "Entidad #{$entity->id} ({$entity->name}) · Sorteo #{$lottery->id} ({$lottery->displayLabel()})";

        if (! $force && $this->wasAlreadyProcessed($entity->id, $lottery->id)) {
            return $this->result(
                'skipped',
                $label,
                'Ya procesado anteriormente (usa --force para repetir en pruebas).',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_ALREADY_CLOSED
            );
        }

        if ($this->requiresSpecialPrizeSettlement($lottery)) {
            return $this->persistSkip(
                $entity,
                $lottery,
                $deadline,
                $dryRun,
                $force,
                $label,
                'Requiere asignación manual de premio especial (serie/fracción).',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_SPECIAL_PRIZE
            );
        }

        $reserveIds = Reserve::query()
            ->where('entity_id', $entity->id)
            ->where('lottery_id', $lottery->id)
            ->where('status', 1)
            ->pluck('id')
            ->all();

        if ($reserveIds === []) {
            return $this->persistSkip(
                $entity,
                $lottery,
                $deadline,
                $dryRun,
                $force,
                $label,
                'Sin reserva activa.',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_NO_PENDING
            );
        }

        $setIds = Set::query()
            ->whereIn('reserve_id', $reserveIds)
            ->where('status', 1)
            ->pluck('id')
            ->all();

        if ($setIds === []) {
            return $this->persistSkip(
                $entity,
                $lottery,
                $deadline,
                $dryRun,
                $force,
                $label,
                'Sin sets activos en la reserva.',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_NO_PENDING
            );
        }

        $physicalToSell = Participation::query()
            ->where('entity_id', $entity->id)
            ->whereIn('set_id', $setIds)
            ->whereIn('status', ['disponible', 'asignada'])
            ->where('status', '!=', 'anulada')
            ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
            ->pluck('id')
            ->all();

        $digitalToReturn = Participation::query()
            ->where('entity_id', $entity->id)
            ->whereIn('set_id', $setIds)
            ->whereRaw("participation_code LIKE '1D/%'")
            ->whereIn('status', ['disponible', 'asignada'])
            ->where('status', '!=', 'anulada')
            ->pluck('id')
            ->all();

        if ($physicalToSell === [] && $digitalToReturn === []) {
            return $this->persistSkip(
                $entity,
                $lottery,
                $deadline,
                $dryRun,
                $force,
                $label,
                'No hay participaciones pendientes de cierre.',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_NO_PENDING
            );
        }

        $totalLiquidation = $this->calculateLiquidation($setIds, $physicalToSell);
        $totalParticipations = count($physicalToSell) + count($digitalToReturn);

        if ($dryRun) {
            return $this->result(
                'completed',
                $label,
                "Simulación: {$totalParticipations} participaciones, liquidación {$totalLiquidation} € (vender ".count($physicalToSell).', devolver digitales '.count($digitalToReturn).').',
                LotteryDeadlineClosureLog::STATUS_COMPLETED,
                [
                    'participations_sold' => count($physicalToSell),
                    'participations_returned_digital' => count($digitalToReturn),
                    'total_liquidation' => $totalLiquidation,
                ]
            );
        }

        try {
            $devolutionId = DB::transaction(function () use (
                $entity,
                $lottery,
                $deadline,
                $physicalToSell,
                $digitalToReturn,
                $totalLiquidation,
                $totalParticipations,
                $force
            ) {
                if ($force) {
                    $this->deletePreviousLogs($entity->id, $lottery->id);
                }

                $now = now();
                $userId = $this->resolveSystemUserId();
                $returnReason = (string) config('lottery.auto_deadline_closure.return_reason');

                $devolution = Devolution::create([
                    'entity_id' => $entity->id,
                    'lottery_id' => $lottery->id,
                    'seller_id' => null,
                    'user_id' => $userId,
                    'total_participations' => $totalParticipations,
                    'total_liquidation' => $totalLiquidation,
                    'return_reason' => $returnReason,
                    'devolution_date' => $now->format('Y-m-d'),
                    'devolution_time' => $now->format('H:i:s'),
                    'status' => 'procesada',
                    'notes' => 'Cierre automático por fecha límite ('.$deadline->format('d/m/Y').')',
                    'special_prize_settlement' => null,
                ]);

                foreach (Participation::query()->whereIn('id', $digitalToReturn)->get() as $participation) {
                    $participation->update([
                        'status' => 'devuelta',
                        'return_date' => $now->format('Y-m-d'),
                        'return_time' => $now->format('H:i:s'),
                        'return_reason' => $returnReason.' (digitales no vendidas)',
                        'returned_by' => $userId,
                    ]);

                    DevolutionDetail::create([
                        'devolution_id' => $devolution->id,
                        'participation_id' => $participation->id,
                        'action' => 'devolver',
                    ]);
                }

                foreach (Participation::with('set')->whereIn('id', $physicalToSell)->get() as $participation) {
                    $saleAmount = $participation->set ? $this->pricePerParticipationSet($participation->set) : 0;
                    $participation->update([
                        'status' => 'vendida',
                        'sale_date' => $now->format('Y-m-d'),
                        'sale_time' => $now->format('H:i:s'),
                        'sale_amount' => $saleAmount,
                        'notes' => 'Liquidación automática por cierre de fecha límite',
                    ]);

                    DevolutionDetail::create([
                        'devolution_id' => $devolution->id,
                        'participation_id' => $participation->id,
                        'action' => 'vender',
                    ]);
                }

                LotteryDeadlineClosureLog::create([
                    'entity_id' => $entity->id,
                    'lottery_id' => $lottery->id,
                    'effective_deadline' => $deadline->toDateString(),
                    'devolution_id' => $devolution->id,
                    'participations_sold' => count($physicalToSell),
                    'participations_returned_digital' => count($digitalToReturn),
                    'total_liquidation' => $totalLiquidation,
                    'status' => LotteryDeadlineClosureLog::STATUS_COMPLETED,
                    'message' => 'Cierre automático completado.',
                    'processed_at' => $now,
                ]);

                return $devolution->id;
            });

            return $this->result(
                'completed',
                $label,
                "Cierre completado. Devolución #{$devolutionId}, liquidación {$totalLiquidation} €.",
                LotteryDeadlineClosureLog::STATUS_COMPLETED,
                [
                    'devolution_id' => $devolutionId,
                    'participations_sold' => count($physicalToSell),
                    'participations_returned_digital' => count($digitalToReturn),
                    'total_liquidation' => $totalLiquidation,
                ]
            );
        } catch (\Throwable $e) {
            if (! $dryRun) {
                LotteryDeadlineClosureLog::create([
                    'entity_id' => $entity->id,
                    'lottery_id' => $lottery->id,
                    'effective_deadline' => $deadline->toDateString(),
                    'devolution_id' => null,
                    'participations_sold' => 0,
                    'participations_returned_digital' => 0,
                    'total_liquidation' => 0,
                    'status' => LotteryDeadlineClosureLog::STATUS_FAILED,
                    'message' => $e->getMessage(),
                    'processed_at' => now(),
                ]);
            }

            return $this->result(
                'failed',
                $label,
                $e->getMessage(),
                LotteryDeadlineClosureLog::STATUS_FAILED
            );
        }
    }

    /**
     * @param  array<int>  $setIds
     * @param  array<int>  $physicalToSellIds
     */
    private function calculateLiquidation(array $setIds, array $physicalToSellIds): float
    {
        $total = 0.0;
        $sets = Set::query()->whereIn('id', $setIds)->get()->keyBy('id');
        $sellCounts = Participation::query()
            ->whereIn('id', $physicalToSellIds)
            ->get()
            ->groupBy('set_id')
            ->map(fn ($group) => $group->count());

        foreach ($setIds as $setId) {
            $set = $sets->get($setId);
            if (! $set) {
                continue;
            }

            $physicalCount = (int) ($sellCounts->get($setId) ?? 0);
            $digitalesVendidas = Participation::query()
                ->where('set_id', $setId)
                ->sold()
                ->whereRaw("participation_code LIKE '1D/%'")
                ->count();

            $price = $this->pricePerParticipationSet($set);
            $total += ($physicalCount + $digitalesVendidas) * $price;
        }

        return round($total, 2);
    }

    private function pricePerParticipationSet(Set $set): float
    {
        return $set->pricePerParticipation();
    }

    private function requiresSpecialPrizeSettlement(Lottery $lottery): bool
    {
        $lottery->loadMissing(['result', 'lotteryType']);

        $premioEspecial = $lottery->result?->premio_especial;
        $primerPremio = $lottery->result?->primer_premio;

        $specialSerie = null;
        $specialFraccion = null;

        if (is_array($premioEspecial) && ! empty($premioEspecial['serie']) && ! empty($premioEspecial['fraccion'])) {
            $specialSerie = $premioEspecial['serie'];
            $specialFraccion = $premioEspecial['fraccion'];
        }

        if (($specialSerie === null || $specialFraccion === null) && is_array($primerPremio) && ! empty($primerPremio['serie']) && ! empty($primerPremio['fraccion'])) {
            $specialSerie = $primerPremio['serie'];
            $specialFraccion = $primerPremio['fraccion'];
        }

        $seriesConfigured = (int) ($lottery->lotteryType?->series ?? 0);

        return ! empty($specialSerie) && ! empty($specialFraccion) && $seriesConfigured > 0;
    }

    private function resolveSystemUserId(): int
    {
        $configured = config('lottery.auto_deadline_closure.system_user_id');
        if (filled($configured)) {
            return (int) $configured;
        }

        $superAdminId = User::query()
            ->where('role', User::ROLE_SUPER_ADMIN)
            ->orderBy('id')
            ->value('id');

        if (! $superAdminId) {
            throw new \RuntimeException('No hay usuario super_admin para registrar el cierre automático. Define LOTTERY_AUTO_DEADLINE_CLOSURE_USER_ID.');
        }

        return (int) $superAdminId;
    }

    private function wasAlreadyProcessed(int $entityId, int $lotteryId): bool
    {
        return LotteryDeadlineClosureLog::query()
            ->where('entity_id', $entityId)
            ->where('lottery_id', $lotteryId)
            ->whereIn('status', [
                LotteryDeadlineClosureLog::STATUS_COMPLETED,
                LotteryDeadlineClosureLog::STATUS_SKIPPED_NO_PENDING,
                LotteryDeadlineClosureLog::STATUS_SKIPPED_SPECIAL_PRIZE,
            ])
            ->exists();
    }

    private function deletePreviousLogs(int $entityId, int $lotteryId): void
    {
        LotteryDeadlineClosureLog::query()
            ->where('entity_id', $entityId)
            ->where('lottery_id', $lotteryId)
            ->delete();
    }

    private function persistSkip(
        Entity $entity,
        Lottery $lottery,
        Carbon $deadline,
        bool $dryRun,
        bool $force,
        string $label,
        string $message,
        string $status
    ): array {
        if (! $dryRun) {
            if ($force) {
                $this->deletePreviousLogs($entity->id, $lottery->id);
            }

            LotteryDeadlineClosureLog::create([
                'entity_id' => $entity->id,
                'lottery_id' => $lottery->id,
                'effective_deadline' => $deadline->toDateString(),
                'devolution_id' => null,
                'participations_sold' => 0,
                'participations_returned_digital' => 0,
                'total_liquidation' => 0,
                'status' => $status,
                'message' => $message,
                'processed_at' => now(),
            ]);
        }

        return $this->result('skipped', $label, $message, $status);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function result(string $statKey, string $label, string $message, string $status, array $extra = []): array
    {
        return array_merge([
            'stat_key' => $statKey,
            'label' => $label,
            'message' => $message,
            'status' => $status,
        ], $extra);
    }
}
