<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repara managers.user_id huérfanos que impiden recrear la FK (p. ej. usuario eliminado).
     */
    public function up(): void
    {
        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'managers'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME IS NOT NULL"
        );

        foreach ($foreignKeys as $foreignKey) {
            Schema::table('managers', function (Blueprint $table) use ($foreignKey) {
                $table->dropForeign($foreignKey->CONSTRAINT_NAME);
            });
        }

        DB::statement(
            'UPDATE managers m
             LEFT JOIN users u ON u.id = m.user_id
             SET m.user_id = NULL
             WHERE m.user_id IS NOT NULL AND u.id IS NULL'
        );

        if (Schema::hasColumn('managers', 'user_id')) {
            Schema::table('managers', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }

        $hasUserForeign = DB::selectOne(
            "SELECT CONSTRAINT_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'managers'
              AND COLUMN_NAME = 'user_id'
              AND REFERENCED_TABLE_NAME = 'users'
            LIMIT 1"
        );

        if (! $hasUserForeign && Schema::hasColumn('managers', 'user_id')) {
            Schema::table('managers', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // No revertir: la limpieza de huérfanos es segura y deseable.
    }
};
