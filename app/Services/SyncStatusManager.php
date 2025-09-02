<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SyncStatusManager
{
    private const SYNC_STATUS_FILE = 'sync_status.json';
    private const DEFAULT_SYNC_STATUS = [
        'last_synced_at' => null,
        'status' => 'pending',
        'pending_records' => 0,
        'last_error' => null,
        'retry_count' => 0,
        'created_at' => null,
        'updated_at' => null,
    ];

    public function __construct()
    {
        $this->initializeSyncStatus();
    }

    /**
     * Initialize sync status file if it doesn't exist
     */
    private function initializeSyncStatus(): void
    {
        if (!Storage::exists(self::SYNC_STATUS_FILE)) {
            $defaultStatus = self::DEFAULT_SYNC_STATUS;
            $defaultStatus['created_at'] = now()->toISOString();
            $defaultStatus['updated_at'] = now()->toISOString();
            
            Storage::put(self::SYNC_STATUS_FILE, json_encode($defaultStatus, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Get current sync status
     */
    public function getStatus(): array
    {
        try {
            $content = Storage::get(self::SYNC_STATUS_FILE);
            return json_decode($content, true) ?? self::DEFAULT_SYNC_STATUS;
        } catch (\Exception $e) {
            Log::error('Failed to read sync status file', ['error' => $e->getMessage()]);
            return self::DEFAULT_SYNC_STATUS;
        }
    }

    /**
     * Update sync status
     */
    public function updateStatus(array $data): bool
    {
        try {
            $currentStatus = $this->getStatus();
            $updatedStatus = array_merge($currentStatus, $data);
            $updatedStatus['updated_at'] = now()->toISOString();
            
            Storage::put(self::SYNC_STATUS_FILE, json_encode($updatedStatus, JSON_PRETTY_PRINT));
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update sync status', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Mark sync as successful
     */
    public function markSuccess(int $pendingRecords = 0): bool
    {
        return $this->updateStatus([
            'last_synced_at' => now()->toISOString(),
            'status' => 'success',
            'pending_records' => $pendingRecords,
            'last_error' => null,
            'retry_count' => 0,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markFailed(string $error, int $retryCount = null): bool
    {
        $currentStatus = $this->getStatus();
        $newRetryCount = $retryCount ?? ($currentStatus['retry_count'] + 1);
        
        return $this->updateStatus([
            'status' => 'failed',
            'last_error' => $error,
            'retry_count' => $newRetryCount,
        ]);
    }

    /**
     * Get last sync timestamp
     */
    public function getLastSyncedAt(): ?string
    {
        $status = $this->getStatus();
        return $status['last_synced_at'];
    }

    /**
     * Check if sync is needed
     */
    public function isSyncNeeded(): bool
    {
        $status = $this->getStatus();
        return $status['status'] === 'failed' || $status['status'] === 'pending';
    }

    /**
     * Reset sync status
     */
    public function reset(): bool
    {
        return $this->updateStatus(self::DEFAULT_SYNC_STATUS);
    }
}
