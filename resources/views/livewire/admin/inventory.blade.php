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
            $newStock = match($this->adjustment_type) {
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

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Inventory Management</h1>
            <p class="text-gray-600 dark:text-gray-400">Monitor and adjust stock levels</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 sm:flex-shrink-0">
            <flux:button wire:click="openSettingsModal" variant="outline" class="w-full sm:w-auto">
                Settings
            </flux:button>
            <flux:button wire:click="openAdjustmentModal" variant="primary" class="w-full sm:w-auto">
                Make Adjustment
            </flux:button>
        </div>
    </div>

    <!-- Stock Overview -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
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
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Current Stock</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($inventory->current_stock, 2) }} kg</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-yellow-100 dark:bg-yellow-900">
                        <svg class="h-5 w-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Minimum Stock</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($inventory->minimum_stock, 2) }} kg</p>
                </div>
            </div>
        </div>

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
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Price per kg</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">₦{{ number_format($inventory->price_per_kg, 2) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg {{ $inventory->isLowStock() ? 'bg-red-100 dark:bg-red-900' : 'bg-green-100 dark:bg-green-900' }}">
                        <svg class="h-5 w-5 {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="ml-4">
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Status</p>
                    <p class="text-2xl font-bold {{ $inventory->isLowStock() ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ $inventory->isLowStock() ? 'Low Stock' : 'Good' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Level Progress -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-white">Stock Level</h3>
        <div class="space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Current: {{ number_format($inventory->current_stock, 2) }} kg</span>
                <span class="text-gray-600 dark:text-gray-400">Minimum: {{ number_format($inventory->minimum_stock, 2) }} kg</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                <div class="bg-blue-600 h-2.5 rounded-full {{ $inventory->isLowStock() ? 'bg-red-600' : '' }}" 
                     style="width: {{ min(100, ($inventory->current_stock / max($inventory->minimum_stock, 1)) * 100) }}%"></div>
            </div>
            <div class="text-xs text-gray-500 dark:text-gray-400">
                {{ number_format(($inventory->current_stock / max($inventory->minimum_stock, 1)) * 100, 1) }}% of minimum stock level
            </div>
        </div>
    </div>

    <!-- Recent Adjustments -->
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Adjustments</h3>
        </div>
        <div class="p-6">
            @if($this->adjustments->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Quantity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Previous</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">New</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Reason</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($this->adjustments as $adjustment)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $adjustment->created_at->format('M d, Y H:i') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                            {{ $adjustment->type === 'addition' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 
                                               ($adjustment->type === 'subtraction' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : 
                                                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200') }}">
                                            {{ $adjustment->type_label }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $adjustment->formatted_quantity }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ number_format($adjustment->previous_stock, 2) }} kg</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ number_format($adjustment->new_stock, 2) }} kg</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $adjustment->reason }}</div>
                                        @if($adjustment->notes)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $adjustment->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $adjustment->user->name }}</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-6">
                    {{ $this->adjustments->links() }}
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No adjustments</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No inventory adjustments have been made yet.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Adjustment Modal -->
    @if($showAdjustmentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-xl bg-white p-6 dark:bg-gray-800">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Make Inventory Adjustment</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Adjust the current stock level</p>
                </div>

                <form wire:submit="makeAdjustment" class="space-y-4">
                    <div>
                        <flux:select wire:model="adjustment_type" label="Adjustment Type" required>
                            <option value="addition">Add Stock</option>
                            <option value="subtraction">Remove Stock</option>
                            <option value="loss">Stock Loss</option>
                            <option value="correction">Stock Correction</option>
                        </flux:select>
                    </div>

                    <div>
                        <flux:input
                            wire:model="adjustment_quantity"
                            label="Quantity (kg)"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="Enter quantity"
                        />
                        @error('adjustment_quantity')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="adjustment_reason"
                            label="Reason"
                            type="text"
                            required
                            placeholder="Reason for adjustment"
                        />
                        @error('adjustment_reason')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea
                            wire:model="adjustment_notes"
                            label="Notes (Optional)"
                            placeholder="Additional notes"
                            rows="3"
                        />
                        @error('adjustment_notes')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('general')
                        <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        </div>
                    @enderror

                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove>Make Adjustment</span>
                            <span wire:loading>Processing...</span>
                        </flux:button>
                        <flux:button type="button" wire:click="closeAdjustmentModal" variant="outline" class="flex-1">
                            Cancel
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Settings Modal -->
    @if($showSettingsModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-xl bg-white p-6 dark:bg-gray-800">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Inventory Settings</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Update pricing and stock alerts</p>
                </div>

                <form wire:submit="updateSettings" class="space-y-4">
                    <div>
                        <flux:input
                            wire:model="new_price_per_kg"
                            label="Price per kg (₦)"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="Enter price per kg"
                        />
                        @error('new_price_per_kg')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="new_minimum_stock"
                            label="Minimum Stock Level (kg)"
                            type="number"
                            step="0.01"
                            min="0.01"
                            required
                            placeholder="Enter minimum stock level"
                        />
                        @error('new_minimum_stock')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('general')
                        <div class="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                            <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        </div>
                    @enderror

                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update Settings</span>
                            <span wire:loading>Processing...</span>
                        </flux:button>
                        <flux:button type="button" wire:click="closeSettingsModal" variant="outline" class="flex-1">
                            Cancel
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
