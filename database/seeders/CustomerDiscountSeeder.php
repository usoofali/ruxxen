<?php

namespace Database\Seeders;

use App\Models\CustomerDiscount;
use Illuminate\Database\Seeder;

class CustomerDiscountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default discount types
        $discounts = [
            [
                'name' => 'Standard Customer',
                'discount_per_kg' => 0.00,
                'is_default' => true,
                'is_active' => true,
                'description' => 'Regular pricing for standard customers',
            ],
            [
                'name' => 'VIP Customer',
                'discount_per_kg' => 5.00,
                'is_default' => false,
                'is_active' => true,
                'description' => 'Premium customers with special pricing',
            ],
            [
                'name' => 'Wholesale',
                'discount_per_kg' => 10.00,
                'is_default' => false,
                'is_active' => true,
                'description' => 'Bulk purchase discount for wholesale customers',
            ],
        ];

        foreach ($discounts as $discount) {
            CustomerDiscount::create($discount);
        }
    }
}