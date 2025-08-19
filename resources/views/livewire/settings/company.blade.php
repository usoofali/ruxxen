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

    <x-settings.layout :heading="__('Company Settings')" :subheading="__('Manage company information and email configuration')">
        <!-- General Settings -->
        <div class="space-y-6">
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">General Settings</h3>
                
                <form wire:submit="updateGeneralSettings" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="company_name"
                            label="Company Name"
                            type="text"
                            required
                            placeholder="Enter company name"
                        />

                        <flux:input
                            wire:model="company_email"
                            label="Company Email"
                            type="email"
                            placeholder="Enter company email"
                        />
                    </div>

                    <flux:input
                        wire:model="company_phone"
                        label="Company Phone"
                        type="text"
                        placeholder="Enter company phone number"
                    />

                    <flux:textarea
                        wire:model="company_address"
                        label="Company Address"
                        placeholder="Enter company address"
                        rows="3"
                    />

                    <!-- Logo Upload -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Company Logo
                            </label>
                            
                            @if($settings->logo_url)
                                <div class="flex items-center space-x-4 mb-4">
                                    <img src="{{ $settings->logo_url }}" alt="Company Logo" class="h-16 w-16 object-contain border rounded-lg">
                                    <flux:button type="button" wire:click="removeLogo" variant="outline" size="sm">
                                        Remove Logo
                                    </flux:button>
                                </div>
                            @endif

                            <flux:input
                                wire:model="logo"
                                type="file"
                                accept="image/*"
                                placeholder="Upload company logo"
                            />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Maximum file size: 2MB. Supported formats: JPG, PNG, GIF
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update General Settings</span>
                            <span wire:loading>Updating...</span>
                        </flux:button>
                    </div>
                </form>
            </div>

            <!-- SMTP Settings -->
            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">SMTP/Email Settings</h3>
                
                <form wire:submit="updateSmtpSettings" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="smtp_host"
                            label="SMTP Host"
                            type="text"
                            placeholder="e.g., smtp.gmail.com"
                        />

                        <flux:input
                            wire:model="smtp_port"
                            label="SMTP Port"
                            type="number"
                            placeholder="e.g., 587"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="smtp_username"
                            label="SMTP Username"
                            type="text"
                            placeholder="Enter SMTP username"
                        />

                        <flux:input
                            wire:model="smtp_password"
                            label="SMTP Password"
                            type="password"
                            placeholder="Enter SMTP password"
                        />
                    </div>

                    <div>
                        <flux:select wire:model="smtp_encryption" label="SMTP Encryption">
                            <option value="">Select encryption</option>
                            <option value="ssl">SSL</option>
                            <option value="tls">TLS</option>
                            <option value="none">None</option>
                        </flux:select>
                    </div>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Update SMTP Settings</span>
                            <span wire:loading>Updating...</span>
                        </flux:button>
                    </div>
                </form>
            </div>
        </div>
    </x-settings.layout>
</section>
