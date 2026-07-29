<?php

use App\Models\Transaction;
use App\Models\User;
use App\Models\Inventory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $dateFilter = '';
    public $statusFilter = '';
    public $cashierFilter = '';
    public $showTransactionModal = false;
    public $selectedTransaction = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedCashierFilter()
    {
        $this->resetPage();
    }

    public function getTransactionsProperty()
    {
        $query = Transaction::with(['cashier', 'customerDiscount']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('transaction_number', 'like', '%' . $this->search . '%')
                    ->orWhere('customer_name', 'like', '%' . $this->search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->dateFilter) {
            switch ($this->dateFilter) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', today()->subDay());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', now()->subMonth()->month)
                        ->whereYear('created_at', now()->subMonth()->year);
                    break;
            }
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->cashierFilter) {
            $query->where('cashier_id', $this->cashierFilter);
        }

        return $query->latest()->paginate(15);
    }

    public function getTotalSalesProperty()
    {
        return $this->transactions->where('status', 'completed')->sum('total_amount');
    }

    public function getTotalQuantityProperty()
    {
        return $this->transactions->where('status', 'completed')->sum('quantity_kg');
    }

    public function getCashiersProperty()
    {
        return User::where('role', 'cashier')->where('is_active', true)->get();
    }

    public function viewTransaction($transactionId)
    {
        $this->selectedTransaction = Transaction::with('cashier')->find($transactionId);
        $this->showTransactionModal = true;
    }

    public function closeTransactionModal()
    {
        $this->showTransactionModal = false;
        $this->selectedTransaction = null;
    }

    public function updateTransactionStatus($transactionId, $status)
    {
        $transaction = Transaction::find($transactionId);
        if (!$transaction) {
            return;
        }

        // Only allow status changes for completed transactions
        if ($transaction->status !== 'completed') {
            return;
        }

        try {
            DB::beginTransaction();

            $inventory = Inventory::first();
            $previousStatus = $transaction->status;

            $updateData = ['status' => $status];
            if ($status === 'cancelled') {
                $updateData['cancellation_reason'] = $transaction->cancellation_reason ?: 'Cancelled by Admin';
                $updateData['cancelled_at'] = $transaction->cancelled_at ?: now();
            }

            // Update transaction status
            $transaction->update($updateData);

            // Handle inventory restoration for cancelled/refunded transactions
            if (in_array($status, ['cancelled', 'refunded'])) {
                // Add the sold quantity back to inventory
                $inventory->addStock(
                    $transaction->quantity_kg,
                    ucfirst($status) . ' transaction: ' . $transaction->transaction_number,
                    Auth::user(),
                    "Transaction {$status} by admin. Previous status: {$previousStatus}"
                );
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            logger()->error('Failed to update transaction status', [
                'transaction_id' => $transactionId,
                'new_status' => $status,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'dateFilter', 'statusFilter', 'cashierFilter']);
        $this->resetPage();
    }

    public function exportTransactions()
    {
        $query = Transaction::with(['cashier', 'customerDiscount']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('transaction_number', 'like', '%' . $this->search . '%')
                    ->orWhere('customer_name', 'like', '%' . $this->search . '%')
                    ->orWhere('customer_phone', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->dateFilter) {
            switch ($this->dateFilter) {
                case 'today':
                    $query->whereDate('created_at', today());
                    break;
                case 'yesterday':
                    $query->whereDate('created_at', today()->subDay());
                    break;
                case 'this_week':
                    $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('created_at', now()->subMonth()->month)
                        ->whereYear('created_at', now()->subMonth()->year);
                    break;
            }
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->cashierFilter) {
            $query->where('cashier_id', $this->cashierFilter);
        }

        $transactions = $query->latest()->get();

        $filename = 'transactions_' . now()->format('Y-m-d_H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // Add headers
            fputcsv($file, [
                'Transaction Number',
                'Date',
                'Cashier',
                'Customer Name',
                'Customer Phone',
                'Quantity (kg)',
                'Price per kg',
                'Total Amount',
                'Payment Type',
                'Status',
                'Notes'
            ]);

            // Add data
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->transaction_number,
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->cashier->name,
                    $transaction->customer_name ?: 'Walk-in Customer',
                    $transaction->customer_phone ?: '',
                    $transaction->quantity_kg,
                    $transaction->price_per_kg,
                    $transaction->total_amount,
                    ucfirst($transaction->payment_type),
                    ucfirst($transaction->status),
                    $transaction->notes ?: ''
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                    <span class="h-2 w-2 rounded-full bg-orange-500 dark:bg-orange-400 animate-pulse"></span>
                    Sales Ledger
                </span>
            </div>
            <h1 class="text-xl sm:text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">All Transactions
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">View, audit, export, and manage all sales records
                across registers</p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="clearFilters"
                class="rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white/80 dark:bg-slate-800/80 px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all shadow-sm cursor-pointer">
                Clear Filters
            </button>
            <button wire:click="exportTransactions"
                class="rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 px-4 py-2.5 text-xs font-semibold text-white transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Export CSV
            </button>
        </div>
    </div>

    <!-- Filters Bar Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden transition-all duration-200">
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Search -->
            <div>
                <flux:input wire:model.live.debounce.300ms="search" label="Search"
                    placeholder="Search receipt # or customer..." icon="magnifying-glass" class="w-full" />
            </div>

            <!-- Date Filter -->
            <div>
                <flux:select wire:model.live="dateFilter" label="Date Filter">
                    <option value="">All Dates</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="this_week">This Week</option>
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                </flux:select>
            </div>

            <!-- Status Filter -->
            <div>
                <flux:select wire:model.live="statusFilter" label="Status">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="refunded">Refunded</option>
                </flux:select>
            </div>

            <!-- Cashier Filter -->
            <div>
                <flux:select wire:model.live="cashierFilter" label="Cashier">
                    <option value="">All Cashiers</option>
                    @foreach($this->cashiers as $cashier)
                        <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Summary KPI Stat Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Sales -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total
                        Sales</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        ₦{{ number_format($this->totalSales, 2) }}</p>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                        </path>
                    </svg>
                </div>
            </div>
            <div
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Completed sales total</span>
            </div>
        </div>

        <!-- Total Quantity -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total
                        Quantity</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ number_format($this->totalQuantity, 2) }} <span
                            class="text-sm font-normal text-slate-500 dark:text-slate-400">kg</span></p>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-lg shadow-orange-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <div
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Total volume dispensed</span>
            </div>
        </div>

        <!-- Transactions Count -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Transactions</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ $this->transactions->total() }}</p>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                </div>
            </div>
            <div
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Matching query count</span>
            </div>
        </div>

        <!-- Active Cashiers -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active
                        Cashiers</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ $this->cashiers->count() }}</p>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white shadow-lg shadow-amber-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                        </path>
                    </svg>
                </div>
            </div>
            <div
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Registered cashiers</span>
            </div>
        </div>
    </div>

    <!-- Transactions Data Table Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
        <div
            class="border-b border-slate-200/80 dark:border-slate-800/80 px-6 py-5 bg-slate-50/60 dark:bg-slate-950/40">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">Master Sales Transactions</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">System audit log of all completed, cancelled, and
                refunded sales</p>
        </div>

        <div class="p-0">
            @if($this->transactions->count() > 0)
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200/80 dark:border-slate-800/60 bg-slate-100/40 dark:bg-slate-950/20 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Receipt #</th>
                                <th class="px-6 py-4">Cashier</th>
                                <th class="px-6 py-4">Customer</th>
                                <th class="px-6 py-4">Quantity</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">Discount</th>
                                <th class="px-6 py-4">Payment</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Date & Time</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 text-sm">
                            @foreach($this->transactions as $transaction)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                                <td class="whitespace-nowrap px-6 py-4 font-mono">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                                        {{ $transaction->transaction_number }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <div class="font-medium text-slate-900 dark:text-white">
                                                        {{ $transaction->cashier->name }}</div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $transaction->cashier->email }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <div class="font-medium text-slate-900 dark:text-white">
                                                        {{ $transaction->customer_name ?: 'Walk-in Customer' }}</div>
                                                    @if($transaction->customer_phone)
                                                        <div class="text-xs text-slate-500 dark:text-slate-400">
                                                            {{ $transaction->customer_phone }}</div>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <span class="inline-flex items-center gap-1 text-slate-700 dark:text-slate-300">
                                                        {{ $transaction->formatted_quantity }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <div class="font-bold text-emerald-600 dark:text-emerald-400">
                                                        {{ $transaction->formatted_total }}</div>
                                                    <div class="text-xs text-slate-500 dark:text-slate-400">
                                                        {{ $transaction->formatted_price_per_kg }}</div>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4 text-xs">
                                                    <div class="font-medium text-slate-900 dark:text-white">
                                                        {{ $transaction->customerDiscount->name ?? 'None' }}</div>
                                                    @if($transaction->customerDiscount && $transaction->customerDiscount->discount_per_kg > 0)
                                                        <div class="text-red-600 dark:text-red-400 font-semibold">
                                                            -{{ $transaction->customerDiscount->formatted_discount }}</div>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <span
                                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
                                                                {{ $transaction->payment_type === 'cash' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' :
                                ($transaction->payment_type === 'card' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' :
                                    'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20') }}">
                                                        {{ ucfirst($transaction->payment_type) }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <span
                                                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
                                                                {{ $transaction->status === 'completed' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' :
                                ($transaction->status === 'cancelled' ? 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20' :
                                    'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20') }}">
                                                        {{ ucfirst($transaction->status) }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $transaction->created_at->format('M d, Y • H:i') }}
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                                    <div class="flex items-center justify-end gap-2">
                                                        <button wire:click="viewTransaction({{ $transaction->id }})"
                                                            class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                                            View
                                                        </button>
                                                        @if($transaction->status === 'completed')
                                                            <flux:dropdown>
                                                                <flux:button variant="outline" size="sm" class="rounded-xl">
                                                                    Actions
                                                                </flux:button>
                                                                <flux:menu>
                                                                    <flux:menu.item
                                                                        wire:click="updateTransactionStatus({{ $transaction->id }}, 'cancelled')">
                                                                        Cancel Transaction
                                                                    </flux:menu.item>
                                                                    <flux:menu.item
                                                                        wire:click="updateTransactionStatus({{ $transaction->id }}, 'refunded')">
                                                                        Mark as Refunded
                                                                    </flux:menu.item>
                                                                </flux:menu>
                                                            </flux:dropdown>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-6 border-t border-slate-200/80 dark:border-slate-800/80">
                    {{ $this->transactions->links() }}
                </div>
            @else
                <div class="text-center py-12 px-4">
                    <div
                        class="mx-auto h-16 w-16 rounded-full bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center text-slate-400 mb-3">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">No transactions found</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Try adjusting search query or cashier
                        filters.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Transaction Detail Modal -->
    @if($showTransactionModal && $selectedTransaction)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div
                class="relative w-full max-w-2xl rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600">
                </div>

                <div class="mb-5 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Transaction Details</h3>
                        <p class="text-xs font-mono text-orange-600 dark:text-orange-400 mt-0.5">
                            {{ $selectedTransaction->transaction_number }}</p>
                    </div>
                    <button wire:click="closeTransactionModal"
                        class="rounded-xl border border-slate-300 dark:border-slate-700 px-3.5 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                        Close
                    </button>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Transaction Info -->
                    <div class="space-y-4">
                        <div>
                            <h4
                                class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                                Sale Details</h4>
                            <div
                                class="space-y-2 text-xs bg-slate-100/80 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/50">
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Status:</span>
                                    <span
                                        class="font-bold {{ $selectedTransaction->status === 'cancelled' ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">{{ ucfirst($selectedTransaction->status) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Date:</span>
                                    <span
                                        class="font-medium text-slate-900 dark:text-white">{{ $selectedTransaction->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Quantity:</span>
                                    <span
                                        class="font-semibold text-slate-900 dark:text-white">{{ $selectedTransaction->formatted_quantity }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500 dark:text-slate-400">Original Price:</span>
                                    <span
                                        class="font-medium text-slate-800 dark:text-slate-200">{{ $selectedTransaction->formatted_price_per_kg }}</span>
                                </div>
                                @if($selectedTransaction->customerDiscount && $selectedTransaction->customerDiscount->discount_per_kg > 0)
                                    <div class="flex justify-between">
                                        <span class="text-slate-500 dark:text-slate-400">Discount
                                            ({{ $selectedTransaction->customerDiscount->name }}):</span>
                                        <span
                                            class="font-semibold text-red-600 dark:text-red-400">-{{ $selectedTransaction->customerDiscount->formatted_discount }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-slate-500 dark:text-slate-400">Effective Price:</span>
                                        <span
                                            class="font-semibold text-slate-900 dark:text-white">{{ $selectedTransaction->formatted_effective_price_per_kg }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-700">
                                    <span class="text-sm font-bold text-slate-900 dark:text-white">Total Amount:</span>
                                    <span
                                        class="text-sm font-extrabold text-emerald-600 dark:text-emerald-400">{{ $selectedTransaction->formatted_total }}</span>
                                </div>
                            </div>
                        </div>

                        @if($selectedTransaction->status === 'cancelled')
                            <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 text-xs">
                                <h4 class="font-semibold text-red-600 dark:text-red-400 flex items-center gap-1.5">
                                    <svg class="h-4 w-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                        </path>
                                    </svg>
                                    Cancellation Details
                                </h4>
                                <div class="mt-2 space-y-1 text-red-700 dark:text-red-300">
                                    <p class="font-medium">
                                        {{ $selectedTransaction->cancellation_reason ?: 'No reason provided' }}</p>
                                    @if($selectedTransaction->cancelled_at)
                                        <p class="text-[11px] text-red-500 dark:text-red-400">Cancelled:
                                            {{ $selectedTransaction->cancelled_at->format('M d, Y H:i:s') }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Customer & Cashier Info -->
                    <div class="space-y-4">
                        <div>
                            <h4
                                class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                                Customer & Cashier</h4>
                            <div
                                class="space-y-3 text-xs bg-slate-100/80 dark:bg-slate-800/50 p-4 rounded-2xl border border-slate-200/80 dark:border-slate-700/50">
                                <div>
                                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">Customer Name:</span>
                                    <span
                                        class="font-bold text-slate-900 dark:text-white">{{ $selectedTransaction->customer_name ?: 'Walk-in Customer' }}</span>
                                    @if($selectedTransaction->customer_phone)
                                        <span
                                            class="text-slate-500 dark:text-slate-400 block">{{ $selectedTransaction->customer_phone }}</span>
                                    @endif
                                </div>
                                <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">Cashier:</span>
                                    <span
                                        class="font-bold text-slate-900 dark:text-white">{{ $selectedTransaction->cashier->name }}</span>
                                    <span
                                        class="text-slate-500 dark:text-slate-400 block">{{ $selectedTransaction->cashier->email }}</span>
                                </div>
                            </div>
                        </div>

                        @if($selectedTransaction->notes)
                            <div>
                                <h4
                                    class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                                    Notes</h4>
                                <div
                                    class="text-xs text-slate-700 dark:text-slate-300 bg-slate-100/80 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-200 dark:border-slate-700/50">
                                    {{ $selectedTransaction->notes }}
                                </div>
                            </div>
                        @endif
                    </div>
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