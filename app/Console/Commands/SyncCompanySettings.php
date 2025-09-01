<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SyncLog;
use App\Services\SyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCompanySettings extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:company-settings {--force : Force sync even if already synced}';

    /**
     * The console command description.
     */
    protected $description = 'Sync company settings once (bootstrapping step)';

    /**
     * Execute the console command.
     */
    public function handle(SyncService $syncService): int
    {
        // Check if we're in slave mode
        if (config('app.mode') !== 'slave' && !$this->option('force')) {
            $this->error('This command should only be run in slave mode.');
            return self::FAILURE;
        }

        $this->info('Starting company settings synchronization...');

        // Check if company settings have already been synced
        $lastSyncTime = SyncLog::getLastSyncTime('company_settings');
        if ($lastSyncTime && !$this->option('force')) {
            $this->warn('Company settings have already been synced. Use --force to sync again.');
            return self::SUCCESS;
        }

        // Push local company settings to master
        $this->info('Pushing company settings to master...');
        if (!$syncService->pushChanges('company_settings')) {
            $this->error('Failed to push company settings to master');
            return self::FAILURE;
        }

        // Pull company settings from master
        $this->info('Pulling company settings from master...');
        if (!$syncService->pullChanges('company_settings')) {
            $this->error('Failed to pull company settings from master');
            return self::FAILURE;
        }

        $this->info('Company settings synchronization completed successfully!');
        Log::info('Company settings synchronization completed successfully');
        
        return self::SUCCESS;
    }
}
