# Disable Sync for Standalone System

## Current Issue

The sync system is trying to connect to a Master server that doesn't have the required API endpoints. This system appears to be standalone and doesn't need synchronization.

## Solution: Disable Sync

### 1. Update Environment Variables

Add this to your `.env` file:

```env
# Sync Configuration (Disabled for standalone system)
SYNC_ENABLED=false
MASTER_URL=https://app.ruxxengas.com
SYNC_INTERVAL=5
SYNC_TIMEOUT=30
SYNC_RETRY_ATTEMPTS=3
SYNC_BATCH_SIZE=100
```

### 2. Test the Configuration

```bash
# Check if sync is disabled
php artisan sync:status

# Try to run sync (should be disabled)
php artisan sync:scheduler
```

### 3. Remove Scheduler (Optional)

If you want to completely remove sync functionality:

#### Remove from Kernel.php:

```php
// Comment out or remove these lines in app/Console/Kernel.php
// $schedule->command('sync:run')->everyFiveMinutes();
// $schedule->command('sync:status')->everyMinute();
```

#### Remove Commands (Optional):

-   `app/Console/Commands/SyncCommand.php`
-   `app/Console/Commands/SyncStatusCommand.php`
-   `app/Console/Commands/SyncSchedulerCommand.php`
-   `app/Services/SyncStatusManager.php`
-   `app/Http/Controllers/Api/SyncController.php`
-   `config/sync.php`

## Alternative: Set Up Master Server

If you want to use sync functionality, you need to:

1. **Deploy this application to a Master server**
2. **Ensure the Master server has the sync API endpoints**
3. **Configure Slave systems to point to the Master**

## Recommended Action

For a standalone system, **disable sync** by setting `SYNC_ENABLED=false` in your `.env` file.
