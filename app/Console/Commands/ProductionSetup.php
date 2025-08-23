<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ProductionSetupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProductionSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup-production 
                            {--force : Force setup even if already configured}
                            {--skip-migrations : Skip database migrations}
                            {--skip-admin : Skip admin user creation}
                            {--skip-company : Skip company settings initialization}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run production setup for Ruxxen LPG system';

    /**
     * Execute the console command.
     */
    public function handle(ProductionSetupService $setupService): int
    {
        $this->info('🚀 Starting Ruxxen LPG Production Setup...');
        $this->newLine();

        try {
            // Check if setup is required
            if (!$setupService->isSetupRequired() && !$this->option('force')) {
                $this->warn('⚠️  System is already configured!');
                $this->info('Use --force flag to run setup again.');
                return self::SUCCESS;
            }

            if ($this->option('force')) {
                $this->warn('⚠️  Force flag detected - running setup even if already configured.');
            }

            // Show current progress
            $progress = $setupService->getSetupProgress();
            $this->info('📊 Current Setup Progress:');
            $this->table(
                ['Step', 'Status'],
                [
                    ['Database Migrations', $progress['database_migrated'] ? '✅ Completed' : '⏳ Pending'],
                    ['Admin User Creation', $progress['admin_created'] ? '✅ Completed' : '⏳ Pending'],
                    ['Company Settings', $progress['company_configured'] ? '✅ Completed' : '⏳ Pending'],
                    ['System Configuration', $progress['system_configured'] ? '✅ Completed' : '⏳ Pending'],
                ]
            );

            $this->newLine();

            // Confirm setup
            if (!$this->option('force') && !$this->confirm('Do you want to proceed with the setup?')) {
                $this->info('Setup cancelled.');
                return self::SUCCESS;
            }

            $this->info('🔄 Running production setup...');
            $this->newLine();

            // Run setup
            $results = $setupService->runSetup();

            if ($results['success']) {
                $this->info('✅ Setup completed successfully!');
                $this->newLine();

                // Display results
                $this->info('📋 Setup Results:');
                foreach ($results['steps'] as $step) {
                    $status = match ($step['status']) {
                        'success' => '✅',
                        'skipped' => '⏭️',
                        'error' => '❌',
                        'warning' => '⚠️',
                        default => 'ℹ️',
                    };
                    
                    $this->line("  {$status} {$step['name']}: {$step['message']}");
                }

                $this->newLine();
                $this->info('🎉 System is now ready for production use!');
                $this->info('📧 Default admin credentials: admin@ruxxenlpg.com / admin123');
                $this->warn('⚠️  Please change the default admin password after first login!');

                Log::info('Production setup completed successfully via command line', $results);

                return self::SUCCESS;
            } else {
                $this->error('❌ Setup failed!');
                $this->newLine();

                // Display errors
                if (!empty($results['errors'])) {
                    $this->error('Errors:');
                    foreach ($results['errors'] as $error) {
                        $this->error("  - {$error}");
                    }
                }

                // Display step results
                if (!empty($results['steps'])) {
                    $this->info('Step Results:');
                    foreach ($results['steps'] as $step) {
                        $status = match ($step['status']) {
                            'success' => '✅',
                            'skipped' => '⏭️',
                            'error' => '❌',
                            'warning' => '⚠️',
                            default => 'ℹ️',
                        };
                        
                        $this->line("  {$status} {$step['name']}: {$step['message']}");
                    }
                }

                Log::error('Production setup failed via command line', $results);

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error('❌ An unexpected error occurred during setup:');
            $this->error($e->getMessage());
            
            Log::error('Production setup command failed', [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }
}
