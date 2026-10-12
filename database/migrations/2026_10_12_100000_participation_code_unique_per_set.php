<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(DB::select("SHOW INDEX FROM participations WHERE Non_unique = 0 AND Key_name <> 'PRIMARY'"))
            ->groupBy('Key_name')
            ->map(fn ($cols) => $cols->sortBy('Seq_in_index')->pluck('Column_name')->all());

        foreach ($indexes as $name => $columns) {
            if ($columns === ['participation_code']) {
                DB::statement("ALTER TABLE participations DROP INDEX `{$name}`");
            }
        }

        if (! $indexes->contains(fn ($columns) => $columns === ['set_id', 'participation_code'])) {
            DB::statement('ALTER TABLE participations ADD UNIQUE INDEX participations_set_code_unique (set_id, participation_code)');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE participations DROP INDEX participations_set_code_unique');
    }
};
