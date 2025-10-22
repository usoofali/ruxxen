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

<div>
    <!-- Main Content -->
    <div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sales History</h1>
                <p class="text-gray-600 dark:text-gray-400">View your transaction history</p>
            </div>
            <a href="{{ route('sales.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">
                New Sale
            </a>
        </div>

        <!-- Filters -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
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
                        class="w-full"
                    >
                        Clear Filters
                    </flux:button>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
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
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Customer</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Quantity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount</th>
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
                                            <button 
                                                wire:click="openReceipt({{ $transaction->id }})"
                                                class="inline-flex items-center justify-center w-8 h-8 border border-transparent text-xs leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors"
                                                title="View Receipt"
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
    </div>

    @if($showReceipt && $currentTransaction)
        <!-- Receipt Modal -->
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
            <div class="w-full max-w-lg rounded-xl bg-white p-6 dark:bg-gray-800">
                <!-- Company Header -->
                <div class="border-b border-gray-200 pb-4 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            @if(\App\Services\CompanySettingsService::getCompanyLogoUrl())
                                <img src="{{ \App\Services\CompanySettingsService::getCompanyLogoUrl() }}" 
                                     alt="Company Logo" 
                                     class="h-12 w-12 rounded-lg object-cover">
                            @else
                                <div class="h-12 w-12 rounded-lg bg-blue-100 dark:bg-blue-900 flex items-center justify-center">
                                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                </div>
                            @endif
                            <div>
                                <h2 class="text-lg font-bold text-gray-900 dark:text-white">
                                    {{ \App\Services\CompanySettingsService::getCompanyName() }}
                                </h2>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ \App\Services\CompanySettingsService::getCompanyAddress() }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ \App\Services\CompanySettingsService::getCompanyPhone() }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-500 dark:text-gray-400">Transaction #</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $currentTransaction->transaction_number }}</p>
                        </div>
                    </div>
                </div>

                <!-- Transaction Details -->
                <div class="py-4">
                    <div class="mb-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-2">Customer Information</h3>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-3">
                            @if($currentTransaction->customer_name)
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Name:</span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $currentTransaction->customer_name }}</span>
                                </div>
                                @if($currentTransaction->customer_phone)
                                    <div class="flex justify-between items-center mt-1">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Phone:</span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $currentTransaction->customer_phone }}</span>
                                    </div>
                                @endif
                            @else
                                <div class="text-center">
                                    <span class="text-sm font-medium text-gray-900 dark:text-white">Walk-in Customer</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Quantity:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $currentTransaction->formatted_quantity }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Price per kg:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $currentTransaction->formatted_price_per_kg }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Payment Type:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ ucfirst($currentTransaction->payment_type) }}</span>
                        </div>
                        @if($currentTransaction->notes)
                            <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Notes:</span>
                                <p class="text-sm text-gray-900 dark:text-white mt-1">{{ $currentTransaction->notes }}</p>
                            </div>
                        @endif
                        <div class="flex justify-between border-t border-gray-200 pt-3 dark:border-gray-700">
                            <span class="text-lg font-medium text-gray-900 dark:text-white">Total:</span>
                            <span class="text-lg font-bold text-gray-900 dark:text-white">{{ $currentTransaction->formatted_total }}</span>
                        </div>
                    </div>

                    <div class="mt-4 text-center text-xs text-gray-500 dark:text-gray-400">
                        <p>Date: {{ $currentTransaction->created_at->format('M d, Y H:i') }}</p>
                        <p>Cashier: {{ $currentTransaction->cashier->name }}</p>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="border-t border-gray-200 pt-4 dark:border-gray-700">
                    <div class="flex gap-3">
                        <button 
                            x-data="{ printReceipt() { 
                                console.log('Alpine.js print function called');
                                const html = @js($this->generateReceiptHtml($currentTransaction));
                                const transactionId = @js($currentTransaction->id);
                                this.printDirectly(html, transactionId);
                            },
                            printDirectly(html, transactionId) {
                                try {
                                    console.log('Creating iframe for printing...');
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
                                    console.error('Print error:', error);
                                    alert('Printing failed: ' + error.message);
                                }
                            } }"
                            @click="printReceipt()"
                            class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-white hover:bg-blue-700 transition-colors"
                        >
                            <svg class="inline-block w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            Print Receipt
                        </button>

                        <button wire:click="closeReceipt" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors">
                            Close
                        </button>
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