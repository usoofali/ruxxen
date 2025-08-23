<?php

/**
 * Production Setup Script for Ruxxen LPG System
 * 
 * This script performs all necessary setup tasks for production deployment.
 * Run this script after deploying to a new environment.
 * 
 * Usage: php production_setup.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🚀 Ruxxen LPG Production Setup\n";
echo "================================\n\n";

try {
    // Check if system is already configured
    if (Schema::hasTable('system_configurations')) {
        $config = DB::table('system_configurations')->where('key', 'system_configured')->first();
        if ($config && $config->value === '1') {
            echo "⚠️  System is already configured. Setup not needed.\n";
            exit(0);
        }
    }

    echo "📊 Step 1: Running database migrations...\n";
    Artisan::call('migrate', ['--force' => true]);
    echo "✅ Migrations completed successfully\n\n";

    echo "👤 Step 2: Creating default admin user...\n";
    
    // Check if admin user already exists
    $adminEmail = 'admin@ruxxen.com';
    $existingUser = DB::table('users')->where('email', $adminEmail)->first();
    
    if ($existingUser) {
        echo "⚠️  Admin user already exists. Skipping...\n";
    } else {
        DB::table('users')->insert([
            'name' => 'System Administrator',
            'email' => $adminEmail,
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        echo "✅ Admin user created: admin@ruxxen.com\n";
    }

    echo "\n🏢 Step 3: Creating default company settings...\n";
    
    // Check if company settings already exist
    if (Schema::hasTable('company_settings') && DB::table('company_settings')->count() > 0) {
        echo "⚠️  Company settings already exist. Skipping...\n";
    } else {
        DB::table('company_settings')->insert([
            'company_name' => 'Ruxxen LPG',
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
        echo "✅ Company settings created\n";
    }

    echo "\n⚙️  Step 4: Marking system as configured...\n";
    
    // Create system configuration entries
    $configs = [
        ['key' => 'database_migrated', 'value' => '1', 'type' => 'boolean', 'description' => 'Database migration completed'],
        ['key' => 'admin_created', 'value' => '1', 'type' => 'boolean', 'description' => 'Admin user created'],
        ['key' => 'company_configured', 'value' => '1', 'type' => 'boolean', 'description' => 'Company settings configured'],
        ['key' => 'system_configured', 'value' => '1', 'type' => 'boolean', 'description' => 'System fully configured'],
    ];

    foreach ($configs as $config) {
        DB::table('system_configurations')->updateOrInsert(
            ['key' => $config['key']],
            [
                'value' => $config['value'],
                'type' => $config['type'],
                'description' => $config['description'],
                'updated_at' => now(),
            ]
        );
    }
    
    echo "✅ System configuration completed\n\n";

    echo "🎉 Setup Summary:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "✅ Database Migration\n";
    echo "✅ Admin User Creation\n";
    echo "✅ Company Settings\n";
    echo "✅ System Configuration\n\n";

    echo "🔑 Default Admin Credentials:\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Email: admin@ruxxen.com\n";
    echo "Password: admin123\n\n";

    echo "⚠️  IMPORTANT: Change the default admin password after first login!\n\n";
    echo "🎉 Your Ruxxen LPG system is ready for production!\n";
    echo "Access your system at: " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'your-domain.com') . "\n";

} catch (Exception $e) {
    echo "❌ Setup failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
