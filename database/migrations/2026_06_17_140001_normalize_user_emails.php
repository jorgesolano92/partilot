<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];

        DB::table('users')
            ->select('id', 'email')
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$seen) {
                foreach ($users as $user) {
                    $normalized = strtolower(trim((string) $user->email));
                    if ($normalized === '' || isset($seen[$normalized])) {
                        continue;
                    }

                    $seen[$normalized] = true;

                    if ($normalized !== $user->email) {
                        DB::table('users')->where('id', $user->id)->update(['email' => $normalized]);
                    }
                }
            });
    }

    public function down(): void
    {
        // No reversible de forma segura.
    }
};
