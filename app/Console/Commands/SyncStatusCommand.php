<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SyncStatusManager;
use Illuminate\Console\Command;

class SyncStatusCommand extends Command
{
    protected $signature = 'sync:status';
    protected $description = 'Show current sync status';

    public function __construct(
        private SyncStatusManager $syncStatusManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $status = $this->syncStatusManager->getStatus();
        
        $this->info('Sync Status:');
        $this->line('Status: ' . ucfirst($status['status']));
        $this->line('Last Synced: ' . ($status['last_synced_at'] ?: 'Never'));
        $this->line('Pending Records: ' . $status['pending_records']);
        $this->line('Retry Count: ' . $status['retry_count']);
        
        if ($status['last_error']) {
            $this->error('Last Error: ' . $status['last_error']);
        }
        
        $this->line('Created: ' . $status['created_at']);
        $this->line('Updated: ' . $status['updated_at']);
        
        return self::SUCCESS;
    }
}
