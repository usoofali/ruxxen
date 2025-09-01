<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_name',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * Get the last sync timestamp for a specific table
     */
    public static function getLastSyncTime(string $tableName): ?string
    {
        $log = static::where('table_name', $tableName)->first();
        return $log?->last_synced_at?->toISOString();
    }

    /**
     * Update the last sync timestamp for a specific table
     */
    public static function updateLastSyncTime(string $tableName): void
    {
        static::updateOrCreate(
            ['table_name' => $tableName],
            ['last_synced_at' => now()]
        );
    }
}
