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

            // Update transaction status
            $transaction->update(['status' => $status]);

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

        $callback = function() use ($transactions) {
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

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">All Transactions</h1>
            <p class="text-gray-600 dark:text-gray-400">View and manage all gas sales transactions</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 sm:flex-shrink-0">
            <flux:button wire:click="clearFilters" variant="outline" class="w-full sm:w-auto">
                Clear Filters
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
            <!-- Search -->
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    label="Search"
                    placeholder="Search transactions..."
                    icon="magnifying-glass"
                />
            </div>

            <!-- Date Filter -->
            <div>
                <flux:select
                    wire:model.live="dateFilter"
                    label="Date Filter"
                >
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
                <flux:select
                    wire:model.live="statusFilter"
                    label="Status"
                >
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="refunded">Refunded</option>
                </flux:select>
            </div>

            <!-- Cashier Filter -->
            <div>
                <flux:select
                    wire:model.live="cashierFilter"
                    label="Cashier"
                >
                    <option value="">All Cashiers</option>
                    @foreach($this->cashiers as $cashier)
                        <option value="{{ $cashier->id }}">{{ $cashier->name }}</option>
                    @endforeach
                </flux:select>
            </div>

            <!-- Export -->
            <div class="flex items-end">
                <flux:button wire:click="exportTransactions" variant="outline" class="w-full">
                    Export
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
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
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($this->totalSales, 2) }}</p>
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
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($this->totalQuantity, 2) }} kg</p>
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
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->transactions->total() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900">
                        <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Cashiers</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->cashiers->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Transactions</h3>
        </div>
        <div class="p-6">
            @if($this->transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Transaction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Cashier</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Discount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Payment</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($this->transactions as $transaction)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $transaction->transaction_number }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $transaction->cashier->name }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $transaction->cashier->email }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $transaction->customer_name ?: 'Walk-in Customer' }}</div>
                                        @if($transaction->customer_phone)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $transaction->customer_phone }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $transaction->formatted_quantity }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $transaction->formatted_total }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $transaction->formatted_price_per_kg }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $transaction->customerDiscount->name ?? 'N/A' }}</div>
                                        @if($transaction->customerDiscount && $transaction->customerDiscount->discount_per_kg > 0)
                                            <div class="text-xs text-red-600 dark:text-red-400">-{{ $transaction->customerDiscount->formatted_discount }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                            {{ $transaction->payment_type === 'cash' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                               ($transaction->payment_type === 'card' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                                'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200') }}">
                                            {{ ucfirst($transaction->payment_type) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                            {{ $transaction->status === 'completed' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                               ($transaction->status === 'cancelled' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                                'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200') }}">
                                            {{ ucfirst($transaction->status) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $transaction->created_at->format('M d, Y H:i') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex gap-2">
                                            <flux:button 
                                                wire:click="viewTransaction({{ $transaction->id }})" 
                                                variant="outline" 
                                                size="sm"
                                            >
                                                View
                                            </flux:button>
                                            @if($transaction->status === 'completed')
                                                <flux:dropdown>
                                                    <flux:button variant="outline" size="sm">
                                                        Actions
                                                    </flux:button>
                                                    <flux:menu>
                                                        <flux:menu.item wire:click="updateTransactionStatus({{ $transaction->id }}, 'cancelled')">
                                                            Cancel Transaction
                                                        </flux:menu.item>
                                                        <flux:menu.item wire:click="updateTransactionStatus({{ $transaction->id }}, 'refunded')">
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
                <div class="mt-6">
                    {{ $this->transactions->links() }}
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No transactions found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Transaction Detail Modal -->
    @if($showTransactionModal && $selectedTransaction)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-2xl rounded-xl bg-white p-6 dark:bg-gray-800">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Transaction Details</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $selectedTransaction->transaction_number }}</p>
                    </div>
                    <flux:button wire:click="closeTransactionModal" variant="outline" size="sm">
                        Close
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Transaction Info -->
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Transaction Information</h4>
                            <div class="mt-2 space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Status:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($selectedTransaction->status) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Date:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->created_at->format('M d, Y H:i') }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Quantity:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->formatted_quantity }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Original Price:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->formatted_price_per_kg }}</span>
                                </div>
                                @if($selectedTransaction->customerDiscount && $selectedTransaction->customerDiscount->discount_per_kg > 0)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Discount ({{ $selectedTransaction->customerDiscount->name }}):</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">-{{ $selectedTransaction->customerDiscount->formatted_discount }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Effective Price:</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->formatted_effective_price_per_kg }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Total Amount:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->formatted_total }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Payment Type:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($selectedTransaction->payment_type) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customer & Cashier Info -->
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Customer Information</h4>
                            <div class="mt-2 space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Name:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->customer_name ?: 'Walk-in Customer' }}</span>
                                </div>
                                @if($selectedTransaction->customer_phone)
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Phone:</span>
                                        <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->customer_phone }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Cashier Information</h4>
                            <div class="mt-2 space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Name:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->cashier->name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Email:</span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $selectedTransaction->cashier->email }}</span>
                                </div>
                            </div>
                        </div>

                        @if($selectedTransaction->notes)
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-white">Notes</h4>
                                <div class="mt-2 text-sm text-gray-900 dark:text-white">
                                    {{ $selectedTransaction->notes }}
                                </div>
                            </div>
                        @endif
                    </div>
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
