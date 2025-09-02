# 🔄 Sync Module Setup Script

This setup script provides a comprehensive way to diagnose and configure the Master-Slave sync module without needing SSH access or artisan commands.

## 🚀 Quick Start

### 1. Access the Setup Dashboard
Visit this URL in your browser on your **Master** server:
```
https://app.ruxxengas.com/setup-sync
```

### 2. What the Setup Script Checks

#### ✅ **System Requirements**
- PHP Version (requires 8.2+)
- Laravel Version
- Environment Variables
- Required Files

#### ✅ **Configuration Status**
- Database Tables
- Sync Routes
- Middleware Registration
- Artisan Commands
- Cache Status

#### ✅ **API Testing**
- Endpoint Accessibility
- Authentication
- Response Codes

## 🛠️ Available Actions

### **Setup Dashboard** (`/setup-sync`)
- Comprehensive system check
- Visual status indicators
- Detailed error reporting

### **API Testing** (`/test-sync`)
- Test sync endpoints
- Verify authentication
- Check response codes

### **Cache Clearing** (`/clear-cache`)
- Clear config cache
- Clear route cache
- Clear view cache

### **Cleanup** (`/remove-setup`)
- Remove setup routes
- Delete setup files
- Clean up temporary code

## 📋 Expected Results

### **If PHP is Still 7.3:**
```
❌ PHP Version: 7.3.33 (TOO OLD)
❌ Laravel Version: ERROR
❌ Routes: 0 sync routes found
❌ Configuration: NULL values
```

### **If PHP is Upgraded to 8.2+:**
```
✅ PHP Version: 8.2.x (OK)
✅ Laravel Version: 11.x (OK)
✅ Routes: 2 sync routes found
✅ Configuration: Proper values
```

## 🔧 Troubleshooting Steps

### **Step 1: Fix PHP Version**
Contact your hosting provider to upgrade PHP to 8.2+ for your domain.

### **Step 2: Set Environment Variables**
Ensure your Master's `.env` has:
```env
APP_MODE=master
SYNC_API_KEY=ruxxen-sync-key-2024
APP_URL=https://app.ruxxengas.com
```

### **Step 3: Clear Caches**
Use the "🗂️ Clear Caches" button to clear all Laravel caches.

### **Step 4: Test API Endpoints**
Use the "🧪 Test API Endpoints" button to verify the sync API is working.

### **Step 5: Verify Slave Connection**
Once Master is working, test from your Slave:
```bash
php artisan sync:run
```

## 🚨 Security Notes

- **This is a temporary debugging tool**
- **Remove setup routes when done** using the "Remove Setup Routes" button
- **Don't leave this accessible in production**

## 📱 Manual Testing

### **Test Pull Endpoint:**
```bash
curl -H "X-Sync-API-Key: YOUR_API_KEY" \
     "https://app.ruxxengas.com/api/sync/pull?table=inventory&since="
```

### **Test Push Endpoint:**
```bash
curl -X POST -H "X-Sync-API-Key: YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"table":"inventory","data":[]}' \
     "https://app.ruxxengas.com/api/sync/push"
```

### **Test Without API Key (Should Return 401):**
```bash
curl "https://app.ruxxengas.com/api/sync/pull?table=inventory&since="
```

## 🎯 Success Criteria

Your Master server is properly configured when:
- ✅ PHP Version: 8.2+
- ✅ APP_MODE: master
- ✅ SYNC_API_KEY: set
- ✅ 2 sync routes found
- ✅ API endpoints return 200 (not 404)
- ✅ Authentication works with API key

## 🧹 Cleanup

When everything is working:
1. Click "🗑️ Remove Setup Routes"
2. The setup script will clean up after itself
3. Your sync module is ready for production use

---

**Note:** This setup script is designed to work even with limited server access (like hPanel shared hosting) where you can't run artisan commands directly.
