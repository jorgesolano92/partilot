<?php

use App\Models\Lottery;
use App\Models\ScrutinyDetailedResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scrutiny_detailed_results', function (Blueprint $table) {
            $table->decimal('total_decimos', 10, 2)->default(0)->change();
        });

        ScrutinyDetailedResult::query()
            ->with(['set', 'scrutiny'])
            ->chunkById(100, function ($results) {
                foreach ($results as $result) {
                    if (! $result->set || ! $result->scrutiny) {
                        continue;
                    }

                    $lottery = Lottery::query()->find($result->scrutiny->lottery_id);
                    $ticketPrice = (float) ($lottery->ticket_price ?? 0);
                    $importeJugado = (float) ($result->set->played_amount ?? 0);
                    $participations = (int) $result->total_participations;

                    if ($ticketPrice <= 0 || $importeJugado <= 0 || $participations <= 0) {
                        continue;
                    }

                    $participacionesPorDecimo = $ticketPrice / $importeJugado;
                    $decimos = round($participations / $participacionesPorDecimo, 2);
                    $premioPorParticipacion = (float) $result->premio_por_participacion;
                    if ($premioPorParticipacion <= 0 && (float) $result->premio_por_decimo > 0) {
                        $premioPorParticipacion = (float) $result->premio_por_decimo * ($importeJugado / $ticketPrice);
                    }

                    $result->update([
                        'total_decimos' => $decimos,
                        'premio_total' => round($premioPorParticipacion * $participations, 2),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('scrutiny_detailed_results', function (Blueprint $table) {
            $table->integer('total_decimos')->default(0)->change();
        });
    }
};
