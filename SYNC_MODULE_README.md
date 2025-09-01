# Laravel Master-Slave Sync Module

## Overview

This module provides bidirectional synchronization between a master Laravel application (hosted remotely) and slave applications (running locally). The slave always initiates synchronization, ensuring data consistency across all instances.

## Architecture

- **Master**: Remote application (app.ruxxengas.com) with SQLite database
- **Slave**: Local application with SQLite database
- **Mode Control**: Environment variable `APP_MODE` controls behavior
- **Timezone**: Both use West Africa (Lagos) timezone
- **Sync Strategy**: Timestamp-based incremental synchronization

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Application Mode (master|slave)
APP_MODE=slave

# Master Application URL (for slave mode)
MASTER_URL=https://app.ruxxengas.com

# Sync API Key (must be same on master and slave)
SYNC_API_KEY=your-secret-api-key-here

# Timezone (automatically set to Africa/Lagos)
APP_TIMEZONE=Africa/Lagos
```

### Master Configuration

```env
APP_MODE=master
SYNC_API_KEY=your-secret-api-key-here
```

### Slave Configuration

```env
APP_MODE=slave
MASTER_URL=https://app.ruxxengas.com
SYNC_API_KEY=your-secret-api-key-here
```

## Database Schema

### New Table: sync_logs

```sql
CREATE TABLE sync_logs (
    id BIGINT PRIMARY KEY,
    table_name VARCHAR(255) UNIQUE,
    last_synced_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Existing Tables (with timestamps)

All critical tables already have `created_at` and `updated_at` timestamps:
- `inventory`
- `transactions` 
- `company_settings`
- `users`
- `inventory_adjustments`

## API Endpoints (Master Only)

### POST /api/sync/push
Receives changes from slave.

**Headers:**
```
X-Sync-API-Key: your-secret-api-key-here
Content-Type: application/json
```

**Request Body:**
```json
{
    "table": "inventory|transactions|company_settings|users|inventory_adjustments",
    "data": [
        {
            "id": 1,
            "current_stock": 500.00,
            "minimum_stock": 100.00,
            "price_per_kg": 25.00,
            "notes": "Updated stock",
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-01-01T12:00:00Z"
        }
    ]
}
```

**Response:**
```json
{
    "success": true,
    "message": "Successfully synced inventory data",
    "synced_count": 1
}
```

### GET /api/sync/pull
Provides changes to slave.

**Headers:**
```
X-Sync-API-Key: your-secret-api-key-here
```

**Query Parameters:**
- `table`: inventory|transactions|company_settings|users|inventory_adjustments
- `since`: ISO 8601 timestamp (optional)

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "current_stock": 500.00,
            "minimum_stock": 100.00,
            "price_per_kg": 25.00,
            "notes": "Updated stock",
            "created_at": "2024-01-01T00:00:00Z",
            "updated_at": "2024-01-01T12:00:00Z"
        }
    ],
    "count": 1,
    "table": "inventory",
    "since": "2024-01-01T00:00:00Z"
}
```

## Artisan Commands

### sync:run
Runs synchronization for inventory, transactions, users, and inventory adjustments.

```bash
# Run sync (slave mode only)
php artisan sync:run

# Force sync (any mode)
php artisan sync:run --force
```

### sync:company-settings
Syncs company settings once (bootstrapping step).

```bash
# Run company settings sync (slave mode only)
php artisan sync:company-settings

# Force sync (any mode)
php artisan sync:company-settings --force
```

## Scheduler Configuration

The scheduler automatically runs sync jobs in slave mode:

```php
// Runs every 15 minutes (syncs inventory, transactions, users, inventory_adjustments)
$schedule->command('sync:run')->everyFifteenMinutes();

// Runs once daily (skips if already synced)
$schedule->command('sync:company-settings')->daily();
```

## Synchronization Process

### 1. Push Phase (Slave → Master)
1. Get last sync timestamp for table
2. Query local changes since last sync
3. Send changes to master via API
4. Master applies changes to database
5. Master updates sync log

### 2. Pull Phase (Master → Slave)
1. Get last sync timestamp for table
2. Request changes from master since last sync
3. Apply master changes to local database
4. Update local sync log

### 3. Conflict Resolution (Inventory Only)
1. Master always overrides slave for inventory
2. Clear local inventory table
3. Replace with complete master data
4. Update sync log

## Recovery System

### Automatic Recovery
- Checks on application boot (slave mode only)
- Detects empty or corrupted databases
- Performs full recovery from master
- Logs recovery status

### Manual Recovery
```bash
# Check if recovery is needed
php artisan sync:run

# Force full recovery
php artisan sync:run --force
```

## Security

### API Authentication
- All sync API requests require `X-Sync-API-Key` header
- Key must match `SYNC_API_KEY` environment variable
- Middleware validates all sync requests

### Data Validation
- Input validation on all API endpoints
- Transaction safety for database operations
- Error handling and logging

## Error Handling

### Logging
All sync operations are logged:
- Success/failure status
- Record counts
- Error messages and stack traces
- Recovery attempts

### Retry Logic
- Failed operations are logged
- Scheduler retries on next run
- No infinite retry loops

## Monitoring

### Log Files
Check `storage/logs/laravel.log` for sync activity:
```
[2024-01-01 12:00:00] local.INFO: Successfully synced inventory
[2024-01-01 12:00:00] local.INFO: Synchronization completed successfully
```

### Database Monitoring
Query sync_logs table for sync status:
```sql
SELECT * FROM sync_logs ORDER BY last_synced_at DESC;
```

## Troubleshooting

### Common Issues

1. **Sync not running**
   - Check `APP_MODE` is set to `slave`
   - Verify scheduler is running
   - Check logs for errors

2. **API authentication failures**
   - Verify `SYNC_API_KEY` matches on master and slave
   - Check network connectivity to master
   - Validate master URL

3. **Database errors**
   - Ensure migrations are run
   - Check database permissions
   - Verify table structure

4. **Recovery failures**
   - Check master availability
   - Verify API endpoints are accessible
   - Review error logs

### Debug Commands
```bash
# Test sync manually
php artisan sync:run --force

# Check sync logs
php artisan tinker
>>> App\Models\SyncLog::all();

# Test API connectivity
curl -H "X-Sync-API-Key: your-key" https://app.ruxxengas.com/api/sync/pull?table=inventory
```

## Performance Considerations

### Optimization
- Incremental sync (timestamp-based)
- Background job processing
- Database transactions for consistency
- HTTP timeout handling

### Limitations
- Sync frequency: every 15 minutes
- Large datasets may take time
- Network dependency for slave operations

## Deployment

### Master Deployment
1. Set `APP_MODE=master`
2. Configure `SYNC_API_KEY`
3. Ensure API endpoints are accessible
4. Run migrations

### Slave Deployment
1. Set `APP_MODE=slave`
2. Configure `MASTER_URL` and `SYNC_API_KEY`
3. Run migrations
4. Start scheduler
5. Verify initial sync

### Production Checklist
- [ ] Environment variables configured
- [ ] Database migrations run
- [ ] Scheduler configured
- [ ] API endpoints accessible
- [ ] Logging configured
- [ ] Error monitoring setup
- [ ] Backup strategy in place

## Support

For issues or questions:
1. Check logs in `storage/logs/laravel.log`
2. Review this documentation
3. Test with debug commands
4. Contact development team
