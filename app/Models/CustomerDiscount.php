<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'discount_per_kg',
        'is_default',
        'is_active',
        'description',
    ];

    protected $casts = [
        'discount_per_kg' => 'decimal:2',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Boot method to ensure only one default discount
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($discount) {
            if ($discount->is_default) {
                static::where('is_default', true)->update(['is_default' => false]);
            }
        });

        static::created(function ($discount) {
            session()->flash('success', 'Customer discount created successfully.');
        });

        static::updating(function ($discount) {
            if ($discount->is_default && $discount->isDirty('is_default')) {
                static::where('is_default', true)
                    ->where('id', '!=', $discount->id)
                    ->update(['is_default' => false]);
            }
        });

        static::updated(function ($discount) {
            session()->flash('success', 'Customer discount updated successfully.');
        });

        static::deleted(function ($discount) {
            session()->flash('success', 'Customer discount deleted successfully.');
        });
    }

    /**
     * Get transactions using this discount
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope for active discounts
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default discount
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get formatted discount amount
     */
    public function getFormattedDiscountAttribute(): string
    {
        return '₦' . number_format((float) $this->discount_per_kg, 2);
    }

    /**
     * Set this discount as default
     */
    public function setAsDefault(): void
    {
        static::where('is_default', true)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }

    /**
     * Get the default discount
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Get effective price per kg for a given base price
     */
    public function getEffectivePricePerKg(float $basePrice): float
    {
        return max(0, $basePrice - (float) $this->discount_per_kg);
    }
}