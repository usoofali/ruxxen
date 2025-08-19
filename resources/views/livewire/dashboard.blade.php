<?php

use App\Models\Inventory;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public $inventory;
    public $todaySales;
    public $monthlySales;
    public $totalCashiers;
    public $recentTransactions;

    public function mount()
    {
        $this->inventory = Inventory::first();
        
        if (Auth::user()->isAdmin()) {
            $this->loadAdminData();
        } else {
            $this->loadCashierData();
        }
    }

    private function loadAdminData()
    {
        $this->todaySales = Transaction::today()->completed()->sum('total_amount');
        $this->monthlySales = Transaction::thisMonth()->completed()->sum('total_amount');
        $this->totalCashiers = User::where('role', 'cashier')->where('is_active', true)->count();
        $this->recentTransactions = Transaction::with('cashier')
            ->latest()
            ->take(5)
            ->get();
    }

    private function loadCashierData()
    {
        $this->todaySales = Transaction::where('cashier_id', Auth::id())
            ->today()
            ->completed()
            ->sum('total_amount');
        $this->monthlySales = Transaction::where('cashier_id', Auth::id())
            ->thisMonth()
            ->completed()
            ->sum('total_amount');
        $this->recentTransactions = Transaction::where('cashier_id', Auth::id())
            ->latest()
            ->take(5)
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                Welcome back, {{ Auth::user()->name }}!
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                {{ Auth::user()->isAdmin() ? 'Admin Dashboard' : 'Cashier Dashboard' }}
            </p>
        </div>
        <div class="text-right">
            <p class="text-sm text-gray-600 dark:text-gray-400">Current Stock</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ number_format($inventory->current_stock, 2) }} kg
            </p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <!-- Today's Sales -->
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
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Today's Sales</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($todaySales, 2) }}</p>
                </div>
            </div>
        </div>

        <!-- Monthly Sales -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                        <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Monthly Sales</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($monthlySales, 2) }}</p>
                </div>
            </div>
        </div>

        @if(Auth::user()->isAdmin())
        <!-- Active Cashiers -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900">
                        <svg class="h-5 w-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Cashiers</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $totalCashiers }}</p>
                </div>
            </div>
        </div>
        @else
        <!-- Stock Level -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg {{ $inventory->isLowStock() ? 'bg-red-100 dark:bg-red-900' : 'bg-green-100 dark:bg-green-900' }}">
                        <svg class="h-5 w-5 {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Stock Level</p>
                    <p class="text-2xl font-bold {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                        {{ $inventory->isLowStock() ? 'Low Stock' : 'Good' }}
                    </p>
                </div>
            </div>
        </div>
        @endif
    </div>

    <!-- Recent Transactions -->
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Transactions</h3>
        </div>
        <div class="p-6">
            @if($recentTransactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Transaction</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($recentTransactions as $transaction)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $transaction->transaction_number }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $transaction->customer_name ?: 'Walk-in Customer' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $transaction->formatted_quantity }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $transaction->formatted_total }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $transaction->created_at->format('M d, Y H:i') }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No transactions</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new sale.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @if(Auth::user()->isCashier())
            <a href="{{ route('sales.create') }}" class="flex items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                <div>
                    <svg class="mx-auto h-8 w-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">New Sale</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create a new gas sale transaction</p>
                </div>
            </a>

            <a href="{{ route('sales.history') }}" class="flex items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                <div>
                    <svg class="mx-auto h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">Sales History</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View your transaction history</p>
                </div>
            </a>
        @else
            <a href="{{ route('admin.inventory') }}" class="flex items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                <div>
                    <svg class="mx-auto h-8 w-8 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">Manage Inventory</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">View and adjust stock levels</p>
                </div>
            </a>

            <a href="{{ route('admin.reports') }}" class="flex items-center justify-center rounded-xl border border-gray-200 bg-white p-6 text-center transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:hover:bg-gray-700">
                <div>
                    <svg class="mx-auto h-8 w-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-white">Reports</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Generate sales and inventory reports</p>
                </div>
            </a>
        @endif
    </div>
</div>
