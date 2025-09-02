<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\SyncService;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Sync every 15 minutes (adjust as needed)
        $schedule->call(function () {
            $syncService = app(SyncService::class);
            $result = $syncService->sync();
            
            // Log results
            \Log::info('Scheduled sync completed', [
                'pull_success' => $result['pull']['success'],
                'push_success' => $result['push']['success']
            ]);
        })->everyFifteenMinutes();

        // Clean up old logs and temporary files
        $schedule->command('log:clear')->daily();
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
