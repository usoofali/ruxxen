<?php

use App\Services\SyncStatusManager;
use Livewire\Volt\Component;

new class extends Component {
    public array $syncStatus = [];
    public bool $isRefreshing = false;

    public function mount()
    {
        $this->refreshStatus();
    }

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
}; ?>

<div class="p-6 bg-white rounded-lg shadow-md">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Synchronization Monitor</h2>
        <div class="flex space-x-2">
            <button 
                wire:click="refreshStatus" 
                wire:loading.attr="disabled"
                class="btn btn-sm btn-outline"
                :disabled="$isRefreshing"
            >
                <span wire:loading.remove wire:target="refreshStatus">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                </span>
                <span wire:loading wire:target="refreshStatus">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
                Refresh
            </button>
            <button 
                wire:click="forceSync" 
                wire:loading.attr="disabled"
                class="btn btn-sm btn-primary"
            >
                <span wire:loading.remove wire:target="forceSync">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                </span>
                <span wire:loading wire:target="forceSync">
                    <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </span>
                Force Sync
            </button>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="stat bg-base-100 shadow">
            <div class="stat-figure text-primary">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-title">Status</div>
            <div class="stat-value text-lg">
                @if($syncStatus['status'] === 'success')
                    <span class="text-success">Success</span>
                @elseif($syncStatus['status'] === 'failed')
                    <span class="text-error">Failed</span>
                @else
                    <span class="text-warning">Pending</span>
                @endif
            </div>
        </div>

        <div class="stat bg-base-100 shadow">
            <div class="stat-figure text-secondary">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
            <div class="stat-title">Last Sync</div>
            <div class="stat-value text-lg">
                @if($syncStatus['last_synced_at'])
                    {{ \Carbon\Carbon::parse($syncStatus['last_synced_at'])->diffForHumans() }}
                @else
                    <span class="text-gray-500">Never</span>
                @endif
            </div>
        </div>

        <div class="stat bg-base-100 shadow">
            <div class="stat-figure text-accent">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
            <div class="stat-title">Pending Records</div>
            <div class="stat-value text-lg">{{ $syncStatus['pending_records'] ?? 0 }}</div>
        </div>
    </div>

    <!-- Detailed Status -->
    <div class="bg-base-100 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-4">Sync Details</h3>
        
        <div class="overflow-x-auto">
            <table class="table table-zebra w-full">
                <tbody>
                    <tr>
                        <td class="font-medium">Status</td>
                        <td>
                            @if($syncStatus['status'] === 'success')
                                <span class="badge badge-success">Success</span>
                            @elseif($syncStatus['status'] === 'failed')
                                <span class="badge badge-error">Failed</span>
                            @else
                                <span class="badge badge-warning">Pending</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="font-medium">Last Synced At</td>
                        <td>
                            @if($syncStatus['last_synced_at'])
                                {{ \Carbon\Carbon::parse($syncStatus['last_synced_at'])->format('Y-m-d H:i:s') }}
                            @else
                                <span class="text-gray-500">Never</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="font-medium">Retry Count</td>
                        <td>{{ $syncStatus['retry_count'] ?? 0 }}</td>
                    </tr>
                    <tr>
                        <td class="font-medium">Created At</td>
                        <td>
                            @if($syncStatus['created_at'])
                                {{ \Carbon\Carbon::parse($syncStatus['created_at'])->format('Y-m-d H:i:s') }}
                            @else
                                <span class="text-gray-500">-</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="font-medium">Updated At</td>
                        <td>
                            @if($syncStatus['updated_at'])
                                {{ \Carbon\Carbon::parse($syncStatus['updated_at'])->format('Y-m-d H:i:s') }}
                            @else
                                <span class="text-gray-500">-</span>
                            @endif
                        </td>
                    </tr>
                    @if($syncStatus['last_error'])
                    <tr>
                        <td class="font-medium">Last Error</td>
                        <td class="text-error">{{ $syncStatus['last_error'] }}</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Actions -->
    <div class="mt-6 flex justify-end space-x-2">
        <button 
            wire:click="resetSync" 
            wire:loading.attr="disabled"
            class="btn btn-outline btn-error"
            onclick="return confirm('Are you sure you want to reset the sync status?')"
        >
            Reset Sync Status
        </button>
    </div>

    <!-- Auto-refresh script -->
    <script>
        // Auto-refresh every 30 seconds
        setInterval(() => {
            @this.refreshStatus();
        }, 30000);
    </script>
</div>
