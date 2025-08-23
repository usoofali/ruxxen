<?php

declare(strict_types=1);

namespace App\Livewire\Setup;

use App\Models\SystemConfiguration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Attributes\Rule;

class Welcome extends Component
{
    #[Rule('required|email')]
    public string $adminEmail = 'admin@ruxxen.com';

    #[Rule('required|min:6')]
    public string $adminPassword = 'admin123';

    #[Rule('required|min:2')]
    public string $adminName = 'System Administrator';

    #[Rule('required|min:2')]
    public string $companyName = 'Ruxxen LPG';

    public bool $isSetupInProgress = false;
    public array $setupSteps = [];
    public string $currentStep = '';
    public string $errorMessage = '';
    public bool $setupCompleted = false;

    public function mount(): void
    {
        // Check if system is already configured
        if (Schema::hasTable('system_configurations') && SystemConfiguration::isConfigured()) {
            $this->setupCompleted = true;
        }
    }

    public function startSetup(): void
    {
        $this->validate();
        $this->isSetupInProgress = true;
        $this->errorMessage = '';
        $this->setupSteps = [];

        try {
            DB::beginTransaction();

            // Step 1: Run migrations
            $this->runMigrations();

            // Step 2: Create admin user
            $this->createAdminUser();

            // Step 3: Create company settings
            $this->createCompanySettings();

            // Step 4: Mark system as configured
            $this->markSystemConfigured();

            DB::commit();

            $this->setupCompleted = true;
            $this->isSetupInProgress = false;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->errorMessage = 'Setup failed: ' . $e->getMessage();
            $this->isSetupInProgress = false;
        }
    }

    private function runMigrations(): void
    {
        $this->currentStep = 'Running database migrations...';
        $this->setupSteps[] = ['step' => 'Database Migration', 'status' => 'running'];

        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->setupSteps[] = ['step' => 'Database Migration', 'status' => 'completed'];
            SystemConfiguration::markSetupStepCompleted('database_migrated');
        } catch (\Exception $e) {
            $this->setupSteps[] = ['step' => 'Database Migration', 'status' => 'failed'];
            throw new \Exception('Migration failed: ' . $e->getMessage());
        }
    }

    private function createAdminUser(): void
    {
        $this->currentStep = 'Creating admin user...';
        $this->setupSteps[] = ['step' => 'Admin User Creation', 'status' => 'running'];

        try {
            // Check if admin user already exists
            if (Schema::hasTable('users')) {
                $existingUser = DB::table('users')->where('email', $this->adminEmail)->first();
                if ($existingUser) {
                    $this->setupSteps[] = ['step' => 'Admin User Creation', 'status' => 'skipped'];
                    SystemConfiguration::markSetupStepCompleted('admin_created');
                    return;
                }
            }

            DB::table('users')->insert([
                'name' => $this->adminName,
                'email' => $this->adminEmail,
                'password' => Hash::make($this->adminPassword),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->setupSteps[] = ['step' => 'Admin User Creation', 'status' => 'completed'];
            SystemConfiguration::markSetupStepCompleted('admin_created');
        } catch (\Exception $e) {
            $this->setupSteps[] = ['step' => 'Admin User Creation', 'status' => 'failed'];
            throw new \Exception('Admin user creation failed: ' . $e->getMessage());
        }
    }

    private function createCompanySettings(): void
    {
        $this->currentStep = 'Creating company settings...';
        $this->setupSteps[] = ['step' => 'Company Settings', 'status' => 'running'];

        try {
            // Check if company settings already exist
            if (Schema::hasTable('company_settings') && DB::table('company_settings')->count() > 0) {
                $this->setupSteps[] = ['step' => 'Company Settings', 'status' => 'skipped'];
                SystemConfiguration::markSetupStepCompleted('company_configured');
                return;
            }

            DB::table('company_settings')->insert([
                'company_name' => $this->companyName,
                'address' => 'Default Address',
                'phone' => '+1234567890',
                'email' => 'info@ruxxen.com',
                'tax_id' => 'TAX-001',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'logo_path' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->setupSteps[] = ['step' => 'Company Settings', 'status' => 'completed'];
            SystemConfiguration::markSetupStepCompleted('company_configured');
        } catch (\Exception $e) {
            $this->setupSteps[] = ['step' => 'Company Settings', 'status' => 'failed'];
            throw new \Exception('Company settings creation failed: ' . $e->getMessage());
        }
    }

    private function markSystemConfigured(): void
    {
        $this->currentStep = 'Finalizing setup...';
        $this->setupSteps[] = ['step' => 'System Configuration', 'status' => 'running'];

        try {
            SystemConfiguration::markAsConfigured();
            $this->setupSteps[] = ['step' => 'System Configuration', 'status' => 'completed'];
        } catch (\Exception $e) {
            $this->setupSteps[] = ['step' => 'System Configuration', 'status' => 'failed'];
            throw new \Exception('System configuration failed: ' . $e->getMessage());
        }
    }

    public function redirectToDashboard(): void
    {
        redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.setup.welcome')
            ->layout('layouts.auth.simple');
    }
}
