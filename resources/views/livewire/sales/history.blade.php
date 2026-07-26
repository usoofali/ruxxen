<?php

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';
    public $dateFilter = '';
    public $statusFilter = '';

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

    public function getTransactionsProperty()
    {
        $query = Transaction::where('cashier_id', Auth::id())
            ->with(['cashier', 'customerDiscount']);

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
            }
        }

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
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

    public $showReceipt = false;
    public $currentTransaction = null;

    public function openReceipt($transactionId)
    {
        $transaction = Transaction::find($transactionId);
        if (!$transaction) {
            return;
        }

        $this->currentTransaction = $transaction;
        $this->showReceipt = true;
    }

    public function closeReceipt()
    {
        $this->showReceipt = false;
        $this->currentTransaction = null;
    }

    private function generateReceiptHtml($transaction): string
    {
        $companyName = \App\Services\CompanySettingsService::getCompanyName();
        $companyAddress = \App\Services\CompanySettingsService::getCompanyAddress();
        $companyPhone = \App\Services\CompanySettingsService::getCompanyPhone();
        $companyLogo = \App\Services\CompanySettingsService::getCompanyLogoUrl();
        
        $customerName = $transaction->customer_name ?: 'Walk-in Customer';
        $customerPhone = $transaction->customer_phone ?: '';
        
        $logoHtml = $companyLogo ? "<img src='$companyLogo' style='width: 160px; height: 160px; object-fit: contain; margin: 0 auto 15px; display: block;' alt='Company Logo'>" : "";
        
        return "
        <div style='font-family: monospace; width: 56mm; max-width: 56mm; margin: 0 auto;'>
            <!-- Header -->
            <div style='text-align: center; margin-bottom: 15px;'>
                $logoHtml
                <h1 style='font-size: 16px; font-weight: bold; margin: 0;'>$companyName</h1>
                <p style='font-size: 12px; margin: 3px 0;'>$companyAddress</p>
                <p style='font-size: 12px; margin: 3px 0;'>$companyPhone</p>
            </div>
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Transaction Info -->
            <div style='margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Receipt #:</span>
                    <span>{$transaction->transaction_number}</span>
                </div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Date:</span>
                    <span>{$transaction->created_at->format('M d, Y H:i')}</span>
                </div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Cashier:</span>
                    <span>{$transaction->cashier->name}</span>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div style='margin-bottom: 12px;'>
                <div style='font-size: 12px; font-weight: bold; margin-bottom: 6px;'>CUSTOMER:</div>
                <div style='font-size: 12px;'>$customerName</div>
                " . ($customerPhone ? "<div style='font-size: 12px;'>$customerPhone</div>" : "") . "
            </div>
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Items -->
            <div style='margin-bottom: 12px;'>
                <div style='font-size: 12px; font-weight: bold; margin-bottom: 6px;'>ITEMS:</div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;  font-weight: bold;'>
                    <span>LPG Gas</span>
                    <span>{$transaction->formatted_quantity}</span>
                </div>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>@ {$transaction->formatted_price_per_kg}</span>
                    <span></span>
                </div>
            </div>
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Payment Info -->
            <div style='margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; font-size: 12px;'>
                    <span>Payment Method:</span>
                    <span>" . ucfirst($transaction->payment_type) . "</span>
                </div>
            </div>
            
            <!-- Total -->
            <div style='margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; font-size: 14px; font-weight: bold;'>
                    <span>TOTAL:</span>
                    <span>{$transaction->formatted_total}</span>
                </div>
            </div>
            
            " . ($transaction->notes ? "
            <!-- Notes -->
            <div style='margin-bottom: 12px;'>
                <div style='font-size: 12px; font-weight: bold; margin-bottom: 6px;'>NOTES:</div>
                <div style='font-size: 12px;'>{$transaction->notes}</div>
            </div>
            " : "") . "
            
            <!-- Divider -->
            <div style='border-top: 1px dashed #000; margin: 10px 0;'></div>
            
            <!-- Footer -->
            <div style='text-align: center; margin-top: 15px;'>
                <p style='font-size: 12px; margin: 3px 0;'>Thank you for your purchase!</p>
                <p style='font-size: 10px; margin: 3px 0;'>Please keep this receipt for your records</p>
                <p style='font-size: 10px; margin: 3px 0;'>For inquiries: $companyPhone</p>
            </div>
        </div>
        ";
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                    <span class="h-2 w-2 rounded-full bg-orange-500 dark:bg-orange-400 animate-pulse"></span>
                    Receipts & History
                </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Sales History</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">View, search, and reprint past transaction receipts</p>
        </div>
    </div>

    <!-- Filters Bar Card -->
    <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden transition-all duration-200">
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Search -->
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    label="Search"
                    placeholder="Search receipt # or customer..."
                    icon="magnifying-glass"
                    class="w-full"
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

            <!-- Clear Filters -->
            <div class="flex items-end">
                <flux:button
                    wire:click="$set('search', ''); $set('dateFilter', ''); $set('statusFilter', '')"
                    variant="outline"
                    class="w-full rounded-xl border-slate-200/80 dark:border-slate-700/80 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                >
                    Clear Filters
                </flux:button>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
        <!-- Total Sales -->
        <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Filtered Sales</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">₦{{ number_format($this->totalSales, 2) }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-lg shadow-emerald-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Total revenue for selection</span>
            </div>
        </div>

        <!-- Total Quantity -->
        <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Quantity</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ number_format($this->totalQuantity, 2) }} <span class="text-base font-normal text-slate-500 dark:text-slate-400">kg</span></p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-lg shadow-orange-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Total LPG gas volume sold</span>
            </div>
        </div>

        <!-- Transactions Count -->
        <div class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Transactions Count</p>
                    <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ $this->transactions->total() }}</p>
                </div>
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Matching receipts count</span>
            </div>
        </div>
    </div>

    <!-- Transactions Data Table Card -->
    <div class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
        <div class="border-b border-slate-200/80 dark:border-slate-800/80 px-6 py-5 bg-slate-50/60 dark:bg-slate-950/40">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">Transaction Log Receipts</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Detailed list of sales completed at your register</p>
        </div>

        <div class="p-0">
            @if($this->transactions->count() > 0)
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200/80 dark:border-slate-800/60 bg-slate-100/40 dark:bg-slate-950/20 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Receipt #</th>
                                <th class="px-6 py-4">Customer</th>
                                <th class="px-6 py-4">Quantity</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">Payment Method</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Date & Time</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 text-sm">
                            @foreach($this->transactions as $transaction)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="whitespace-nowrap px-6 py-4 font-mono">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                            {{ $transaction->transaction_number }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $transaction->customer_name ?: 'Walk-in Customer' }}</div>
                                        @if($transaction->customer_phone)
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $transaction->customer_phone }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center gap-1 text-slate-700 dark:text-slate-300">
                                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m0 0l-3 9m3-9l3 2m-6 2l6-2m0 0l-3 9m3-9l3 2m-6 2l3 9"/></svg>
                                            {{ $transaction->formatted_quantity }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">{{ $transaction->formatted_total }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
                                            {{ $transaction->payment_type === 'cash' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 
                                               ($transaction->payment_type === 'card' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 
                                                'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20') }}">
                                            {{ ucfirst($transaction->payment_type) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
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
                                        <button 
                                            wire:click="openReceipt({{ $transaction->id }})"
                                            class="inline-flex items-center justify-center h-9 w-9 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20 hover:bg-orange-500 hover:text-white dark:hover:bg-orange-500 dark:hover:text-white transition-all shadow-sm cursor-pointer"
                                            title="View & Print Receipt"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                            </svg>
                                        </button>
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
                    <div class="mx-auto h-16 w-16 rounded-full bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center text-slate-400 mb-3">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">No transactions found</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Try adjusting your search query or date filters.</p>
                </div>
            @endif
        </div>
    </div>

    @if($showReceipt && $currentTransaction)
        <!-- Receipt Modal Container -->
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div class="relative w-full max-w-md rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>

                <!-- Company Header -->
                <div class="border-b border-slate-200/80 dark:border-slate-800/80 pb-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            @if(\App\Services\CompanySettingsService::getCompanyLogoUrl())
                                <img src="{{ \App\Services\CompanySettingsService::getCompanyLogoUrl() }}" 
                                     alt="Company Logo" 
                                     class="h-12 w-12 rounded-xl object-contain bg-slate-100 dark:bg-slate-800 p-1 border border-slate-200 dark:border-slate-700">
                            @else
                                <div class="h-12 w-12 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center text-white shadow-md">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h2 class="text-base font-bold text-slate-900 dark:text-white">
                                    {{ \App\Services\CompanySettingsService::getCompanyName() }}
                                </h2>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ \App\Services\CompanySettingsService::getCompanyAddress() }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                {{ $currentTransaction->transaction_number }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Transaction Details -->
                <div class="py-4 space-y-4">
                    <div>
                        <div class="bg-slate-100/80 dark:bg-slate-800/50 rounded-2xl p-3.5 border border-slate-200/80 dark:border-slate-700/50 text-xs">
                            @if($currentTransaction->customer_name)
                                <div class="flex justify-between items-center">
                                    <span class="text-slate-500 dark:text-slate-400">Customer:</span>
                                    <span class="font-semibold text-slate-900 dark:text-white">{{ $currentTransaction->customer_name }}</span>
                                </div>
                                @if($currentTransaction->customer_phone)
                                    <div class="flex justify-between items-center mt-1">
                                        <span class="text-slate-500 dark:text-slate-400">Phone:</span>
                                        <span class="font-medium text-slate-700 dark:text-slate-300">{{ $currentTransaction->customer_phone }}</span>
                                    </div>
                                @endif
                            @else
                                <div class="text-center font-medium text-slate-700 dark:text-slate-300">
                                    Walk-in Customer
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800/60">
                            <span class="text-slate-500 dark:text-slate-400">Gas Quantity:</span>
                            <span class="font-semibold text-slate-900 dark:text-white">{{ $currentTransaction->formatted_quantity }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800/60">
                            <span class="text-slate-500 dark:text-slate-400">Unit Price:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ $currentTransaction->formatted_price_per_kg }}</span>
                        </div>
                        <div class="flex justify-between py-1 border-b border-slate-200/60 dark:border-slate-800/60">
                            <span class="text-slate-500 dark:text-slate-400">Payment Method:</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ ucfirst($currentTransaction->payment_type) }}</span>
                        </div>
                        
                        @if($currentTransaction->status === 'cancelled')
                            <div class="mt-3 rounded-2xl border border-red-500/30 bg-red-500/10 p-3 text-xs">
                                <div class="font-bold text-red-600 dark:text-red-400">CANCELLATION REASON:</div>
                                <div class="mt-1 text-red-700 dark:text-red-300 font-medium">{{ $currentTransaction->cancellation_reason ?: 'No reason provided' }}</div>
                            </div>
                        @endif

                        <div class="flex justify-between pt-2">
                            <span class="text-base font-bold text-slate-900 dark:text-white">Total Amount:</span>
                            <span class="text-base font-extrabold text-emerald-600 dark:text-emerald-400">{{ $currentTransaction->formatted_total }}</span>
                        </div>
                    </div>

                    <div class="text-center text-[11px] text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-200/80 dark:border-slate-800/80">
                        <p>Date: {{ $currentTransaction->created_at->format('M d, Y • H:i') }}</p>
                        <p>Cashier: {{ $currentTransaction->cashier->name }}</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="border-t border-slate-200/80 dark:border-slate-800/80 pt-4">
                    <div class="flex gap-3">
                        <button 
                            x-data="{ printReceipt() { 
                                const html = @js($this->generateReceiptHtml($currentTransaction));
                                const transactionId = @js($currentTransaction->id);
                                this.printDirectly(html, transactionId);
                            },
                            printDirectly(html, transactionId) {
                                try {
                                    const printFrame = document.createElement('iframe');
                                    printFrame.style.position = 'fixed';
                                    printFrame.style.right = '0';
                                    printFrame.style.bottom = '0';
                                    printFrame.style.width = '0';
                                    printFrame.style.height = '0';
                                    printFrame.style.border = '0';
                                    printFrame.style.visibility = 'hidden';
                                    
                                    document.body.appendChild(printFrame);
                                    
                                    const printContent = `<!DOCTYPE html>
<html>
<head>
    <title>Receipt - ${transactionId}</title>
    <style>
        @page { size: 56mm auto; margin: 0; }
        body { margin: 2px; padding: 10px; font-family: monospace; background: white; width: 56mm; max-width: 56mm; font-size: 12px; }
        @media print { body { width: 56mm; max-width: 56mm; } }
    </style>
</head>
<body>
    ${html}
</body>
</html>`;
                                    
                                    printFrame.contentDocument.write(printContent);
                                    printFrame.contentDocument.close();
                                    
                                    printFrame.onload = function() {
                                        setTimeout(() => {
                                            printFrame.contentWindow.print();
                                            setTimeout(() => {
                                                document.body.removeChild(printFrame);
                                            }, 1000);
                                        }, 100);
                                    };
                                } catch (error) {
                                    alert('Printing failed: ' + error.message);
                                }
                            } }"
                            @click="printReceipt()"
                            class="flex-1 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold py-2.5 shadow-lg shadow-orange-500/20 active:scale-[0.99] transition-all cursor-pointer flex items-center justify-center gap-2 text-sm"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Receipt
                        </button>

                        <button wire:click="closeReceipt" class="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-sm font-medium cursor-pointer">
                            Close
                        </button>
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