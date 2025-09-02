# Master-Slave Synchronization Setup Guide

## Architecture Overview

This system implements a **Master-Slave synchronization architecture** where:

- **Master Server**: `https://app.ruxxengas.com` (remote, stable)
- **Slave Server**: Local system (volatile, frequent changes)
- **Same Codebase**: Different `.env` configurations and SQLite databases
- **Critical Sync**: Inventory table (both can modify, but slave is more frequent)
- **Recovery**: Slave auto-syncs from Master on startup

## Environment Configuration

### Master Server (.env)
```env
# Master Configuration
APP_ENV=production
APP_DEBUG=false

# Database (Master SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/master/database.sqlite

# Sync Configuration (Master)
SYNC_ENABLED=true
MASTER_URL=https://app.ruxxengas.com
SYNC_INTERVAL=5
SYNC_TIMEOUT=30
SYNC_RETRY_ATTEMPTS=3
SYNC_BATCH_SIZE=100

# Server Type
SYNC_SERVER_TYPE=master
```

### Slave Server (.env)
```env
# Slave Configuration
APP_ENV=local
APP_DEBUG=true

# Database (Slave SQLite)
DB_CONNECTION=sqlite
DB_DATABASE=/path/to/slave/database.sqlite

# Sync Configuration (Slave)
SYNC_ENABLED=true
MASTER_URL=https://app.ruxxengas.com
SYNC_INTERVAL=5
SYNC_TIMEOUT=30
SYNC_RETRY_ATTEMPTS=3
SYNC_BATCH_SIZE=100

# Server Type
SYNC_SERVER_TYPE=slave
```

## Sync Behavior

### Priority System
1. **High Priority**: `inventories` - Sync first, Master wins conflicts
2. **Medium Priority**: `transactions` - Sync second, Slave wins conflicts  
3. **Low Priority**: `inventory_adjustments` - Sync last, merge conflicts

### Conflict Resolution
- **Inventory**: Master changes take precedence (Master can modify inventory directly)
- **Transactions**: Slave changes take precedence (Slave creates most transactions)
- **Adjustments**: Both sides merged (audit trail)

### Startup Sync
- **Slave**: Automatically syncs from Master on startup
- **Master**: No startup sync (acts as source of truth)

## Commands Available

### Slave Commands
```bash
# Startup sync (runs automatically on application start)
php artisan sync:startup

# Manual startup sync with force
php artisan sync:startup --force

# Regular sync (uploads local changes, downloads remote changes)
php artisan sync:run

# Dry run (show what would be synced)
php artisan sync:run --dry-run

# Check sync status
php artisan sync:status

# Manual scheduler
php artisan sync:scheduler
```

### Master Commands
```bash
# Check sync status
php artisan sync:status

# Regular sync (uploads local changes, downloads remote changes)
php artisan sync:run
```

## API Endpoints

### Master Server Endpoints
- `GET /api/sync/status` - Get sync status
- `POST /api/sync/upload` - Receive changes from Slave
- `GET /api/sync/download` - Send changes to Slave
- `POST /api/sync/acknowledge` - Receive sync acknowledgment

### Slave Server Endpoints
- `GET /api/sync/status` - Get sync status
- `POST /api/sync/upload` - Send changes to Master
- `GET /api/sync/download` - Receive changes from Master
- `POST /api/sync/acknowledge` - Send sync acknowledgment

## Setup Instructions

### 1. Master Server Setup
1. Deploy the application to `https://app.ruxxengas.com`
2. Configure `.env` with Master settings
3. Ensure API endpoints are accessible
4. Test with: `curl https://app.ruxxengas.com/api/sync/status`

### 2. Slave Server Setup
1. Configure `.env` with Slave settings
2. Set `MASTER_URL=https://app.ruxxengas.com`
3. Test connectivity: `php artisan sync:startup`
4. Verify startup sync works

### 3. Testing the Setup
```bash
# On Slave - Test startup sync
php artisan sync:startup

# On Slave - Test regular sync
php artisan sync:run --dry-run

# On Slave - Check status
php artisan sync:status

# On Master - Check status
curl https://app.ruxxengas.com/api/sync/status
```

## Recovery Scenarios

### Slave Recovery
If the Slave system fails or loses data:
1. **Automatic**: Slave will sync from Master on startup
2. **Manual**: Run `php artisan sync:startup --force`
3. **Full Reset**: Delete local database, run migrations, then startup sync

### Master Recovery
If the Master system fails:
1. **Backup**: Restore from backup
2. **Sync**: All Slaves will sync their changes back to Master
3. **Verification**: Check sync status on all systems

## Monitoring

### Log Files
- `storage/logs/laravel.log` - Sync operations and errors
- `storage/app/sync_status.json` - Sync status tracking

### Status Monitoring
```bash
# Check sync status
php artisan sync:status

# Monitor logs
tail -f storage/logs/laravel.log | grep sync
```

## Troubleshooting

### Common Issues

1. **Connection Failed**
   - Check Master URL in `.env`
   - Verify Master server is running
   - Test connectivity: `curl https://app.ruxxengas.com/api/sync/status`

2. **HTTP 404 Errors**
   - Ensure Master has sync API endpoints
   - Check routes are properly configured
   - Verify API middleware

3. **HTTP 500 Errors**
   - Check Master server logs
   - Verify database connectivity
   - Check sync configuration

4. **Startup Sync Fails**
   - Check sync is enabled in `.env`
   - Verify Master connectivity
   - Check logs for specific errors

### Debug Commands
```bash
# Test Master connectivity
curl -v https://app.ruxxengas.com/api/sync/status

# Check sync configuration
php artisan tinker --execute="print_r(config('sync'));"

# Test startup sync manually
php artisan sync:startup --force

# Check sync status
php artisan sync:status
```

## Security Considerations

1. **API Security**: Implement authentication for sync endpoints
2. **Data Encryption**: Consider encrypting sensitive data during sync
3. **Network Security**: Use HTTPS for all sync communications
4. **Access Control**: Limit access to sync endpoints

## Performance Optimization

1. **Batch Size**: Adjust `SYNC_BATCH_SIZE` based on data volume
2. **Sync Interval**: Adjust `SYNC_INTERVAL` based on change frequency
3. **Timeout**: Adjust `SYNC_TIMEOUT` based on network conditions
4. **Retry Logic**: Configure `SYNC_RETRY_ATTEMPTS` for reliability
