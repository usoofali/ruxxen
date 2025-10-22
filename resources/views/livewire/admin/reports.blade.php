<?php

use App\Models\Transaction;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public $reportType = 'sales';
    public $dateRange = 'this_month';
    public $startDate = '';
    public $endDate = '';
    public $cashierFilter = '';

    public function mount()
    {
        $this->setDateRange();
    }

    public function updatedDateRange()
    {
        $this->setDateRange();
    }

    public function setDateRange()
    {
        switch ($this->dateRange) {
            case 'today':
                $this->startDate = today()->format('Y-m-d');
                $this->endDate = today()->format('Y-m-d');
                break;
            case 'yesterday':
                $this->startDate = today()->subDay()->format('Y-m-d');
                $this->endDate = today()->subDay()->format('Y-m-d');
                break;
            case 'this_week':
                $this->startDate = now()->startOfWeek()->format('Y-m-d');
                $this->endDate = now()->endOfWeek()->format('Y-m-d');
                break;
            case 'last_week':
                $this->startDate = now()->subWeek()->startOfWeek()->format('Y-m-d');
                $this->endDate = now()->subWeek()->endOfWeek()->format('Y-m-d');
                break;
            case 'this_month':
                $this->startDate = now()->startOfMonth()->format('Y-m-d');
                $this->endDate = now()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
                $this->endDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            case 'custom':
                // Keep existing custom dates
                break;
        }
    }

    public function getTransactionsProperty()
    {
        $query = Transaction::with('cashier')
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59']);

        if ($this->cashierFilter) {
            $query->where('cashier_id', $this->cashierFilter);
        }

        return $query->get();
    }

    public function getSalesDataProperty()
    {
        $transactions = $this->transactions;
        $completedTransactions = $transactions->where('status', 'completed');
        
        return [
            'total_sales' => $completedTransactions->sum('total_amount'),
            'total_quantity' => $completedTransactions->sum('quantity_kg'),
            'total_transactions' => $transactions->count(),
            'average_transaction' => $completedTransactions->count() > 0 ? $completedTransactions->sum('total_amount') / $completedTransactions->count() : 0,
            'completed_transactions' => $transactions->where('status', 'completed')->count(),
            'cancelled_transactions' => $transactions->where('status', 'cancelled')->count(),
            'refunded_transactions' => $transactions->where('status', 'refunded')->count(),
            'cash_payments' => $completedTransactions->where('payment_type', 'cash')->count(),
            'card_payments' => $completedTransactions->where('payment_type', 'card')->count(),
            'transfer_payments' => $completedTransactions->where('payment_type', 'transfer')->count(),
            'cash_amount' => $completedTransactions->where('payment_type', 'cash')->sum('total_amount'),
            'card_amount' => $completedTransactions->where('payment_type', 'card')->sum('total_amount'),
            'transfer_amount' => $completedTransactions->where('payment_type', 'transfer')->sum('total_amount'),
        ];
    }

    public function getDailySalesProperty()
    {
        $transactions = $this->transactions;
        $dailyData = [];

        $start = Carbon::parse($this->startDate);
        $end = Carbon::parse($this->endDate);

        for ($date = $start; $date->lte($end); $date->addDay()) {
            $dayTransactions = $transactions->filter(function ($transaction) use ($date) {
                return $transaction->created_at->format('Y-m-d') === $date->format('Y-m-d');
            });

            $completedDayTransactions = $dayTransactions->where('status', 'completed');

            $dailyData[] = [
                'date' => $date->format('M d'),
                'sales' => $completedDayTransactions->sum('total_amount'),
                'quantity' => $completedDayTransactions->sum('quantity_kg'),
                'transactions' => $dayTransactions->count(),
            ];
        }

        return $dailyData;
    }

    public function getCashierPerformanceProperty()
    {
        $transactions = $this->transactions;
        $cashierData = [];

        $cashiers = User::where('role', 'cashier')->where('is_active', true)->get();

        foreach ($cashiers as $cashier) {
            $cashierTransactions = $transactions->where('cashier_id', $cashier->id);
            $completedCashierTransactions = $cashierTransactions->where('status', 'completed');
            
            $cashierData[] = [
                'name' => $cashier->name,
                'email' => $cashier->email,
                'total_sales' => $completedCashierTransactions->sum('total_amount'),
                'total_quantity' => $completedCashierTransactions->sum('quantity_kg'),
                'transactions' => $cashierTransactions->count(),
                'average_transaction' => $completedCashierTransactions->count() > 0 ? $completedCashierTransactions->sum('total_amount') / $completedCashierTransactions->count() : 0,
            ];
        }

        return collect($cashierData)->sortByDesc('total_sales')->values();
    }

    public function getInventoryDataProperty()
    {
        $inventory = Inventory::first();
        
        if (!$inventory) {
            return [
                'current_stock' => 0,
                'minimum_stock' => 0,
                'price_per_kg' => 0,
                'stock_percentage' => 0,
                'is_low_stock' => false,
                'stock_value' => 0,
            ];
        }
        
        return [
            'current_stock' => $inventory->current_stock,
            'minimum_stock' => $inventory->minimum_stock,
            'price_per_kg' => $inventory->price_per_kg,
            'stock_percentage' => $inventory->getStockPercentage(),
            'is_low_stock' => $inventory->isLowStock(),
            'stock_value' => $inventory->current_stock * $inventory->price_per_kg,
        ];
    }

    public function getCashiersProperty()
    {
        return User::where('role', 'cashier')->where('is_active', true)->get();
    }

    public function exportReport()
    {
        try {
            $filename = 'ruxxen_lpg_report_' . $this->reportType . '_' . date('Y-m-d_H-i-s');
            
            switch ($this->reportType) {
                case 'sales':
                    return $this->exportSalesReport($filename);
                case 'inventory':
                    return $this->exportInventoryReport($filename);
                case 'cashier':
                    return $this->exportCashierReport($filename);
                default:
                    $this->dispatch('notify', [
                        'type' => 'error',
                        'message' => 'Invalid report type selected.'
                    ]);
                    return null;
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Export failed: ' . $e->getMessage()
            ]);
            return null;
        }
    }

    private function exportSalesReport($filename)
    {
        $transactions = $this->transactions;
        $salesData = $this->salesData;
        $dailySales = $this->dailySales;
        
        $csvData = [];
        
        // Header
        $csvData[] = ['RUXXEN LPG GAS PLANT - SALES REPORT'];
        $csvData[] = ['Period: ' . Carbon::parse($this->startDate)->format('M d, Y') . ' - ' . Carbon::parse($this->endDate)->format('M d, Y')];
        $csvData[] = ['Generated: ' . now()->format('M d, Y H:i:s')];
        $csvData[] = [];
        
        // Summary
        $csvData[] = ['SUMMARY'];
        $csvData[] = ['Total Sales', '₦' . number_format($salesData['total_sales'], 2)];
        $csvData[] = ['Total Quantity', number_format($salesData['total_quantity'], 2) . ' kg'];
        $csvData[] = ['Total Transactions', $salesData['total_transactions']];
        $csvData[] = ['Average Transaction', '₦' . number_format($salesData['average_transaction'], 2)];
        $csvData[] = [];
        
        // Status Breakdown
        $csvData[] = ['TRANSACTION STATUS BREAKDOWN'];
        $csvData[] = ['Status', 'Count'];
        $csvData[] = ['Completed', $salesData['completed_transactions']];
        $csvData[] = ['Cancelled', $salesData['cancelled_transactions']];
        $csvData[] = ['Refunded', $salesData['refunded_transactions']];
        $csvData[] = [];
        
        // Payment Method Breakdown
        $csvData[] = ['PAYMENT METHOD BREAKDOWN'];
        $csvData[] = ['Payment Type', 'Count', 'Total Amount (₦)'];
        $csvData[] = ['Cash', $salesData['cash_payments'], number_format($salesData['cash_amount'], 2)];
        $csvData[] = ['Card', $salesData['card_payments'], number_format($salesData['card_amount'], 2)];
        $csvData[] = ['Transfer', $salesData['transfer_payments'], number_format($salesData['transfer_amount'], 2)];
        $csvData[] = [];
        
        // Daily Sales
        $csvData[] = ['DAILY SALES BREAKDOWN'];
        $csvData[] = ['Date', 'Sales (₦)', 'Quantity (kg)', 'Transactions'];
        foreach ($dailySales as $day) {
            $csvData[] = [
                $day['date'],
                number_format($day['sales'], 2),
                number_format($day['quantity'], 2),
                $day['transactions']
            ];
        }
        $csvData[] = [];
        
        // Detailed Transactions
        $csvData[] = ['DETAILED TRANSACTIONS'];
        $csvData[] = ['Transaction #', 'Date', 'Cashier', 'Customer', 'Quantity (kg)', 'Price/kg', 'Total (₦)', 'Payment Type', 'Status'];
        foreach ($transactions as $transaction) {
            $csvData[] = [
                $transaction->transaction_number,
                $transaction->created_at->format('M d, Y H:i'),
                $transaction->cashier->name,
                $transaction->customer_name,
                number_format($transaction->quantity_kg, 2),
                number_format($transaction->price_per_kg, 2),
                number_format($transaction->total_amount, 2),
                ucfirst($transaction->payment_type),
                ucfirst($transaction->status)
            ];
        }
        
        return $this->downloadCsv($csvData, $filename . '.csv');
    }

    private function exportInventoryReport($filename)
    {
        $inventoryData = $this->inventoryData;
        $inventory = Inventory::first();
        $adjustments = InventoryAdjustment::with('user')
            ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        $csvData = [];
        
        // Header
        $csvData[] = ['RUXXEN LPG GAS PLANT - INVENTORY REPORT'];
        $csvData[] = ['Period: ' . Carbon::parse($this->startDate)->format('M d, Y') . ' - ' . Carbon::parse($this->endDate)->format('M d, Y')];
        $csvData[] = ['Generated: ' . now()->format('M d, Y H:i:s')];
        $csvData[] = [];
        
        // Current Status
        $csvData[] = ['CURRENT INVENTORY STATUS'];
        $csvData[] = ['Current Stock', number_format($inventoryData['current_stock'], 2) . ' kg'];
        $csvData[] = ['Minimum Stock', number_format($inventoryData['minimum_stock'], 2) . ' kg'];
        $csvData[] = ['Price per kg', '₦' . number_format($inventoryData['price_per_kg'], 2)];
        $csvData[] = ['Stock Value', '₦' . number_format($inventoryData['stock_value'], 2)];
        $csvData[] = ['Stock Level', number_format($inventoryData['stock_percentage'], 1) . '%'];
        $csvData[] = ['Status', $inventoryData['is_low_stock'] ? 'Low Stock' : 'Good'];
        $csvData[] = [];
        
        // Inventory Adjustments
        $csvData[] = ['INVENTORY ADJUSTMENTS'];
        $csvData[] = ['Date', 'Type', 'Quantity (kg)', 'Reason', 'Notes', 'User'];
        foreach ($adjustments as $adjustment) {
            $csvData[] = [
                $adjustment->created_at->format('M d, Y H:i'),
                ucfirst($adjustment->adjustment_type),
                number_format($adjustment->quantity, 2),
                $adjustment->reason,
                $adjustment->notes ?? '',
                $adjustment->user->name
            ];
        }
        
        return $this->downloadCsv($csvData, $filename . '.csv');
    }

    private function exportCashierReport($filename)
    {
        $cashierPerformance = $this->cashierPerformance;
        $transactions = $this->transactions;
        
        $csvData = [];
        
        // Header
        $csvData[] = ['RUXXEN LPG GAS PLANT - CASHIER PERFORMANCE REPORT'];
        $csvData[] = ['Period: ' . Carbon::parse($this->startDate)->format('M d, Y') . ' - ' . Carbon::parse($this->endDate)->format('M d, Y')];
        $csvData[] = ['Generated: ' . now()->format('M d, Y H:i:s')];
        $csvData[] = [];
        
        // Cashier Performance Summary
        $csvData[] = ['CASHIER PERFORMANCE SUMMARY'];
        $csvData[] = ['Cashier', 'Email', 'Total Sales (₦)', 'Quantity Sold (kg)', 'Transactions', 'Avg. Transaction (₦)'];
        foreach ($cashierPerformance as $cashier) {
            $csvData[] = [
                $cashier['name'],
                $cashier['email'],
                number_format($cashier['total_sales'], 2),
                number_format($cashier['total_quantity'], 2),
                $cashier['transactions'],
                number_format($cashier['average_transaction'], 2)
            ];
        }
        $csvData[] = [];
        
        // Detailed Transactions by Cashier
        $csvData[] = ['DETAILED TRANSACTIONS BY CASHIER'];
        $csvData[] = ['Transaction #', 'Date', 'Cashier', 'Customer', 'Quantity (kg)', 'Price/kg', 'Total (₦)', 'Payment Type', 'Status'];
        foreach ($transactions as $transaction) {
            $csvData[] = [
                $transaction->transaction_number,
                $transaction->created_at->format('M d, Y H:i'),
                $transaction->cashier->name,
                $transaction->customer_name,
                number_format($transaction->quantity_kg, 2),
                number_format($transaction->price_per_kg, 2),
                number_format($transaction->total_amount, 2),
                ucfirst($transaction->payment_type),
                ucfirst($transaction->status)
            ];
        }
        
        return $this->downloadCsv($csvData, $filename . '.csv');
    }

    private function downloadCsv($data, $filename)
    {
        $handle = fopen('php://temp', 'r+');
        
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function generateReport()
    {
        // Trigger report generation
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Report data updated for ' . $this->dateRange . ' period.'
        ]);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Reports & Analytics</h1>
            <p class="text-gray-600 dark:text-gray-400">Generate comprehensive business reports</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 sm:flex-shrink-0">
            <flux:button wire:click="generateReport" variant="outline" class="w-full sm:w-auto">
                Refresh Data
            </flux:button>
            <flux:button wire:click="exportReport" variant="primary" class="w-full sm:w-auto">
                Export Report
            </flux:button>
        </div>
    </div>

    <!-- Report Controls -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <!-- Report Type -->
            <div>
                <flux:select wire:model.live="reportType" label="Report Type">
                    <option value="sales">Sales Report</option>
                    <option value="inventory">Inventory Report</option>
                    <option value="cashier">Cashier Performance</option>
                </flux:select>
            </div>

            <!-- Date Range -->
            <div>
                <flux:select wire:model.live="dateRange" label="Date Range">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_week">This Week</option>
                    <option value="last_week">Last Week</option>
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="custom">Custom Range</option>
                </flux:select>
            </div>

            <!-- Custom Date Range -->
            @if($dateRange === 'custom')
                <div class="grid grid-cols-2 gap-2">
                    <flux:input
                        wire:model="startDate"
                        label="Start Date"
                        type="date"
                    />
                    <flux:input
                        wire:model="endDate"
                        label="End Date"
                        type="date"
                    />
                </div>
            @else
                <div>
                    <flux:select wire:model.live="cashierFilter" label="Cashier Filter">
                        <option value="">All Cashiers</option>
                        @foreach($this->cashiers as $cashier)
                            <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <!-- Date Display -->
            <div class="flex items-end">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Period: {{ Carbon::parse($startDate)->format('M d, Y') }} - {{ Carbon::parse($endDate)->format('M d, Y') }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($reportType === 'sales')
        <!-- Sales Report -->
        <div class="space-y-6">
            <!-- Sales Summary Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Sales</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($this->salesData['total_sales'], 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Quantity</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->salesData['total_quantity'], 2) }} kg</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900">
                                <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Transactions</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->salesData['total_transactions'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900">
                                <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg. Transaction</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($this->salesData['average_transaction'], 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Status Breakdown -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Transaction Status</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Completed:</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ $this->salesData['completed_transactions'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Cancelled:</span>
                            <span class="font-medium text-red-600 dark:text-red-400">{{ $this->salesData['cancelled_transactions'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Refunded:</span>
                            <span class="font-medium text-yellow-600 dark:text-yellow-400">{{ $this->salesData['refunded_transactions'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Payment Methods</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Cash:</span>
                            <span class="font-medium text-green-600 dark:text-green-400">{{ $this->salesData['cash_payments'] }} (₦{{ number_format($this->salesData['cash_amount'], 2) }})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Card:</span>
                            <span class="font-medium text-blue-600 dark:text-blue-400">{{ $this->salesData['card_payments'] }} (₦{{ number_format($this->salesData['card_amount'], 2) }})</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Transfer:</span>
                            <span class="font-medium text-purple-600 dark:text-purple-400">{{ $this->salesData['transfer_payments'] }} (₦{{ number_format($this->salesData['transfer_amount'], 2) }})</span>
                        </div>
                    </div>
                </div>

                <!-- Daily Sales Chart -->
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800 md:col-span-2">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Daily Sales Trend</h3>
                    <div class="h-64">
                        <canvas id="dailySalesChart" wire:ignore></canvas>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($reportType === 'inventory')
        <!-- Inventory Report -->
        <div class="space-y-6">
            <!-- Inventory Summary -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                                <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Current Stock</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->inventoryData['current_stock'], 2) }} kg</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                                <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Stock Value</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($this->inventoryData['stock_value'], 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900">
                                <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Minimum Stock</p>
                            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->inventoryData['minimum_stock'], 2) }} kg</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg {{ $this->inventoryData['is_low_stock'] ? 'bg-red-100 dark:bg-red-900' : 'bg-green-100 dark:bg-green-900' }}">
                                <svg class="h-5 w-5 {{ $this->inventoryData['is_low_stock'] ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</p>
                            <p class="text-2xl font-bold {{ $this->inventoryData['is_low_stock'] ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                {{ $this->inventoryData['is_low_stock'] ? 'Low Stock' : 'Good' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Level Progress -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Stock Level Overview</h3>
                <div class="space-y-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">Current Stock: {{ number_format($this->inventoryData['current_stock'], 2) }} kg</span>
                        <span class="text-gray-600 dark:text-gray-400">Minimum Required: {{ number_format($this->inventoryData['minimum_stock'], 2) }} kg</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                        <div class="bg-blue-600 h-3 rounded-full {{ $this->inventoryData['is_low_stock'] ? 'bg-red-600' : '' }}" 
                             style="width: {{ min(100, $this->inventoryData['stock_percentage']) }}%"></div>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ number_format($this->inventoryData['stock_percentage'], 1) }}% of minimum stock level
                    </div>
                </div>
            </div>

            <!-- Inventory Chart -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Stock Level Chart</h3>
                <div class="h-64">
                    <canvas id="inventoryChart" wire:ignore></canvas>
                </div>
            </div>
        </div>
    @endif

    @if($reportType === 'cashier')
        <!-- Cashier Performance Report -->
        <div class="space-y-6">
            <!-- Cashier Performance Chart -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Cashier Performance Chart</h3>
                <div class="h-64">
                    <canvas id="cashierChart" wire:ignore></canvas>
                </div>
            </div>

            <!-- Cashier Performance Table -->
            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Cashier Performance</h3>
                </div>
                <div class="p-6">
                    @if($this->cashierPerformance->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Cashier</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Sales</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Quantity Sold</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Transactions</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Avg. Transaction</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                    @foreach($this->cashierPerformance as $cashier)
                                        <tr>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $cashier['name'] }}</div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $cashier['email'] }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">₦{{ number_format($cashier['total_sales'], 2) }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="text-sm text-gray-900 dark:text-white">{{ number_format($cashier['total_quantity'], 2) }} kg</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="text-sm text-gray-900 dark:text-white">{{ $cashier['transactions'] }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="text-sm text-gray-900 dark:text-white">₦{{ number_format($cashier['average_transaction'], 2) }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No cashier data</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No transactions found for the selected period.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
    <!-- Flash Message -->
    @if (session()->has('error'))
        <div class="fixed bottom-4 right-4 z-50">
        <x-alert variant="error" :timeout="5000">
            {{ session('error') }}
        </x-alert>
    </div>
    @endif
    @if (session()->has('success'))
        <div class="fixed bottom-4 right-4 z-50">
        <x-alert variant="success" :timeout="5000">
            {{ session('success') }}
        </x-alert>
    </div>
    @endif
</div>

<!-- Chart.js Library -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let dailySalesChart = null;
    let inventoryChart = null;
    let cashierChart = null;

    // Function to initialize Daily Sales Chart
    function initDailySalesChart() {
        const ctx = document.getElementById('dailySalesChart');
        if (!ctx) return;

        if (dailySalesChart) {
            dailySalesChart.destroy();
        }

        const dailyData = @json($this->dailySales);
        const labels = dailyData.map(item => item.date);
        const salesData = dailyData.map(item => item.sales);
        const quantityData = dailyData.map(item => item.quantity);

        dailySalesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales (₦)',
                    data: salesData,
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Quantity (kg)',
                    data: quantityData,
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Sales (₦)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Quantity (kg)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }

    // Function to initialize Inventory Chart
    function initInventoryChart() {
        const ctx = document.getElementById('inventoryChart');
        if (!ctx) return;

        if (inventoryChart) {
            inventoryChart.destroy();
        }

        const inventoryData = @json($this->inventoryData);
        const currentStock = inventoryData.current_stock || 0;
        const minimumStock = inventoryData.minimum_stock || 0;
        const stockValue = inventoryData.stock_value || 0;

        inventoryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Current Stock', 'Minimum Required', 'Available Above Minimum'],
                datasets: [{
                    data: [currentStock, minimumStock, Math.max(0, currentStock - minimumStock)],
                    backgroundColor: [
                        'rgb(59, 130, 246)',
                        'rgb(239, 68, 68)',
                        'rgb(34, 197, 94)'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                return label + ': ' + value.toFixed(2) + ' kg';
                            }
                        }
                    }
                }
            }
        });
    }

    // Function to initialize Cashier Performance Chart
    function initCashierChart() {
        const ctx = document.getElementById('cashierChart');
        if (!ctx) return;

        if (cashierChart) {
            cashierChart.destroy();
        }

        const cashierData = @json($this->cashierPerformance);
        const labels = cashierData.map(item => item.name);
        const salesData = cashierData.map(item => item.total_sales);
        const transactionData = cashierData.map(item => item.transactions);

        cashierChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Sales (₦)',
                    data: salesData,
                    backgroundColor: 'rgba(59, 130, 246, 0.8)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Transactions',
                    data: transactionData,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Cashier'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Sales (₦)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Transactions'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    }
                }
            }
        });
    }

    // Initialize charts based on current report type
    const currentReportType = '{{ $reportType }}';
    
    if (currentReportType === 'sales') {
        initDailySalesChart();
    } else if (currentReportType === 'inventory') {
        initInventoryChart();
    } else if (currentReportType === 'cashier') {
        initCashierChart();
    }

    // Listen for Livewire events to reinitialize charts when data changes
    window.addEventListener('livewire:updated', function() {
        setTimeout(() => {
            const newReportType = document.querySelector('[wire\\:model\\.live="reportType"]')?.value || '{{ $reportType }}';
            
            if (newReportType === 'sales') {
                initDailySalesChart();
            } else if (newReportType === 'inventory') {
                initInventoryChart();
            } else if (newReportType === 'cashier') {
                initCashierChart();
            }
        }, 100);
    });
});
</script>
