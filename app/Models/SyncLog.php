<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'sync_status',
        'error_message',
        'synced_at',
        'retry_count',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    /**
     * Sync status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_SYNCING = 'syncing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Get the transaction that this sync log belongs to
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Scope for pending sync logs
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', self::STATUS_PENDING);
    }

    /**
     * Scope for syncing logs
     */
    public function scopeSyncing($query)
    {
        return $query->where('sync_status', self::STATUS_SYNCING);
    }

    /**
     * Scope for completed sync logs
     */
    public function scopeCompleted($query)
    {
        return $query->where('sync_status', self::STATUS_COMPLETED);
    }

    /**
     * Scope for failed sync logs
     */
    public function scopeFailed($query)
    {
        return $query->where('sync_status', self::STATUS_FAILED);
    }

    /**
     * Scope for logs that need retry
     */
    public function scopeNeedsRetry($query, int $maxRetries = 3)
    {
        return $query->where('sync_status', self::STATUS_FAILED)
                    ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Mark sync as started
     */
    public function markAsSyncing(): void
    {
        $this->update([
            'sync_status' => self::STATUS_SYNCING,
        ]);
    }

    /**
     * Mark sync as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'sync_status' => self::STATUS_COMPLETED,
            'synced_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark sync as failed
     */
    public function markAsFailed(string $errorMessage = null): void
    {
        $this->update([
            'sync_status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Reset sync status to pending for retry
     */
    public function resetForRetry(): void
    {
        $this->update([
            'sync_status' => self::STATUS_PENDING,
            'error_message' => null,
        ]);
    }

    /**
     * Check if sync is pending
     */
    public function isPending(): bool
    {
        return $this->sync_status === self::STATUS_PENDING;
    }

    /**
     * Check if sync is syncing
     */
    public function isSyncing(): bool
    {
        return $this->sync_status === self::STATUS_SYNCING;
    }

    /**
     * Check if sync is completed
     */
    public function isCompleted(): bool
    {
        return $this->sync_status === self::STATUS_COMPLETED;
    }

    /**
     * Check if sync is failed
     */
    public function isFailed(): bool
    {
        return $this->sync_status === self::STATUS_FAILED;
    }

    /**
     * Check if sync can be retried
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->isFailed() && $this->retry_count < $maxRetries;
    }

    /**
     * Get formatted sync status
     */
    public function getFormattedStatusAttribute(): string
    {
        return match ($this->sync_status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SYNCING => 'Syncing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->sync_status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_SYNCING => 'info',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_FAILED => 'error',
            default => 'neutral',
        };
    }
}
