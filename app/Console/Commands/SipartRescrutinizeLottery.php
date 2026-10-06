<?php

namespace App\Console\Commands;

use App\Http\Controllers\LotteryScrutinyController;
use App\Models\AdministrationLotteryScrutiny;
use App\Models\Lottery;
use App\Models\Participation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SipartRescrutinizeLottery extends Command
{
    protected $signature = 'sipart:rescrutinize
                            {lottery_id* : IDs de los sorteos a re-escrutar}
                            {--apply : Guardar los nuevos importes (sin esta opción solo simula)}
                            {--user= : ID del superadmin con el que se ejecuta (por defecto, el primero)}';

    protected $description = 'Recalcula los escrutinios ya procesados de uno o varios sorteos con el motor de premios actual';

    public function handle(LotteryScrutinyController $scrutinyController): int
    {
        $user = $this->option('user')
            ? User::find((int) $this->option('user'))
            : User::where('role', User::ROLE_SUPER_ADMIN)->orderBy('id')->first();

        if (! $user || ! $user->isSuperAdmin()) {
            $this->error('No se encontró un superadmin válido (usa --user=ID).');

            return self::FAILURE;
        }
        Auth::setUser($user);

        $apply = (bool) $this->option('apply');
        if (! $apply) {
            $this->warn('Simulación: no se guardará nada. Usa --apply para aplicar los cambios.');
        }

        $failed = false;
        foreach ($this->argument('lottery_id') as $lotteryId) {
            $lottery = Lottery::with(['result', 'lotteryType'])->find((int) $lotteryId);
            if (! $lottery || ! $lottery->result) {
                $this->error("Sorteo {$lotteryId}: no existe o no tiene resultados.");
                $failed = true;

                continue;
            }

            $scrutinies = AdministrationLotteryScrutiny::where('lottery_id', $lottery->id)
                ->where('is_scrutinized', true)
                ->get();

            $this->newLine();
            $this->info("Sorteo {$lottery->id} — {$lottery->name}: {$scrutinies->count()} escrutinio(s) de administración");

            foreach ($scrutinies as $scrutiny) {
                try {
                    $this->rescrutinize($scrutinyController, $lottery, $scrutiny, $apply);
                } catch (\Throwable $e) {
                    $failed = true;
                    $this->error("  Administración {$scrutiny->administration_id}: {$e->getMessage()}");
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function rescrutinize(
        LotteryScrutinyController $scrutinyController,
        Lottery $lottery,
        AdministrationLotteryScrutiny $scrutiny,
        bool $apply
    ): void {
        $before = $this->detailedTotals($scrutiny->id);

        DB::beginTransaction();
        try {
            $scrutinyController->runScrutiny($lottery, (int) $scrutiny->administration_id);
            $after = $this->detailedTotals($scrutiny->id);
            $apply ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $rows = [];
        $changedSets = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? 0.0;
            $new = $after[$key] ?? 0.0;
            if (abs($new - $old) < 0.005) {
                continue;
            }
            [$entityId, $setId, $number] = explode('|', $key);
            $changedSets[$setId] = true;
            $rows[] = [$entityId, $setId, $number, number_format($old, 2, ',', '.'), number_format($new, 2, ',', '.'), number_format($new - $old, 2, ',', '.')];
        }

        $totalBefore = array_sum($before);
        $totalAfter = array_sum($after);
        $this->line(sprintf(
            '  Administración %d: %s € → %s € (%s €)%s',
            $scrutiny->administration_id,
            number_format($totalBefore, 2, ',', '.'),
            number_format($totalAfter, 2, ',', '.'),
            number_format($totalAfter - $totalBefore, 2, ',', '.'),
            $apply ? ' — aplicado' : ''
        ));

        if ($rows === []) {
            return;
        }
        $this->table(['Entidad', 'Set', 'Número', 'Antes (€)', 'Después (€)', 'Diferencia (€)'], $rows);

        $collected = Participation::whereIn('set_id', array_keys($changedSets))
            ->whereNotNull('collected_at')
            ->count();
        if ($collected > 0) {
            $this->warn("  {$collected} participación(es) de esos sets ya cobraron el premio con el importe anterior: hay que regularizarlas a mano.");
        }
    }

    /** @return array<string, float> premio_total por "entidad|set|número" */
    private function detailedTotals(int $scrutinyId): array
    {
        $totals = [];
        DB::table('scrutiny_detailed_results')
            ->where('scrutiny_id', $scrutinyId)
            ->get(['entity_id', 'set_id', 'winning_number', 'premio_total'])
            ->each(function ($row) use (&$totals) {
                $key = $row->entity_id . '|' . ($row->set_id ?? '-') . '|' . str_pad((string) $row->winning_number, 5, '0', STR_PAD_LEFT);
                $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row->premio_total;
            });

        return $totals;
    }
}
