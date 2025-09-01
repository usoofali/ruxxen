<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SyncService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class RecoveryServiceProvider extends ServiceProvider
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
        // Only run recovery check in slave mode
        if (config('app.mode') !== 'slave') {
            return;
        }

        try {
            $syncService = app(SyncService::class);
            
            if ($syncService->needsRecovery()) {
                Log::warning('Slave database needs recovery. Starting automatic recovery...');
                
                if ($syncService->performRecovery()) {
                    Log::info('Automatic recovery completed successfully');
                } else {
                    Log::error('Automatic recovery failed');
                }
            }
        } catch (\Exception $e) {
            Log::error('Error during recovery check', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
