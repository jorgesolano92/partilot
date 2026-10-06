<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Requiere cron: * * * * * php artisan schedule:run
        // Guía y tabla de `kind`: docs/SCHEDULER_AND_KIND_LABELS.md
        //
        $schedule->command('sipart:expire-pending-gifts')->dailyAt('02:00');
        $schedule->command('sipart:expire-unverified-collections')->hourly();
        $schedule->command('pdf:cleanup')->dailyAt('03:00');
        // $schedule->command('sipart:pending-payments-check')->dailyAt('08:00');
        $schedule->command('sipart:lottery-deadline-reminder')->dailyAt('09:00');
        $schedule->command('sipart:send-role-invitation-reminders')->hourly();
        $schedule->command('sipart:process-account-deletions')->dailyAt('03:30');
        $schedule->command('sipart:lottery-deadline-closure')
            ->dailyAt('00:30')
            ->when(fn () => (bool) config('lottery.auto_deadline_closure.enabled', false));
        // $schedule->command('sipart:new-lotteries-announce')->weeklyOn(1, '10:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
