<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_number',
        'cashier_id',
        'quantity_kg',
        'price_per_kg',
        'total_amount',
        'customer_name',
        'customer_phone',
        'payment_type',
        'notes',
        'status',
    ];

    protected $casts = [
        'quantity_kg' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Boot method to generate transaction number
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = self::generateTransactionNumber();
            }
        });
    }

    /**
     * Generate unique transaction number
     */
    public static function generateTransactionNumber(): string
    {
        do {
            $number = 'TXN-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (self::where('transaction_number', $number)->exists());

        return $number;
    }

    /**
     * Get cashier who made the transaction
     */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Scope for completed transactions
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for today's transactions
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for this month's transactions
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }

    /**
     * Get formatted total amount
     */
    public function getFormattedTotalAttribute(): string
    {
        return '₦' . number_format($this->total_amount, 2);
    }

    /**
     * Get formatted quantity
     */
    public function getFormattedQuantityAttribute(): string
    {
        return number_format($this->quantity_kg, 2) . ' kg';
    }

    /**
     * Get formatted price per kg
     */
    public function getFormattedPricePerKgAttribute(): string
    {
        return '₦' . number_format($this->price_per_kg, 2);
    }
}
