<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Setup</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-base-200">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-4xl">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-primary-content" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-base-content mb-2">Welcome to Ruxxen LPG</h1>
                <p class="text-base-content/70">Let's get your system up and running</p>
            </div>

            <!-- Setup Card -->
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body">
                    <!-- Progress Bar -->
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-sm font-medium text-base-content">Setup Progress</span>
                            <span class="text-sm text-base-content/70" id="progress-text">
                                {{ $progress['completed_steps'] }}/{{ $progress['total_steps'] }} steps completed
                            </span>
                        </div>
                        <progress class="progress progress-primary w-full" value="{{ ($progress['completed_steps'] / $progress['total_steps']) * 100 }}" max="100"></progress>
                    </div>

                    <!-- Setup Steps -->
                    <div class="space-y-4 mb-6" id="setup-steps">
                        <div class="flex items-center p-3 rounded-lg border" data-step="database_migrated">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-6 h-6 rounded-full bg-base-300 flex items-center justify-center">
                                    <span class="text-xs font-medium">1</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-medium text-base-content">Database Setup</h3>
                                <p class="text-sm text-base-content/70">Initialize database tables and structure</p>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="step-status" data-status="{{ $progress['database_migrated'] ? 'completed' : 'pending' }}">
                                    @if($progress['database_migrated'])
                                        <svg class="w-5 h-5 text-success" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @else
                                        <div class="w-5 h-5 rounded-full bg-base-300"></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center p-3 rounded-lg border" data-step="admin_created">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-6 h-6 rounded-full bg-base-300 flex items-center justify-center">
                                    <span class="text-xs font-medium">2</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-medium text-base-content">Admin Account</h3>
                                <p class="text-sm text-base-content/70">Create default administrator account</p>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="step-status" data-status="{{ $progress['admin_created'] ? 'completed' : 'pending' }}">
                                    @if($progress['admin_created'])
                                        <svg class="w-5 h-5 text-success" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @else
                                        <div class="w-5 h-5 rounded-full bg-base-300"></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center p-3 rounded-lg border" data-step="company_configured">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-6 h-6 rounded-full bg-base-300 flex items-center justify-center">
                                    <span class="text-xs font-medium">3</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-medium text-base-content">Company Settings</h3>
                                <p class="text-sm text-base-content/70">Initialize company information and settings</p>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="step-status" data-status="{{ $progress['company_configured'] ? 'completed' : 'pending' }}">
                                    @if($progress['company_configured'])
                                        <svg class="w-5 h-5 text-success" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @else
                                        <div class="w-5 h-5 rounded-full bg-base-300"></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center p-3 rounded-lg border" data-step="system_configured">
                            <div class="flex-shrink-0 mr-3">
                                <div class="w-6 h-6 rounded-full bg-base-300 flex items-center justify-center">
                                    <span class="text-xs font-medium">4</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-medium text-base-content">System Configuration</h3>
                                <p class="text-sm text-base-content/70">Finalize system setup and optimization</p>
                            </div>
                            <div class="flex-shrink-0">
                                <div class="step-status" data-status="{{ $progress['system_configured'] ? 'completed' : 'pending' }}">
                                    @if($progress['system_configured'])
                                        <svg class="w-5 h-5 text-success" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                    @else
                                        <div class="w-5 h-5 rounded-full bg-base-300"></div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-center space-x-4">
                        <button 
                            id="run-setup-btn"
                            class="btn btn-primary"
                            onclick="runSetup()"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Start Setup
                        </button>
                        
                        @if(config('app.env') !== 'production')
                        <button 
                            id="reset-setup-btn"
                            class="btn btn-outline btn-warning"
                            onclick="resetSetup()"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Reset Setup
                        </button>
                        @endif
                    </div>

                    <!-- Status Messages -->
                    <div id="status-messages" class="mt-6 space-y-3"></div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-8 text-sm text-base-content/50">
                <p>Ruxxen LPG Gas Plant Management System</p>
                <p class="mt-1">Version 1.0.0</p>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loading-modal" class="modal">
        <div class="modal-box">
            <div class="flex items-center justify-center">
                <div class="loading loading-spinner loading-lg text-primary"></div>
            </div>
            <h3 class="font-bold text-lg text-center mt-4">Setting up your system...</h3>
            <p class="py-4 text-center text-base-content/70">Please wait while we configure everything for you.</p>
        </div>
    </div>

    <script>
        // CSRF Token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Run Setup Function
        async function runSetup() {
            const runBtn = document.getElementById('run-setup-btn');
            const statusMessages = document.getElementById('status-messages');
            const loadingModal = document.getElementById('loading-modal');

            // Disable button and show loading
            runBtn.disabled = true;
            runBtn.innerHTML = '<span class="loading loading-spinner loading-sm"></span> Running Setup...';
            loadingModal.classList.add('modal-open');

            try {
                const response = await fetch('{{ route("setup.run") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    // Show success message
                    statusMessages.innerHTML = `
                        <div class="alert alert-success">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${data.message}</span>
                        </div>
                    `;

                    // Update progress
                    updateProgress(data.steps);

                    // Redirect after delay
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 2000);

                } else {
                    // Show error message
                    statusMessages.innerHTML = `
                        <div class="alert alert-error">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${data.message}</span>
                        </div>
                    `;

                    // Show detailed errors if available
                    if (data.errors && data.errors.length > 0) {
                        data.errors.forEach(error => {
                            statusMessages.innerHTML += `
                                <div class="alert alert-warning">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                    </svg>
                                    <span>${error}</span>
                                </div>
                            `;
                        });
                    }
                }

            } catch (error) {
                statusMessages.innerHTML = `
                    <div class="alert alert-error">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>An unexpected error occurred. Please try again.</span>
                    </div>
                `;
            } finally {
                // Re-enable button and hide loading
                runBtn.disabled = false;
                runBtn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>Start Setup';
                loadingModal.classList.remove('modal-open');
            }
        }

        // Reset Setup Function
        async function resetSetup() {
            if (!confirm('Are you sure you want to reset the setup? This will clear all configuration data.')) {
                return;
            }

            const resetBtn = document.getElementById('reset-setup-btn');
            const statusMessages = document.getElementById('status-messages');

            resetBtn.disabled = true;
            resetBtn.innerHTML = '<span class="loading loading-spinner loading-sm"></span> Resetting...';

            try {
                const response = await fetch('{{ route("setup.reset") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    statusMessages.innerHTML = `
                        <div class="alert alert-success">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${data.message}</span>
                        </div>
                    `;

                    // Reload page after delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    statusMessages.innerHTML = `
                        <div class="alert alert-error">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>${data.message}</span>
                        </div>
                    `;
                }

            } catch (error) {
                statusMessages.innerHTML = `
                    <div class="alert alert-error">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>An unexpected error occurred. Please try again.</span>
                    </div>
                `;
            } finally {
                resetBtn.disabled = false;
                resetBtn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>Reset Setup';
            }
        }

        // Update Progress Function
        function updateProgress(steps) {
            steps.forEach(step => {
                const stepElement = document.querySelector(`[data-step="${step.name.toLowerCase().replace(/\s+/g, '_')}"]`);
                if (stepElement) {
                    const statusElement = stepElement.querySelector('.step-status');
                    if (step.status === 'success' || step.status === 'completed') {
                        statusElement.innerHTML = `
                            <svg class="w-5 h-5 text-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        `;
                    } else if (step.status === 'error') {
                        statusElement.innerHTML = `
                            <svg class="w-5 h-5 text-error" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        `;
                    }
                }
            });
        }
    </script>
</body>
</html>
