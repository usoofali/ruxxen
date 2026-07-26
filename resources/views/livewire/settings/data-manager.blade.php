<?php

use App\Models\Transaction;
use App\Models\InventoryAdjustment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;

new class extends Component {
    public bool $showDeleteConfirmation = false;
    public string $confirmationType = '';
    public int $transactionCount = 0;
    public int $adjustmentCount = 0;

    public function mount()
    {
        // Check if user is admin
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('dashboard');
        }

        $this->loadCounts();
    }

    public function loadCounts()
    {
        $this->transactionCount = Transaction::count();
        $this->adjustmentCount = InventoryAdjustment::count();
    }

    public function confirmDeleteTransactions()
    {
        $this->confirmationType = 'transactions';
        $this->showDeleteConfirmation = true;
    }

    public function confirmDeleteAdjustments()
    {
        $this->confirmationType = 'adjustments';
        $this->showDeleteConfirmation = true;
    }

    public function confirmDeleteAll()
    {
        $this->confirmationType = 'all';
        $this->showDeleteConfirmation = true;
    }

    public function cancelDelete()
    {
        $this->showDeleteConfirmation = false;
        $this->confirmationType = '';
    }

    public function deleteData()
    {
        try {
            DB::transaction(function () {
                switch ($this->confirmationType) {
                    case 'transactions':
                        Transaction::truncate();
                        $this->dispatch('notify', [
                            'type' => 'success',
                            'message' => 'All transactions have been deleted successfully.'
                        ]);
                        break;

                    case 'adjustments':
                        InventoryAdjustment::truncate();
                        $this->dispatch('notify', [
                            'type' => 'success',
                            'message' => 'All inventory adjustments have been deleted successfully.'
                        ]);
                        break;

                    case 'all':
                        Transaction::truncate();
                        InventoryAdjustment::truncate();
                        $this->dispatch('notify', [
                            'type' => 'success',
                            'message' => 'All transactions and inventory adjustments have been deleted successfully.'
                        ]);
                        break;
                }
            });

            $this->loadCounts();
            $this->showDeleteConfirmation = false;
            $this->confirmationType = '';

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to delete data. Please try again.'
            ]);
        }
    }

    public function getConfirmationMessage()
    {
        return match($this->confirmationType) {
            'transactions' => "Are you sure you want to delete all {$this->transactionCount} transactions? This action cannot be undone.",
            'adjustments' => "Are you sure you want to delete all {$this->adjustmentCount} inventory adjustments? This action cannot be undone.",
            'all' => "Are you sure you want to delete all {$this->transactionCount} transactions and {$this->adjustmentCount} inventory adjustments? This action cannot be undone.",
            default => ''
        };
    }

    public function getConfirmationTitle()
    {
        return match($this->confirmationType) {
            'transactions' => 'Delete All Transactions',
            'adjustments' => 'Delete All Inventory Adjustments',
            'all' => 'Delete All Data',
            default => 'Confirm Deletion'
        };
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Data Manager')" :subheading="__('System database purge utilities for sales transactions and inventory logs')">
        <div class="space-y-6 my-4">
            <!-- Data Overview Cards -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-950/40 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Transactions</p>
                            <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($transactionCount) }}</p>
                        </div>
                        <div class="h-10 w-10 bg-orange-500/10 text-orange-600 dark:text-orange-400 rounded-xl flex items-center justify-center">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-950/40 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Inventory Adjustments</p>
                            <p class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($adjustmentCount) }}</p>
                        </div>
                        <div class="h-10 w-10 bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl flex items-center justify-center">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warning Box -->
            <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 text-red-600 dark:text-red-400 mt-0.5">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-red-600 dark:text-red-400">
                            Warning: Irreversible Database Action
                        </h3>
                        <p class="mt-1 text-xs text-red-700 dark:text-red-300">
                            Deleting database records is permanent and cannot be undone. Ensure you have exported CSV reports before proceeding.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Delete Actions Cards Grid -->
            <div class="space-y-3 pt-2">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Purge Data Operations</h3>
                
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <!-- Delete Transactions -->
                    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50/40 dark:bg-slate-950/20 p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white">Transactions</h4>
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ number_format($transactionCount) }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Truncate all sales receipt records</p>
                        </div>
                        <button 
                            wire:click="confirmDeleteTransactions" 
                            class="w-full rounded-xl border border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20 px-3 py-2 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            @disabled($transactionCount === 0)
                        >
                            Delete Transactions
                        </button>
                    </div>

                    <!-- Delete Adjustments -->
                    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50/40 dark:bg-slate-950/20 p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white">Inventory Logs</h4>
                                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">{{ number_format($adjustmentCount) }}</span>
                            </div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Truncate all stock refill & adjustment logs</p>
                        </div>
                        <button 
                            wire:click="confirmDeleteAdjustments" 
                            class="w-full rounded-xl border border-red-500/30 bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20 px-3 py-2 text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            @disabled($adjustmentCount === 0)
                        >
                            Delete Adjustments
                        </button>
                    </div>

                    <!-- Delete All -->
                    <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-sm font-bold text-red-600 dark:text-red-400">All System Data</h4>
                                <span class="text-[11px] font-bold text-red-600 dark:text-red-400">{{ number_format($transactionCount + $adjustmentCount) }}</span>
                            </div>
                            <p class="text-xs text-red-700 dark:text-red-300 mb-4">Purge all sales and inventory records</p>
                        </div>
                        <button 
                            wire:click="confirmDeleteAll" 
                            class="w-full rounded-xl bg-red-600 hover:bg-red-700 text-white px-3 py-2 text-xs font-bold transition-colors shadow-lg shadow-red-500/20 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                            @disabled($transactionCount === 0 && $adjustmentCount === 0)
                        >
                            Delete All Data
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div x-data="{ show: @entangle('showDeleteConfirmation') }" x-show="show" x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4" style="display: none;">
            <div class="relative w-full max-w-lg rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Red Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-red-500 to-rose-600"></div>

                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ $this->getConfirmationTitle() }}</h3>
                    <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                        {{ $this->getConfirmationMessage() }}
                    </p>
                </div>

                <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 mb-6">
                    <div class="flex items-center gap-2 text-red-600 dark:text-red-400 font-bold text-xs">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <span>Permanent Database Truncation</span>
                    </div>
                    <p class="text-xs text-red-700 dark:text-red-300 mt-1">Selected database tables will be cleared permanently.</p>
                </div>
                
                <div class="flex gap-3 pt-2">
                    <button wire:click="deleteData" class="flex-1 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 text-xs transition-all shadow-lg shadow-red-500/20 cursor-pointer">
                        Confirm & Purge Data
                    </button>
                    <button wire:click="cancelDelete" class="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-xs font-semibold cursor-pointer">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>

