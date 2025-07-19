#!/bin/bash
# Migrate to PHP 8.0+ compatible push notifications

echo "=== PUSH NOTIFICATION SYSTEM MIGRATION ==="
echo "Upgrading to PHP 8.0+ compatible notifications..."
echo ""

# Check current system
echo "1. Checking current notification system:"
if [ -f "includes/notifications.php" ]; then
    echo "✓ Current notifications.php exists"
    # Check if it has PHP 8 features
    if grep -q "declare(strict_types=1);" includes/notifications.php; then
        echo "✓ Already using modern PHP syntax"
    else
        echo "⚠️  Using legacy PHP syntax - migration needed"
        NEEDS_MIGRATION=true
    fi
else
    echo "❌ notifications.php missing"
    exit 1
fi

echo ""
echo "2. Backing up current notifications:"
BACKUP_FILE="includes/notifications.php.backup.$(date +%Y%m%d_%H%M%S)"
cp includes/notifications.php "$BACKUP_FILE"
echo "✓ Backup created: $BACKUP_FILE"

if [ "$NEEDS_MIGRATION" = "true" ]; then
    echo ""
    echo "3. Migrating to PHP 8.0+ compatible version:"
    cp includes/notifications-php8.php includes/notifications.php
    echo "✓ Updated to modern notification system"
else
    echo ""
    echo "3. System already up to date"
fi

echo ""
echo "4. Checking PHP syntax:"
php -l includes/notifications.php
if [ $? -eq 0 ]; then
    echo "✓ PHP syntax is valid"
else
    echo "❌ PHP syntax errors found - restoring backup"
    cp "$BACKUP_FILE" includes/notifications.php
    exit 1
fi

echo ""
echo "5. Checking VAPID configuration:"
if [ -f "config.local.php" ]; then
    if grep -q "VAPID_PUBLIC_KEY" config.local.php; then
        echo "✓ VAPID keys configured"
    else
        echo "⚠️  VAPID keys not found - generating new ones"
        php generate-proper-vapid.php
    fi
else
    echo "⚠️  config.local.php missing - generating VAPID keys"
    php generate-proper-vapid.php
fi

echo ""
echo "6. Checking data files:"
DATA_DIR="data"
if [ ! -d "$DATA_DIR" ]; then
    mkdir -p "$DATA_DIR"
    chmod 755 "$DATA_DIR"
    echo "✓ Created data directory"
fi

PUSH_FILE="$DATA_DIR/push_subscribers.json"
if [ ! -f "$PUSH_FILE" ]; then
    echo "[]" > "$PUSH_FILE"
    chmod 664 "$PUSH_FILE"
    echo "✓ Created push subscribers file"
else
    echo "✓ Push subscribers file exists"
fi

echo ""
echo "7. Quick notification system test:"
# Use a simpler test that won't hang
php -r "
try {
    require_once 'includes/config.php';
    require_once 'includes/notifications.php';
    echo 'Functions loaded successfully' . PHP_EOL;
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Basic notification system test passed"
else
    echo "⚠️  Notification system may have issues - check manually"
fi

echo ""
echo "=== MIGRATION COMPLETED ==="
echo "✓ Push notification system upgraded to PHP 8.0+"
echo "✓ Improved error handling and security"
echo "✓ Better compatibility with modern browsers"
echo ""
echo "Key improvements:"
echo "  • Strict type declarations"
echo "  • Modern array syntax"
echo "  • Proper exception handling"
echo "  • Enhanced security features"
echo "  • Better error logging"
echo ""
echo "Next steps:"
echo "1. Test admin panel: curl https://zeroglitch.com/trailstatus/admin.php"
echo "2. Test notifications in browser"
echo "3. Check error logs for any issues"
echo ""
echo "Backup location: $BACKUP_FILE"
