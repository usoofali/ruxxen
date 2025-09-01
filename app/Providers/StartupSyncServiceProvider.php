<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class StartupSyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only run startup sync in console or when explicitly requested
        if ($this->app->runningInConsole()) {
            return;
        }

        // Check if this is a web request and sync is enabled
        if (!config('sync.enabled', true)) {
            return;
        }

        // Run startup sync in background to avoid blocking the application
        $this->runStartupSyncInBackground();
    }

    /**
     * Run startup sync in background
     */
    private function runStartupSyncInBackground(): void
    {
        try {
            // Use dispatch to run the command in background
            dispatch(function () {
                try {
                    Artisan::call('sync:startup');
                    Log::info('Startup sync completed successfully');
                } catch (\Exception $e) {
                    Log::error('Startup sync failed', ['error' => $e->getMessage()]);
                }
            })->afterResponse();
        } catch (\Exception $e) {
            Log::error('Failed to dispatch startup sync', ['error' => $e->getMessage()]);
        }
    }
}
