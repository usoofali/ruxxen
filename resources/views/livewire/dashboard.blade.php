<?php

use App\Models\Inventory;
use App\Models\SyncLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public $inventory;
    public $todaySales;
    public $monthlySales;
    public $totalCashiers;
    public $recentTransactions;
    public int $unsyncedTransactions = 0;
    public ?string $lastSyncAt = null;
    public bool $isOnline = false;

    public function mount()
    {
        $this->inventory = Inventory::first();

        if (Auth::user()->isAdmin()) {
            $this->loadAdminData();
        } else {
            $this->loadCashierData();
        }

        $this->checkConnectivity();
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

        // Compute unsynced transactions only on slave mode
        if (config('app.mode') === 'slave') {
            $this->computeUnsyncedTransactions();
        }
    }

    private function computeUnsyncedTransactions(): void
    {
        try {
            // Count transactions with pending or failed sync logs
            $this->unsyncedTransactions = SyncLog::whereIn('sync_status', ['pending', 'failed'])
                ->count();

            // Get the latest sync time from completed sync logs
            $latestCompletedSync = SyncLog::where('sync_status', 'completed')
                ->latest('synced_at')
                ->first();

            if ($latestCompletedSync && $latestCompletedSync->synced_at) {
                $this->lastSyncAt = $latestCompletedSync->synced_at->toDateTimeString();
            } else {
                // Fallback to file-based approach if no completed syncs exist
                $filePath = storage_path('app/sync_data/transactions_last_sync.dat');
                $fallback = '1970-01-01 00:00:00';

                $lastSync = $fallback;
                if (File::exists($filePath)) {
                    $content = trim((string) File::get($filePath));
                    $lastSync = $content !== '' ? $content : $fallback;
                }

                try {
                    $parsed = Carbon::parse($lastSync);
                    $this->lastSyncAt = $parsed->toDateTimeString();
                } catch (\Throwable $e) {
                    $this->lastSyncAt = $fallback;
                }
            }

        } catch (\Exception $e) {
            // Fallback to file-based approach on error
            $filePath = storage_path('app/sync_data/transactions_last_sync.dat');
            $fallback = '1970-01-01 00:00:00';

            $lastSync = $fallback;
            if (File::exists($filePath)) {
                $content = trim((string) File::get($filePath));
                $lastSync = $content !== '' ? $content : $fallback;
            }

            try {
                $parsed = Carbon::parse($lastSync);
                $this->lastSyncAt = $parsed->toDateTimeString();
            } catch (\Throwable $e) {
                $this->lastSyncAt = $fallback;
            }

            // Fallback to timestamp-based counting
            $this->unsyncedTransactions = Transaction::where(function ($q) {
                $q->where('updated_at', '>', $this->lastSyncAt)
                    ->orWhere('created_at', '>', $this->lastSyncAt);
            })->count();
        }
    }

    public function checkConnectivity(): void
    {
        $hosts = [
            'www.google.com',
            'www.cloudflare.com',
            'www.amazon.com',
        ];

        $this->isOnline = false;
        foreach ($hosts as $host) {
            try {
                $conn = @fsockopen($host, 80, $errno, $errstr, 1);
                if ($conn) {
                    fclose($conn);
                    $this->isOnline = true;
                    break;
                }
            } catch (\Throwable $e) {
                // ignore and try next
            }
        }
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
    <!-- Hero Banner Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 sm:p-8 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden flex flex-col md:flex-row items-start md:items-center justify-between gap-6 transition-colors duration-200">
        <!-- Accent Bar & Glow -->
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>
        <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-orange-500/10 blur-3xl pointer-events-none">
        </div>

        <div class="space-y-2 relative z-10">
            <div class="flex items-center gap-3 flex-wrap">
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                    <span class="h-2 w-2 rounded-full bg-orange-500 dark:bg-orange-400 animate-pulse"></span>
                    {{ Auth::user()->isAdmin() ? 'Admin Overview' : 'Cashier Station' }}
                </span>
                <span class="text-xs text-slate-500 dark:text-slate-400">{{ now()->format('l, F j, Y') }}</span>
            </div>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                Welcome back, <span
                    class="bg-gradient-to-r from-orange-500 to-amber-500 dark:from-orange-400 dark:to-amber-300 bg-clip-text text-transparent">{{ Auth::user()->name }}</span>!
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Here is the real-time summary of your sales and gas stock inventory today.
            </p>
        </div>

        <div class="relative z-10 w-full md:w-auto flex-shrink-0">
            <div
                class="flex items-center justify-between md:justify-end gap-4 p-4 rounded-2xl bg-slate-100/80 dark:bg-slate-950/60 border border-slate-200/80 dark:border-slate-800/80 backdrop-blur-md">
                <div
                    class="h-10 w-10 rounded-xl bg-orange-500/15 border border-orange-500/30 flex items-center justify-center text-orange-600 dark:text-orange-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <div class="text-right">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">Current
                        Stock</p>
                    <p class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                        {{ number_format($inventory->current_stock, 2) }} <span
                            class="text-sm font-normal text-slate-500 dark:text-slate-400">kg</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-3 gap-4 sm:gap-6">
        <!-- Today's Sales -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 hover:shadow-orange-500/10 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Today's
                        Sales</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        ₦{{ number_format($todaySales, 2) }}
                    </p>
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
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>Completed today</span>
                <span class="text-emerald-600 dark:text-emerald-400 font-medium">Live sync</span>
            </div>
        </div>

        <!-- Monthly Sales -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 hover:shadow-orange-500/10 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Monthly
                        Sales</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        ₦{{ number_format($monthlySales, 2) }}
                    </p>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-lg shadow-orange-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                </div>
            </div>
            <div
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <span>{{ now()->format('F Y') }}</span>
                <span class="text-orange-600 dark:text-orange-400 font-medium">Accumulated</span>
            </div>
        </div>

        @if(Auth::user()->isAdmin())
            <!-- Active Cashiers -->
            <div
                class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 hover:shadow-orange-500/10 transition-all duration-300 group overflow-hidden">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Active
                            Cashiers</p>
                        <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                            {{ $totalCashiers }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/20 group-hover:scale-110 transition-transform">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                            </path>
                        </svg>
                    </div>
                </div>
                <div
                    class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span>System users</span>
                    <span class="text-purple-600 dark:text-purple-400 font-medium">Active</span>
                </div>
            </div>
        @else
            <!-- Stock Level -->
            <div
                class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 hover:shadow-orange-500/10 transition-all duration-300 group overflow-hidden">
                <div class="flex items-center justify-between">
                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Stock
                            Status</p>
                        <p
                            class="text-2xl sm:text-3xl font-extrabold {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} tracking-tight">
                            {{ $inventory->isLowStock() ? 'Low Stock' : 'Good' }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $inventory->isLowStock() ? 'bg-gradient-to-br from-red-500 to-rose-600 shadow-red-500/20' : 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/20' }} text-white shadow-lg group-hover:scale-110 transition-transform">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                </div>
                <div
                    class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                    <span>Threshold monitoring</span>
                    <span
                        class="{{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} font-medium">{{ $inventory->isLowStock() ? 'Replenish' : 'Sufficient' }}</span>
                </div>
            </div>

            @if(Auth::user()->isCashier() && config('app.mode') === 'slave')
                <!-- Unsynced Transactions -->
                <div
                    class="relative rounded-2xl border border-amber-500/30 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                                Unsynced Logs</p>
                            <p class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                                {{ number_format($unsyncedTransactions) }}
                            </p>
                        </div>
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white shadow-lg shadow-amber-500/20">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v3m0 4h.01M5.07 19h13.86A2 2 0 0021 17.07L13.93 3.64a2 2 0 00-3.86 0L3 17.07A2 2 0 005.07 19z" />
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400 truncate">Last sync: {{ $lastSyncAt }}</p>
                </div>

                <!-- Connectivity Status -->
                <div wire:poll.15s="checkConnectivity"
                    class="relative rounded-2xl border {{ $isOnline ? 'border-emerald-500/30' : 'border-red-500/30' }} bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl overflow-hidden">
                    <div class="flex items-center justify-between">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Connectivity</p>
                            <div class="flex items-center gap-2">
                                <span class="relative flex h-3 w-3">
                                    <span
                                        class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $isOnline ? 'bg-emerald-400' : 'bg-red-400' }} opacity-75"></span>
                                    <span
                                        class="relative inline-flex rounded-full h-3 w-3 {{ $isOnline ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                </span>
                                <p
                                    class="text-2xl sm:text-3xl font-extrabold {{ $isOnline ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }} tracking-tight">
                                    {{ $isOnline ? 'Online' : 'Offline' }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $isOnline ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/20 text-red-600 dark:text-red-400' }} shadow-lg">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8.5 17a3.5 3.5 0 016.999.001M6 13a7 7 0 0112 0M3.5 9a10.5 10.5 0 0117 0" />
                            </svg>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Auto ping every 15s</p>
                </div>
            @endif
        @endif
    </div>

    <!-- Recent Transactions Table -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
        <div
            class="border-b border-slate-200/80 dark:border-slate-800/80 px-6 py-5 flex items-center justify-between flex-wrap gap-4 bg-slate-50/60 dark:bg-slate-950/40">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">Recent Transactions</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Latest sales completed across all registers</p>
            </div>
            @if(Auth::user()->isAdmin())
                <a href="{{ route('admin.transactions') }}" wire:navigate
                    class="text-xs font-semibold text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300 flex items-center gap-1 transition-colors">
                    View all transactions
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @else
                <a href="{{ route('sales.history') }}" wire:navigate
                    class="text-xs font-semibold text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300 flex items-center gap-1 transition-colors">
                    View my sales history
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            @endif
        </div>

        <div class="p-0">
            @if($recentTransactions->count() > 0)
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200/80 dark:border-slate-800/60 bg-slate-100/40 dark:bg-slate-950/20 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Transaction Code</th>
                                <th class="px-6 py-4">Customer</th>
                                <th class="px-6 py-4">Quantity</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">Date & Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 text-sm">
                            @foreach($recentTransactions as $transaction)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="whitespace-nowrap px-6 py-4 font-mono">
                                        <span
                                            class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-semibold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                            {{ $transaction->transaction_number }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-medium text-slate-900 dark:text-white">
                                            {{ $transaction->customer_name ?: 'Walk-in Customer' }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center gap-1 text-slate-700 dark:text-slate-300">
                                            <svg class="w-4 h-4 text-slate-400 dark:text-slate-500" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m0 0l-3 9m3-9l3 2m-6 2l6-2m0 0l-3 9m3-9l3 2m-6 2l3 9" />
                                            </svg>
                                            {{ $transaction->formatted_quantity }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-bold text-emerald-600 dark:text-emerald-400">
                                            {{ $transaction->formatted_total }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $transaction->created_at->format('M d, Y • H:i') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
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
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Get started by performing a new sale at the
                        POS register.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
        @if(Auth::user()->isCashier())
            <a href="{{ route('sales.history') }}" wire:navigate
                class="group relative flex items-center gap-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 hover:shadow-orange-500/10 transition-all duration-300">
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-lg shadow-orange-500/20 group-hover:scale-105 transition-transform">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                        </path>
                    </svg>
                </div>
                <div>
                    <h3
                        class="text-base font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                        Sales History</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Browse past sales receipts & printing logs
                    </p>
                </div>
            </a>
        @else
            <a href="{{ route('admin.inventory') }}" wire:navigate
                class="group relative flex items-center gap-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 hover:shadow-orange-500/10 transition-all duration-300">
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white shadow-lg shadow-orange-500/20 group-hover:scale-105 transition-transform">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <div>
                    <h3
                        class="text-base font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors">
                        Manage Inventory</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Monitor LPG tank capacity & log stock
                        additions</p>
                </div>
            </a>

            <a href="{{ route('admin.reports') }}" wire:navigate
                class="group relative flex items-center gap-5 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 hover:shadow-orange-500/10 transition-all duration-300">
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-purple-500 to-indigo-600 text-white shadow-lg shadow-purple-500/20 group-hover:scale-105 transition-transform">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                        </path>
                    </svg>
                </div>
                <div>
                    <h3
                        class="text-base font-bold text-slate-900 dark:text-white group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors">
                        Sales & Stock Reports</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Generate daily, weekly, or cashier CSV
                        reports</p>
                </div>
            </a>
        @endif
    </div>
</div>