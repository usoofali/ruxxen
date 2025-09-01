<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\CompanySetting;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    /**
     * Push changes from slave to master
     */
    public function push(Request $request): JsonResponse
    {
        Log::info('SyncController@push: Request received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'timestamp' => now()->toISOString()
        ]);

        $validator = Validator::make($request->all(), [
            'table' => 'required|string|in:inventory,transactions,company_settings,users,inventory_adjustments',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $table = $request->input('table');
        $data = $request->input('data');

        Log::info('SyncController@push: Processing request', [
            'table' => $table,
            'data_count' => count($data),
            'request_data_sample' => array_slice($data, 0, 2) // Log first 2 records as sample
        ]);

        try {
            DB::beginTransaction();

            switch ($table) {
                case 'inventory':
                    $this->syncInventory($data);
                    break;
                case 'transactions':
                    $this->syncTransactions($data);
                    break;
                case 'company_settings':
                    $this->syncCompanySettings($data);
                    break;
                case 'users':
                    $this->syncUsers($data);
                    break;
                case 'inventory_adjustments':
                    $this->syncInventoryAdjustments($data);
                    break;
            }

            // Update sync log
            SyncLog::updateLastSyncTime($table);

            DB::commit();

            Log::info('SyncController@push: Successfully completed', [
                'table' => $table,
                'synced_count' => count($data),
                'sync_log_updated' => true
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$table} data",
                'synced_count' => count($data)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('SyncController@push: Exception occurred', [
                'table' => $table,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Sync failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Pull changes from master to slave
     */
    public function pull(Request $request): JsonResponse
    {
        Log::info('SyncController@pull: Request received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'query_params' => $request->query(),
            'timestamp' => now()->toISOString()
        ]);

        $validator = Validator::make($request->all(), [
            'table' => 'required|string|in:inventory,transactions,company_settings,users,inventory_adjustments',
            'since' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $table = $request->input('table');
        $since = $request->input('since');

        Log::info('SyncController@pull: Processing request', [
            'table' => $table,
            'since' => $since,
            'since_parsed' => $since ? now()->parse($since)->toISOString() : 'null'
        ]);

        try {
            $data = $this->getDataSince($table, $since);

            Log::info('SyncController@pull: Successfully retrieved data', [
                'table' => $table,
                'data_count' => count($data),
                'since' => $since,
                'response_size' => strlen(json_encode($data))
            ]);

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => count($data),
                'table' => $table,
                'since' => $since
            ]);

        } catch (\Exception $e) {
            Log::error('SyncController@pull: Exception occurred', [
                'table' => $table,
                'since' => $since,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Pull failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync inventory data
     */
    private function syncInventory(array $data): void
    {
        Log::info('SyncController: Starting inventory sync', [
            'total_items' => count($data),
            'first_item_id' => $data[0]['id'] ?? 'N/A'
        ]);

        $created = 0;
        $updated = 0;

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
                $updated++;
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
                $created++;
            }
        }

        Log::info('SyncController: Completed inventory sync', [
            'total_items' => count($data),
            'created' => $created,
            'updated' => $updated
        ]);
    }

    /**
     * Sync transactions data
     */
    private function syncTransactions(array $data): void
    {
        Log::info('SyncController: Starting transactions sync', [
            'total_items' => count($data),
            'first_item_id' => $data[0]['id'] ?? 'N/A'
        ]);

        $created = 0;
        $updated = 0;

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
                $updated++;
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
                $created++;
            }
        }

        Log::info('SyncController: Completed transactions sync', [
            'total_items' => count($data),
            'created' => $created,
            'updated' => $updated
        ]);
    }

    /**
     * Sync company settings data
     */
    private function syncCompanySettings(array $data): void
    {
        Log::info('SyncController: Starting company settings sync', [
            'total_items' => count($data),
            'first_item_id' => $data[0]['id'] ?? 'N/A'
        ]);

        $created = 0;
        $updated = 0;

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
                $updated++;
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
                $created++;
            }
        }

        Log::info('SyncController: Completed company settings sync', [
            'total_items' => count($data),
            'created' => $created,
            'updated' => $updated
        ]);
    }

    /**
     * Sync users data
     */
    private function syncUsers(array $data): void
    {
        Log::info('SyncController: Starting users sync', [
            'total_items' => count($data),
            'first_item_id' => $data[0]['id'] ?? 'N/A'
        ]);

        $created = 0;
        $updated = 0;

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
                $updated++;
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
                $created++;
            }
        }

        Log::info('SyncController: Completed users sync', [
            'total_items' => count($data),
            'created' => $created,
            'updated' => $updated
        ]);
    }

    /**
     * Sync inventory adjustments data
     */
    private function syncInventoryAdjustments(array $data): void
    {
        Log::info('SyncController: Starting inventory adjustments sync', [
            'total_items' => count($data),
            'first_item_id' => $data[0]['id'] ?? 'N/A'
        ]);

        $created = 0;
        $updated = 0;

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
                $updated++;
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
                $created++;
            }
        }

        Log::info('SyncController: Completed inventory adjustments sync', [
            'total_items' => count($data),
            'created' => $created,
            'updated' => $updated
        ]);
    }

    /**
     * Get data since a specific timestamp
     */
    private function getDataSince(string $table, ?string $since): array
    {
        Log::info('SyncController: getDataSince called', [
            'table' => $table,
            'since' => $since,
            'since_parsed' => $since ? now()->parse($since)->toISOString() : 'null'
        ]);

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

        $result = $query->get()->toArray();

        Log::info('SyncController: getDataSince completed', [
            'table' => $table,
            'since' => $since,
            'result_count' => count($result),
            'query_sql' => $query->toSql(),
            'query_bindings' => $query->getBindings()
        ]);

        return $result;
    }
}
