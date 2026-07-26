<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-4">
    <div>
        <h3 class="text-base font-bold text-red-600 dark:text-red-400">{{ __('Delete Account') }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Permanently remove your user account and all assigned permissions') }}</p>
    </div>

    <div class="rounded-2xl border border-red-500/30 bg-red-500/10 p-4 flex items-center justify-between">
        <p class="text-xs text-red-700 dark:text-red-300 font-medium">This action cannot be undone. Please proceed with caution.</p>
        
        <flux:modal.trigger name="confirm-user-deletion">
            <button type="button" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" class="rounded-xl bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-xs font-bold transition-all shadow-md shadow-red-500/20 cursor-pointer">
                {{ __('Delete Account') }}
            </button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg" class="text-slate-900 dark:text-white font-bold">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading class="text-xs text-slate-500 dark:text-slate-400 mt-2">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </flux:subheading>
            </div>

            <flux:input wire:model="password" :label="__('Password')" type="password" />

            <div class="flex justify-end gap-3 pt-2">
                <flux:modal.close>
                    <button type="button" class="rounded-xl border border-slate-300 dark:border-slate-700 px-4 py-2 text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer">
                        {{ __('Cancel') }}
                    </button>
                </flux:modal.close>

                <button type="submit" class="rounded-xl bg-red-600 hover:bg-red-700 text-white px-4 py-2 text-xs font-bold transition-all shadow-md shadow-red-500/20 cursor-pointer">
                    {{ __('Confirm Delete') }}
                </button>
            </div>
        </form>
    </flux:modal>
</section>

