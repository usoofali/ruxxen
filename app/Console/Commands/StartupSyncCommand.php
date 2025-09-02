<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SyncStatusManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class StartupSyncCommand extends Command
{
    protected $signature = 'sync:startup {--force : Force sync even if not needed}';
    protected $description = 'Perform startup sync to ensure slave is synchronized with master';

    public function __construct(
        private SyncStatusManager $syncStatusManager
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting startup synchronization...');

        try {
            // Check if sync is enabled
            if (!config('sync.enabled', true)) {
                $this->info('Sync is disabled in configuration.');
                return self::SUCCESS;
            }

            $masterUrl = config('sync.master_url');
            if (!$masterUrl) {
                $this->error('Master URL not configured.');
                return self::FAILURE;
            }

            $this->info("Connecting to Master: {$masterUrl}");

            // Step 1: Check Master connectivity
            if (!$this->checkMasterConnectivity($masterUrl)) {
                $this->error('Cannot connect to Master server.');
                return self::FAILURE;
            }

            // Step 2: Get Master's last sync timestamp
            $masterStatus = $this->getMasterStatus($masterUrl);
            if (!$masterStatus) {
                $this->error('Cannot get Master status.');
                return self::FAILURE;
            }

            $this->info("Master last synced: " . ($masterStatus['last_synced_at'] ?: 'Never'));

            // Step 3: Check if local sync is needed
            $localStatus = $this->syncStatusManager->getStatus();
            $localLastSync = $localStatus['last_synced_at'];
            
            if (!$this->option('force') && $localLastSync && $masterStatus['last_synced_at']) {
                $localTime = strtotime($localLastSync);
                $masterTime = strtotime($masterStatus['last_synced_at']);
                
                if ($localTime >= $masterTime) {
                    $this->info('Local system is up to date with Master.');
                    return self::SUCCESS;
                }
            }

            // Step 4: Perform full sync from Master
            $this->info('Local system needs synchronization. Starting full sync...');
            
            // Download all changes from Master
            $downloadResult = $this->downloadFromMaster($masterUrl);
            if (!$downloadResult['success']) {
                $this->error('Failed to download from Master: ' . $downloadResult['message']);
                return self::FAILURE;
            }

            $this->info("Downloaded {$downloadResult['count']} records from Master");

            // Apply changes locally
            $applyResult = $this->applyChanges($downloadResult['records']);
            if (!$applyResult['success']) {
                $this->error('Failed to apply changes: ' . $applyResult['message']);
                return self::FAILURE;
            }

            $this->info("Applied {$applyResult['count']} changes locally");

            // Update local sync status
            $this->syncStatusManager->markSuccess($downloadResult['count']);

            $this->info('Startup synchronization completed successfully!');
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Startup sync failed: ' . $e->getMessage());
            Log::error('Startup sync failed', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }

    private function checkMasterConnectivity(string $masterUrl): bool
    {
        try {
            $response = Http::timeout(config('sync.timeout', 30))
                ->get($masterUrl . '/api/sync/status');
            
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getMasterStatus(string $masterUrl): ?array
    {
        try {
            $response = Http::timeout(config('sync.timeout', 30))
                ->get($masterUrl . '/api/sync/status');
            
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error('Failed to get Master status', ['error' => $e->getMessage()]);
        }
        
        return null;
    }

    private function downloadFromMaster(string $masterUrl): array
    {
        try {
            $response = Http::timeout(config('sync.timeout', 30))
                ->get($masterUrl . '/api/sync/download', [
                    'tables' => config('sync.tables'),
                    'batch_size' => config('sync.batch_size', 100)
                ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'count' => $data['count'] ?? 0,
                    'records' => $data['records'] ?? []
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

    private function applyChanges(array $records): array
    {
        $appliedCount = 0;
        $errors = [];

        foreach ($records as $record) {
            try {
                $result = $this->processRecord($record);
                if ($result['success']) {
                    $appliedCount++;
                } else {
                    $errors[] = $result['error'];
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to process record: " . $e->getMessage();
            }
        }

        return [
            'success' => empty($errors),
            'count' => $appliedCount,
            'errors' => $errors
        ];
    }

    private function processRecord(array $record): array
    {
        $table = $record['table'];
        $action = $record['action'];
        $data = $record['data'];

        try {
            switch ($table) {
                case 'inventories':
                    return $this->processInventoryRecord($action, $data);
                case 'transactions':
                    return $this->processTransactionRecord($action, $data);
                case 'inventory_adjustments':
                    return $this->processInventoryAdjustmentRecord($action, $data);
                default:
                    return ['success' => false, 'error' => "Unknown table: {$table}"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function processInventoryRecord(string $action, array $data): array
    {
        $model = \App\Models\Inventory::class;
        
        switch ($action) {
            case 'create':
            case 'update':
                $model::updateOrCreate(['id' => $data['id']], $data);
                break;
            case 'delete':
                $model::where('id', $data['id'])->delete();
                break;
        }
        
        return ['success' => true];
    }

    private function processTransactionRecord(string $action, array $data): array
    {
        $model = \App\Models\Transaction::class;
        
        switch ($action) {
            case 'create':
            case 'update':
                $model::updateOrCreate(['id' => $data['id']], $data);
                break;
            case 'delete':
                $model::where('id', $data['id'])->delete();
                break;
        }
        
        return ['success' => true];
    }

    private function processInventoryAdjustmentRecord(string $action, array $data): array
    {
        $model = \App\Models\InventoryAdjustment::class;
        
        switch ($action) {
            case 'create':
            case 'update':
                $model::updateOrCreate(['id' => $data['id']], $data);
                break;
            case 'delete':
                $model::where('id', $data['id'])->delete();
                break;
        }
        
        return ['success' => true];
    }
}
