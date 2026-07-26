<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerDiscount;
use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class PosApiController extends Controller
{
    /**
     * Authenticate cashier terminal
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials.',
            ], 401);
        }

        if ($user->role !== 'cashier' && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Account is not authorized as cashier or admin.',
            ], 403);
        }

        $token = method_exists($user, 'createToken') 
            ? $user->createToken('pos-terminal')->plainTextToken 
            : base64_encode($user->id . ':' . $user->email . ':' . now()->timestamp);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ]
            ]
        ]);
    }

    /**
     * Get initial system data for Desktop POS initialization
     */
    public function initialData()
    {
        $inventory = Inventory::first();
        $discounts = CustomerDiscount::active()->get()->map(function ($discount) use ($inventory) {
            $basePrice = $inventory ? (float)$inventory->price_per_kg : 0.00;
            $effectivePrice = max(0, $basePrice - (float)$discount->discount_per_kg);

            return [
                'id' => $discount->id,
                'name' => $discount->name,
                'discount_per_kg' => (float)$discount->discount_per_kg,
                'effective_price_per_kg' => $effectivePrice,
                'is_default' => (bool)$discount->is_default,
                'description' => $discount->description,
            ];
        });

        $companySettings = DB::table('company_settings')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'base_price_per_kg' => $inventory ? (float)$inventory->price_per_kg : 0.00,
                'current_stock_kg' => $inventory ? (float)$inventory->current_stock : 0.00,
                'minimum_stock_kg' => $inventory ? (float)$inventory->minimum_stock : 0.00,
                'pricing_tiers' => $discounts,
                'company' => [
                    'name' => (!empty($companySettings->company_name)) ? $companySettings->company_name : 'Ruxxen Gas Plant',
                    'address' => (!empty($companySettings->company_address)) ? $companySettings->company_address : 'Along Bye Pass Zaria Road, Lalan Gusau, Zamfara State',
                    'phone' => (!empty($companySettings->company_phone)) ? $companySettings->company_phone : '+234 123 456 7890',
                    'email' => (!empty($companySettings->company_email)) ? $companySettings->company_email : 'info@ruxxenlpg.com',
                    'logo_path' => $companySettings->logo_path ?? null,
                    'receipt_footer' => 'Thank you for buying from Ruxxen Gas!',
                ]

            ]
        ]);

    }

    /**
     * Get real-time inventory stock level
     */
    public function checkStock()
    {
        $inventory = Inventory::first();

        return response()->json([
            'success' => true,
            'data' => [
                'current_stock_kg' => $inventory ? (float)$inventory->current_stock : 0.00,
                'minimum_stock_kg' => $inventory ? (float)$inventory->minimum_stock : 0.00,
                'is_low_stock' => $inventory ? $inventory->isLowStock() : false,
            ]
        ]);
    }

    /**
     * Batch process transactions (synced from desktop POS offline or real-time)
     */
    public function syncTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transactions' => 'required|array|min:1',
            'transactions.*.transaction_number' => 'nullable|string',
            'transactions.*.cashier_id' => 'required|integer|exists:users,id',
            'transactions.*.customer_discount_id' => 'nullable|integer|exists:customer_discounts,id',
            'transactions.*.quantity_kg' => 'required|numeric|min:0.1',
            'transactions.*.price_per_kg' => 'required|numeric|min:0',
            'transactions.*.total_amount' => 'required|numeric|min:0',
            'transactions.*.customer_name' => 'nullable|string|max:255',
            'transactions.*.customer_phone' => 'nullable|string|max:50',
            'transactions.*.payment_type' => 'required|string',
            'transactions.*.notes' => 'nullable|string',
            'transactions.*.created_at' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error in sync payload',
                'errors' => $validator->errors()
            ], 422);
        }

        $processed = [];

        DB::beginTransaction();
        try {
            $inventory = Inventory::first();

            foreach ($request->transactions as $txnData) {
                // Prevent duplicate processing if transaction_number already exists
                if (!empty($txnData['transaction_number'])) {
                    $existing = Transaction::where('transaction_number', $txnData['transaction_number'])->first();
                    if ($existing) {
                        if ($existing->status === 'cancelled' || ($txnData['status'] ?? '') === 'cancelled') {
                            if ($existing->status !== 'cancelled') {
                                $existing->status = 'cancelled';
                                $existing->cancellation_reason = $txnData['cancellation_reason'] ?? 'Cancelled at POS';
                                $existing->cancelled_at = $txnData['cancelled_at'] ?? now();
                                $existing->save();

                                if ($inventory) {
                                    $inventory->current_stock += (float)$existing->quantity_kg;
                                    $inventory->save();
                                }
                            }
                            $processed[] = [
                                'transaction_number' => $existing->transaction_number,
                                'status' => 'cancelled',
                                'id' => $existing->id,
                            ];
                            continue;
                        }

                        $processed[] = [
                            'transaction_number' => $existing->transaction_number,
                            'status' => 'already_synced',
                            'id' => $existing->id,
                        ];
                        continue;
                    }
                }

                // Check and update stock if creating new transaction
                $qty = (float)$txnData['quantity_kg'];
                $status = strtolower($txnData['status'] ?? 'completed');

                if ($status === 'completed' && $inventory) {
                    $inventory->current_stock = max(0, $inventory->current_stock - $qty);
                    $inventory->save();
                }

                $transaction = new Transaction();
                if (!empty($txnData['transaction_number'])) {
                    $transaction->transaction_number = $txnData['transaction_number'];
                }
                $transaction->cashier_id = $txnData['cashier_id'];
                $transaction->customer_discount_id = $txnData['customer_discount_id'] ?? CustomerDiscount::getDefault()?->id;
                $transaction->quantity_kg = $qty;
                $transaction->price_per_kg = $txnData['price_per_kg'];
                $transaction->total_amount = $txnData['total_amount'];
                $transaction->customer_name = $txnData['customer_name'] ?? 'Walk-in Customer';
                $transaction->customer_phone = $txnData['customer_phone'] ?? null;
                $transaction->payment_type = strtolower($txnData['payment_type']);
                $transaction->notes = $txnData['notes'] ?? 'Created via Desktop POS';
                $transaction->status = $status;
                $transaction->cancellation_reason = $txnData['cancellation_reason'] ?? null;
                if (!empty($txnData['cancelled_at'])) {
                    $transaction->cancelled_at = $txnData['cancelled_at'];
                }
                
                if (!empty($txnData['created_at'])) {
                    $transaction->created_at = $txnData['created_at'];
                }

                $transaction->save();

                $processed[] = [
                    'transaction_number' => $transaction->transaction_number,
                    'status' => $status,
                    'id' => $transaction->id,
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transactions synced successfully.',
                'data' => [
                    'processed' => $processed,
                    'remaining_stock_kg' => $inventory ? (float)$inventory->current_stock : 0.00
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a transaction endpoint
     */
    public function cancelTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_number' => 'required|string',
            'cancellation_reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $txn = Transaction::where('transaction_number', $request->input('transaction_number'))->first();
        if (!$txn) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.'
            ], 404);
        }

        if ($txn->status === 'cancelled') {
            return response()->json([
                'success' => true,
                'message' => 'Transaction is already cancelled.'
            ]);
        }

        $txn->status = 'cancelled';
        $txn->cancellation_reason = $request->input('cancellation_reason');
        $txn->cancelled_at = now();
        $txn->save();

        // Restock gas inventory
        $inventory = Inventory::first();
        if ($inventory) {
            $inventory->current_stock += (float)$txn->quantity_kg;
            $inventory->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction cancelled successfully.',
            'data' => [
                'transaction_number' => $txn->transaction_number,
                'status' => 'cancelled',
                'current_stock_kg' => $inventory ? (float)$inventory->current_stock : 0.00
            ]
        ]);
    }

    /**
     * Get cashier's daily sales summary
     */
    public function getCashierDailySummary(Request $request)
    {
        $cashierId = $request->user()?->id ?? $request->input('cashier_id');

        if (!$cashierId) {
            return response()->json([
                'success' => false,
                'message' => 'Cashier ID is required.'
            ], 400);
        }

        $todayTxns = Transaction::where('cashier_id', $cashierId)
            ->whereDate('created_at', today())
            ->get();

        $completedTxns = $todayTxns->where('status', 'completed');
        $cancelledTxns = $todayTxns->where('status', 'cancelled');

        $totalSalesCount = $completedTxns->count();
        $totalQtyKg = (float)$completedTxns->sum('quantity_kg');
        $totalAmount = (float)$completedTxns->sum('total_amount');

        $cancelledCount = $cancelledTxns->count();
        $cancelledAmount = (float)$cancelledTxns->sum('total_amount');

        $byPayment = [
            'cash' => (float)$completedTxns->where('payment_type', 'cash')->sum('total_amount'),
            'card' => (float)$completedTxns->whereIn('payment_type', ['card', 'pos'])->sum('total_amount'),
            'transfer' => (float)$completedTxns->where('payment_type', 'transfer')->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'cashier_id' => $cashierId,
                'date' => today()->format('Y-m-d'),
                'total_sales_count' => $totalSalesCount,
                'total_quantity_kg' => $totalQtyKg,
                'total_amount' => $totalAmount,
                'cancelled_sales_count' => $cancelledCount,
                'cancelled_total_amount' => $cancelledAmount,
                'payment_breakdown' => $byPayment,
                'recent_transactions' => $todayTxns->take(20)->values(),
            ]
        ]);
    }
}


