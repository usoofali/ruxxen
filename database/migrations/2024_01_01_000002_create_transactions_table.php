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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique(); // Unique transaction number
            $table->foreignId('cashier_id')->constrained('users')->onDelete('cascade');
            $table->decimal('quantity_kg', 10, 2); // Quantity sold in kg
            $table->decimal('price_per_kg', 10, 2); // Price per kg at time of sale
            $table->decimal('total_amount', 12, 2); // Total amount
            $table->string('customer_name')->nullable(); // Optional customer name
            $table->string('customer_phone')->nullable(); // Optional customer phone
            $table->text('notes')->nullable(); // Additional notes
            $table->enum('status', ['completed', 'cancelled', 'refunded'])->default('completed');
            $table->timestamps();
            
            $table->index(['cashier_id', 'created_at']);
            $table->index('transaction_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
