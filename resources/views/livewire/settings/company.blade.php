<?php

use App\Models\CompanySetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;
    #[Validate('required|string|max:255')]
    public string $company_name = '';

    #[Validate('nullable|string|max:500')]
    public ?string $company_address = null;

    #[Validate('nullable|string|max:50')]
    public ?string $company_phone = null;

    #[Validate('nullable|email|max:255')]
    public ?string $company_email = null;

    #[Validate('nullable|image|max:2048')] // 2MB max
    public ?TemporaryUploadedFile $logo = null;

    #[Validate('nullable|string|max:255')]
    public ?string $smtp_host = null;

    #[Validate('nullable|integer|min:1|max:65535')]
    public ?int $smtp_port = null;

    #[Validate('nullable|string|max:255')]
    public ?string $smtp_username = null;

    #[Validate('nullable|string|max:255')]
    public ?string $smtp_password = null;

    #[Validate('nullable|in:ssl,tls,none')]
    public ?string $smtp_encryption = null;

    public $settings;
    public $currentLogoUrl;

    public function mount()
    {
        // Check if user is admin
        if (!Auth::user()->isAdmin()) {
            return redirect()->route('settings.company.view');
        }

        $this->settings = CompanySetting::getSettings();
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $this->company_name = $this->settings->company_name;
        $this->company_address = $this->settings->company_address;
        $this->company_phone = $this->settings->company_phone;
        $this->company_email = $this->settings->company_email;
        $this->smtp_host = $this->settings->smtp_host;
        $this->smtp_port = $this->settings->smtp_port;
        $this->smtp_username = $this->settings->smtp_username;
        $this->smtp_encryption = $this->settings->smtp_encryption;
        $this->currentLogoUrl = $this->settings->logo_url;
    }

    public function updatedLogo()
    {
        // Reset the logo URL when a new file is selected
        $this->currentLogoUrl = null;
    }

    public function updateGeneralSettings()
    {
        $this->validate([
            'company_name' => 'required|string|max:255',
            'company_address' => 'nullable|string|max:500',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email|max:255',
            'logo' => 'nullable|image|max:2048',
        ]);

        try {
            $updateData = [
                'company_name' => $this->company_name,
                'company_address' => $this->company_address,
                'company_phone' => $this->company_phone,
                'company_email' => $this->company_email,
            ];

            // Handle logo upload
            if ($this->logo) {
                // Delete old logo if exists
                if ($this->settings->logo_path) {
                    Storage::disk('public')->delete($this->settings->logo_path);
                }

                // Store new logo
                $logoPath = $this->logo->store('logos', 'public');
                $updateData['logo_path'] = $logoPath;
            }

            $this->settings->update($updateData);
            $this->settings->refresh();
            
            // Clear cache
            \App\Services\CompanySettingsService::clearCache();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'General settings updated successfully.'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update general settings. Please try again.'
            ]);
        }
    }

    public function updateSmtpSettings()
    {
        $this->validate([
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|integer|min:1|max:65535',
            'smtp_username' => 'nullable|string|max:255',
            'smtp_password' => 'nullable|string|max:255',
            'smtp_encryption' => 'nullable|in:ssl,tls,none',
        ]);

        try {
            $updateData = [
                'smtp_host' => $this->smtp_host,
                'smtp_port' => $this->smtp_port,
                'smtp_username' => $this->smtp_username,
                'smtp_encryption' => $this->smtp_encryption,
            ];

            // Only update password if provided
            if ($this->smtp_password) {
                $updateData['smtp_password'] = $this->smtp_password;
            }

            $this->settings->update($updateData);

            // Clear cache
            \App\Services\CompanySettingsService::clearCache();

            $this->dispatch('notify', [
                'type' => 'success',
                'message' => 'SMTP settings updated successfully.'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to update SMTP settings. Please try again.'
            ]);
        }
    }

    public function removeLogo()
    {
        try {
            if ($this->settings->logo_path) {
                Storage::disk('public')->delete($this->settings->logo_path);
                $this->settings->update(['logo_path' => null]);
                $this->settings->refresh();

                // Clear cache
                \App\Services\CompanySettingsService::clearCache();

                $this->dispatch('notify', [
                    'type' => 'success',
                    'message' => 'Logo removed successfully.'
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Failed to remove logo. Please try again.'
            ]);
        }
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Company Settings')" :subheading="__('Manage business details, logo branding, and SMTP email server credentials')">
        <div class="space-y-8 my-4">
            <!-- General Settings -->
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-xl bg-orange-500/10 flex items-center justify-center text-orange-600 dark:text-orange-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Business Details</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Appears on printed receipts and customer invoices</p>
                    </div>
                </div>
                
                <form wire:submit="updateGeneralSettings" class="space-y-4 pt-2">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model="company_name"
                            label="Company Name"
                            type="text"
                            required
                            placeholder="e.g. Ruxxen LPG Depot"
                        />

                        <flux:input
                            wire:model="company_email"
                            label="Company Email"
                            type="email"
                            placeholder="info@ruxxen.com"
                        />
                    </div>

                    <flux:input
                        wire:model="company_phone"
                        label="Company Phone"
                        type="text"
                        placeholder="+234 800 000 0000"
                    />

                    <flux:textarea
                        wire:model="company_address"
                        label="Company Address"
                        placeholder="Enter full physical depot address"
                        rows="2"
                    />

                    <!-- Logo Upload Section -->
                    <div class="rounded-2xl border border-slate-200/80 dark:border-slate-800/80 bg-slate-50/60 dark:bg-slate-950/40 p-4 space-y-3">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                            Company Branding Logo
                        </label>
                        
                        @if($settings->logo_url)
                            <div class="flex items-center gap-4 p-2 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
                                <img src="{{ $settings->logo_url }}" alt="Company Logo" class="h-14 w-14 object-contain rounded-lg">
                                <div>
                                    <p class="text-xs font-medium text-slate-900 dark:text-white">Current Active Logo</p>
                                    <button type="button" wire:click="removeLogo" class="mt-1 text-xs font-semibold text-red-600 dark:text-red-400 hover:underline cursor-pointer">
                                        Remove Logo
                                    </button>
                                </div>
                            </div>
                        @endif

                        <flux:input
                            wire:model="logo"
                            type="file"
                            accept="image/*"
                        />
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">
                            Recommended format: PNG or JPG (Max 2MB)
                        </p>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 px-6 py-2.5 text-xs font-semibold text-white transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update Business Details</span>
                            <span wire:loading>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- SMTP Settings -->
            <div class="pt-8 border-t border-slate-200/80 dark:border-slate-800/80 space-y-4">
                <div class="flex items-center gap-2">
                    <div class="h-8 w-8 rounded-xl bg-blue-500/10 flex items-center justify-center text-blue-600 dark:text-blue-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">SMTP Mail Server</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Configure outbound email credentials for receipt dispatches</p>
                    </div>
                </div>
                
                <form wire:submit="updateSmtpSettings" class="space-y-4 pt-2">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model="smtp_host"
                            label="SMTP Host"
                            type="text"
                            placeholder="e.g. smtp.mailgun.org"
                        />

                        <flux:input
                            wire:model="smtp_port"
                            label="SMTP Port"
                            type="number"
                            placeholder="587"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input
                            wire:model="smtp_username"
                            label="SMTP Username"
                            type="text"
                            placeholder="postmaster@ruxxen.com"
                        />

                        <flux:input
                            wire:model="smtp_password"
                            label="SMTP Password"
                            type="password"
                            placeholder="••••••••"
                        />
                    </div>

                    <div>
                        <flux:select wire:model="smtp_encryption" label="Encryption Protocol">
                            <option value="">Select encryption</option>
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="none">None</option>
                        </flux:select>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button type="submit" class="rounded-xl bg-gradient-to-r from-orange-500 via-amber-500 to-orange-600 hover:from-orange-600 hover:to-amber-700 px-6 py-2.5 text-xs font-semibold text-white transition-all shadow-lg shadow-orange-500/20 active:scale-[0.99] cursor-pointer" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update SMTP Credentials</span>
                            <span wire:loading>Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </x-settings.layout>
</section>

