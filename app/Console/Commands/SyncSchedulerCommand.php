<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSchedulerCommand extends Command
{
    protected $signature = 'sync:scheduler';
    protected $description = 'Run the sync scheduler manually';

    public function handle(): int
    {
        $this->info('Running sync scheduler...');
        
        try {
            // Check if sync is enabled
            if (!config('app.sync_enabled', true)) {
                $this->info('Sync is disabled in configuration.');
                return self::SUCCESS;
            }

            // Run the sync command
            $this->call('sync:run');
            
            $this->info('Sync scheduler completed successfully.');
            return self::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Sync scheduler failed: ' . $e->getMessage());
            Log::error('Sync scheduler failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
