<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pendiente = null; Activo = 1; Inactivo = 0; Bloqueado = 3 (INC-006).
     * El boolean NOT NULL convertía null a 0 (Inactivo) al crear.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE administrations MODIFY status TINYINT NULL DEFAULT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE administrations ALTER COLUMN status DROP NOT NULL');
            DB::statement('ALTER TABLE administrations ALTER COLUMN status DROP DEFAULT');
        } elseif ($driver === 'sqlite') {
            // SQLite no aplica NOT NULL de forma estricta en ALTER; se deja como está.
        }

        DB::table('administrations')
            ->whereNotNull('status')
            ->whereNotIn('status', [0, 1, 3])
            ->update(['status' => null]);
    }

    public function down(): void
    {
        DB::table('administrations')->whereNull('status')->update(['status' => 1]);
        DB::table('administrations')->where('status', 3)->update(['status' => 0]);

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE administrations MODIFY status TINYINT(1) NOT NULL DEFAULT 1');
        }
    }
};
