<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventory';

    protected $fillable = [
        'current_stock',
        'minimum_stock',
        'price_per_kg',
        'notes',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'minimum_stock' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
    ];

    /**
     * Check if stock is low
     */
    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->minimum_stock;
    }

    /**
     * Get stock percentage
     */
    public function getStockPercentage(): float
    {
        if ($this->minimum_stock == 0) {
            return 0;
        }
        
        return ($this->current_stock / $this->minimum_stock) * 100;
    }

    /**
     * Add stock to inventory
     */
    public function addStock(float $quantity, string $reason, User $user, ?string $notes = null): void
    {
        $previousStock = $this->current_stock;
        $this->current_stock += $quantity;
        $this->save();

        // Record adjustment
        InventoryAdjustment::create([
            'user_id' => $user->id,
            'type' => 'addition',
            'quantity_kg' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $this->current_stock,
            'reason' => $reason,
            'notes' => $notes,
        ]);

        session()->flash('success', "Stock added successfully. Added {$quantity} kg to inventory.");
    }

    /**
     * Subtract stock from inventory
     */
    public function subtractStock(float $quantity, string $reason, User $user, ?string $notes = null): bool
    {
        if ($this->current_stock < $quantity) {
            session()->flash('error', 'Insufficient stock. Cannot subtract more than available stock.');
            return false; // Insufficient stock
        }

        $previousStock = $this->current_stock;
        $this->current_stock -= $quantity;
        $this->save();

        // Record adjustment
        InventoryAdjustment::create([
            'user_id' => $user->id,
            'type' => 'subtraction',
            'quantity_kg' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $this->current_stock,
            'reason' => $reason,
            'notes' => $notes,
        ]);

        session()->flash('success', "Sale Completed, {$quantity} kg removed from inventory.");
        return true;
    }

    /**
     * Get inventory adjustments
     */
    public function adjustments()
    {
        return $this->hasMany(InventoryAdjustment::class);
    }
}
