<?php

use App\Models\CompanySetting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new class extends Component {
    public $settings;

    public function mount()
    {
        $this->settings = CompanySetting::getSettings();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Company Information')" :subheading="__('View company details and contact information')">
        <div class="space-y-6">
            <!-- Company Information -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Company Information</h3>
                
                <div class="space-y-4">
                    @if($settings->logo_url)
                        <div class="flex items-center space-x-4">
                            <img src="{{ $settings->logo_url }}" alt="Company Logo" class="h-16 w-16 object-contain border rounded-lg">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $settings->company_name }}</h4>
                            </div>
                        </div>
                    @else
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $settings->company_name }}</h4>
                        </div>
                    @endif

                    @if($settings->company_address)
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">Address</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $settings->company_address }}</p>
                        </div>
                    @endif

                    @if($settings->company_phone)
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">Phone</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $settings->company_phone }}</p>
                        </div>
                    @endif

                    @if($settings->company_email)
                        <div>
                            <label class="block text-sm font-medium text-gray-600 dark:text-gray-400">Email</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-white">{{ $settings->company_email }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- SMTP Configuration Status -->
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Email Configuration</h3>
                
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">SMTP Host</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ $settings->smtp_host ?: 'Not configured' }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">SMTP Port</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ $settings->smtp_port ?: 'Not configured' }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">SMTP Username</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ $settings->smtp_username ?: 'Not configured' }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">SMTP Encryption</span>
                        <span class="text-sm text-gray-900 dark:text-white">{{ ucfirst($settings->smtp_encryption) ?: 'Not configured' }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-600 dark:text-gray-400">Password Status</span>
                        <span class="text-sm {{ $settings->smtp_password ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                            {{ $settings->smtp_password ? 'Configured' : 'Not configured' }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                    <p class="text-sm text-blue-700 dark:text-blue-300">
                        <strong>Note:</strong> Only administrators can modify these settings. Contact your system administrator for any changes.
                    </p>
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>
