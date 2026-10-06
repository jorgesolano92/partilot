<?php

namespace App\Services;

use App\Mail\LotteryDeadlineReminderMail;
use App\Models\Entity;
use App\Models\Lottery;
use App\Models\LotteryDeadlineAdminDecision;
use App\Models\LotteryDeadlineReminderLog;
use App\Models\Participation;
use App\Models\Set;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class LotteryDeadlineReminderService
{
    public const REMINDER_DAYS = [3, 2, 1, 0];

    /** @var array<string, Collection<int, array<string, mixed>>> */
    private array $cachedReminderContexts = [];

    public function __construct(
        private SellerLiquidationService $sellerLiquidationService
    ) {}

    public function resolveEffectiveDeadline(Entity $entity, Lottery $lottery): ?Carbon
    {
        if (! $lottery->deadline_date) {
            return null;
        }

        $lotteryDeadline = $lottery->deadline_date->copy()->startOfDay();

        $setDeadlines = Set::query()
            ->where('entity_id', $entity->id)
            ->whereHas('reserve', function ($query) use ($lottery) {
                $query->where('lottery_id', $lottery->id)->where('status', 1);
            })
            ->whereNotNull('deadline_date')
            ->pluck('deadline_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        if ($setDeadlines->isEmpty()) {
            return $lotteryDeadline;
        }

        $earliestSetDeadline = $setDeadlines->min();

        return $earliestSetDeadline->lt($lotteryDeadline) ? $earliestSetDeadline : $lotteryDeadline;
    }

    public function countPendingDevolutionParticipations(int $entityId, int $lotteryId): int
    {
        return Participation::query()
            ->where('entity_id', $entityId)
            ->whereIn('status', ['disponible', 'asignada'])
            ->whereHas('set.reserve', function ($query) use ($lotteryId) {
                $query->where('lottery_id', $lotteryId)->where('status', 1);
            })
            ->count();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    /**
     * @param  array<int, int>|null  $entityIds  Limitar a estas entidades (null = todas)
     */
    public function collectReminderContexts(?Carbon $today = null, ?array $entityIds = null): Collection
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $cacheKey = $today->toDateString().'|'.($entityIds === null ? '*' : implode(',', collect($entityIds)->map(fn ($id) => (int) $id)->unique()->sort()->all()));

        if (isset($this->cachedReminderContexts[$cacheKey])) {
            return $this->cachedReminderContexts[$cacheKey];
        }

        $contexts = collect();

        if ($entityIds === []) {
            return $this->cachedReminderContexts[$cacheKey] = $contexts;
        }

        // El plazo efectivo nunca es posterior al del sorteo: los sorteos con plazo vencido no pueden generar avisos.
        $lotteries = Lottery::query()
            ->where('status', 1)
            ->whereNotNull('deadline_date')
            ->whereDate('deadline_date', '>=', $today->toDateString())
            ->get();

        foreach ($lotteries as $lottery) {
            $entities = Entity::query()
                ->where('status', 1)
                ->when($entityIds !== null, fn ($query) => $query->whereIn('id', $entityIds))
                ->whereHas('reserves', function ($query) use ($lottery) {
                    $query->where('lottery_id', $lottery->id)->where('status', 1);
                })
                ->with(['administration', 'manager.user'])
                ->get();

            foreach ($entities as $entity) {
                $deadline = $this->resolveEffectiveDeadline($entity, $lottery);
                if (! $deadline) {
                    continue;
                }

                $daysBefore = (int) $today->diffInDays($deadline, false);
                if (! in_array($daysBefore, self::REMINDER_DAYS, true)) {
                    continue;
                }

                $pendingCount = $this->countPendingDevolutionParticipations($entity->id, $lottery->id);
                $sellerPendingAmount = $this->sellerLiquidationService
                    ->sumPendingLiquidationForEntityLottery($entity->id, $lottery->id);

                if ($pendingCount <= 0 && $sellerPendingAmount <= 0) {
                    continue;
                }

                $contexts->push([
                    'key' => $this->alertKey($entity->id, $lottery->id, $daysBefore),
                    'entity_id' => $entity->id,
                    'lottery_id' => $lottery->id,
                    'entity_name' => $entity->name,
                    'lottery_name' => $lottery->displayLabel(),
                    'days_before' => $daysBefore,
                    'deadline' => $deadline,
                    'pending_count' => $pendingCount,
                    'seller_pending_amount' => round($sellerPendingAmount, 2),
                    'entity' => $entity,
                    'lottery' => $lottery,
                ]);
            }
        }

        return $this->cachedReminderContexts[$cacheKey] = $contexts->sortBy([
            ['days_before', 'asc'],
            ['entity_name', 'asc'],
        ])->values();
    }

    /**
     * @return array{sent: int, skipped: int, contexts: int}
     */
    public function runEmailReminders(bool $dryRun = false): array
    {
        $today = now()->startOfDay();
        $contexts = $this->collectReminderContexts($today);
        $sent = 0;
        $skipped = 0;

        foreach ($contexts as $context) {
            $recipients = $this->emailRecipientsForContext($context);

            foreach ($recipients as $channel => $email) {
                if ($this->wasAlreadySent(
                    (int) $context['entity_id'],
                    (int) $context['lottery_id'],
                    (int) $context['days_before'],
                    $channel,
                    $email,
                    $today
                )) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $sent++;

                    continue;
                }

                Mail::to($email)->send(new LotteryDeadlineReminderMail($context, $channel));

                LotteryDeadlineReminderLog::create([
                    'entity_id' => $context['entity_id'],
                    'lottery_id' => $context['lottery_id'],
                    'days_before' => $context['days_before'],
                    'channel' => $channel,
                    'recipient' => $email,
                    'reminded_on' => $today->toDateString(),
                    'sent_at' => now(),
                ]);

                $sent++;
            }
        }

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'contexts' => $contexts->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getModalAlertsForUser(User $user): array
    {
        if (! $this->userCanSeeModalAlerts($user)) {
            return [];
        }

        $accessibleEntityIds = $user->accessibleEntityIds();
        if ($accessibleEntityIds === []) {
            return [];
        }

        $today = now()->startOfDay();

        return $this->collectReminderContexts($today, $user->isSuperAdmin() ? null : $accessibleEntityIds)
            ->filter(function (array $context) use ($accessibleEntityIds, $user, $today) {
                if (! in_array((int) $context['entity_id'], $accessibleEntityIds, true)) {
                    return false;
                }

                return ! $this->wasAlreadySent(
                    (int) $context['entity_id'],
                    (int) $context['lottery_id'],
                    (int) $context['days_before'],
                    LotteryDeadlineReminderLog::CHANNEL_MODAL,
                    (string) $user->id,
                    $today
                );
            })
            ->map(function (array $context) {
                return [
                    'key' => $context['key'],
                    'entity_id' => $context['entity_id'],
                    'lottery_id' => $context['lottery_id'],
                    'entity_name' => $context['entity_name'],
                    'lottery_name' => $context['lottery_name'],
                    'days_before' => $context['days_before'],
                    'deadline_label' => $context['deadline']->format('d/m/Y'),
                    'pending_count' => $context['pending_count'],
                    'seller_pending_amount' => $context['seller_pending_amount'] ?? 0,
                    'message' => $this->buildMessage($context),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Modal día 0: decisión admin (anular / asumir deuda). Solo administración, no superadmin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminDecisionModalsForUser(User $user): array
    {
        if (! $user->isAdministration() || $user->isSuperAdmin()) {
            return [];
        }

        $accessibleEntityIds = $user->accessibleEntityIds();
        if ($accessibleEntityIds === []) {
            return [];
        }

        $today = now()->startOfDay();

        return $this->collectReminderContexts($today, $accessibleEntityIds)
            ->filter(fn (array $context) => (int) $context['days_before'] === 0)
            ->filter(fn (array $context) => in_array((int) $context['entity_id'], $accessibleEntityIds, true))
            ->filter(fn (array $context) => ! LotteryDeadlineAdminDecision::hasDecision(
                (int) $context['entity_id'],
                (int) $context['lottery_id']
            ))
            ->map(function (array $context) {
                return [
                    'key' => $context['key'],
                    'entity_id' => $context['entity_id'],
                    'lottery_id' => $context['lottery_id'],
                    'entity_name' => $context['entity_name'],
                    'lottery_name' => $context['lottery_name'],
                    'deadline_label' => $context['deadline']->format('d/m/Y'),
                    'pending_count' => $context['pending_count'],
                    'seller_pending_amount' => $context['seller_pending_amount'] ?? 0,
                ];
            })
            ->values()
            ->all();
    }

    public function isAdminDecisionRequired(int $entityId, int $lotteryId): bool
    {
        $today = now()->startOfDay();

        $context = $this->collectReminderContexts($today)->first(
            fn (array $c) => (int) $c['entity_id'] === $entityId
                && (int) $c['lottery_id'] === $lotteryId
                && (int) $c['days_before'] === 0
        );

        if (! $context) {
            return false;
        }

        return ! LotteryDeadlineAdminDecision::hasDecision($entityId, $lotteryId);
    }

    /**
     * @param  array<int, string>  $alertKeys
     */
    public function dismissModalAlertsForUser(User $user, array $alertKeys): void
    {
        if ($alertKeys === []) {
            return;
        }

        $today = now()->startOfDay();
        $contexts = $this->collectReminderContexts($today)->keyBy('key');

        foreach ($alertKeys as $alertKey) {
            $context = $contexts->get($alertKey);
            if (! $context) {
                continue;
            }

            if ($this->wasAlreadySent(
                (int) $context['entity_id'],
                (int) $context['lottery_id'],
                (int) $context['days_before'],
                LotteryDeadlineReminderLog::CHANNEL_MODAL,
                (string) $user->id,
                $today
            )) {
                continue;
            }

            LotteryDeadlineReminderLog::create([
                'entity_id' => $context['entity_id'],
                'lottery_id' => $context['lottery_id'],
                'days_before' => $context['days_before'],
                'channel' => LotteryDeadlineReminderLog::CHANNEL_MODAL,
                'recipient' => (string) $user->id,
                'reminded_on' => $today->toDateString(),
                'sent_at' => now(),
            ]);
        }
    }

    public function buildMessage(array $context): string
    {
        $daysBefore = (int) $context['days_before'];
        $entityName = (string) $context['entity_name'];
        $lotteryName = (string) $context['lottery_name'];
        $pendingCount = (int) $context['pending_count'];
        $sellerPendingAmount = (float) ($context['seller_pending_amount'] ?? 0);
        $deadlineLabel = $context['deadline']->format('d/m/Y');

        if ($daysBefore === 0) {
            $intro = "Hoy es el último día ({$deadlineLabel}) para que la entidad {$entityName} registre la devolución de las participaciones no vendidas del sorteo {$lotteryName}.";
        } elseif ($daysBefore === 1) {
            $intro = "Queda 1 día para que la entidad {$entityName} registre la devolución de las participaciones no vendidas del sorteo {$lotteryName} (fecha límite: {$deadlineLabel}).";
        } else {
            $intro = "Quedan {$daysBefore} días para que la entidad {$entityName} registre la devolución de las participaciones no vendidas del sorteo {$lotteryName} (fecha límite: {$deadlineLabel}).";
        }

        $details = [];
        if ($pendingCount > 0) {
            $details[] = "{$pendingCount} participaciones pendientes de devolución";
        }
        if ($sellerPendingAmount > 0) {
            $details[] = 'deuda de liquidación de vendedores pendiente ('.number_format($sellerPendingAmount, 2, ',', '.').' €)';
        }

        $body = $details !== []
            ? ' Nuestro sistema indica: '.implode(' y ', $details).'.'
            : '';

        return $intro
            .$body
            .' Importante: todas las participaciones que no se registren como devueltas al finalizar el día de fecha límite, serán automáticamente consideradas "vendidas" y generarán la deuda correspondiente.';
    }

    public function alertKey(int $entityId, int $lotteryId, int $daysBefore): string
    {
        return "{$entityId}:{$lotteryId}:{$daysBefore}";
    }

    private function userCanSeeModalAlerts(User $user): bool
    {
        if ($user->isSeller() || $user->isPrintShop()) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return (bool) config('lottery.deadline_reminders.superadmin_modal', false);
        }

        return $user->isAdministration() || $user->isEntity();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function emailRecipientsForContext(array $context): array
    {
        /** @var Entity $entity */
        $entity = $context['entity'];
        $recipients = [];

        $entityEmail = trim((string) ($entity->email ?? ''));
        if ($entityEmail !== '') {
            $recipients[LotteryDeadlineReminderLog::CHANNEL_EMAIL_ENTITY] = $entityEmail;
        }

        $managerEmail = trim((string) ($entity->manager?->user?->email ?? ''));
        if ($managerEmail !== '' && $managerEmail !== $entityEmail) {
            $recipients[LotteryDeadlineReminderLog::CHANNEL_EMAIL_MANAGER] = $managerEmail;
        }

        $administrationEmail = trim((string) ($entity->administration?->email ?? ''));
        if ($administrationEmail !== '') {
            $recipients[LotteryDeadlineReminderLog::CHANNEL_EMAIL_ADMINISTRATION] = $administrationEmail;
        }

        return $recipients;
    }

    private function wasAlreadySent(
        int $entityId,
        int $lotteryId,
        int $daysBefore,
        string $channel,
        string $recipient,
        Carbon $today
    ): bool {
        return LotteryDeadlineReminderLog::query()
            ->where('entity_id', $entityId)
            ->where('lottery_id', $lotteryId)
            ->where('days_before', $daysBefore)
            ->where('channel', $channel)
            ->where('recipient', $recipient)
            ->whereDate('reminded_on', $today->toDateString())
            ->exists();
    }
}
