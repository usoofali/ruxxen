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

<div class="flex h-full w-full flex-1 flex-col gap-6 p-4 sm:p-6 md:p-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
                    <span class="h-2 w-2 rounded-full bg-orange-500 dark:bg-orange-400 animate-pulse"></span>
                    Access Control
                </span>
            </div>
            <h1 class="text-xl sm:text-xl font-extrabold tracking-tight text-slate-900 dark:text-white">User Management
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400">Manage admin and cashier system access, status, and
                credentials</p>
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
                Add User Account
            </button>
        </div>
    </div>

    <!-- Filters Bar Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 p-6 backdrop-blur-xl shadow-xl overflow-hidden transition-all duration-200">
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600"></div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 items-end">
            <!-- Search -->
            <div>
                <flux:input wire:model.live.debounce.300ms="search" label="Search" placeholder="Search name or email..."
                    icon="magnifying-glass" class="w-full" />
            </div>

            <!-- Role Filter -->
            <div>
                <flux:select wire:model.live="roleFilter" label="Role Filter">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="cashier">Cashier</option>
                </flux:select>
            </div>

            <!-- Status Filter -->
            <div>
                <flux:select wire:model.live="statusFilter" label="Status Filter">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </flux:select>
            </div>

            <!-- User Counter -->
            <div>
                <div
                    class="rounded-xl bg-slate-100/80 dark:bg-slate-800/60 p-2.5 border border-slate-200/80 dark:border-slate-700/60 text-xs font-semibold text-slate-700 dark:text-slate-300 text-center">
                    Total Registered Accounts: <strong
                        class="text-orange-600 dark:text-orange-400">{{ $this->users->total() }}</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Users Table Card -->
    <div
        class="relative rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/80 dark:bg-slate-900/70 backdrop-blur-xl shadow-xl dark:shadow-2xl overflow-hidden">
        <div
            class="border-b border-slate-200/80 dark:border-slate-800/80 px-6 py-5 bg-slate-50/60 dark:bg-slate-950/40">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">System User Directory</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">List of active administrators and point-of-sale
                cashiers</p>
        </div>

        <div class="p-0">
            @if($this->users->count() > 0)
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr
                                class="border-b border-slate-200/80 dark:border-slate-800/60 bg-slate-100/40 dark:bg-slate-950/20 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                <th class="px-6 py-4">User Details</th>
                                <th class="px-6 py-4">Role</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Registered Date</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/60 dark:divide-slate-800/60 text-sm">
                            @foreach($this->users as $user)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="h-10 w-10 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 text-white font-bold text-sm flex items-center justify-center shadow-md shadow-orange-500/20">
                                                {{ $user->initials() }}
                                            </div>
                                            <div>
                                                <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                    {{ $user->name }}
                                                    @if($user->id === auth()->id())
                                                        <span
                                                            class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">You</span>
                                                    @endif
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
                                                    {{ $user->role === 'admin' ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' }}">
                                            {{ ucfirst($user->role) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span
                                            class="inline-flex rounded-full px-3 py-1 text-xs font-semibold 
                                                    {{ $user->is_active ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20' }}">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-xs text-slate-500 dark:text-slate-400">
                                        {{ $user->created_at->format('M d, Y') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="openEditModal({{ $user->id }})"
                                                class="rounded-xl border border-slate-200 dark:border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                                                Edit
                                            </button>
                                            @if($user->id !== auth()->id())
                                                <button wire:click="toggleUserStatus({{ $user->id }})"
                                                    class="rounded-xl border px-3 py-1.5 text-xs font-semibold cursor-pointer transition-colors {{ $user->is_active ? 'border-red-500/30 text-red-600 hover:bg-red-500/10' : 'border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/10' }}">
                                                    {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                                <button wire:click="deleteUser({{ $user->id }})"
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
                    {{ $this->users->links() }}
                </div>
            @else
                <div class="text-center py-12 px-4">
                    <div
                        class="mx-auto h-16 w-16 rounded-full bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 flex items-center justify-center text-slate-400 mb-3">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z">
                            </path>
                        </svg>
                    </div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">No users found</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Try adjusting search or role filters.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Create User Modal -->
    @if($showCreateModal)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div
                class="relative w-full max-w-md rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600">
                </div>

                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Add New User Account</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Create login credentials and assign user role</p>
                </div>

                <form wire:submit="createUser" class="space-y-4">
                    <div>
                        <flux:input wire:model="name" label="Full Name" type="text" required placeholder="e.g. John Doe" />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="email" label="Email Address" type="email" required
                            placeholder="john@ruxxen.com" />
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="password" label="Password" type="password" required
                            placeholder="••••••••" />
                        @error('password')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:select wire:model="role" label="Role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Administrator</option>
                        </flux:select>
                        @error('role')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center pt-1">
                        <flux:checkbox wire:model="is_active" label="Active Account" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold py-2.5 text-sm transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>Create Account</span>
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

    <!-- Edit User Modal -->
    @if($showEditModal && $editingUser)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 backdrop-blur-md p-4 animate-in fade-in duration-200">
            <div
                class="relative w-full max-w-md rounded-3xl border border-slate-200/80 dark:border-slate-800/80 bg-white/95 dark:bg-slate-900/95 p-6 backdrop-blur-xl shadow-2xl overflow-hidden">
                <!-- Top Accent Line -->
                <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600">
                </div>

                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit User Account</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Update account credentials or assign role</p>
                </div>

                <form wire:submit="updateUser" class="space-y-4">
                    <div>
                        <flux:input wire:model="name" label="Full Name" type="text" required placeholder="e.g. John Doe" />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="email" label="Email Address" type="email" required
                            placeholder="john@ruxxen.com" />
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:input wire:model="password" label="New Password (Optional)" type="password"
                            placeholder="Leave blank to keep existing password" />
                        @error('password')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <flux:select wire:model="role" label="Role" required>
                            <option value="cashier">Cashier</option>
                            <option value="admin">Administrator</option>
                        </flux:select>
                        @error('role')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center pt-1">
                        <flux:checkbox wire:model="is_active" label="Active Account" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                            class="flex-1 rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold py-2.5 text-sm transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer"
                            wire:loading.attr="disabled">
                            <span wire:loading.remove>Update User</span>
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