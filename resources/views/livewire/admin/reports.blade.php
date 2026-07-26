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

<div class="flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                    <span class="h-2 w-2 rounded-full bg-orange-500 dark:bg-orange-400 animate-pulse"></span>
                    Business Intelligence
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Reports & Analytics</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">Generate, analyze, and export sales, inventory, and cashier reports</p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="generateReport" class="rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white/80 dark:bg-slate-800/80 px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all shadow-sm cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh Data
            </button>
            <button wire:click="exportReport" class="rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 px-4 py-2.5 text-xs font-semibold text-white transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export Report
            </button>
        </div>
    </div>

    <!-- Report Controls Card -->
    <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden transition-all duration-200">
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <!-- Report Type -->
            <div>
                <flux:select wire:model.live="reportType" label="Report Category">
                    <option value="sales">Sales Analytics</option>
                    <option value="inventory">Inventory Metrics</option>
                    <option value="cashier">Cashier Performance</option>
                </flux:select>
            </div>

            <!-- Date Range -->
            <div>
                <flux:select wire:model.live="dateRange" label="Period">
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_week">This Week</option>
                    <option value="last_week">Last Week</option>
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="custom">Custom Range</option>
                </flux:select>
            </div>

            <!-- Custom Date Range or Cashier Filter -->
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
            <div>
                <div class="rounded-xl bg-slate-100/80 dark:bg-slate-800/60 p-2.5 border border-slate-200/80 dark:border-slate-700/60 text-xs font-semibold text-slate-700 dark:text-slate-300 text-center">
                    {{ Carbon::parse($startDate)->format('M d, Y') }} &rarr; {{ Carbon::parse($endDate)->format('M d, Y') }}
                </div>
            </div>
        </div>
    </div>

    @if($reportType === 'sales')
        <!-- Sales Report -->
        <div class="space-y-6">
            <!-- Sales Summary KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <!-- Total Revenue -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Sales</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">₦{{ number_format($this->salesData['total_sales'], 2) }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                        <span>Period completed sales</span>
                    </div>
                </div>

                <!-- Total Volume -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Quantity</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ number_format($this->salesData['total_quantity'], 2) }} <span class="text-sm font-normal text-slate-500 dark:text-slate-400">kg</span></p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-lg shadow-orange-500/20 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                        <span>LPG volume dispensed</span>
                    </div>
                </div>

                <!-- Total Orders -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Transactions</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ $this->salesData['total_transactions'] }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/20 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                        <span>Total order tickets</span>
                    </div>
                </div>

                <!-- Average Sale -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Avg. Transaction</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">₦{{ number_format($this->salesData['average_transaction'], 2) }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white shadow-lg shadow-amber-500/20 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                        <span>Average ticket value</span>
                    </div>
                </div>
            </div>

            <!-- Transaction Status & Payment Method Breakdown Cards -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden">
                    <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-white tracking-tight">Transaction Status</h3>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center py-2 border-b border-slate-200/60 dark:border-slate-800/60">
                            <span class="text-slate-600 dark:text-slate-400">Completed:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $this->salesData['completed_transactions'] }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-200/60 dark:border-slate-800/60">
                            <span class="text-slate-600 dark:text-slate-400">Cancelled:</span>
                            <span class="font-bold text-red-600 dark:text-red-400">{{ $this->salesData['cancelled_transactions'] }}</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-slate-600 dark:text-slate-400">Refunded:</span>
                            <span class="font-bold text-amber-600 dark:text-amber-400">{{ $this->salesData['refunded_transactions'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden">
                    <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-white tracking-tight">Payment Methods</h3>
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center py-2 border-b border-slate-200/60 dark:border-slate-800/60">
                            <span class="text-slate-600 dark:text-slate-400">Cash:</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-400">{{ $this->salesData['cash_payments'] }} (₦{{ number_format($this->salesData['cash_amount'], 2) }})</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-slate-200/60 dark:border-slate-800/60">
                            <span class="text-slate-600 dark:text-slate-400">Card:</span>
                            <span class="font-bold text-blue-600 dark:text-blue-400">{{ $this->salesData['card_payments'] }} (₦{{ number_format($this->salesData['card_amount'], 2) }})</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-slate-600 dark:text-slate-400">Transfer:</span>
                            <span class="font-bold text-purple-600 dark:text-purple-400">{{ $this->salesData['transfer_payments'] }} (₦{{ number_format($this->salesData['transfer_amount'], 2) }})</span>
                        </div>
                    </div>
                </div>

                <!-- Daily Sales Chart Card -->
                <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden lg:col-span-1">
                    <h3 class="mb-4 text-base font-bold text-slate-900 dark:text-white tracking-tight">Daily Sales Trend</h3>
                    <div class="h-52">
                        <canvas id="dailySalesChart" wire:ignore></canvas>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($reportType === 'inventory')
        <!-- Inventory Report -->
        <div class="space-y-6">
            <!-- Inventory Summary Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
                <!-- Current Stock -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Current Stock</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ number_format($this->inventoryData['current_stock'], 2) }} <span class="text-sm font-normal text-slate-500 dark:text-slate-400">kg</span></p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-lg shadow-orange-500/20 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Stock Value -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Stock Value</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">₦{{ number_format($this->inventoryData['stock_value'], 2) }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Minimum Stock -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Minimum Stock</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ number_format($this->inventoryData['minimum_stock'], 2) }} <span class="text-sm font-normal text-slate-500 dark:text-slate-400">kg</span></p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white shadow-lg shadow-amber-500/20 group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</p>
                            <p class="text-2xl sm:text-3xl font-extrabold {{ $this->inventoryData['is_low_stock'] ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} tracking-tight">
                                {{ $this->inventoryData['is_low_stock'] ? 'Low Stock' : 'Good' }}
                            </p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $this->inventoryData['is_low_stock'] ? 'bg-gradient-to-br from-red-500 to-rose-600 shadow-red-500/20' : 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/20' }} text-white shadow-lg group-hover:scale-110 transition-transform">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Level Progress Card -->
            <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden">
                <h3 class="mb-3 text-lg font-bold text-slate-900 dark:text-white tracking-tight">Stock Level Capacity Overview</h3>
                <div class="space-y-3">
                    <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-slate-400">
                        <span>Current: <strong class="text-slate-900 dark:text-white">{{ number_format($this->inventoryData['current_stock'], 2) }} kg</strong></span>
                        <span>Minimum Required: <strong class="text-slate-900 dark:text-white">{{ number_format($this->inventoryData['minimum_stock'], 2) }} kg</strong></span>
                    </div>
                    <div class="w-full bg-slate-200/80 dark:bg-slate-800/80 rounded-full h-3 overflow-hidden p-0.5 border border-slate-200 dark:border-slate-700/50">
                        <div class="h-full rounded-full transition-all duration-500 {{ $this->inventoryData['is_low_stock'] ? 'bg-gradient-to-r from-red-500 to-rose-600' : 'bg-gradient-to-r from-orange-500 via-amber-500 to-emerald-500' }}" 
                             style="width: {{ min(100, $this->inventoryData['stock_percentage']) }}%"></div>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Operating at <strong class="text-slate-800 dark:text-slate-200">{{ number_format($this->inventoryData['stock_percentage'], 1) }}%</strong> of minimum stock level
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($reportType === 'cashier')
        <!-- Cashier Performance Report -->
        <div class="space-y-6">
            <!-- Cashier Performance Table Card -->
            <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
                <div class="border-b border-slate-200/80 dark:border-slate-800/80 px-6 py-5 bg-slate-50/60 dark:bg-slate-950/40">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">Cashier Performance Ledger</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sales revenue and quantity volume breakdown per cashier</p>
                </div>

                <div class="p-0">
                    @if($this->cashierPerformance->count() > 0)
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-200/80 dark:border-slate-800/60 bg-slate-100/40 dark:bg-slate-950/20 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                        <th class="px-6 py-4">Cashier</th>
                                        <th class="px-6 py-4">Total Sales</th>
                                        <th class="px-6 py-4">Quantity Sold</th>
                                        <th class="px-6 py-4">Transactions</th>
                                        <th class="px-6 py-4">Avg. Transaction</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 text-sm">
                                    @foreach($this->cashierPerformance as $cashier)
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="font-bold text-slate-900 dark:text-white">{{ $cashier['name'] }}</div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $cashier['email'] }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="font-extrabold text-emerald-600 dark:text-emerald-400">₦{{ number_format($cashier['total_sales'], 2) }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="font-semibold text-slate-900 dark:text-white">{{ number_format($cashier['total_quantity'], 2) }} kg</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="font-medium text-slate-900 dark:text-white">{{ $cashier['transactions'] }}</div>
                                            </td>
                                            <td class="whitespace-nowrap px-6 py-4">
                                                <div class="font-semibold text-slate-800 dark:text-slate-200">₦{{ number_format($cashier['average_transaction'], 2) }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-12 px-4">
                            <div class="mx-auto h-16 w-16 rounded-full bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center text-slate-400 mb-3">
                                <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                </svg>
                            </div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">No cashier data recorded</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Cashier performance metrics for this period will appear here.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <!-- Flash Messages -->
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

    function initDailySalesChart() {
        const ctx = document.getElementById('dailySalesChart');
        if (!ctx) return;
        if (dailySalesChart) dailySalesChart.destroy();

        const dailyData = @json($this->dailySales);
        const labels = dailyData.map(item => item.date);
        const salesData = dailyData.map(item => item.sales);

        dailySalesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Sales (₦)',
                    data: salesData,
                    borderColor: '#f97316',
                    backgroundColor: 'rgba(249, 115, 22, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: 'rgba(148, 163, 184, 0.1)' } }
                }
            }
        });
    }

    const currentReportType = '{{ $reportType }}';
    if (currentReportType === 'sales') {
        initDailySalesChart();
    }

    window.addEventListener('livewire:updated', function() {
        setTimeout(() => {
            initDailySalesChart();
        }, 100);
    });
});
</script>
