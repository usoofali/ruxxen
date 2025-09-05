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

    <x-settings.layout :heading="__('Data Manager')" :subheading="__('Manage and delete transaction and inventory data')">
        <div class="space-y-6">
            <!-- Data Overview -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Data Overview</h3>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div class="bg-white dark:bg-gray-700 rounded-lg p-4 border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total Transactions</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($transactionCount) }}</p>
                            </div>
                            <div class="h-8 w-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-700 rounded-lg p-4 border">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Inventory Adjustments</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($adjustmentCount) }}</p>
                            </div>
                            <div class="h-8 w-8 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                                <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warning Section -->
            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                            Warning: Irreversible Actions
                        </h3>
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                            <p>Deleting data is permanent and cannot be undone. Make sure you have proper backups before proceeding.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delete Actions -->
            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Delete Data</h3>
                
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <!-- Delete Transactions -->
                    <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Transactions</h4>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($transactionCount) }} records</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">Delete all transaction records</p>
                        <flux:button 
                            wire:click="confirmDeleteTransactions" 
                            variant="danger" 
                            size="sm" 
                            class="w-full"
                            :disabled="$transactionCount === 0"
                        >
                            Delete Transactions
                        </flux:button>
                    </div>

                    <!-- Delete Adjustments -->
                    <div class="bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white">Inventory Adjustments</h4>
                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ number_format($adjustmentCount) }} records</span>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">Delete all inventory adjustment records</p>
                        <flux:button 
                            wire:click="confirmDeleteAdjustments" 
                            variant="danger" 
                            size="sm" 
                            class="w-full"
                            :disabled="$adjustmentCount === 0"
                        >
                            Delete Adjustments
                        </flux:button>
                    </div>

                    <!-- Delete All -->
                    <div class="bg-white dark:bg-gray-700 rounded-lg border border-red-200 dark:border-red-600 p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-sm font-medium text-red-900 dark:text-red-100">All Data</h4>
                            <span class="text-xs text-red-600 dark:text-red-400">{{ number_format($transactionCount + $adjustmentCount) }} records</span>
                        </div>
                        <p class="text-xs text-red-600 dark:text-red-400 mb-4">Delete all transactions and adjustments</p>
                        <flux:button 
                            wire:click="confirmDeleteAll" 
                            variant="danger" 
                            size="sm" 
                            class="w-full"
                            :disabled="$transactionCount === 0 && $adjustmentCount === 0"
                        >
                            Delete All Data
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div x-data="{ show: @entangle('showDeleteConfirmation') }" x-show="show" x-transition class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex min-h-screen items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" x-on:click="show = false"></div>
                
                <div class="relative transform overflow-hidden rounded-lg bg-white border border-gray-200 dark:border-gray-600 dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                    <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $this->getConfirmationTitle() }}</h3>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $this->getConfirmationMessage() }}
                                </p>
                            </div>

                            <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                            This action cannot be undone
                                        </h3>
                                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                            <p>All selected data will be permanently deleted from the database.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                        <flux:button wire:click="deleteData" variant="danger" class="w-full sm:w-auto">
                            Delete Data
                        </flux:button>
                        <flux:button wire:click="cancelDelete" variant="filled" class="w-full sm:w-auto">
                            Cancel
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>
