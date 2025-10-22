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

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Customer Discounts</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage customer discount types and pricing</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 sm:flex-shrink-0">
            <flux:button wire:click="clearFilters" variant="outline" class="w-full sm:w-auto">
                Clear Filters
            </flux:button>
            <flux:button wire:click="openCreateModal" variant="primary" class="w-full sm:w-auto">
                Add Discount Type
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Search -->
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    label="Search"
                    placeholder="Search discount types..."
                    icon="magnifying-glass"
                />
            </div>

            <!-- Status Filter -->
            <div>
                <flux:select
                    wire:model.live="statusFilter"
                    label="Status Filter"
                >
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </flux:select>
            </div>

            <!-- Discount Count -->
            <div class="flex items-end">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Total Discounts: {{ $this->discounts->total() }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Discounts Table -->
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Discount Types</h3>
        </div>
        <div class="p-6">
            @if($this->discounts->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Discount per kg</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Default</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Transactions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($this->discounts as $discount)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $discount->name }}</div>
                                        @if($discount->description)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($discount->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $discount->formatted_discount }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                            {{ $discount->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                            {{ $discount->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        @if($discount->is_default)
                                            <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Default
                                            </span>
                                        @else
                                            <span class="text-sm text-gray-500 dark:text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-900 dark:text-white">{{ $discount->transactions()->count() }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex gap-2">
                                            <flux:button 
                                                wire:click="openEditModal({{ $discount->id }})" 
                                                variant="outline" 
                                                size="sm"
                                            >
                                                Edit
                                            </flux:button>
                                            @if(!$discount->is_default)
                                                <flux:button 
                                                    wire:click="setAsDefault({{ $discount->id }})" 
                                                    variant="outline" 
                                                    size="sm"
                                                    class="text-blue-600 hover:text-blue-700"
                                                >
                                                    Set Default
                                                </flux:button>
                                            @endif
                                            <flux:button 
                                                wire:click="toggleDiscountStatus({{ $discount->id }})" 
                                                variant="outline" 
                                                size="sm"
                                                class="{{ $discount->is_active ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }}"
                                            >
                                                {{ $discount->is_active ? 'Deactivate' : 'Activate' }}
                                            </flux:button>
                                            @if(!$discount->is_default && $discount->transactions()->count() === 0)
                                                <flux:button 
                                                    wire:click="deleteDiscount({{ $discount->id }})" 
                                                    variant="outline" 
                                                    size="sm"
                                                    class="text-red-600 hover:text-red-700"
                                                >
                                                    Delete
                                                </flux:button>
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
                    {{ $this->discounts->links() }}
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No discount types found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Create Discount Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-xl bg-white p-6 dark:bg-gray-800">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New Discount Type</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Create a new customer discount type</p>
                </div>

                <form wire:submit="createDiscount" class="space-y-4">
                    <div>
                        <flux:input
                            wire:model="name"
                            label="Discount Name"
                            type="text"
                            required
                            placeholder="e.g., VIP Customer, Wholesale"
                        />
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="discount_per_kg"
                            label="Discount per kg (₦)"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            placeholder="Enter discount amount"
                        />
                        @error('discount_per_kg')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea
                            wire:model="description"
                            label="Description (Optional)"
                            placeholder="Additional notes about this discount type"
                            rows="3"
                        />
                        @error('description')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <flux:checkbox wire:model="is_default" label="Set as Default" />
                    </div>

                    <div class="flex items-center">
                        <flux:checkbox wire:model="is_active" label="Active" />
                    </div>

                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove>Create Discount</span>
                            <span wire:loading>Creating...</span>
                        </flux:button>
                        <flux:button type="button" wire:click="closeCreateModal" variant="outline" class="flex-1">
                            Cancel
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Edit Discount Modal -->
    @if($showEditModal && $editingDiscount)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-xl bg-white p-6 dark:bg-gray-800">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit Discount Type</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Update discount information</p>
                </div>

                <form wire:submit="updateDiscount" class="space-y-4">
                    <div>
                        <flux:input
                            wire:model="name"
                            label="Discount Name"
                            type="text"
                            required
                            placeholder="e.g., VIP Customer, Wholesale"
                        />
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="discount_per_kg"
                            label="Discount per kg (₦)"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            placeholder="Enter discount amount"
                        />
                        @error('discount_per_kg')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:textarea
                            wire:model="description"
                            label="Description (Optional)"
                            placeholder="Additional notes about this discount type"
                            rows="3"
                        />
                        @error('description')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <flux:checkbox wire:model="is_default" label="Set as Default" />
                    </div>

                    <div class="flex items-center">
                        <flux:checkbox wire:model="is_active" label="Active" />
                    </div>

                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update Discount</span>
                            <span wire:loading>Updating...</span>
                        </flux:button>
                        <flux:button type="button" wire:click="closeEditModal" variant="outline" class="flex-1">
                            Cancel
                        </flux:button>
                    </div>
                </form>
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
