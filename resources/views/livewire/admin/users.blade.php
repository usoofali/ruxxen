<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('required|email|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8')]
    public string $password = '';

    #[Validate('required|in:admin,cashier')]
    public string $role = 'cashier';

    public bool $is_active = true;

    public $search = '';
    public $roleFilter = '';
    public $statusFilter = '';
    public $showCreateModal = false;
    public $showEditModal = false;
    public $editingUser = null;

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedRoleFilter()
    {
        $this->resetPage();
    }

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function getUsersProperty()
    {
        $query = User::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->roleFilter) {
            $query->where('role', $this->roleFilter);
        }

        if ($this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === 'active');
        }

        return $query->latest()->paginate(10);
    }

    public function openCreateModal()
    {
        $this->reset(['name', 'email', 'password', 'role', 'is_active']);
        $this->showCreateModal = true;
    }

    public function closeCreateModal()
    {
        $this->showCreateModal = false;
    }

    public function createUser()
    {
        $this->validate();

        try {
            User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'role' => $this->role,
                'is_active' => $this->is_active,
                'email_verified_at' => now(),
            ]);

            $this->closeCreateModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'User created successfully.'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to create user. Please try again.'
            ]);
        }
    }

    public function openEditModal($userId)
    {
        $this->editingUser = User::find($userId);
        if ($this->editingUser) {
            $this->name = $this->editingUser->name;
            $this->email = $this->editingUser->email;
            $this->role = $this->editingUser->role;
            $this->is_active = $this->editingUser->is_active;
            $this->password = ''; // Don't populate password
            $this->showEditModal = true;
        }
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingUser = null;
    }

    public function updateUser()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $this->editingUser->id,
            'role' => 'required|in:admin,cashier',
            'password' => 'nullable|string|min:8',
        ]);

        try {
            $updateData = [
                'name' => $this->name,
                'email' => $this->email,
                'role' => $this->role,
                'is_active' => $this->is_active,
            ];

            if ($this->password) {
                $updateData['password'] = Hash::make($this->password);
            }

            $this->editingUser->update($updateData);

            $this->closeEditModal();
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'User updated successfully.'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update user. Please try again.'
            ]);
        }
    }

    public function toggleUserStatus($userId)
    {
        $user = User::find($userId);
        if ($user && $user->id !== auth()->id()) {
            $user->update(['is_active' => !$user->is_active]);
            
            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'User status updated successfully.'
            ]);
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot deactivate your own account.'
            ]);
        }
    }

    public function deleteUser($userId)
    {
        $user = User::find($userId);
        if ($user && $user->id !== auth()->id()) {
            try {
                $user->delete();
                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'User deleted successfully.'
                ]);
            } catch (\Exception $e) {
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => 'Cannot delete user with existing transactions.'
                ]);
            }
        } else {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Cannot delete your own account.'
            ]);
        }
    }

    public function clearFilters()
    {
        $this->reset(['search', 'roleFilter', 'statusFilter']);
        $this->resetPage();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 p-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">User Management</h1>
            <p class="text-gray-600 dark:text-gray-400">Manage cashier accounts and permissions</p>
        </div>
        <div class="flex flex-col gap-2 sm:flex-row sm:gap-3 sm:flex-shrink-0">
            <flux:button wire:click="clearFilters" variant="outline" class="w-full sm:w-auto">
                Clear Filters
            </flux:button>
            <flux:button wire:click="openCreateModal" variant="primary" class="w-full sm:w-auto">
                Add User
            </flux:button>
        </div>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
            <!-- Search -->
            <div>
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    label="Search"
                    placeholder="Search users..."
                    icon="magnifying-glass"
                />
            </div>

            <!-- Role Filter -->
            <div>
                <flux:select
                    wire:model.live="roleFilter"
                    label="Role Filter"
                >
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="cashier">Cashier</option>
                </flux:select>
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

            <!-- User Count -->
            <div class="flex items-end">
                <div class="text-sm text-gray-600 dark:text-gray-400">
                    <div>Total Users: {{ $this->users->total() }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table -->
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Users</h3>
        </div>
        <div class="p-6">
            @if($this->users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Created</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            @foreach($this->users as $user)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10">
                                                <div class="h-10 w-10 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center">
                                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                                        {{ $user->initials() }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                            {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' }}">
                                            {{ ucfirst($user->role) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex rounded-full px-2 text-xs font-semibold leading-5 
                                            {{ $user->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $user->created_at->format('M d, Y') }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex gap-2">
                                            <flux:button 
                                                wire:click="openEditModal({{ $user->id }})" 
                                                variant="outline" 
                                                size="sm"
                                            >
                                                Edit
                                            </flux:button>
                                            @if($user->id !== auth()->id())
                                                <flux:button 
                                                    wire:click="toggleUserStatus({{ $user->id }})" 
                                                    variant="outline" 
                                                    size="sm"
                                                    class="{{ $user->is_active ? 'text-red-600 hover:text-red-700' : 'text-green-600 hover:text-green-700' }}"
                                                >
                                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                                </flux:button>
                                                <flux:button 
                                                    wire:click="deleteUser({{ $user->id }})" 
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
                    {{ $this->users->links() }}
                </div>
            @else
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No users found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Create User Modal -->
    @if($showCreateModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-xl bg-white p-6 dark:bg-gray-800">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Add New User</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Create a new user account</p>
                </div>

                <form wire:submit="createUser" class="space-y-4">
                    <div>
                        <flux:input
                            wire:model="name"
                            label="Full Name"
                            type="text"
                            required
                            placeholder="Enter full name"
                        />
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="email"
                            label="Email Address"
                            type="email"
                            required
                            placeholder="Enter email address"
                        />
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="password"
                            label="Password"
                            type="password"
                            required
                            placeholder="Enter password"
                        />
                        @error('password')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:select wire:model="role" label="Role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                        </flux:select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <flux:checkbox wire:model="is_active" label="Active Account" />
                    </div>

                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove>Create User</span>
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

    <!-- Edit User Modal -->
    @if($showEditModal && $editingUser)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div class="w-full max-w-md rounded-xl bg-white p-6 dark:bg-gray-800">
                <div class="mb-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">Edit User</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Update user information</p>
                </div>

                <form wire:submit="updateUser" class="space-y-4">
                    <div>
                        <flux:input
                            wire:model="name"
                            label="Full Name"
                            type="text"
                            required
                            placeholder="Enter full name"
                        />
                        @error('name')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="email"
                            label="Email Address"
                            type="email"
                            required
                            placeholder="Enter email address"
                        />
                        @error('email')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input
                            wire:model="password"
                            label="Password (leave blank to keep current)"
                            type="password"
                            placeholder="Enter new password"
                        />
                        @error('password')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:select wire:model="role" label="Role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Admin</option>
                        </flux:select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <flux:checkbox wire:model="is_active" label="Active Account" />
                    </div>

                    <div class="flex gap-3">
                        <flux:button type="submit" variant="primary" class="flex-1" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update User</span>
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
