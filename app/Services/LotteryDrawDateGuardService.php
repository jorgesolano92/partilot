<?php

namespace App\Services;

use App\Exceptions\LotteryDrawDateBlockedException;
use App\Models\AdministrationLotteryScrutiny;
use App\Models\Lottery;
use App\Models\Reserve;
use App\Models\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Reglas de elegibilidad según draw_date (activables vía LOTTERY_ENFORCE_DRAW_DATE_RULES).
 */
class LotteryDrawDateGuardService
{
    public function isEnforcementEnabled(): bool
    {
        return (bool) config('lottery.enforce_draw_date_rules', true);
    }

    public function hasDrawDatePassed(?Lottery $lottery): bool
    {
        if (! $lottery?->draw_date) {
            return false;
        }

        $tz = config('app.timezone');
        $drawDay = Carbon::parse($lottery->draw_date, $tz)->startOfDay();
        $today = Carbon::today($tz);

        return $drawDay->lt($today);
    }

    public function allowsMutationForLottery(?Lottery $lottery): bool
    {
        if (! $this->isEnforcementEnabled()) {
            return true;
        }

        return ! $this->hasDrawDatePassed($lottery);
    }

    public function blockedMessage(?Lottery $lottery = null): string
    {
        if ($lottery?->draw_date) {
            return 'No se puede continuar: el sorteo del '
                .$lottery->draw_date->format('d/m/Y')
                .' ya ha pasado.';
        }

        return (string) config('lottery.draw_date_passed_message');
    }

    /**
     * @return string|null Mensaje de error o null si está permitido.
     */
    public function mutationDeniedMessage(?Lottery $lottery): ?string
    {
        if ($this->allowsMutationForLottery($lottery)) {
            return null;
        }

        return $this->blockedMessage($lottery);
    }

    public function mutationDeniedMessageForReserve(?Reserve $reserve): ?string
    {
        if (! $this->isEnforcementEnabled()) {
            return null;
        }

        $reserve?->loadMissing(['lottery', 'entity']);

        if ($message = $this->mutationDeniedMessage($reserve?->lottery)) {
            return $message;
        }

        if ($this->hasCompletedScrutiny($reserve?->lottery, $reserve?->entity?->administration_id)) {
            return $this->scrutinyBlockedMessage($reserve?->lottery);
        }

        return null;
    }

    public function mutationDeniedMessageForSet(?Set $set): ?string
    {
        if (! $this->isEnforcementEnabled()) {
            return null;
        }

        $set?->loadMissing(['reserve.lottery', 'entity']);

        if ($message = $this->mutationDeniedMessage($set?->reserve?->lottery)) {
            return $message;
        }

        if ($this->hasCompletedScrutiny($set?->reserve?->lottery, $set?->entity?->administration_id)) {
            return $this->scrutinyBlockedMessage($set?->reserve?->lottery);
        }

        return null;
    }

    public function hasCompletedScrutiny(?Lottery $lottery, ?int $administrationId): bool
    {
        if (! $lottery || ! $administrationId) {
            return false;
        }

        return AdministrationLotteryScrutiny::query()
            ->where('administration_id', $administrationId)
            ->where('lottery_id', $lottery->id)
            ->where('is_scrutinized', true)
            ->exists();
    }

    public function scrutinyBlockedMessage(?Lottery $lottery = null): string
    {
        if ($lottery?->draw_date) {
            return 'No se puede modificar: el escrutinio del sorteo del '
                .$lottery->draw_date->format('d/m/Y')
                .' ya está completado.';
        }

        return 'No se puede modificar: el escrutinio de este sorteo ya está completado.';
    }

    public function assertMutationAllowed(?Lottery $lottery): void
    {
        $message = $this->mutationDeniedMessage($lottery);
        if ($message !== null) {
            throw new LotteryDrawDateBlockedException($message);
        }
    }

    public function assertMutationAllowedForReserve(?Reserve $reserve): void
    {
        $message = $this->mutationDeniedMessageForReserve($reserve);
        if ($message !== null) {
            throw new LotteryDrawDateBlockedException($message);
        }
    }

    public function assertMutationAllowedForSet(?Set $set): void
    {
        $message = $this->mutationDeniedMessageForSet($set);
        if ($message !== null) {
            throw new LotteryDrawDateBlockedException($message);
        }
    }

    public function applyOpenForOperationsScope(Builder $query): Builder
    {
        if (! $this->isEnforcementEnabled()) {
            return $query;
        }

        $today = Carbon::today(config('app.timezone'))->toDateString();

        return $query->where(function (Builder $q) use ($today) {
            $q->whereNull('draw_date')
                ->orWhereDate('draw_date', '>=', $today);
        });
    }
}
