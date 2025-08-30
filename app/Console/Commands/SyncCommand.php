<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\InventoryAdjustment;
use App\Services\SyncStatusManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncCommand extends Command
{
    protected $signature = 'sync:run 
                            {--force : Force sync even if not needed}
                            {--master-url= : Master server URL}
                            {--dry-run : Show what would be synced without actually syncing}';

    protected $description = 'Synchronize data with Master server';

    public function __construct(
        private SyncStatusManager $syncStatusManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting synchronization process...');

        try {
            // Check if sync is needed
            if (!$this->option('force') && !$this->syncStatusManager->isSyncNeeded()) {
                $this->info('No sync needed at this time.');
                return self::SUCCESS;
            }

            $masterUrl = $this->option('master-url') ?? config('app.master_url', 'http://localhost:8000');
            
            if ($this->option('dry-run')) {
                return $this->performDryRun($masterUrl);
            }

            return $this->performSync($masterUrl);

        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            $this->syncStatusManager->markFailed($e->getMessage());
            Log::error('Sync command failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    /**
     * Perform actual sync operation
     */
    private function performSync(string $masterUrl): int
    {
        $this->info('Performing sync with Master server: ' . $masterUrl);

        // Step 1: Upload local changes to Master
        $uploadResult = $this->uploadChanges($masterUrl);
        if (!$uploadResult['success']) {
            $this->error('Upload failed: ' . $uploadResult['message']);
            return self::FAILURE;
        }

        $this->info("Uploaded {$uploadResult['count']} records to Master");

        // Step 2: Download changes from Master
        $downloadResult = $this->downloadChanges($masterUrl);
        if (!$downloadResult['success']) {
            $this->error('Download failed: ' . $downloadResult['message']);
            return self::FAILURE;
        }

        $this->info("Downloaded {$downloadResult['count']} records from Master");

        // Step 3: Apply downloaded changes locally
        $applyResult = $this->applyChanges($downloadResult['records']);
        if (!$applyResult['success']) {
            $this->error('Apply changes failed: ' . $applyResult['message']);
            return self::FAILURE;
        }

        $this->info("Applied {$applyResult['count']} changes locally");

        // Step 4: Acknowledge sync completion
        $ackResult = $this->acknowledgeSync($masterUrl, 'success');
        if (!$ackResult['success']) {
            $this->warn('Acknowledgment failed: ' . $ackResult['message']);
        }

        // Step 5: Update local sync status
        $this->syncStatusManager->markSuccess($uploadResult['count'] + $downloadResult['count']);

        $this->info('Synchronization completed successfully!');
        return self::SUCCESS;
    }

    /**
     * Perform dry run to show what would be synced
     */
    private function performDryRun(string $masterUrl): int
    {
        $this->info('Performing dry run...');

        $lastSyncedAt = $this->syncStatusManager->getLastSyncedAt();
        
        // Show local changes
        $localChanges = $this->getLocalChanges($lastSyncedAt);
        $this->info("Local changes to upload: " . array_sum(array_map('count', $localChanges)));

        foreach ($localChanges as $table => $records) {
            if (count($records) > 0) {
                $this->line("  - {$table}: " . count($records) . " records");
            }
        }

        // Show remote changes
        $remoteChanges = $this->getRemoteChanges($masterUrl, $lastSyncedAt);
        if ($remoteChanges['success']) {
            $this->info("Remote changes to download: " . $remoteChanges['count']);
            foreach ($remoteChanges['records'] as $table => $records) {
                if (count($records) > 0) {
                    $this->line("  - {$table}: " . count($records) . " records");
                }
            }
        } else {
            $this->warn("Could not fetch remote changes: " . $remoteChanges['message']);
        }

        return self::SUCCESS;
    }

    /**
     * Upload local changes to Master
     */
    private function uploadChanges(string $masterUrl): array
    {
        $lastSyncedAt = $this->syncStatusManager->getLastSyncedAt();
        $localChanges = $this->getLocalChanges($lastSyncedAt);

        $records = [];
        foreach ($localChanges as $table => $tableRecords) {
            foreach ($tableRecords as $record) {
                $records[] = [
                    'table' => $table,
                    'data' => (array) $record,
                    'action' => 'create', // You might want to detect create/update/delete
                    'id' => $record->id ?? $record->uuid ?? uniqid(),
                ];
            }
        }

        if (empty($records)) {
            return ['success' => true, 'count' => 0, 'message' => 'No changes to upload'];
        }

        try {
            $response = Http::timeout(30)->post($masterUrl . '/api/sync/upload', [
                'records' => $records
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['success'] ?? false,
                    'count' => $data['uploaded_count'] ?? 0,
                    'message' => $data['message'] ?? 'Upload completed'
                ];
            }

            return [
                'success' => false,
                'count' => 0,
                'message' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'count' => 0,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Download changes from Master
     */
    private function downloadChanges(string $masterUrl): array
    {
        $lastSyncedAt = $this->syncStatusManager->getLastSyncedAt();

        try {
            $response = Http::timeout(30)->get($masterUrl . '/api/sync/download', [
                'last_synced_at' => $lastSyncedAt,
                'tables' => ['inventories', 'transactions', 'inventory_adjustments', 'company_settings', 'users']
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['success'] ?? false,
                    'count' => $data['total_count'] ?? 0,
                    'records' => $data['records'] ?? [],
                    'message' => $data['message'] ?? 'Download completed'
                ];
            }

            return [
                'success' => false,
                'count' => 0,
                'records' => [],
                'message' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'count' => 0,
                'records' => [],
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Apply downloaded changes locally
     */
    private function applyChanges(array $records): array
    {
        $appliedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($records as $table => $tableRecords) {
                foreach ($tableRecords as $record) {
                    $result = $this->applyRecord($table, (array) $record);
                    
                    if ($result['success']) {
                        $appliedCount++;
                    } else {
                        $errors[] = $result['error'];
                    }
                }
            }

            DB::commit();

            return [
                'success' => true,
                'count' => $appliedCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'count' => 0,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Apply individual record
     */
    private function applyRecord(string $table, array $data): array
    {
        try {
            switch ($table) {
                case 'inventories':
                    Inventory::updateOrCreate(['id' => $data['id']], $data);
                    break;
                case 'transactions':
                    Transaction::updateOrCreate(['id' => $data['id']], $data);
                    break;
                case 'inventory_adjustments':
                    InventoryAdjustment::updateOrCreate(['id' => $data['id']], $data);
                    break;
                default:
                    return ['success' => false, 'error' => "Unknown table: {$table}"];
            }
            
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Acknowledge sync completion
     */
    private function acknowledgeSync(string $masterUrl, string $status, string $message = ''): array
    {
        try {
            $response = Http::timeout(10)->post($masterUrl . '/api/sync/acknowledge', [
                'timestamp' => now()->toISOString(),
                'status' => $status,
                'message' => $message
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['success'] ?? false,
                    'message' => $data['message'] ?? 'Acknowledgment sent'
                ];
            }

            return [
                'success' => false,
                'message' => 'HTTP ' . $response->status() . ': ' . $response->body()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get local changes since last sync
     */
    private function getLocalChanges(?string $lastSyncedAt): array
    {
        $changes = [];

        // Get inventory changes
        $inventoryQuery = Inventory::query();
        if ($lastSyncedAt) {
            $inventoryQuery->where('updated_at', '>', $lastSyncedAt);
        }
        $changes['inventories'] = $inventoryQuery->get();

        // Get transaction changes
        $transactionQuery = Transaction::query();
        if ($lastSyncedAt) {
            $transactionQuery->where('updated_at', '>', $lastSyncedAt);
        }
        $changes['transactions'] = $transactionQuery->get();

        // Get inventory adjustment changes
        $adjustmentQuery = InventoryAdjustment::query();
        if ($lastSyncedAt) {
            $adjustmentQuery->where('updated_at', '>', $lastSyncedAt);
        }
        $changes['inventory_adjustments'] = $adjustmentQuery->get();

        return $changes;
    }

    /**
     * Get remote changes (for dry run)
     */
    private function getRemoteChanges(string $masterUrl, ?string $lastSyncedAt): array
    {
        try {
            $response = Http::timeout(10)->get($masterUrl . '/api/sync/download', [
                'last_synced_at' => $lastSyncedAt,
                'tables' => ['inventories', 'transactions', 'inventory_adjustments']
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => $data['success'] ?? false,
                    'count' => $data['total_count'] ?? 0,
                    'records' => $data['records'] ?? []
                ];
            }

            return [
                'success' => false,
                'count' => 0,
                'records' => [],
                'message' => 'HTTP ' . $response->status()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'count' => 0,
                'records' => [],
                'message' => $e->getMessage()
            ];
        }
    }
}
