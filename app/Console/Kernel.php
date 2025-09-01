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
        // Clean up old logs and temporary files
        $schedule->command('log:clear')->daily();

        // Sync jobs (only run in slave mode)
        if (config('app.mode') === 'slave') {
            // Run sync every 15 minutes
            $schedule->command('sync:run')
                ->everyFifteenMinutes()
                ->withoutOverlapping()
                ->runInBackground();

            // Run company settings sync once daily (will skip if already synced)
            $schedule->command('sync:company-settings')
                ->daily()
                ->withoutOverlapping()
                ->runInBackground();
        }
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
