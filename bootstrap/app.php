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
        // Sync every 15 minutes only if this is a slave instance
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
        })->everyFifteenMinutes();

        // Clean up old logs and temporary files
        $schedule->command('log:clear')->daily();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
