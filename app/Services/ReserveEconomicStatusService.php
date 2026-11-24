<?php

namespace App\Services;

use App\Models\DevolutionDetail;
use App\Models\Participation;
use App\Models\Reserve;
use App\Models\Set;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReserveEconomicStatusService
{
    /**
     * Techo económico de la reserva (importe contractual).
     */
    public function reserveCeiling(Reserve $reserve): float
    {
        $numNumbers = is_array($reserve->reservation_numbers) ? count($reserve->reservation_numbers) : 0;
        $fromNumbers = $numNumbers > 0
            ? round($numNumbers * (float) $reserve->reservation_amount, 2)
            : (float) $reserve->total_amount;

        return max((float) $reserve->total_amount, $fromNumbers);
    }

    /**
     * Importe emitido en sets (columna BD, sin restar anuladas del accessor).
     */
    public function setsIssuedAmount(Reserve $reserve): float
    {
        return round((float) Set::where('reserve_id', $reserve->id)->sum('total_amount'), 2);
    }

    /**
     * Suma de devoluciones entidad→administración (action=devolver) sobre sets de la reserva.
     */
    public function returnsHistoricalAmount(Reserve $reserve): float
    {
        $rows = DevolutionDetail::query()
            ->where('action', 'devolver')
            ->whereHas('participation.set', fn ($q) => $q->where('reserve_id', $reserve->id))
            ->with(['participation.set'])
            ->get();

        $total = 0.0;
        foreach ($rows as $detail) {
            $set = $detail->participation?->set;
            if (! $set) {
                continue;
            }
            $total += $this->commitmentUnitForSet($reserve, $set);
        }

        return round($total, 2);
    }

    /**
     * Devoluciones efectivas = histórico no absorbido por sets reemitidos por encima del techo.
     * Ejemplo: histórico 3000, emitido 12000, techo 10000 → efectiva 1000.
     */
    public function returnsEffectiveAmount(float $reserveCeiling, float $setsIssued, float $returnsHistorical): float
    {
        $reissuedBeyondCeiling = max(0.0, round($setsIssued - $reserveCeiling, 2));

        return max(0.0, round($returnsHistorical - $reissuedBeyondCeiling, 2));
    }

    /**
     * Disponible para nuevos sets: techo + histórico − emitido.
     */
    public function availableForNewSets(float $reserveCeiling, float $setsIssued, float $returnsHistorical): float
    {
        return max(0.0, round($reserveCeiling + $returnsHistorical - $setsIssued, 2));
    }

    /**
     * @return array{
     *     reserve_id: int,
     *     lottery_name: ?string,
     *     reservation_numbers_label: string,
     *     reserve_amount: float,
     *     sets_issued_amount: float,
     *     returns_historical_amount: float,
     *     returns_effective_amount: float,
     *     physical_sold_amount: float,
     *     digital_sold_amount: float,
     *     annulled_amount: float,
     *     net_sales_amount: float,
     *     available_for_new_sets: float
     * }
     */
    public function forReserve(Reserve $reserve): array
    {
        $reserve->loadMissing(['lottery', 'sets']);

        $ceiling = $this->reserveCeiling($reserve);
        $setsIssued = $this->setsIssuedAmount($reserve);
        $returnsHistorical = $this->returnsHistoricalAmount($reserve);
        $returnsEffective = $this->returnsEffectiveAmount($ceiling, $setsIssued, $returnsHistorical);
        $available = $this->availableForNewSets($ceiling, $setsIssued, $returnsHistorical);
        $sold = $this->soldAmountsByChannel($reserve);
        $annulled = $this->annulledAmount($reserve);
        $netSales = round($setsIssued - $returnsHistorical, 2);

        $numbers = is_array($reserve->reservation_numbers)
            ? implode(', ', $reserve->reservation_numbers)
            : (string) ($reserve->reservation_numbers ?? $reserve->id);

        return [
            'reserve_id' => (int) $reserve->id,
            'lottery_name' => $reserve->lottery?->name,
            'reservation_numbers_label' => $numbers,
            'reserve_amount' => $ceiling,
            'sets_issued_amount' => $setsIssued,
            'returns_historical_amount' => $returnsHistorical,
            'returns_effective_amount' => $returnsEffective,
            'physical_sold_amount' => $sold['physical'],
            'digital_sold_amount' => $sold['digital'],
            'annulled_amount' => $annulled,
            'net_sales_amount' => $netSales,
            'available_for_new_sets' => $available,
        ];
    }

    /**
     * Cálculo puro para tests / UI sin tocar BD.
     *
     * @param  array{
     *     reserve_amount: float,
     *     sets_issued: float,
     *     returns_historical: float,
     *     physical_sold?: float,
     *     digital_sold?: float,
     *     annulled?: float
     * }  $input
     * @return array{
     *     reserve_amount: float,
     *     sets_issued_amount: float,
     *     returns_historical_amount: float,
     *     returns_effective_amount: float,
     *     physical_sold_amount: float,
     *     digital_sold_amount: float,
     *     annulled_amount: float,
     *     net_sales_amount: float,
     *     available_for_new_sets: float
     * }
     */
    public function computeFromAggregates(array $input): array
    {
        $ceiling = round((float) $input['reserve_amount'], 2);
        $setsIssued = round((float) $input['sets_issued'], 2);
        $historical = round((float) $input['returns_historical'], 2);

        return [
            'reserve_amount' => $ceiling,
            'sets_issued_amount' => $setsIssued,
            'returns_historical_amount' => $historical,
            'returns_effective_amount' => $this->returnsEffectiveAmount($ceiling, $setsIssued, $historical),
            'physical_sold_amount' => round((float) ($input['physical_sold'] ?? 0), 2),
            'digital_sold_amount' => round((float) ($input['digital_sold'] ?? 0), 2),
            'annulled_amount' => round((float) ($input['annulled'] ?? 0), 2),
            'net_sales_amount' => round($setsIssued - $historical, 2),
            'available_for_new_sets' => $this->availableForNewSets($ceiling, $setsIssued, $historical),
        ];
    }

    /**
     * Estado económico de reservas visibles para el usuario (home operativo).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forUserReserves(User $user, int $limit = 20): Collection
    {
        $reserves = Reserve::query()
            ->forUser($user)
            ->with(['lottery'])
            ->whereIn('status', [0, 1, 3])
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $reserves->map(fn (Reserve $reserve) => $this->forReserve($reserve));
    }

    /**
     * Total + disponible para creación de sets (API usada por SetController).
     *
     * @return array{total: float, available: float, sets_issued: float, returns_historical: float}
     */
    public function reserveTotalAndAvailable(Reserve $reserve): array
    {
        $ceiling = $this->reserveCeiling($reserve);
        $setsIssued = $this->setsIssuedAmount($reserve);
        $historical = $this->returnsHistoricalAmount($reserve);

        return [
            'total' => $ceiling,
            'available' => $this->availableForNewSets($ceiling, $setsIssued, $historical),
            'sets_issued' => $setsIssued,
            'returns_historical' => $historical,
        ];
    }

    /**
     * Importe unitario que compromete cupo por participación (jugado × números).
     */
    public function commitmentUnitForSet(Reserve $reserve, Set $set): float
    {
        $numNumbers = is_array($reserve->reservation_numbers) ? count($reserve->reservation_numbers) : 0;
        $played = (float) ($set->getAttributes()['played_amount'] ?? 0);
        if ($played <= 0 && isset($set->attributes['played_amount'])) {
            $played = (float) $set->attributes['played_amount'];
        }
        if ($played <= 0) {
            $played = (float) ($set->played_amount ?? 0);
        }

        return $numNumbers <= 1 ? round($played, 2) : round($played * $numNumbers, 2);
    }

    /**
     * @return array{physical: float, digital: float}
     */
    private function soldAmountsByChannel(Reserve $reserve): array
    {
        $physical = 0.0;
        $digital = 0.0;

        $sets = Set::where('reserve_id', $reserve->id)->get(['id', 'physical_participations', 'digital_participations', 'played_amount']);
        if ($sets->isEmpty()) {
            return ['physical' => 0.0, 'digital' => 0.0];
        }

        $counts = Participation::query()
            ->whereIn('set_id', $sets->pluck('id'))
            ->sold()
            ->select('set_id', DB::raw('COUNT(*) as c'))
            ->groupBy('set_id')
            ->pluck('c', 'set_id');

        foreach ($sets as $set) {
            $count = (int) ($counts[$set->id] ?? 0);
            if ($count === 0) {
                continue;
            }
            $amount = round($count * $this->commitmentUnitForSet($reserve, $set), 2);
            $isDigitalOnly = (int) ($set->digital_participations ?? 0) > 0
                && (int) ($set->physical_participations ?? 0) === 0;
            if ($isDigitalOnly) {
                $digital += $amount;
            } else {
                $physical += $amount;
            }
        }

        return [
            'physical' => round($physical, 2),
            'digital' => round($digital, 2),
        ];
    }

    private function annulledAmount(Reserve $reserve): float
    {
        $sets = Set::where('reserve_id', $reserve->id)->get(['id', 'played_amount']);
        if ($sets->isEmpty()) {
            return 0.0;
        }

        $counts = Participation::query()
            ->whereIn('set_id', $sets->pluck('id'))
            ->where('status', 'anulada')
            ->select('set_id', DB::raw('COUNT(*) as c'))
            ->groupBy('set_id')
            ->pluck('c', 'set_id');

        $total = 0.0;
        foreach ($sets as $set) {
            $count = (int) ($counts[$set->id] ?? 0);
            if ($count === 0) {
                continue;
            }
            $total += $count * $this->commitmentUnitForSet($reserve, $set);
        }

        return round($total, 2);
    }
}
