# Master-Slave Synchronization System

## Overview

This Laravel application implements a comprehensive Master-Slave synchronization system that allows offline/local systems to sync with a central Master server. The system uses file-based tracking and HTTP API calls for reliable data synchronization.

## Architecture

### Components

1. **SyncStatusManager** - Manages sync status using JSON file storage
2. **SyncController** - API endpoints for Master server
3. **SyncCommand** - Artisan command for Slave operations
4. **SyncMonitor** - Livewire component for monitoring sync status
5. **Scheduler** - Automated sync execution

### File Structure

```
app/
├── Services/
│   └── SyncStatusManager.php          # Sync status management
├── Http/Controllers/Api/
│   └── SyncController.php             # API endpoints
├── Console/Commands/
│   └── SyncCommand.php                # Sync command
├── Livewire/
│   └── SyncMonitor.php                # Sync monitoring UI
└── Console/
    └── Kernel.php                     # Scheduler configuration

resources/views/livewire/
└── sync-monitor.blade.php             # Sync monitor view

routes/
└── api.php                           # API routes

storage/
└── app/
    └── sync_status.json              # Sync status file
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Master server URL (for Slave systems)
MASTER_URL=http://your-master-server.com

# Sync configuration
SYNC_ENABLED=true
SYNC_INTERVAL=5
```

### Database Tables

The system syncs these tables:
- `inventories`
- `transactions` 
- `inventory_adjustments`

## Usage

### Master Server Setup

1. **Deploy the application** to your Master server
2. **Configure the API endpoints** in `routes/api.php`
3. **Set up authentication** if needed (currently open)
4. **Start the server** and ensure it's accessible

### Slave System Setup

1. **Deploy the application** to your Slave system
2. **Configure the Master URL** in `.env`
3. **Run migrations** to set up local database
4. **Test connectivity** to Master server

### Manual Sync Commands

```bash
# Check what would be synced (dry run)
php artisan sync:run --dry-run

# Force sync regardless of status
php artisan sync:run --force

# Sync with specific Master URL
php artisan sync:run --master-url=http://custom-master.com

# Check sync status
php artisan sync:status
```

### Automated Sync

The system automatically runs sync every 5 minutes via Laravel's scheduler:

```bash
# Ensure scheduler is running (add to crontab)
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Web Interface

Access the sync monitor at `/admin/sync` (admin only) to:
- View current sync status
- Force manual sync
- Reset sync status
- Monitor sync history

## API Endpoints

### Master Server Endpoints

#### POST /api/sync/upload
Upload records from Slave to Master

**Request:**
```json
{
  "records": [
    {
      "table": "inventories",
      "data": {...},
      "action": "create",
      "id": "unique-id"
    }
  ]
}
```

#### GET /api/sync/download
Download records from Master to Slave

**Parameters:**
- `last_synced_at` (optional) - ISO timestamp
- `tables` (optional) - Array of table names

#### POST /api/sync/acknowledge
Acknowledge sync completion

**Request:**
```json
{
  "timestamp": "2025-08-30T09:00:00Z",
  "status": "success",
  "message": "Optional message"
}
```

## Sync Workflow

### Slave → Master (Upload)
1. Read local `sync_status.json`
2. Collect records updated since `last_synced_at`
3. Send to Master via POST `/api/sync/upload`
4. Handle response and errors

### Master → Slave (Download)
1. Request updates from Master via GET `/api/sync/download`
2. Apply received records locally
3. Update local database
4. Send acknowledgment to Master

### Status Tracking
- All sync operations update `sync_status.json`
- Tracks success/failure, timestamps, retry counts
- Enables recovery from failures

## Error Handling

### Automatic Retry
- Failed syncs are retried on next scheduled run
- Retry count is tracked in status file
- Exponential backoff can be implemented

### Manual Recovery
- Use `--force` flag to override status checks
- Reset sync status if needed
- Check logs for detailed error information

### Network Issues
- Timeout handling (30s for upload/download)
- Connection error logging
- Graceful degradation

## Monitoring

### Logs
Check Laravel logs for sync operations:
```bash
tail -f storage/logs/laravel.log | grep sync
```

### Status File
Monitor `storage/app/sync_status.json`:
```json
{
  "last_synced_at": "2025-08-30T09:00:00Z",
  "status": "success",
  "pending_records": 0,
  "last_error": null,
  "retry_count": 0,
  "created_at": "2025-08-30T08:00:00Z",
  "updated_at": "2025-08-30T09:00:00Z"
}
```

### Web Interface
Real-time monitoring via `/admin/sync` with:
- Current status indicators
- Last sync timestamp
- Pending record count
- Manual sync controls

## Security Considerations

### API Security
- Implement authentication for API endpoints
- Use HTTPS for all communications
- Validate all incoming data
- Rate limiting for API calls

### Data Integrity
- Database transactions for atomic operations
- Validation of sync data
- Conflict resolution strategies
- Backup before major sync operations

## Troubleshooting

### Common Issues

1. **Connection Refused**
   - Check Master server is running
   - Verify URL in configuration
   - Check firewall settings

2. **Sync Stuck in Failed State**
   - Check error logs
   - Reset sync status if needed
   - Verify database connectivity

3. **Data Conflicts**
   - Implement conflict resolution
   - Use timestamps for ordering
   - Consider unique constraints

### Debug Commands

```bash
# Check sync status
php artisan sync:run --dry-run

# View sync logs
tail -f storage/logs/laravel.log

# Reset sync status
php artisan tinker
>>> app(\App\Services\SyncStatusManager::class)->reset()
```

## Performance Optimization

### Batch Processing
- Process records in batches
- Use database transactions
- Implement pagination for large datasets

### Caching
- Cache frequently accessed data
- Use Redis for session storage
- Implement query optimization

### Monitoring
- Monitor sync performance
- Track sync duration
- Alert on failures

## Future Enhancements

1. **Conflict Resolution** - Implement sophisticated conflict resolution
2. **Incremental Sync** - Sync only changed fields
3. **Compression** - Compress data during transfer
4. **Encryption** - Encrypt sensitive data
5. **Multi-Master** - Support multiple Master servers
6. **Real-time Sync** - WebSocket-based real-time updates
