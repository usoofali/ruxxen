<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Master-Slave synchronization system
    |
    */

    'enabled' => env('SYNC_ENABLED', true),
    
    'master_url' => env('MASTER_URL', 'http://localhost:8000'),
    
    'interval' => env('SYNC_INTERVAL', 5), // minutes
    
    'timeout' => env('SYNC_TIMEOUT', 30), // seconds
    
    'retry_attempts' => env('SYNC_RETRY_ATTEMPTS', 3),
    
    'tables' => [
        'inventories',
        'transactions', 
        'inventory_adjustments',
    ],
    
    'batch_size' => env('SYNC_BATCH_SIZE', 100),
    
    // Priority settings for critical tables
    'priorities' => [
        'inventories' => 'high',      // Most critical - sync first
        'transactions' => 'medium',   // Important but less critical
        'inventory_adjustments' => 'low', // Audit trail - sync last
    ],
    
    // Conflict resolution strategy
    'conflict_resolution' => [
        'inventories' => 'master_wins',     // Master inventory changes take precedence
        'transactions' => 'slave_wins',     // Slave transaction changes take precedence
        'inventory_adjustments' => 'merge', // Merge both sides
    ],
];
