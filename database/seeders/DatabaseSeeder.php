<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Inventory;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@ruxxen.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create sample cashier
        User::create([
            'name' => 'Cashier User',
            'email' => 'cashier@ruxxen.com',
            'password' => Hash::make('password'),
            'role' => 'cashier',
            'email_verified_at' => now(),
        ]);

        // Initialize inventory
        Inventory::create([
            'current_stock' => 1000.00, // 1000 kg initial stock
            'minimum_stock' => 500.00,  // Alert when below 100 kg
            'price_per_kg' => 1240.00,   // ₦850 per kg
            'notes' => 'Initial inventory setup for Ruxxen LPG Gas Plant',
        ]);

        // Create default company settings
        \App\Models\CompanySetting::create([
            'company_name' => 'Ruxxen Investment Limited',
            'company_address' => '123 Gas Plant Street, Lagos, Nigeria',
            'company_phone' => '+234 123 456 7890',
            'company_email' => 'info@ruxxenlpg.com',
            'smtp_host' => 'smtp.mailtrap.io',
            'smtp_port' => 2525,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
        ]);
    }
}
