<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\CompanySetting;
use App\Models\SyncLog;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncService
{
    private PendingRequest $httpClient;
    private string $masterUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->masterUrl = config('app.master_url', 'https://app.ruxxengas.com');
        $this->apiKey = config('app.sync_api_key');
        
        $this->httpClient = Http::withHeaders([
            'X-Sync-API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }

    /**
     * Push local changes to master
     */
    public function pushChanges(string $table): bool
    {
        try {
            $lastSyncTime = SyncLog::getLastSyncTime($table);
            $data = $this->getLocalChanges($table, $lastSyncTime);

            if (empty($data)) {
                Log::info("No changes to push for table: {$table}");
                return true;
            }

            $response = $this->httpClient->post("{$this->masterUrl}/api/sync/push", [
                'table' => $table,
                'data' => $data,
            ]);

            if ($response->successful()) {
                Log::info("Successfully pushed {$table} changes", [
                    'count' => count($data),
                    'response' => $response->json()
                ]);
                return true;
            }

            Log::error("Failed to push {$table} changes", [
                'status' => $response->status(),
                'response' => $response->body()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("Exception while pushing {$table} changes", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Pull changes from master
     */
    public function pullChanges(string $table): bool
    {
        try {
            $lastSyncTime = SyncLog::getLastSyncTime($table);
            
            $response = $this->httpClient->get("{$this->masterUrl}/api/sync/pull", [
                'table' => $table,
                'since' => $lastSyncTime,
            ]);

            if (!$response->successful()) {
                Log::error("Failed to pull {$table} changes", [
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return false;
            }

            $result = $response->json();
            $data = $result['data'] ?? [];

            if (empty($data)) {
                Log::info("No changes to pull for table: {$table}");
                return true;
            }

            DB::beginTransaction();

            try {
                $this->applyMasterChanges($table, $data);
                SyncLog::updateLastSyncTime($table);
                
                DB::commit();
                
                Log::info("Successfully pulled {$table} changes", [
                    'count' => count($data)
                ]);
                return true;

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Exception while pulling {$table} changes", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Resolve conflicts (Master overrides Slave for inventory)
     */
    public function resolveConflicts(string $table): bool
    {
        if ($table !== 'inventory') {
            return true; // Only inventory needs conflict resolution
        }

        try {
            $lastSyncTime = SyncLog::getLastSyncTime($table);
            
            // Get all inventory records from master
            $response = $this->httpClient->get("{$this->masterUrl}/api/sync/pull", [
                'table' => $table,
                'since' => null, // Get all records
            ]);

            if (!$response->successful()) {
                return false;
            }

            $result = $response->json();
            $masterData = $result['data'] ?? [];

            DB::beginTransaction();

            try {
                // Clear local inventory and replace with master data
                Inventory::truncate();
                
                foreach ($masterData as $item) {
                    Inventory::create([
                        'id' => $item['id'],
                        'current_stock' => $item['current_stock'],
                        'minimum_stock' => $item['minimum_stock'],
                        'price_per_kg' => $item['price_per_kg'],
                        'notes' => $item['notes'] ?? null,
                        'created_at' => $item['created_at'],
                        'updated_at' => $item['updated_at']
                    ]);
                }

                SyncLog::updateLastSyncTime($table);
                DB::commit();

                Log::info("Successfully resolved conflicts for {$table}");
                return true;

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error("Exception while resolving conflicts for {$table}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get local changes since last sync
     */
    private function getLocalChanges(string $table, ?string $since): array
    {
        $query = match ($table) {
            'inventory' => Inventory::query(),
            'transactions' => Transaction::query(),
            'company_settings' => CompanySetting::query(),
            'users' => \App\Models\User::query(),
            'inventory_adjustments' => \App\Models\InventoryAdjustment::query(),
            default => throw new \InvalidArgumentException("Invalid table: {$table}")
        };

        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        return $query->get()->toArray();
    }

    /**
     * Apply master changes to local database
     */
    private function applyMasterChanges(string $table, array $data): void
    {
        match ($table) {
            'inventory' => $this->applyInventoryChanges($data),
            'transactions' => $this->applyTransactionChanges($data),
            'company_settings' => $this->applyCompanySettingChanges($data),
            'users' => $this->applyUserChanges($data),
            'inventory_adjustments' => $this->applyInventoryAdjustmentChanges($data),
            default => throw new \InvalidArgumentException("Invalid table: {$table}")
        };
    }

    /**
     * Apply inventory changes from master
     */
    private function applyInventoryChanges(array $data): void
    {
        foreach ($data as $item) {
            $inventory = Inventory::find($item['id']);
            
            if ($inventory) {
                // Update existing record
                $inventory->update([
                    'current_stock' => $item['current_stock'],
                    'minimum_stock' => $item['minimum_stock'],
                    'price_per_kg' => $item['price_per_kg'],
                    'notes' => $item['notes'] ?? null,
                    'updated_at' => $item['updated_at']
                ]);
            } else {
                // Create new record
                Inventory::create([
                    'id' => $item['id'],
                    'current_stock' => $item['current_stock'],
                    'minimum_stock' => $item['minimum_stock'],
                    'price_per_kg' => $item['price_per_kg'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at']
                ]);
            }
        }
    }

    /**
     * Apply transaction changes from master
     */
    private function applyTransactionChanges(array $data): void
    {
        foreach ($data as $item) {
            $transaction = Transaction::find($item['id']);
            
            if ($transaction) {
                // Update existing record
                $transaction->update([
                    'transaction_number' => $item['transaction_number'],
                    'cashier_id' => $item['cashier_id'],
                    'quantity_kg' => $item['quantity_kg'],
                    'price_per_kg' => $item['price_per_kg'],
                    'total_amount' => $item['total_amount'],
                    'customer_name' => $item['customer_name'] ?? null,
                    'customer_phone' => $item['customer_phone'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'status' => $item['status'],
                    'updated_at' => $item['updated_at']
                ]);
            } else {
                // Create new record
                Transaction::create([
                    'id' => $item['id'],
                    'transaction_number' => $item['transaction_number'],
                    'cashier_id' => $item['cashier_id'],
                    'quantity_kg' => $item['quantity_kg'],
                    'price_per_kg' => $item['price_per_kg'],
                    'total_amount' => $item['total_amount'],
                    'customer_name' => $item['customer_name'] ?? null,
                    'customer_phone' => $item['customer_phone'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'status' => $item['status'],
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at']
                ]);
            }
        }
    }

    /**
     * Apply company setting changes from master
     */
    private function applyCompanySettingChanges(array $data): void
    {
        foreach ($data as $item) {
            $setting = CompanySetting::find($item['id']);
            
            if ($setting) {
                // Update existing record
                $setting->update([
                    'company_name' => $item['company_name'],
                    'company_address' => $item['company_address'] ?? null,
                    'company_phone' => $item['company_phone'] ?? null,
                    'company_email' => $item['company_email'] ?? null,
                    'logo_path' => $item['logo_path'] ?? null,
                    'smtp_host' => $item['smtp_host'] ?? null,
                    'smtp_port' => $item['smtp_port'] ?? 587,
                    'smtp_username' => $item['smtp_username'] ?? null,
                    'smtp_password' => $item['smtp_password'] ?? null,
                    'smtp_encryption' => $item['smtp_encryption'] ?? 'tls',
                    'updated_at' => $item['updated_at']
                ]);
            } else {
                // Create new record
                CompanySetting::create([
                    'id' => $item['id'],
                    'company_name' => $item['company_name'],
                    'company_address' => $item['company_address'] ?? null,
                    'company_phone' => $item['company_phone'] ?? null,
                    'company_email' => $item['company_email'] ?? null,
                    'logo_path' => $item['logo_path'] ?? null,
                    'smtp_host' => $item['smtp_host'] ?? null,
                    'smtp_port' => $item['smtp_port'] ?? 587,
                    'smtp_username' => $item['smtp_username'] ?? null,
                    'smtp_password' => $item['smtp_password'] ?? null,
                    'smtp_encryption' => $item['smtp_encryption'] ?? 'tls',
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at']
                ]);
            }
        }
    }

    /**
     * Apply user changes from master
     */
    private function applyUserChanges(array $data): void
    {
        foreach ($data as $item) {
            $user = \App\Models\User::find($item['id']);
            
            if ($user) {
                // Update existing record
                $user->update([
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'email_verified_at' => $item['email_verified_at'] ?? null,
                    'password' => $item['password'],
                    'role' => $item['role'],
                    'is_active' => $item['is_active'],
                    'updated_at' => $item['updated_at']
                ]);
            } else {
                // Create new record
                \App\Models\User::create([
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'email' => $item['email'],
                    'email_verified_at' => $item['email_verified_at'] ?? null,
                    'password' => $item['password'],
                    'role' => $item['role'],
                    'is_active' => $item['is_active'],
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at']
                ]);
            }
        }
    }

    /**
     * Apply inventory adjustment changes from master
     */
    private function applyInventoryAdjustmentChanges(array $data): void
    {
        foreach ($data as $item) {
            $adjustment = \App\Models\InventoryAdjustment::find($item['id']);
            
            if ($adjustment) {
                // Update existing record
                $adjustment->update([
                    'user_id' => $item['user_id'],
                    'type' => $item['type'],
                    'quantity_kg' => $item['quantity_kg'],
                    'previous_stock' => $item['previous_stock'],
                    'new_stock' => $item['new_stock'],
                    'reason' => $item['reason'],
                    'notes' => $item['notes'] ?? null,
                    'updated_at' => $item['updated_at']
                ]);
            } else {
                // Create new record
                \App\Models\InventoryAdjustment::create([
                    'id' => $item['id'],
                    'user_id' => $item['user_id'],
                    'type' => $item['type'],
                    'quantity_kg' => $item['quantity_kg'],
                    'previous_stock' => $item['previous_stock'],
                    'new_stock' => $item['new_stock'],
                    'reason' => $item['reason'],
                    'notes' => $item['notes'] ?? null,
                    'created_at' => $item['created_at'],
                    'updated_at' => $item['updated_at']
                ]);
            }
        }
    }

    /**
     * Check if slave needs recovery (empty or corrupted)
     */
    public function needsRecovery(): bool
    {
        // Check if sync_logs table exists and has data
        try {
            $syncLogsCount = SyncLog::count();
            $inventoryCount = Inventory::count();
            $transactionsCount = Transaction::count();
            $companySettingsCount = CompanySetting::count();
            $usersCount = \App\Models\User::count();
            $inventoryAdjustmentsCount = \App\Models\InventoryAdjustment::count();

            // If any critical table is empty, we need recovery
            return $syncLogsCount === 0 || 
                   $inventoryCount === 0 || 
                   $transactionsCount === 0 || 
                   $companySettingsCount === 0 ||
                   $usersCount === 0 ||
                   $inventoryAdjustmentsCount === 0;

        } catch (\Exception $e) {
            // If we can't query the database, we need recovery
            Log::error("Database query failed, recovery needed", [
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    /**
     * Perform full recovery from master
     */
    public function performRecovery(): bool
    {
        try {
            Log::info("Starting full recovery from master");

            $tables = ['inventory', 'transactions', 'company_settings', 'users', 'inventory_adjustments'];
            $success = true;

            foreach ($tables as $table) {
                if (!$this->pullChanges($table)) {
                    $success = false;
                    Log::error("Failed to recover table: {$table}");
                }
            }

            if ($success) {
                Log::info("Full recovery completed successfully");
            }

            return $success;

        } catch (\Exception $e) {
            Log::error("Recovery failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
}
