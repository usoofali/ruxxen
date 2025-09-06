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
     * Boot method to generate transaction number and create sync log
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (empty($transaction->transaction_number)) {
                $transaction->transaction_number = self::generateTransactionNumber();
            }
        });

        static::created(function ($transaction) {
            // Automatically create a sync log for every new transaction
            $transaction->createSyncLog();
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
     * Get sync logs for this transaction
     */
    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * Get the latest sync log for this transaction
     */
    public function latestSyncLog()
    {
        return $this->hasOne(SyncLog::class)->latest();
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
        return '₦' . number_format((float) $this->total_amount, 2);
    }

    /**
     * Get formatted quantity
     */
    public function getFormattedQuantityAttribute(): string
    {
        return number_format((float) $this->quantity_kg, 2) . ' kg';
    }

    /**
     * Get formatted price per kg
     */
    public function getFormattedPricePerKgAttribute(): string
    {
        return '₦' . number_format((float) $this->price_per_kg, 2);
    }

    /**
     * Create a sync log for this transaction
     */
    public function createSyncLog(): SyncLog
    {
        return $this->syncLogs()->create([
            'sync_status' => SyncLog::STATUS_PENDING,
        ]);
    }

    /**
     * Check if transaction has pending sync
     */
    public function hasPendingSync(): bool
    {
        return $this->syncLogs()->pending()->exists();
    }

    /**
     * Check if transaction has completed sync
     */
    public function hasCompletedSync(): bool
    {
        return $this->syncLogs()->completed()->exists();
    }

    /**
     * Check if transaction has failed sync
     */
    public function hasFailedSync(): bool
    {
        return $this->syncLogs()->failed()->exists();
    }

    /**
     * Get the current sync status
     */
    public function getCurrentSyncStatus(): ?string
    {
        $latestSyncLog = $this->latestSyncLog;
        return $latestSyncLog ? $latestSyncLog->sync_status : null;
    }
}
