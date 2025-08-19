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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['addition', 'subtraction', 'loss', 'correction']);
            $table->decimal('quantity_kg', 10, 2); // Quantity adjusted
            $table->decimal('previous_stock', 10, 2); // Stock before adjustment
            $table->decimal('new_stock', 10, 2); // Stock after adjustment
            $table->text('reason'); // Reason for adjustment
            $table->text('notes')->nullable(); // Additional notes
            $table->timestamps();
            
            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
