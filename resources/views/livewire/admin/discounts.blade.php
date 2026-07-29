<?php

use App\Models\CustomerDiscount;
use App\Models\Inventory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Validate('required|string|max:255|unique:customer_discounts,name')]
    public string $name = '';

    #[Validate('required|numeric|min:0')]
    public float $discount_per_kg = 0.00;

    public bool $is_default = false;
    public bool $is_active = true;

    #[Validate('nullable|string|max:1000')]
    public ?string $description = null;

    public $search = '';
    public $statusFilter = '';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $editingDiscount = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function getDiscountsProperty()
    {
        $query = CustomerDiscount::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        return $query->latest()->paginate(10);
    }

    public function openCreateModal()
    {
        $this->reset(['name', 'discount_per_kg', 'is_default', 'is_active', 'description']);
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
    }

    public function createDiscount()
    {
        $this->validate();

        // Validate discount amount against current price
        $inventory = Inventory::first();
        if ($inventory && $this->discount_per_kg >= $inventory->price_per_kg) {
            $this->addError('discount_per_kg', 'Discount cannot be greater than or equal to current price per kg.');
            return;
        }

        try {
            CustomerDiscount::create([
                'name' => $this->name,
                'discount_per_kg' => $this->discount_per_kg,
                'is_default' => $this->is_default,
                'is_active' => $this->is_active,
                'description' => $this->description,
            ]);

            $this->closeCreateModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Discount created successfully.'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to create discount. Please try again.'
            ]);
        }
    }

    public function openEditModal($discountId)
    {
        $this->editingDiscount = CustomerDiscount::find($discountId);
        if ($this->editingDiscount) {
            $this->name = $this->editingDiscount->name;
            $this->discount_per_kg = $this->editingDiscount->discount_per_kg;
            $this->is_default = $this->editingDiscount->is_default;
            $this->is_active = $this->editingDiscount->is_active;
            $this->description = $this->editingDiscount->description;
            $this->showEditModal = true;
        }
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingDiscount = null;
    }

    public function updateDiscount()
    {
        $this->validate([
            'name' => 'required|string|max:255|unique:customer_discounts,name,' . $this->editingDiscount->id,
            'discount_per_kg' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:1000',
        ]);

        // Validate discount amount against current price
        $inventory = Inventory::first();
        if ($inventory && $this->discount_per_kg >= $inventory->price_per_kg) {
            $this->addError('discount_per_kg', 'Discount cannot be greater than or equal to current price per kg.');
            return;
        }

        try {
            $this->editingDiscount->update([
                'name' => $this->name,
                'discount_per_kg' => $this->discount_per_kg,
                'is_default' => $this->is_default,
                'is_active' => $this->is_active,
                'description' => $this->description,
            ]);

            $this->closeEditModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Discount updated successfully.'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update discount. Please try again.'
            ]);
        }
    }

    public function toggleDiscountStatus($discountId)
    {
        $discount = CustomerDiscount::find($discountId);
        if ($discount) {
            // Prevent deactivating the default discount
            if ($discount->is_default && $discount->is_active) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Cannot deactivate the default discount. Set another discount as default first.'
                ]);
                return;
            }

            $discount->update(['is_active' => !$discount->is_active]);

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Discount status updated successfully.'
            ]);
        }
    }

    public function setAsDefault($discountId)
    {
        $discount = CustomerDiscount::find($discountId);
        if ($discount && $discount->is_active) {
            $discount->setAsDefault();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'Default discount updated successfully.'
            ]);
        }
    }

    public function deleteDiscount($discountId)
    {
        $discount = CustomerDiscount::find($discountId);
        if ($discount) {
            // Check if discount has transactions
            if ($discount->transactions()->count() > 0) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Cannot delete discount with existing transactions.'
                ]);
                return;
            }

            // Prevent deleting the default discount
            if ($discount->is_default) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Cannot delete the default discount. Set another discount as default first.'
                ]);
                return;
            }

            try {
                $discount->delete();
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Discount deleted successfully.'
                ]);
            } catch (\Exception $e) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Failed to delete discount. Please try again.'
                ]);
            }
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter']);
        $this->resetPage();
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
                    Pricing Rules
                </span>
            </div>
            <h1 class="text-xl sm:text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">Customer
                Discounts</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">Configure customer tiers, per-kg price reductions, and
                default discount rules</p>
        </div>
        <div class="flex items-center gap-3">
            <button wire:click="clearFilters"
                class="rounded-xl border border-slate-200/80 dark:border-slate-700/80 bg-white/80 dark:bg-slate-800/80 px-4 py-2.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-all shadow-sm cursor-pointer">
                Clear Filters
            </button>
            <button wire:click="openCreateModal"
                class="rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 px-4 py-2.5 text-xs font-semibold text-white transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Discount Rule
            </button>
        </div>
    </div>

    <!-- Filters Bar Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden transition-all duration-200">
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 items-end">
            <!-- Search -->
            <div>
                <flux:input wire:model.live.debounce.300ms="search" label="Search"
                    placeholder="Search discount names..." icon="magnifying-glass" class="w-full" />
            </div>

            <!-- Status Filter -->
            <div>
                <flux:select wire:model.live="statusFilter" label="Status Filter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </flux:select>
            </div>

            <!-- Total Counter -->
            <div>
                <div
                    class="rounded-xl bg-slate-100/80 dark:bg-slate-800/60 p-2.5 border border-slate-200/80 dark:border-slate-700/60 text-xs font-semibold text-slate-700 dark:text-slate-300 text-center">
                    Total Active Discount Rules: <strong
                        class="text-orange-600 dark:text-orange-400">{{ $this->discounts->total() }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Discounts Table Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
        <div
            class="border-b border-slate-200/80 dark:border-slate-800/80 px-6 py-5 bg-slate-50/60 dark:bg-slate-950/40">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">Active Discount Rules</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Discount per kg applied automatically during register
                checkout</p>
        </div>

        <div class="p-0">
            @if($this->discounts->count() > 0)
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200/80 dark:border-slate-800/60 bg-slate-100/40 dark:bg-slate-950/20 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">Discount Name</th>
                                <th class="px-6 py-4">Discount / kg</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Default Rule</th>
                                <th class="px-6 py-4">Sales Count</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 text-sm">
                            @foreach($this->discounts as $discount)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-bold text-slate-900 dark:text-white">{{ $discount->name }}</div>
                                        @if($discount->description)
                                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                                {{ Str::limit($discount->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 font-mono">
                                        <span
                                            class="font-bold text-emerald-600 dark:text-emerald-400">-{{ $discount->formatted_discount }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
                                                    {{ $discount->is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20' }}">
                                            {{ $discount->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        @if($discount->is_default)
                                            <span
                                                class="inline-flex rounded-full px-3 py-1 text-xs font-semibold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                                                Default Rule
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-400 dark:text-slate-600">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-semibold text-slate-900 dark:text-white">
                                            {{ $discount->transactions()->count() }} sales</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="openEditModal({{ $discount->id }})"
                                                class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                                Edit
                                            </button>
                                            @if(!$discount->is_default)
                                                <button wire:click="setAsDefault({{ $discount->id }})"
                                                    class="rounded-xl border border-orange-500/30 bg-orange-500/10 px-3 py-1.5 text-xs font-semibold text-orange-600 dark:text-orange-400 hover:bg-orange-500/20 transition-colors cursor-pointer">
                                                    Set Default
                                                </button>
                                            @endif
                                            <button wire:click="toggleDiscountStatus({{ $discount->id }})"
                                                class="rounded-xl border px-3 py-1.5 text-xs font-semibold cursor-pointer transition-colors {{ $discount->is_active ? 'border-red-500/30 text-red-600 hover:bg-red-500/10' : 'border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/10' }}">
                                                {{ $discount->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                            @if(!$discount->is_default && $discount->transactions()->count() === 0)
                                                <button wire:click="deleteDiscount({{ $discount->id }})"
                                                    class="rounded-xl border border-red-500/30 text-red-600 hover:bg-red-500/10 px-3 py-1.5 text-xs font-semibold cursor-pointer transition-colors">
                                                    Delete
                                                </button>
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
                    {{ $this->discounts->links() }}
                </div>
            @else
                <div class="text-center py-12 px-4">
                    <div
                        class="mx-auto h-16 w-16 rounded-full bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center text-slate-400 mb-3">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">No discount rules found</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Try adjusting search or status filter.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Create Discount Modal -->
    @if($showCreateModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div
                class="relative w-full max-w-md rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600">
                </div>

                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add New Discount Type</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Configure discount per kg rate and default rules
                    </p>
                </div>

                <form wire:submit="createDiscount" class="space-y-4">
                    <div>
                        <flux:input wire:model="name" label="Discount Name" type="text" required
                            placeholder="e.g., VIP Customer, Wholesale Rate" />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="discount_per_kg" label="Discount per kg (₦)" type="number" step="0.01"
                            min="0" required placeholder="e.g. 50.00" />
                        @error('discount_per_kg')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea wire:model="description" label="Description (Optional)"
                            placeholder="Additional details..." rows="2" />
                        @error('description')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2 pt-1">
                        <div class="flex items-center">
                            <flux:checkbox wire:model="is_default" label="Set as Default Rule" />
                        </div>

                        <div class="flex items-center">
                            <flux:checkbox wire:model="is_active" label="Active" />
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold py-2.5 text-sm transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>Create Discount</span>
                            <span wire:loading>Creating...</span>
                        </button>
                        <button type="button" wire:click="closeCreateModal"
                            class="flex-1 rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2.5 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-sm font-medium cursor-pointer">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Discount Modal -->
    @if($showEditModal && $editingDiscount)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div
                class="relative w-full max-w-md rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600">
                </div>

                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit Discount Type</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Update discount per kg rate or title</p>
                </div>

                <form wire:submit="updateDiscount" class="space-y-4">
                    <div>
                        <flux:input wire:model="name" label="Discount Name" type="text" required
                            placeholder="e.g., VIP Customer, Wholesale Rate" />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="discount_per_kg" label="Discount per kg (₦)" type="number" step="0.01"
                            min="0" required placeholder="e.g. 50.00" />
                        @error('discount_per_kg')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea wire:model="description" label="Description (Optional)"
                            placeholder="Additional details..." rows="2" />
                        @error('description')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col gap-2 pt-1">
                        <div class="flex items-center">
                            <flux:checkbox wire:model="is_default" label="Set as Default Rule" />
                        </div>

                        <div class="flex items-center">
                            <flux:checkbox wire:model="is_active" label="Active" />
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold py-2.5 text-sm transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>Update Discount</span>
                            <span wire:loading>Saving...</span>
                        </button>
                        <button type="button" wire:click="closeEditModal"
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