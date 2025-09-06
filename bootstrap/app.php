<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Sync every 5 minutes only if this is a slave instance
        $schedule->call(function () {
            // Check the mode at runtime
            if (config('app.mode') !== 'slave') {
                \Log::info('Scheduled sync skipped - not a slave instance');
                return;
            }
            
            $syncService = app(\App\Services\SyncService::class);
            $result = $syncService->sync();
            
            // Log results
            \Log::info('Scheduled sync completed', [
                'pull_success' => $result['pull']['success'],
                'push_success' => $result['push']['success']
            ]);
        })->everyMinute();

        // Clean up old log files daily at 2:00 AM
        $schedule->call(function () {
            $logPath = storage_path('logs');
            $files = glob($logPath . '/*.log');
            $cutoff = now()->subDays(7); // Keep logs for 7 days
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff->timestamp) {
                    unlink($file);
                    \Log::info('Cleaned up old log file: ' . basename($file));
             
                }
            }
            
            \Log::info('Log cleanup completed');
        })->dailyAt('02:00');

        // Clean up temporary files weekly
        $schedule->call(function () {
            $tempPaths = [
                storage_path('framework/cache'),
                storage_path('framework/sessions'),
                storage_path('framework/views')
            ];
            
            foreach ($tempPaths as $path) {
                if (is_dir($path)) {
                    $files = glob($path . '/*');
                    foreach ($files as $file) {
                        if (is_file($file) && filemtime($file) < now()->subDays(3)->timestamp) {
                            unlink($file);
                        }
                    }
                }
            }
            
            \Log::info('Temporary files cleanup completed');
        })->weekly();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
