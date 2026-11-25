<?php

namespace App\Services;

use App\Models\Devolution;
use App\Models\DevolutionDetail;
use App\Models\Entity;
use App\Models\EntityLotteryPrizeSetting;
use App\Models\Lottery;
use App\Models\LotteryDeadlineAdminDecision;
use App\Models\LotteryDeadlineClosureLog;
use App\Models\Participation;
use App\Models\ParticipationActivityLog;
use App\Models\Reserve;
use App\Models\Set;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LotteryDeadlineClosureService
{
    public const ANNUL_REASON = 'Anulada por impago tras fecha límite de devolución';

    public const ANNUL_PUBLIC_MESSAGE = 'Esta participación ha sido anulada por impago y no es válida.';

    public function __construct(
        private LotteryDeadlineReminderService $reminderService
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('lottery.auto_deadline_closure.enabled', false);
    }

    /**
     * Aplica de inmediato la decisión de administración (asumir deuda / anular por impago).
     *
     * @return array<string, mixed>
     */
    public function applyAdminDecision(int $entityId, int $lotteryId, string $decision, bool $force = true): array
    {
        $entity = Entity::query()->findOrFail($entityId);
        $lottery = Lottery::query()->findOrFail($lotteryId);
        $deadline = $this->reminderService->resolveEffectiveDeadline($entity, $lottery) ?? now()->startOfDay();

        return $this->processCandidate(
            [
                'entity' => $entity,
                'lottery' => $lottery,
                'deadline' => $deadline,
            ],
            now()->startOfDay(),
            false,
            $force,
            $decision
        );
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
    public function processCandidate(
        array $candidate,
        Carbon $asOf,
        bool $dryRun = false,
        bool $force = false,
        ?string $forcedDecision = null
    ): array {
        /** @var Entity $entity */
        $entity = $candidate['entity'];
        /** @var Lottery $lottery */
        $lottery = $candidate['lottery'];
        /** @var Carbon $deadline */
        $deadline = $candidate['deadline'];

        $label = "Entidad #{$entity->id} ({$entity->name}) · Sorteo #{$lottery->id} ({$lottery->displayLabel()})";

        if (! $force && $this->wasAlreadyProcessed($entity->id, $lottery->id)
            && ! $this->hasDueSetsStillOpen($entity, $lottery, $asOf)) {
            return $this->result(
                'skipped',
                $label,
                'Ya procesado anteriormente (usa --force para repetir en pruebas).',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_ALREADY_CLOSED
            );
        }

        $decision = $forcedDecision
            ?? LotteryDeadlineAdminDecision::query()
                ->where('entity_id', $entity->id)
                ->where('lottery_id', $lottery->id)
                ->value('decision');

        if (! $decision) {
            // No persistir log: si no, wasAlreadyProcessed bloquearía el reintento tras la decisión.
            return $this->result(
                'skipped',
                $label,
                'Pendiente de decisión de administración (asumir deuda / anular por impago).',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_NO_PENDING
            );
        }

        if ($this->requiresSpecialPrizeSettlement($lottery)
            && $decision === LotteryDeadlineAdminDecision::DECISION_ASSUME_DEBT) {
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

        // AUD-046: solo cerrar sets cuya fecha límite ya ha pasado (no la más temprana de todos).
        // Si la decisión se aplica en el día 0, permitir sets cuya deadline es hoy o ya pasó.
        $setIds = Set::query()
            ->whereIn('reserve_id', $reserveIds)
            ->where('status', 1)
            ->get()
            ->filter(function (Set $set) use ($lottery, $asOf, $forcedDecision) {
                if ($forcedDecision !== null) {
                    return $this->setDeadlineHasPassedOrIsToday($set, $lottery, $asOf);
                }

                return $this->setDeadlineHasPassed($set, $lottery, $asOf);
            })
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
                'Sin sets activos con fecha límite vencida.',
                LotteryDeadlineClosureLog::STATUS_SKIPPED_NO_PENDING
            );
        }

        $pendingIds = Participation::query()
            ->where('entity_id', $entity->id)
            ->whereIn('set_id', $setIds)
            ->whereIn('status', ['disponible', 'asignada'])
            ->pluck('id')
            ->all();

        if ($pendingIds === []) {
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

        if ($decision === LotteryDeadlineAdminDecision::DECISION_ANNUL) {
            return $this->processAnnulCandidate(
                $entity,
                $lottery,
                $deadline,
                $setIds,
                $pendingIds,
                $label,
                $dryRun,
                $force
            );
        }

        return $this->processAssumeDebtCandidate(
            $entity,
            $lottery,
            $deadline,
            $setIds,
            $label,
            $dryRun,
            $force
        );
    }

    /**
     * @param  array<int>  $setIds
     * @param  array<int>  $pendingIds
     * @return array<string, mixed>
     */
    private function processAnnulCandidate(
        Entity $entity,
        Lottery $lottery,
        Carbon $deadline,
        array $setIds,
        array $pendingIds,
        string $label,
        bool $dryRun,
        bool $force
    ): array {
        if ($dryRun) {
            return $this->result(
                'completed',
                $label,
                'Simulación: anular por impago '.count($pendingIds).' participaciones pendientes.',
                LotteryDeadlineClosureLog::STATUS_COMPLETED,
                ['participations_annulled' => count($pendingIds)]
            );
        }

        try {
            $annulled = DB::transaction(function () use ($entity, $lottery, $deadline, $pendingIds, $force) {
                if ($force) {
                    $this->deletePreviousLogs($entity->id, $lottery->id);
                }

                $now = now();
                $userId = $this->resolveSystemUserId();
                $reason = self::ANNUL_REASON;
                $count = 0;

                Participation::query()->whereIn('id', $pendingIds)->orderBy('id')->chunkById(200, function ($chunk) use ($now, $userId, $reason, &$count) {
                    $ids = $chunk->pluck('id')->all();
                    Participation::whereIn('id', $ids)->update([
                        'status' => 'anulada',
                        'cancellation_reason' => $reason,
                        'cancelled_by' => $userId,
                        'cancellation_date' => $now->format('Y-m-d'),
                        'updated_at' => $now,
                    ]);

                    foreach ($chunk as $participation) {
                        ParticipationActivityLog::log($participation->id, 'cancelled', [
                            'entity_id' => $participation->entity_id,
                            'seller_id' => $participation->seller_id,
                            'old_status' => $participation->status,
                            'new_status' => 'anulada',
                            'description' => 'Participación anulada por impago (decisión administración)',
                            'metadata' => [
                                'cancellation_reason' => $reason,
                                'cancelled_by' => $userId,
                                'cancellation_date' => $now->format('Y-m-d'),
                                'source' => 'lottery_deadline_admin_annul',
                            ],
                        ]);
                    }

                    $count += count($ids);
                });

                LotteryDeadlineClosureLog::create([
                    'entity_id' => $entity->id,
                    'lottery_id' => $lottery->id,
                    'effective_deadline' => $deadline->toDateString(),
                    'devolution_id' => null,
                    'participations_sold' => 0,
                    'participations_returned_digital' => 0,
                    'total_liquidation' => 0,
                    'status' => LotteryDeadlineClosureLog::STATUS_COMPLETED,
                    'message' => "Anulación por impago completada ({$count} participaciones).",
                    'processed_at' => $now,
                ]);

                return $count;
            });

            return $this->result(
                'completed',
                $label,
                "Anulación por impago completada ({$annulled} participaciones).",
                LotteryDeadlineClosureLog::STATUS_COMPLETED,
                [
                    'decision' => LotteryDeadlineAdminDecision::DECISION_ANNUL,
                    'participations_annulled' => $annulled,
                ]
            );
        } catch (\Throwable $e) {
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
     * @return array<string, mixed>
     */
    private function processAssumeDebtCandidate(
        Entity $entity,
        Lottery $lottery,
        Carbon $deadline,
        array $setIds,
        string $label,
        bool $dryRun,
        bool $force
    ): array {
        $physicalToSell = Participation::query()
            ->where('entity_id', $entity->id)
            ->whereIn('set_id', $setIds)
            ->whereIn('status', ['disponible', 'asignada'])
            ->whereRaw("(participation_code IS NULL OR participation_code NOT LIKE '1D/%')")
            ->pluck('id')
            ->all();

        $digitalToReturn = Participation::query()
            ->where('entity_id', $entity->id)
            ->whereIn('set_id', $setIds)
            ->whereRaw("participation_code LIKE '1D/%'")
            ->whereIn('status', ['disponible', 'asignada'])
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
                    'notes' => 'Liquidación por decisión de administración — se asume la deuda ('.$deadline->format('d/m/Y').')',
                    'special_prize_settlement' => null,
                ]);

                // AUD-058: actualizar por lotes para no agotar la memoria con miles de observadores.
                Participation::query()->whereIn('id', $digitalToReturn)->orderBy('id')->chunkById(200, function ($chunk) use ($now, $returnReason, $userId, $devolution) {
                    $details = [];
                    foreach ($chunk as $participation) {
                        Participation::whereKey($participation->id)->update([
                            'status' => 'devuelta',
                            'return_date' => $now->format('Y-m-d'),
                            'return_time' => $now->format('H:i:s'),
                            'return_reason' => $returnReason.' (digitales no vendidas)',
                            'returned_by' => $userId,
                            'updated_at' => $now,
                        ]);
                        $details[] = [
                            'devolution_id' => $devolution->id,
                            'participation_id' => $participation->id,
                            'action' => 'devolver',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if ($details !== []) {
                        DevolutionDetail::insert($details);
                    }
                });

                Participation::query()->with('set')->whereIn('id', $physicalToSell)->orderBy('id')->chunkById(200, function ($chunk) use ($now, $devolution) {
                    $details = [];
                    foreach ($chunk as $participation) {
                        $saleAmount = $participation->set ? $this->pricePerParticipationSet($participation->set) : 0;
                        Participation::whereKey($participation->id)->update([
                            'status' => 'vendida',
                            'sale_date' => $now->format('Y-m-d'),
                            'sale_time' => $now->format('H:i:s'),
                            'sale_amount' => $saleAmount,
                            'notes' => 'Liquidación por asunción de deuda (fecha límite)',
                            'updated_at' => $now,
                        ]);
                        $details[] = [
                            'devolution_id' => $devolution->id,
                            'participation_id' => $participation->id,
                            'action' => 'vender',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if ($details !== []) {
                        DevolutionDetail::insert($details);
                    }
                });

                LotteryDeadlineClosureLog::create([
                    'entity_id' => $entity->id,
                    'lottery_id' => $lottery->id,
                    'effective_deadline' => $deadline->toDateString(),
                    'devolution_id' => $devolution->id,
                    'participations_sold' => count($physicalToSell),
                    'participations_returned_digital' => count($digitalToReturn),
                    'total_liquidation' => $totalLiquidation,
                    'status' => LotteryDeadlineClosureLog::STATUS_COMPLETED,
                    'message' => 'Liquidación por asunción de deuda completada.',
                    'processed_at' => $now,
                ]);

                return $devolution->id;
            });

            // AUD-054: fijar modalidad de pago de premios para no bloquear el escrutinio.
            try {
                $alreadyLocked = EntityLotteryPrizeSetting::query()
                    ->where('entity_id', $entity->id)
                    ->where('lottery_id', $lottery->id)
                    ->whereNotNull('mode_locked_at')
                    ->exists();
                if (! $alreadyLocked) {
                    app(EntityLotteryPrizePaymentService::class)->lockModeFromDevolution(
                        (int) $entity->id,
                        (int) $lottery->id,
                        EntityLotteryPrizeSetting::MODE_PRESENCIAL,
                        $this->resolveSystemUserId()
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Cierre automático: no se pudo fijar modalidad de premios: '.$e->getMessage());
            }

            return $this->result(
                'completed',
                $label,
                "Liquidación completada. Devolución #{$devolutionId}, liquidación {$totalLiquidation} €.",
                LotteryDeadlineClosureLog::STATUS_COMPLETED,
                [
                    'decision' => LotteryDeadlineAdminDecision::DECISION_ASSUME_DEBT,
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

            // Entidad→admin: PVP o PVP−donativo según LOTTERY_DONATION_RECIPIENT
            $price = $this->administrationLiquidationUnitPrice($set);
            $total += ($physicalCount + $digitalesVendidas) * $price;
        }

        return round($total, 2);
    }

    private function pricePerParticipationSet(Set $set): float
    {
        return $set->pricePerParticipation();
    }

    private function administrationLiquidationUnitPrice(Set $set): float
    {
        return $set->administrationLiquidationUnitPrice();
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

    private function setDeadlineHasPassed(Set $set, Lottery $lottery, Carbon $asOf): bool
    {
        $setDeadline = $set->deadline_date
            ? Carbon::parse($set->deadline_date)->startOfDay()
            : ($lottery->deadline_date ? $lottery->deadline_date->copy()->startOfDay() : null);

        return $setDeadline !== null && $setDeadline->lt($asOf);
    }

    private function setDeadlineHasPassedOrIsToday(Set $set, Lottery $lottery, Carbon $asOf): bool
    {
        $setDeadline = $set->deadline_date
            ? Carbon::parse($set->deadline_date)->startOfDay()
            : ($lottery->deadline_date ? $lottery->deadline_date->copy()->startOfDay() : null);

        return $setDeadline !== null && $setDeadline->lte($asOf);
    }

    private function hasDueSetsStillOpen(Entity $entity, Lottery $lottery, Carbon $asOf): bool
    {
        $reserveIds = Reserve::query()
            ->where('entity_id', $entity->id)
            ->where('lottery_id', $lottery->id)
            ->where('status', 1)
            ->pluck('id');

        $dueSetIds = Set::query()
            ->whereIn('reserve_id', $reserveIds)
            ->where('status', 1)
            ->get()
            ->filter(fn (Set $set) => $this->setDeadlineHasPassed($set, $lottery, $asOf))
            ->pluck('id');

        if ($dueSetIds->isEmpty()) {
            return false;
        }

        return Participation::query()
            ->where('entity_id', $entity->id)
            ->whereIn('set_id', $dueSetIds)
            ->whereIn('status', ['disponible', 'asignada'])
            ->where('status', '!=', 'anulada')
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
