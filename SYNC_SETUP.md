# Sync System Setup Guide

## Quick Setup

### 1. Environment Configuration

Add these to your `.env` file:

```env
# Sync Configuration
SYNC_ENABLED=true
MASTER_URL=http://your-master-server.com
SYNC_INTERVAL=5
SYNC_TIMEOUT=30
SYNC_RETRY_ATTEMPTS=3
SYNC_BATCH_SIZE=100
```

### 2. Manual Scheduler Setup

Since the Laravel scheduler isn't working properly, use this manual approach:

#### Option A: Cron Job (Recommended)
Add this to your crontab:
```bash
# Run sync every 5 minutes
*/5 * * * * cd /path/to/your/project && php artisan sync:scheduler >> /dev/null 2>&1
```

#### Option B: Windows Task Scheduler
Create a scheduled task to run:
```cmd
php artisan sync:scheduler
```

### 3. Test the Sync System

```bash
# Check sync status
php artisan sync:status

# Test sync (dry run)
php artisan sync:run --dry-run

# Force sync
php artisan sync:run --force

# Run scheduler manually
php artisan sync:scheduler
```

## Troubleshooting

### Scheduler Issues
- The Laravel scheduler isn't working in this version
- Use the manual `sync:scheduler` command instead
- Set up cron/task scheduler to run it every 5 minutes

### Connection Issues
- Check if Master server is running
- Verify MASTER_URL in .env
- Test connectivity: `curl http://your-master-server.com/api/sync/download`

### API Errors
- Check Master server logs
- Verify API endpoints are working
- Test with Postman or curl

## Commands Available

- `sync:status` - Show current sync status
- `sync:run` - Run synchronization
- `sync:scheduler` - Run scheduler manually

## Files

- `config/sync.php` - Sync configuration
- `app/Services/SyncStatusManager.php` - Status management
- `app/Console/Commands/SyncCommand.php` - Main sync logic
- `app/Console/Commands/SyncSchedulerCommand.php` - Manual scheduler
- `app/Http/Controllers/Api/SyncController.php` - API endpoints
