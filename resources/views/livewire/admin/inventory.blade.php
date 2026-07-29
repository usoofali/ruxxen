<?php

use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Validate('required|numeric|min:0.01')]
    public float $adjustment_quantity = 0;

    #[Validate('required|in:addition,subtraction,loss,correction')]
    public string $adjustment_type = 'addition';

    #[Validate('required|string|max:255')]
    public string $adjustment_reason = '';

    #[Validate('nullable|string|max:500')]
    public ?string $adjustment_notes = null;

    #[Validate('required|numeric|min:0.01')]
    public float $new_price_per_kg = 0;

    #[Validate('required|numeric|min:0.01')]
    public float $new_minimum_stock = 0;

    public $inventory;
    public $showAdjustmentModal = false;
    public $showSettingsModal = false;

    public function mount()
    {
        $this->inventory = Inventory::first();
        $this->new_price_per_kg = $this->inventory->price_per_kg;
        $this->new_minimum_stock = $this->inventory->minimum_stock;
    }

    public function getAdjustmentsProperty()
    {
        return InventoryAdjustment::with('user')
            ->latest()
            ->paginate(10);
    }

    public function openAdjustmentModal()
    {
        $this->reset(['adjustment_quantity', 'adjustment_type', 'adjustment_reason', 'adjustment_notes']);
        $this->showAdjustmentModal = true;
    }

    public function closeAdjustmentModal()
    {
        $this->showAdjustmentModal = false;
    }

    public function makeAdjustment()
    {
        $this->validate();

        try {
            DB::beginTransaction();

            $previousStock = $this->inventory->current_stock;
            $newStock = match ($this->adjustment_type) {
                'addition' => $previousStock + $this->adjustment_quantity,
                'subtraction', 'loss' => $previousStock - $this->adjustment_quantity,
                'correction' => $this->adjustment_quantity,
            };

            // Validate stock won't go negative
            if ($newStock < 0) {
                $this->addError('adjustment_quantity', 'Adjustment would result in negative stock.');
                return;
            }

            // Update inventory
            $this->inventory->current_stock = $newStock;
            $this->inventory->save();

            // Record adjustment
            InventoryAdjustment::create([
                'user_id' => Auth::id(),
                'type' => $this->adjustment_type,
                'quantity_kg' => $this->adjustment_quantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => $this->adjustment_reason,
                'notes' => $this->adjustment_notes,
            ]);

            DB::commit();

            $this->closeAdjustmentModal();
            $this->inventory->refresh();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->addError('general', 'Failed to make adjustment. Please try again.');
        }
    }

    public function openSettingsModal()
    {
        $this->new_price_per_kg = $this->inventory->price_per_kg;
        $this->new_minimum_stock = $this->inventory->minimum_stock;
        $this->showSettingsModal = true;
    }

    public function closeSettingsModal()
    {
        $this->showSettingsModal = false;
    }

    public function updateSettings()
    {
        $this->validate([
            'new_price_per_kg' => 'required|numeric|min:0.01',
            'new_minimum_stock' => 'required|numeric|min:0.01',
        ]);

        try {
            $this->inventory->update([
                'price_per_kg' => $this->new_price_per_kg,
                'minimum_stock' => $this->new_minimum_stock,
            ]);

            $this->closeSettingsModal();
            $this->inventory->refresh();

        } catch (\Exception $e) {
            $this->addError('general', 'Failed to update settings. Please try again.');
        }
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
                    Inventory Controller
                </span>
            </div>
            <h1 class="text-xl sm:text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Inventory
                Management</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">Monitor tank capacity, price per kg, and log stock
                additions</p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="openSettingsModal"
                class="rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white/80 dark:bg-slate-800/80 px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all shadow-sm cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Pricing & Alerts
            </button>
            <button wire:click="openAdjustmentModal"
                class="rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 px-4 py-2.5 text-xs font-semibold text-white transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Make Adjustment
            </button>
        </div>
    </div>

    <!-- Stock Overview Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Current Stock -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Current
                        Stock</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ number_format($inventory->current_stock, 2) }} <span
                            class="text-sm font-normal text-slate-500 dark:text-slate-400">kg</span>
                    </p>
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
                <span>Real-time available LPG</span>
            </div>
        </div>

        <!-- Minimum Stock Threshold -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Minimum
                        Stock</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        {{ number_format($inventory->minimum_stock, 2) }} <span
                            class="text-sm font-normal text-slate-500 dark:text-slate-400">kg</span>
                    </p>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-500 to-yellow-600 text-white shadow-lg shadow-amber-500/20 group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z">
                        </path>
                    </svg>
                </div>
            </div>
            <div
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span>Low stock warning mark</span>
            </div>
        </div>

        <!-- Price per kg -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Price
                        per kg</p>
                    <p class="text-xl sm:text-xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                        ₦{{ number_format($inventory->price_per_kg, 2) }}</p>
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
                <span>Base retail rate</span>
            </div>
        </div>

        <!-- Inventory Status -->
        <div
            class="relative rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-5 sm:p-6 backdrop-blur-xl shadow-lg dark:shadow-xl hover:border-orange-500/40 transition-all duration-300 group overflow-hidden">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Stock
                        Status</p>
                    <p
                        class="text-xl sm:text-xl font-extrabold {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} tracking-tight">
                        {{ $inventory->isLowStock() ? 'Low Stock' : 'Good' }}
                    </p>
                </div>
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl {{ $inventory->isLowStock() ? 'bg-gradient-to-br from-red-500 to-rose-600 shadow-red-500/20' : 'bg-gradient-to-br from-emerald-500 to-teal-600 shadow-emerald-500/20' }} text-white shadow-lg group-hover:scale-110 transition-transform">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <div
                class="mt-4 pt-3 border-t border-slate-200/60 dark:border-slate-800/60 text-xs text-slate-500 dark:text-slate-400">
                <span
                    class="{{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }} font-medium">{{ $inventory->isLowStock() ? 'Replenishment needed' : 'Sufficient supply' }}</span>
            </div>
        </div>
    </div>

    <!-- Stock Level Progress Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden">
        <h3 class="mb-3 text-lg font-bold text-slate-900 dark:text-white tracking-tight">Stock Level Capacity</h3>
        <div class="space-y-3">
            <div class="flex justify-between text-xs font-semibold text-slate-600 dark:text-slate-400">
                <span>Current: <strong
                        class="text-slate-900 dark:text-white">{{ number_format($inventory->current_stock, 2) }}
                        kg</strong></span>
                <span>Minimum Mark: <strong
                        class="text-slate-900 dark:text-white">{{ number_format($inventory->minimum_stock, 2) }}
                        kg</strong></span>
            </div>
            <div
                class="w-full bg-slate-200/80 dark:bg-slate-800/80 rounded-full h-3 overflow-hidden p-0.5 border border-slate-200 dark:border-slate-700/50">
                <div class="h-full rounded-full transition-all duration-500 {{ $inventory->isLowStock() ? 'bg-gradient-to-r from-red-500 to-rose-600' : 'bg-gradient-to-r from-orange-500 via-amber-500 to-emerald-500' }}"
                    style="width: {{ min(100, ($inventory->current_stock / max($inventory->minimum_stock, 1)) * 100) }}%">
                </div>
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
                Operating at <strong
                    class="text-slate-800 dark:text-slate-200">{{ number_format(($inventory->current_stock / max($inventory->minimum_stock, 1)) * 100, 1) }}%</strong>
                of defined minimum threshold
            </div>
        </div>
    </div>

    <!-- Adjustments Table Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
        <div
            class="border-b border-slate-200/80 dark:border-slate-800/80 px-6 py-5 bg-slate-50/60 dark:bg-slate-950/40">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">Recent Stock Adjustments</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Audit trail of gas refills, manual corrections, and
                stock losses</p>
        </div>

        <div class="p-0">
            @if($this->adjustments->count() > 0)
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200/80 dark:border-slate-800/60 bg-slate-100/40 dark:bg-slate-950/20 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Date & Time</th>
                                <th class="px-6 py-4">Type</th>
                                <th class="px-6 py-4">Quantity</th>
                                <th class="px-6 py-4">Previous Stock</th>
                                <th class="px-6 py-4">New Stock</th>
                                <th class="px-6 py-4">Reason & Notes</th>
                                <th class="px-6 py-4">Logged By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 text-sm">
                            @foreach($this->adjustments as $adjustment)
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                                <td class="whitespace-nowrap px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $adjustment->created_at->format('M d, Y • H:i') }}
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
                                                                                    {{ $adjustment->type === 'addition' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' :
                                ($adjustment->type === 'subtraction' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20' :
                                    'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20') }}">
                                                        {{ $adjustment->type_label }}
                                                    </span>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4 font-mono">
                                                    <span
                                                        class="font-bold text-slate-900 dark:text-white">{{ $adjustment->formatted_quantity }}</span>
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4 text-slate-600 dark:text-slate-400">
                                                    {{ number_format($adjustment->previous_stock, 2) }} kg
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                                    {{ number_format($adjustment->new_stock, 2) }} kg
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="font-medium text-slate-900 dark:text-white">{{ $adjustment->reason }}</div>
                                                    @if($adjustment->notes)
                                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $adjustment->notes }}
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="whitespace-nowrap px-6 py-4">
                                                    <div class="font-medium text-slate-900 dark:text-white">{{ $adjustment->user->name }}
                                                    </div>
                                                </td>
                                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="p-6 border-t border-slate-200/80 dark:border-slate-800/80">
                    {{ $this->adjustments->links() }}
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
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">No adjustments recorded</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Stock adjustments and refilling logs will
                        appear here.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Adjustment Modal -->
    @if($showAdjustmentModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div
                class="relative w-full max-w-md rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600">
                </div>

                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Make Stock Adjustment</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Log stock refill, removal, or manual correction
                    </p>
                </div>

                <form wire:submit="makeAdjustment" class="space-y-4">
                    <div>
                        <flux:select wire:model="adjustment_type" label="Adjustment Type" required>
                            <option value="addition">Add Stock (Refill)</option>
                            <option value="subtraction">Remove Stock</option>
                            <option value="loss">Stock Loss</option>
                            <option value="correction">Set Exact Stock</option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:input wire:model="adjustment_quantity" label="Quantity (kg)" type="number" step="0.01"
                            min="0.01" required placeholder="e.g. 500" />
                        @error('adjustment_quantity')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="adjustment_reason" label="Reason" type="text" required
                            placeholder="e.g. Depot Refill Batch #42" />
                        @error('adjustment_reason')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea wire:model="adjustment_notes" label="Notes (Optional)"
                            placeholder="Additional details..." rows="2" />
                        @error('adjustment_notes')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('general')
                        <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-3">
                            <p class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                        </div>
                    @enderror

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold py-2.5 text-sm transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>Save Adjustment</span>
                            <span wire:loading>Processing...</span>
                        </button>
                        <button type="button" wire:click="closeAdjustmentModal"
                            class="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-sm font-medium cursor-pointer">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Settings Modal -->
    @if($showSettingsModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div
                class="relative w-full max-w-md rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600">
                </div>

                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Inventory Settings</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Update retail price per kg & low stock warning
                        threshold</p>
                </div>

                <form wire:submit="updateSettings" class="space-y-4">
                    <div>
                        <flux:input wire:model="new_price_per_kg" label="Price per kg (₦)" type="number" step="0.01"
                            min="0.01" required placeholder="e.g. 1200.00" />
                        @error('new_price_per_kg')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="new_minimum_stock" label="Minimum Stock Level (kg)" type="number"
                            step="0.01" min="0.01" required placeholder="e.g. 1000.00" />
                        @error('new_minimum_stock')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('general')
                        <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-3">
                            <p class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
                        </div>
                    @enderror

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold py-2.5 text-sm transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>Update Settings</span>
                            <span wire:loading>Saving...</span>
                        </button>
                        <button type="button" wire:click="closeSettingsModal"
                            class="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-sm font-medium cursor-pointer">
                            Cancel
                        </button>
                    </div>
                </form>
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