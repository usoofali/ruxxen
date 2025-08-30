<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SyncStatusManager;
use Livewire\Component;
use Livewire\Attributes\On;

class SyncMonitor extends Component
{
    public array $syncStatus = [];
    public bool $isRefreshing = false;

    public function mount()
    {
        $this->refreshStatus();
    }

    #[On('sync-status-updated')]
    public function refreshStatus()
    {
        $this->isRefreshing = true;
        
        $syncManager = app(SyncStatusManager::class);
        $this->syncStatus = $syncManager->getStatus();
        
        $this->isRefreshing = false;
    }

    public function forceSync()
    {
        $this->dispatch('show-loading', 'Initiating manual sync...');
        
        // Run sync command
        $output = shell_exec('php artisan sync:run --force 2>&1');
        
        $this->refreshStatus();
        $this->dispatch('hide-loading');
        
        if (str_contains($output, 'Synchronization completed successfully')) {
            $this->dispatch('show-success', 'Manual sync completed successfully!');
        } else {
            $this->dispatch('show-error', 'Manual sync failed. Check logs for details.');
        }
    }

    public function resetSync()
    {
        $syncManager = app(SyncStatusManager::class);
        $syncManager->reset();
        $this->refreshStatus();
        $this->dispatch('show-success', 'Sync status has been reset.');
    }

    public function render()
    {
        return view('livewire.sync-monitor');
    }
}
