<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncRun extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sync:run {--force : Force sync even if not in slave mode}';

    /**
     * The console command description.
     */
    protected $description = 'Run synchronization between slave and master';

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

        $this->info('Starting synchronization...');

        // Check if recovery is needed
        if ($syncService->needsRecovery()) {
            $this->warn('Recovery needed. Performing full recovery from master...');
            
            if (!$syncService->performRecovery()) {
                $this->error('Recovery failed!');
                return self::FAILURE;
            }
            
            $this->info('Recovery completed successfully.');
            return self::SUCCESS;
        }

        $tables = ['inventory', 'transactions', 'users', 'inventory_adjustments'];
        $success = true;

        foreach ($tables as $table) {
            $this->info("Syncing {$table}...");

            // Push local changes to master
            if (!$syncService->pushChanges($table)) {
                $this->error("Failed to push {$table} changes to master");
                $success = false;
                continue;
            }

            // Pull changes from master
            if (!$syncService->pullChanges($table)) {
                $this->error("Failed to pull {$table} changes from master");
                $success = false;
                continue;
            }

            // Resolve conflicts for inventory
            if ($table === 'inventory' && !$syncService->resolveConflicts($table)) {
                $this->error("Failed to resolve conflicts for {$table}");
                $success = false;
                continue;
            }

            $this->info("Successfully synced {$table}");
        }

        if ($success) {
            $this->info('Synchronization completed successfully!');
            Log::info('Synchronization completed successfully');
            return self::SUCCESS;
        } else {
            $this->error('Synchronization completed with errors!');
            Log::error('Synchronization completed with errors');
            return self::FAILURE;
        }
    }
}
