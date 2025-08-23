<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-4">
    <div class="max-w-2xl w-full">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="flex justify-center mb-4">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome to Ruxxen LPG</h1>
            <p class="text-gray-600">Let's get your system up and running in just a few steps</p>
        </div>

        @if($setupCompleted)
            <!-- Setup Completed -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Setup Completed!</h2>
                    <p class="text-gray-600 mb-6">Your Ruxxen LPG system has been successfully configured and is ready to use.</p>
                    
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 class="font-semibold text-gray-900 mb-2">Default Admin Credentials:</h3>
                        <div class="text-sm text-gray-600">
                            <p><strong>Email:</strong> {{ $adminEmail }}</p>
                            <p><strong>Password:</strong> {{ $adminPassword }}</p>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mb-6">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm">Please change the default admin password after your first login!</span>
                    </div>
                    
                    <button wire:click="redirectToDashboard" class="btn btn-primary btn-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Go to Dashboard
                    </button>
                </div>
            </div>
        @else
            <!-- Setup Form -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                @if($errorMessage)
                    <div class="alert alert-error mb-6">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ $errorMessage }}</span>
                    </div>
                @endif

                @if($isSetupInProgress)
                    <!-- Setup Progress -->
                    <div class="text-center">
                        <div class="loading loading-spinner loading-lg mb-4"></div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Setting up your system...</h3>
                        <p class="text-gray-600 mb-6">{{ $currentStep }}</p>
                        
                        <!-- Progress Steps -->
                        <div class="space-y-3">
                            @foreach($setupSteps as $step)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <span class="text-sm font-medium text-gray-700">{{ $step['step'] }}</span>
                                    <div class="flex items-center">
                                        @if($step['status'] === 'running')
                                            <div class="loading loading-spinner loading-sm"></div>
                                        @elseif($step['status'] === 'completed')
                                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        @elseif($step['status'] === 'skipped')
                                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                            </svg>
                                        @elseif($step['status'] === 'failed')
                                            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <!-- Setup Form -->
                    <form wire:submit="startSetup">
                        <div class="space-y-6">
                            <!-- Admin User Section -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Admin User Setup</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="adminName" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                        <input type="text" id="adminName" wire:model="adminName" class="input input-bordered w-full" placeholder="System Administrator">
                                        @error('adminName') <span class="text-error text-sm">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label for="adminEmail" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                                        <input type="email" id="adminEmail" wire:model="adminEmail" class="input input-bordered w-full" placeholder="admin@ruxxen.com">
                                        @error('adminEmail') <span class="text-error text-sm">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label for="adminPassword" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                                    <input type="password" id="adminPassword" wire:model="adminPassword" class="input input-bordered w-full" placeholder="Enter a strong password">
                                    @error('adminPassword') <span class="text-error text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- Company Settings Section -->
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-4">Company Information</h3>
                                <div>
                                    <label for="companyName" class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                                    <input type="text" id="companyName" wire:model="companyName" class="input input-bordered w-full" placeholder="Your Company Name">
                                    @error('companyName') <span class="text-error text-sm">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <!-- System Information -->
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h4 class="font-semibold text-blue-900 mb-2">What will be set up?</h4>
                                <ul class="text-sm text-blue-800 space-y-1">
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Database tables and structure
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Default admin user account
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Company settings and configuration
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        System security and access controls
                                    </li>
                                </ul>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-center">
                                <button type="submit" class="btn btn-primary btn-lg" wire:loading.attr="disabled">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Start Setup
                                </button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        @endif

        <!-- Footer -->
        <div class="text-center mt-8">
            <p class="text-sm text-gray-500">Ruxxen LPG Management System</p>
        </div>
    </div>
</div>
