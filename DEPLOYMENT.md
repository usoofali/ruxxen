# Ruxxen LPG - Production Deployment Guide

This guide explains how to deploy the Ruxxen LPG system to production and configure it for first use.

## Overview

The Ruxxen LPG system includes an automated production setup process that handles:
- Database initialization and migrations
- Default admin user creation
- Company settings configuration
- System configuration tracking

## Deployment Workflow

### 1. Initial Deployment

1. **Deploy the application** to your hosting environment
2. **Configure environment variables** in `.env` file:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-domain.com
   
   DB_CONNECTION=mysql
   DB_HOST=your-db-host
   DB_PORT=3306
   DB_DATABASE=your-database-name
   DB_USERNAME=your-db-username
   DB_PASSWORD=your-db-password
   ```

3. **Run the production setup** using one of the methods below

### 2. Setup Methods

#### Method A: Web-Based Setup (Recommended)
1. Access your application URL
2. You will be automatically redirected to `/setup`
3. Fill in the setup form with your preferences
4. Click "Start Setup" to begin the automated process
5. Once completed, you'll be redirected to the dashboard

#### Method B: Command Line Setup
```bash
# Run the Artisan command
php artisan production:setup

# With custom parameters
php artisan production:setup \
  --admin-email=your-admin@company.com \
  --admin-password=your-secure-password \
  --admin-name="Your Name" \
  --company-name="Your Company Name"
```

#### Method C: Direct Script Execution
```bash
# Run the standalone setup script
php production_setup.php
```

### 3. Post-Setup Configuration

After successful setup:

1. **Login with default credentials**:
   - Email: `admin@ruxxen.com`
   - Password: `admin123`

2. **Change the default password** immediately:
   - Go to Settings → Password
   - Set a strong, secure password

3. **Configure company settings**:
   - Go to Settings → Company
   - Update company information, address, contact details

4. **Set up additional users** (optional):
   - Go to Admin → Users
   - Create cashier accounts as needed

## System Configuration Tracking

The system automatically tracks setup completion status in the `system_configurations` table:

| Key | Description |
|-----|-------------|
| `database_migrated` | Database migrations completed |
| `admin_created` | Default admin user created |
| `company_configured` | Company settings initialized |
| `system_configured` | Overall system setup completed |

## Idempotent Setup

All setup methods are **idempotent**, meaning:
- Running setup multiple times won't break the system
- Already completed steps are skipped
- Existing data is preserved
- No duplicate entries are created

## Security Considerations

### Default Credentials
- **Always change the default admin password** after first login
- Default credentials are: `admin@ruxxen.com` / `admin123`
- These credentials are clearly displayed after setup completion

### Environment Security
- Set `APP_ENV=production` in production
- Set `APP_DEBUG=false` in production
- Use strong database passwords
- Configure proper file permissions
- Enable HTTPS in production

## Troubleshooting

### Setup Fails
1. Check database connection in `.env`
2. Ensure database user has proper permissions
3. Check application logs in `storage/logs/`
4. Verify PHP extensions are installed (pdo_mysql, etc.)

### Cannot Access Setup Page
1. Check if `system_configurations` table exists
2. Verify middleware configuration in `bootstrap/app.php`
3. Check route configuration in `routes/web.php`

### Database Migration Issues
1. Ensure database exists and is accessible
2. Check database user permissions
3. Run `php artisan migrate:status` to check migration status
4. Use `php artisan migrate:rollback` if needed

## File Structure

```
ruxxen/
├── app/
│   ├── Console/Commands/ProductionSetup.php    # Setup command
│   ├── Http/Middleware/CheckSystemSetup.php    # Setup middleware
│   ├── Livewire/Setup/Welcome.php              # Setup component
│   └── Models/SystemConfiguration.php          # Config model
├── database/migrations/
│   └── *_create_system_configurations_table.php # Config table
├── resources/views/livewire/setup/
│   └── welcome.blade.php                       # Setup view
├── routes/web.php                              # Updated routes
├── production_setup.php                        # Standalone script
└── DEPLOYMENT.md                               # This file
```

## Support

For deployment issues:
1. Check the Laravel logs in `storage/logs/`
2. Verify all environment variables are set correctly
3. Ensure database connectivity
4. Check file permissions on storage and bootstrap/cache directories

## Version History

- **v1.0**: Initial production setup implementation
- Automated setup process
- Idempotent configuration
- Web-based and command-line setup options
- Security best practices integration
