<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'quantity_kg',
        'previous_stock',
        'new_stock',
        'reason',
        'notes',
    ];

    protected $casts = [
        'quantity_kg' => 'decimal:2',
        'previous_stock' => 'decimal:2',
        'new_stock' => 'decimal:2',
    ];

    /**
     * Get user who made the adjustment
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted quantity
     */
    public function getFormattedQuantityAttribute(): string
    {
        $prefix = $this->type === 'addition' ? '+' : '-';
        return $prefix . number_format($this->quantity_kg, 2) . ' kg';
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'addition' => 'Stock Added',
            'subtraction' => 'Stock Removed',
            'loss' => 'Stock Loss',
            'correction' => 'Stock Correction',
            default => ucfirst($this->type),
        };
    }

    /**
     * Get type color for UI
     */
    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'addition' => 'success',
            'subtraction' => 'warning',
            'loss' => 'error',
            'correction' => 'info',
            default => 'neutral',
        };
    }
}
