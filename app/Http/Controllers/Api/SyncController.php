<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\InventoryAdjustment;
use App\Services\SyncStatusManager;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function __construct(
        private SyncStatusManager $syncStatusManager
    ) {}

    /**
     * Upload records from Slave to Master
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'records' => 'required|array',
                'records.*.table' => 'required|string|in:inventories,transactions,inventory_adjustments',
                'records.*.data' => 'required|array',
                'records.*.action' => 'required|string|in:create,update,delete',
                'records.*.id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $uploadedCount = 0;
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($request->input('records', []) as $record) {
                    $result = $this->processRecord($record);
                    
                    if ($result['success']) {
                        $uploadedCount++;
                    } else {
                        $errors[] = $result['error'];
                    }
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Successfully processed {$uploadedCount} records",
                    'uploaded_count' => $uploadedCount,
                    'errors' => $errors
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Sync upload failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download records from Master to Slave
     */
    public function download(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'last_synced_at' => 'nullable|date',
                'tables' => 'nullable|array',
                'tables.*' => 'string|in:inventories,transactions,inventory_adjustments'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $lastSyncedAt = $request->input('last_synced_at');
            $tables = $request->input('tables', ['inventories', 'transactions', 'inventory_adjustments']);

            $records = [];

            foreach ($tables as $table) {
                $tableRecords = $this->getUpdatedRecords($table, $lastSyncedAt);
                $records[$table] = $tableRecords;
            }

            return response()->json([
                'success' => true,
                'records' => $records,
                'total_count' => array_sum(array_map('count', $records)),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            Log::error('Sync download failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Download failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Acknowledge sync completion
     */
    public function acknowledge(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'timestamp' => 'required|date',
                'status' => 'required|string|in:success,failed',
                'message' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $status = $request->input('status');
            
            if ($status === 'success') {
                $this->syncStatusManager->markSuccess();
            } else {
                $this->syncStatusManager->markFailed($request->input('message', 'Unknown error'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Sync acknowledgment received'
            ]);

        } catch (\Exception $e) {
            Log::error('Sync acknowledgment failed', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Acknowledgment failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process individual record
     */
    private function processRecord(array $record): array
    {
        try {
            $table = $record['table'];
            $data = $record['data'];
            $action = $record['action'];
            $id = $record['id'];

            switch ($table) {
                case 'inventories':
                    return $this->processInventoryRecord($action, $data, $id);
                case 'transactions':
                    return $this->processTransactionRecord($action, $data, $id);
                case 'inventory_adjustments':
                    return $this->processInventoryAdjustmentRecord($action, $data, $id);
                default:
                    return ['success' => false, 'error' => "Unknown table: {$table}"];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process inventory record
     */
    private function processInventoryRecord(string $action, array $data, string $id): array
    {
        switch ($action) {
            case 'create':
                Inventory::create($data);
                break;
            case 'update':
                Inventory::where('id', $id)->update($data);
                break;
            case 'delete':
                Inventory::where('id', $id)->delete();
                break;
        }
        
        return ['success' => true];
    }

    /**
     * Process transaction record
     */
    private function processTransactionRecord(string $action, array $data, string $id): array
    {
        switch ($action) {
            case 'create':
                Transaction::create($data);
                break;
            case 'update':
                Transaction::where('id', $id)->update($data);
                break;
            case 'delete':
                Transaction::where('id', $id)->delete();
                break;
        }
        
        return ['success' => true];
    }

    /**
     * Process inventory adjustment record
     */
    private function processInventoryAdjustmentRecord(string $action, array $data, string $id): array
    {
        switch ($action) {
            case 'create':
                InventoryAdjustment::create($data);
                break;
            case 'update':
                InventoryAdjustment::where('id', $id)->update($data);
                break;
            case 'delete':
                InventoryAdjustment::where('id', $id)->delete();
                break;
        }
        
        return ['success' => true];
    }

    /**
     * Get updated records for a specific table
     */
    private function getUpdatedRecords(string $table, ?string $lastSyncedAt): array
    {
        $query = DB::table($table);
        
        if ($lastSyncedAt) {
            $query->where('updated_at', '>', $lastSyncedAt);
        }

        return $query->get()->toArray();
    }
}
