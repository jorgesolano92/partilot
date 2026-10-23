<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devolutions', function (Blueprint $table) {
            $table->foreignId('reserve_id')
                ->nullable()
                ->after('lottery_id')
                ->constrained('reserves')
                ->nullOnDelete();
            $table->index('reserve_id');
        });

        // Backfill: reserve más frecuente entre los sets de los detalles
        $rows = DB::table('devolution_details as dd')
            ->join('participations as p', 'p.id', '=', 'dd.participation_id')
            ->join('sets as s', 's.id', '=', 'p.set_id')
            ->whereNotNull('s.reserve_id')
            ->select('dd.devolution_id', 's.reserve_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('dd.devolution_id', 's.reserve_id')
            ->orderBy('dd.devolution_id')
            ->orderByDesc('cnt')
            ->get();

        $chosen = [];
        foreach ($rows as $row) {
            $devId = (int) $row->devolution_id;
            if (isset($chosen[$devId])) {
                continue;
            }
            $chosen[$devId] = (int) $row->reserve_id;
        }

        foreach ($chosen as $devolutionId => $reserveId) {
            DB::table('devolutions')
                ->where('id', $devolutionId)
                ->whereNull('reserve_id')
                ->update(['reserve_id' => $reserveId]);
        }
    }

    public function down(): void
    {
        Schema::table('devolutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reserve_id');
        });
    }
};
