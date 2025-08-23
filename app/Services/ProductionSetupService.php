<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\CompanySetting;
use App\Models\SystemConfiguration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Exception;

class ProductionSetupService
{
    /**
     * Run complete production setup
     */
    public function runSetup(): array
    {
        $results = [
            'success' => true,
            'steps' => [],
            'errors' => [],
        ];

        try {
            // Step 1: Check database connection
            $this->checkDatabaseConnection($results);

            // Step 2: Run database migrations
            $this->runMigrations($results);

            // Step 3: Create default admin user
            $this->createDefaultAdmin($results);

            // Step 4: Initialize company settings
            $this->initializeCompanySettings($results);

            // Step 5: Set up system configurations
            $this->setupSystemConfigurations($results);

            // Step 6: Mark system as configured
            $this->markSystemConfigured($results);

            // Step 7: Clear caches
            $this->clearCaches($results);

        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            Log::error('Production setup failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $results;
    }

    /**
     * Check if setup is required
     */
    public function isSetupRequired(): bool
    {
        try {
            // Check if system_configurations table exists
            if (!Schema::hasTable('system_configurations')) {
                return true;
            }

            // Check if system is already configured
            return !SystemConfiguration::isConfigured();
        } catch (Exception $e) {
            Log::warning('Could not check setup status: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Get setup progress
     */
    public function getSetupProgress(): array
    {
        try {
            if (!Schema::hasTable('system_configurations')) {
                return [
                    'database_migrated' => false,
                    'admin_created' => false,
                    'company_configured' => false,
                    'system_configured' => false,
                    'total_steps' => 4,
                    'completed_steps' => 0,
                ];
            }

            $progress = SystemConfiguration::getSetupProgress();
            $completedSteps = count(array_filter($progress));
            
            return array_merge($progress, [
                'total_steps' => 4,
                'completed_steps' => $completedSteps,
            ]);
        } catch (Exception $e) {
            return [
                'database_migrated' => false,
                'admin_created' => false,
                'company_configured' => false,
                'system_configured' => false,
                'total_steps' => 4,
                'completed_steps' => 0,
            ];
        }
    }

    /**
     * Check database connection
     */
    private function checkDatabaseConnection(array &$results): void
    {
        try {
            DB::connection()->getPdo();
            $results['steps'][] = [
                'name' => 'Database Connection',
                'status' => 'success',
                'message' => 'Database connection established successfully',
            ];
        } catch (Exception $e) {
            $results['steps'][] = [
                'name' => 'Database Connection',
                'status' => 'error',
                'message' => 'Failed to connect to database: ' . $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Run database migrations
     */
    private function runMigrations(array &$results): void
    {
        try {
            if (SystemConfiguration::getValue('database_migrated', false)) {
                $results['steps'][] = [
                    'name' => 'Database Migrations',
                    'status' => 'skipped',
                    'message' => 'Database migrations already completed',
                ];
                return;
            }

            Artisan::call('migrate', ['--force' => true]);
            
            SystemConfiguration::markStepCompleted('database_migrated');
            
            $results['steps'][] = [
                'name' => 'Database Migrations',
                'status' => 'success',
                'message' => 'Database migrations completed successfully',
            ];
        } catch (Exception $e) {
            $results['steps'][] = [
                'name' => 'Database Migrations',
                'status' => 'error',
                'message' => 'Failed to run migrations: ' . $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Create default admin user
     */
    private function createDefaultAdmin(array &$results): void
    {
        try {
            if (SystemConfiguration::getValue('admin_created', false)) {
                $results['steps'][] = [
                    'name' => 'Default Admin User',
                    'status' => 'skipped',
                    'message' => 'Default admin user already exists',
                ];
                return;
            }

            // Check if admin user already exists
            $adminExists = User::where('role', 'admin')->exists();
            
            if ($adminExists) {
                SystemConfiguration::markStepCompleted('admin_created');
                $results['steps'][] = [
                    'name' => 'Default Admin User',
                    'status' => 'skipped',
                    'message' => 'Admin user already exists in the system',
                ];
                return;
            }

            // Create default admin user
            $admin = User::create([
                'name' => 'System Administrator',
                'email' => 'admin@ruxxenlpg.com',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            SystemConfiguration::markStepCompleted('admin_created');
            
            $results['steps'][] = [
                'name' => 'Default Admin User',
                'status' => 'success',
                'message' => 'Default admin user created successfully (Email: admin@ruxxenlpg.com, Password: admin123)',
            ];
        } catch (Exception $e) {
            $results['steps'][] = [
                'name' => 'Default Admin User',
                'status' => 'error',
                'message' => 'Failed to create admin user: ' . $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Initialize company settings
     */
    private function initializeCompanySettings(array &$results): void
    {
        try {
            if (SystemConfiguration::getValue('company_configured', false)) {
                $results['steps'][] = [
                    'name' => 'Company Settings',
                    'status' => 'skipped',
                    'message' => 'Company settings already initialized',
                ];
                return;
            }

            // Initialize company settings
            CompanySetting::getSettings();
            
            SystemConfiguration::markStepCompleted('company_configured');
            
            $results['steps'][] = [
                'name' => 'Company Settings',
                'status' => 'success',
                'message' => 'Company settings initialized successfully',
            ];
        } catch (Exception $e) {
            $results['steps'][] = [
                'name' => 'Company Settings',
                'status' => 'error',
                'message' => 'Failed to initialize company settings: ' . $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Set up system configurations
     */
    private function setupSystemConfigurations(array &$results): void
    {
        try {
            // Set default system configurations
            SystemConfiguration::setValue('app_name', 'Ruxxen LPG Gas Plant', 'string', 'Application name');
            SystemConfiguration::setValue('app_version', '1.0.0', 'string', 'Application version');
            SystemConfiguration::setValue('setup_date', now()->toISOString(), 'string', 'Date when system was set up');
            SystemConfiguration::setValue('environment', config('app.env'), 'string', 'Current environment');
            
            $results['steps'][] = [
                'name' => 'System Configurations',
                'status' => 'success',
                'message' => 'System configurations set up successfully',
            ];
        } catch (Exception $e) {
            $results['steps'][] = [
                'name' => 'System Configurations',
                'status' => 'error',
                'message' => 'Failed to set up system configurations: ' . $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Mark system as configured
     */
    private function markSystemConfigured(array &$results): void
    {
        try {
            SystemConfiguration::markAsConfigured();
            
            $results['steps'][] = [
                'name' => 'System Configuration',
                'status' => 'success',
                'message' => 'System marked as fully configured',
            ];
        } catch (Exception $e) {
            $results['steps'][] = [
                'name' => 'System Configuration',
                'status' => 'error',
                'message' => 'Failed to mark system as configured: ' . $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Clear application caches
     */
    private function clearCaches(array &$results): void
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');
            
            $results['steps'][] = [
                'name' => 'Cache Clear',
                'status' => 'success',
                'message' => 'Application caches cleared successfully',
            ];
        } catch (Exception $e) {
            $results['steps'][] = [
                'name' => 'Cache Clear',
                'status' => 'warning',
                'message' => 'Some caches could not be cleared: ' . $e->getMessage(),
            ];
            // Don't throw exception for cache clearing failures
        }
    }
}
