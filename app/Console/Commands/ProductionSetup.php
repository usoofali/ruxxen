<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemConfiguration;
use App\Models\User;
use App\Models\CompanySetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProductionSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'production:setup 
                            {--admin-email=admin@ruxxen.com : Admin email address}
                            {--admin-password=admin123 : Admin password}
                            {--admin-name=System Administrator : Admin full name}
                            {--company-name=Ruxxen LPG : Company name}
                            {--force : Force setup even if already configured}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup the system for production deployment';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🚀 Starting Production Setup for Ruxxen LPG System...');
        
        // Check if already configured
        if (SystemConfiguration::isConfigured() && !$this->option('force')) {
            $this->warn('⚠️  System is already configured. Use --force to re-run setup.');
            return self::SUCCESS;
        }

        try {
            DB::beginTransaction();

            // Step 1: Run database migrations
            $this->runMigrations();

            // Step 2: Create default admin user
            $this->createAdminUser();

            // Step 3: Create default company settings
            $this->createCompanySettings();

            // Step 4: Mark system as configured
            $this->markSystemConfigured();

            DB::commit();

            $this->info('✅ Production setup completed successfully!');
            $this->displaySetupSummary();

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Setup failed: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return self::FAILURE;
        }
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        $this->info('📊 Running database migrations...');
        
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->info('✅ Migrations completed successfully');
            SystemConfiguration::markSetupStepCompleted('database_migrated');
        } catch (\Exception $e) {
            throw new \Exception('Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Create default admin user
     */
    private function createAdminUser(): void
    {
        $this->info('👤 Creating default admin user...');

        $email = $this->option('admin-email');
        $password = $this->option('admin-password');
        $name = $this->option('admin-name');

        // Check if admin user already exists
        if (User::where('email', $email)->exists()) {
            $this->warn("⚠️  Admin user with email {$email} already exists. Skipping...");
            SystemConfiguration::markSetupStepCompleted('admin_created');
            return;
        }

        try {
            $admin = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->info("✅ Admin user created: {$admin->name} ({$admin->email})");
            SystemConfiguration::markSetupStepCompleted('admin_created');
        } catch (\Exception $e) {
            throw new \Exception('Admin user creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Create default company settings
     */
    private function createCompanySettings(): void
    {
        $this->info('🏢 Creating default company settings...');

        $companyName = $this->option('company-name');

        // Check if company settings already exist
        if (CompanySetting::count() > 0) {
            $this->warn('⚠️  Company settings already exist. Skipping...');
            SystemConfiguration::markSetupStepCompleted('company_configured');
            return;
        }

        try {
            CompanySetting::create([
                'company_name' => $companyName,
                'address' => 'Default Address',
                'phone' => '+1234567890',
                'email' => 'info@ruxxen.com',
                'tax_id' => 'TAX-001',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'logo_path' => null,
                'is_active' => true,
            ]);

            $this->info("✅ Company settings created: {$companyName}");
            SystemConfiguration::markSetupStepCompleted('company_configured');
        } catch (\Exception $e) {
            throw new \Exception('Company settings creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Mark system as configured
     */
    private function markSystemConfigured(): void
    {
        $this->info('✅ Marking system as configured...');
        SystemConfiguration::markAsConfigured();
        $this->info('✅ System configuration completed');
    }

    /**
     * Display setup summary
     */
    private function displaySetupSummary(): void
    {
        $this->newLine();
        $this->info('📋 Setup Summary:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        $status = SystemConfiguration::getSetupStatus();
        
        foreach ($status as $step => $completed) {
            $icon = $completed ? '✅' : '❌';
            $stepName = str_replace('_', ' ', ucfirst($step));
            $this->line("{$icon} {$stepName}");
        }

        $this->newLine();
        $this->info('🔑 Default Admin Credentials:');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('Email: ' . $this->option('admin-email'));
        $this->line('Password: ' . $this->option('admin-password'));
        
        $this->newLine();
        $this->warn('⚠️  IMPORTANT: Change the default admin password after first login!');
        $this->newLine();
        $this->info('🎉 Your Ruxxen LPG system is ready for production!');
    }
}
