# Ruxxen LPG - Production Deployment Guide

This guide covers the deployment and setup process for the Ruxxen LPG Gas Plant Management System in a production environment.

## 🚀 Quick Start Deployment

### Prerequisites

- PHP 8.2 or higher
- Composer
- MySQL/MariaDB database
- Web server (Apache/Nginx)
- SSL certificate (recommended for production)

### 1. Upload Files

Upload all project files to your hosting environment's web directory.

### 2. Environment Configuration

Create a `.env` file in the project root with the following configuration:

```env
APP_NAME="Ruxxen LPG Gas Plant"
APP_ENV=production
APP_KEY=base64:your-app-key-here
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
SESSION_LIFETIME=120

MEMCACHED_HOST=127.0.0.1

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false

PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_APP_CLUSTER=mt1

VITE_APP_NAME="${APP_NAME}"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

### 3. Install Dependencies

Run the following commands in your project directory:

```bash
composer install --optimize-autoloader --no-dev
npm install
npm run build
```

### 4. Set Permissions

Set proper permissions for Laravel:

```bash
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 5. Generate Application Key

```bash
php artisan key:generate
```

## 🔧 Automatic Setup Process

### Option 1: Web-Based Setup (Recommended)

1. **Access Setup Page**: Navigate to `https://your-domain.com/setup`
2. **Automatic Detection**: The system will automatically detect if setup is required
3. **One-Click Setup**: Click "Start Setup" to run the complete setup process
4. **Automatic Redirect**: Once complete, you'll be redirected to the dashboard

### Option 2: Command Line Setup

Run the setup command:

```bash
php artisan app:setup-production
```

For forced setup (if already configured):

```bash
php artisan app:setup-production --force
```

## 📋 Manual Setup Steps

If you prefer to run setup steps manually or need to troubleshoot:

### 1. Database Migrations

```bash
php artisan migrate --force
```

### 2. Create Admin User

```bash
php artisan tinker
```

```php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

User::create([
    'name' => 'System Administrator',
    'email' => 'admin@ruxxenlpg.com',
    'password' => Hash::make('your-secure-password'),
    'role' => 'admin',
    'is_active' => true,
    'email_verified_at' => now(),
]);
```

### 3. Initialize Company Settings

```bash
php artisan tinker
```

```php
use App\Models\CompanySetting;

CompanySetting::getSettings();
```

### 4. Mark System as Configured

```bash
php artisan tinker
```

```php
use App\Models\SystemConfiguration;

SystemConfiguration::markAsConfigured();
```

## 🔒 Security Considerations

### 1. Change Default Admin Password

After first login, immediately change the default admin password:
- Email: `admin@ruxxenlpg.com`
- Default Password: `admin123`

### 2. Environment Security

- Set `APP_DEBUG=false` in production
- Use strong database passwords
- Enable SSL/HTTPS
- Configure proper file permissions

### 3. Database Security

- Use dedicated database user with minimal privileges
- Enable database encryption if available
- Regular database backups

## 📊 Post-Deployment Checklist

- [ ] System setup completed successfully
- [ ] Admin user can log in
- [ ] Company settings configured
- [ ] SSL certificate installed
- [ ] Database backups configured
- [ ] Error logging configured
- [ ] Performance monitoring set up
- [ ] Security headers configured

## 🛠️ Troubleshooting

### Common Issues

#### 1. Setup Page Not Accessible
- Check if `.env` file exists and is properly configured
- Verify database connection settings
- Check file permissions

#### 2. Database Connection Errors
- Verify database credentials in `.env`
- Ensure database server is running
- Check database user privileges

#### 3. Permission Errors
- Set proper ownership: `chown -R www-data:www-data .`
- Set proper permissions: `chmod -R 755 storage bootstrap/cache`

#### 4. Setup Stuck on Loading
- Check browser console for JavaScript errors
- Verify CSRF token configuration
- Check server error logs

### Log Files

Check these log files for errors:
- `storage/logs/laravel.log`
- Web server error logs
- PHP error logs

### Reset Setup (Development Only)

To reset setup for testing:

```bash
php artisan app:setup-production --force
```

Or via web interface (non-production only):
- Navigate to `/setup`
- Click "Reset Setup" button

## 🔄 Maintenance

### Regular Tasks

1. **Database Backups**: Set up automated daily backups
2. **Log Rotation**: Configure log rotation to prevent disk space issues
3. **Security Updates**: Keep Laravel and dependencies updated
4. **Performance Monitoring**: Monitor system performance and resource usage

### Update Process

1. Backup database and files
2. Upload new code
3. Run `composer install --optimize-autoloader --no-dev`
4. Run `php artisan migrate --force`
5. Clear caches: `php artisan config:clear && php artisan cache:clear`

## 📞 Support

For deployment issues or questions:
- Check the troubleshooting section above
- Review Laravel documentation
- Contact system administrator

## 🎯 Production Optimization

### Performance

- Enable OPcache
- Use Redis for caching (if available)
- Configure proper web server caching
- Optimize database queries

### Monitoring

- Set up application monitoring
- Configure error reporting
- Monitor disk space and memory usage
- Set up uptime monitoring

---

**Note**: This system is designed to be self-configuring. The automatic setup process should handle most deployment scenarios. Manual intervention is only required for specific customizations or troubleshooting.
