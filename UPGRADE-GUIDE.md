# AlmaLinux 9 + PHP 8.0 Upgrade Guide
## Trail Status Website Modernization

### ✅ COMPLETED UPGRADES

**System:**
- ✅ AlmaLinux 9 
- ✅ Apache 2.4.62
- ✅ PHP 8.0.30 with FPM

**Code Modernization:**
- ✅ PHP 8.0+ features (strict types, typed properties, match expressions)
- ✅ Removed PHP 5.4 compatibility shims
- ✅ Modern error handling with JsonException
- ✅ Class-based push notification system
- ✅ Better type safety throughout

### 🔧 DEPLOYMENT STEPS

#### 1. Run Emergency Memory Fix (if needed)
```bash
cd /home/jwhetsel/dev/lcftf/trailstatus
chmod +x emergency-memory-fix.sh
sudo ./emergency-memory-fix.sh
```

#### 2. Deploy the Modern Website
```bash
chmod +x deploy-website.sh troubleshoot-apache.sh
sudo ./deploy-website.sh
```

#### 3. Install Modern Push Notification Dependencies
```bash
chmod +x install-web-push.sh
sudo ./install-web-push.sh
```

#### 4. Generate New VAPID Keys
```bash
cd /opt/web-push-lib
sudo php generate-vapid-modern.php
```

### 📁 MODERNIZED FILES

**Core Configuration:**
- `includes/config.php` - Updated for PHP 8.0+ with strict types
- `includes/notifications-modern.php` - New modern notification system

**New Features:**
- Class-based push subscribers (`PushSubscriber` class)
- Better error handling with `JsonException`
- Modern HTTP client for push notifications
- Support for both FCM and Web Push protocols

**Deployment Scripts:**
- `deploy-website.sh` - Updated for AlmaLinux 9
- `install-web-push.sh` - Installs Composer + web-push-php library
- `troubleshoot-apache.sh` - Updated diagnostics

### 🚀 NEW CAPABILITIES

#### Modern Push Notifications
```php
// Type-safe subscriber creation
$subscriber = new PushSubscriber(
    id: 1,
    endpoint: $endpoint,
    p256dh_key: $p256dh,
    auth_key: $auth,
    trails: [1, 2, 3]
);

// Modern message sending
$message = new PushMessage(
    title: "Trail Update",
    body: "Main Trail is now Open",
    data: ['trail_id' => 1]
);
```

#### Better Error Handling
```php
// JSON operations with exceptions
try {
    $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    error_log("JSON error: " . $e->getMessage());
}
```

#### Modern PHP Features
- Strict type declarations
- Named parameters
- Match expressions for status handling
- Readonly properties
- Better array syntax

### 🔧 CONFIGURATION UPDATES

#### Apache 2.4 Configuration
- Security headers (X-Frame-Options, X-XSS-Protection)
- Better caching for static assets
- Proper PHP-FPM integration
- Enhanced directory security

#### PHP 8.0 Optimizations
- Better memory usage
- Improved error logging
- Modern JSON handling
- Type safety improvements

### 📊 PERFORMANCE IMPROVEMENTS

**Memory Usage:**
- More efficient JSON handling
- Better memory cleanup
- Reduced object overhead

**Error Handling:**
- Structured error logging
- Better exception handling
- Graceful degradation

**Push Notifications:**
- Batch notification support
- Better endpoint handling
- Improved error reporting

### 🛠️ TROUBLESHOOTING

#### Check System Status
```bash
sudo ./troubleshoot-apache.sh
```

#### Test Push Notifications
```bash
cd /opt/web-push-lib
sudo php -r "require 'vendor/autoload.php'; echo 'Web Push library loaded successfully\n';"
```

#### Monitor Logs
```bash
# Apache errors
sudo tail -f /var/log/httpd/error_log

# PHP errors  
sudo tail -f /var/log/httpd/php_errors.log

# System messages
sudo journalctl -f
```

### 🔮 NEXT STEPS

#### Immediate:
1. Deploy the modernized code
2. Test basic website functionality
3. Generate and configure VAPID keys
4. Test push notifications

#### Optional Enhancements:
1. Add Redis for session storage
2. Implement proper logging framework
3. Add unit tests with PHPUnit
4. Consider moving to containerized deployment

### 📚 REFERENCES

- [PHP 8.0 Migration Guide](https://www.php.net/manual/en/migration80.php)
- [Apache 2.4 Documentation](https://httpd.apache.org/docs/2.4/)
- [Web Push Protocol](https://tools.ietf.org/html/rfc8030)
- [AlmaLinux Documentation](https://wiki.almalinux.org/)

### 🆘 EMERGENCY CONTACTS

If something breaks:
1. Run: `sudo ./troubleshoot-apache.sh`
2. Check: `sudo systemctl status httpd php-fpm`
3. Revert: `sudo systemctl stop httpd && sudo ./emergency-memory-fix.sh`
4. Restart: `sudo systemctl start httpd`
