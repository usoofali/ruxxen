<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->decimal('current_stock', 10, 2)->default(0); // Current stock in kg
            $table->decimal('minimum_stock', 10, 2)->default(100); // Minimum stock level for alerts
            $table->decimal('price_per_kg', 10, 2)->default(0); // Price per kg
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
