<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessAccountDeletionsCommand extends Command
{
    protected $signature = 'sipart:process-account-deletions';

    protected $description = 'Ejecuta el borrado real de cuentas tras el periodo de gracia (L9 fase 2)';

    public function handle(): int
    {
        $due = User::query()
            ->where('deletion_status', 'scheduled')
            ->whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', now())
            ->get();

        $count = 0;

        foreach ($due as $user) {
            DB::transaction(function () use ($user) {
                $anonymizedEmail = 'deleted_'.$user->id.'@partilot.invalid';

                $user->update([
                    'name' => 'Usuario eliminado',
                    'last_name' => null,
                    'last_name2' => null,
                    'email' => $anonymizedEmail,
                    'phone' => null,
                    'nif_cif' => null,
                    'birthday' => null,
                    'comment' => null,
                    'image' => null,
                    'password' => bcrypt(Str::random(64)),
                    'deletion_status' => 'executed',
                    'status' => false,
                ]);
            });
            $count++;
        }

        $this->info("Cuentas anonimizadas: {$count}");

        return self::SUCCESS;
    }
}
