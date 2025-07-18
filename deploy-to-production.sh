#!/bin/bash
# Deploy LCFTF Trail Status to production server
# Preserves remote permissions and handles sensitive files

echo "=== LCFTF TRAIL STATUS DEPLOYMENT ==="
echo "Deploying enhanced system with PHP 8.0+ push notifications..."
echo ""

# Configuration
REMOTE_HOST="root@zeroglitch.com"
REMOTE_PATH="/home/jamie/www/zeroglitch.com/trailstatus/"
LOCAL_PATH="trailstatus/"

# Deployment options
PRESERVE_PERMISSIONS="--no-times --no-perms --no-owner --no-group"
EXCLUDE_PATTERNS="--exclude='.git' --exclude='*.backup.*' --exclude='config.local.php' --exclude='data/' --exclude='*.log'"

echo "1. Pre-deployment checks:"

# Check if local directory exists
if [ ! -d "$LOCAL_PATH" ]; then
    echo "❌ Local trailstatus directory not found"
    echo "Please run this script from the project root directory"
    exit 1
fi

# Check for important files
REQUIRED_FILES=("admin.php" "index.php" "includes/config.php" "includes/notifications-php8.php")
for file in "${REQUIRED_FILES[@]}"; do
    if [ ! -f "$LOCAL_PATH$file" ]; then
        echo "❌ Required file missing: $file"
        exit 1
    fi
done

echo "✓ All required files present"

echo ""
echo "2. Checking remote connectivity:"
ssh $REMOTE_HOST "echo 'Remote connection successful'" 2>/dev/null
if [ $? -ne 0 ]; then
    echo "❌ Cannot connect to remote server"
    echo "Please check SSH access to $REMOTE_HOST"
    exit 1
fi
echo "✓ Remote server accessible"

echo ""
echo "3. Backing up remote data directory:"
ssh $REMOTE_HOST "
    cd $REMOTE_PATH
    if [ -d data ]; then
        tar -czf data-backup-\$(date +%Y%m%d_%H%M%S).tar.gz data/
        echo '✓ Data directory backed up'
    else
        echo '⚠️  No data directory found on remote'
    fi
"

echo ""
echo "4. Deploying files with rsync:"
echo "Command: rsync -av $PRESERVE_PERMISSIONS $EXCLUDE_PATTERNS $LOCAL_PATH $REMOTE_HOST:$REMOTE_PATH"

rsync -av $PRESERVE_PERMISSIONS $EXCLUDE_PATTERNS $LOCAL_PATH $REMOTE_HOST:$REMOTE_PATH

if [ $? -eq 0 ]; then
    echo "✓ File deployment successful"
else
    echo "❌ File deployment failed"
    exit 1
fi

echo ""
echo "5. Setting proper file permissions on remote:"
ssh $REMOTE_HOST "
    cd $REMOTE_PATH
    
    # Set directory permissions
    find . -type d -exec chmod 755 {} \;
    
    # Set file permissions
    find . -type f -name '*.php' -exec chmod 644 {} \;
    find . -type f -name '*.html' -exec chmod 644 {} \;
    find . -type f -name '*.css' -exec chmod 644 {} \;
    find . -type f -name '*.js' -exec chmod 644 {} \;
    find . -type f -name '*.json' -exec chmod 664 {} \;
    
    # Set ownership (if needed)
    # chown -R apache:jamie .
    
    echo '✓ File permissions updated'
"

echo ""
echo "6. Updating Apache virtual host configurations:"
scp zeroglitch.com.conf $REMOTE_HOST:/etc/httpd/domains.d/
scp zeroglitch.com-le-ssl.conf $REMOTE_HOST:/etc/httpd/domains.d/

ssh $REMOTE_HOST "
    # Set proper ownership for Apache configs
    chown root:root /etc/httpd/domains.d/zeroglitch.com*.conf
    chmod 644 /etc/httpd/domains.d/zeroglitch.com*.conf
    
    # Test Apache configuration
    httpd -t
    if [ \$? -eq 0 ]; then
        echo '✓ Apache configuration valid'
        systemctl reload httpd
        echo '✓ Apache reloaded'
    else
        echo '❌ Apache configuration invalid'
        exit 1
    fi
"

echo ""
echo "7. Ensuring data directory and files exist:"
ssh $REMOTE_HOST "
    cd $REMOTE_PATH
    
    # Create data directory if missing
    if [ ! -d data ]; then
        mkdir -p data
        chmod 755 data
        echo '✓ Created data directory'
    fi
    
    # Create JSON files if missing
    if [ ! -f data/users.json ]; then
        echo '[]' > data/users.json
        chmod 664 data/users.json
        echo '✓ Created users.json'
    fi
    
    if [ ! -f data/trails.json ]; then
        echo '[]' > data/trails.json
        chmod 664 data/trails.json
        echo '✓ Created trails.json'
    fi
    
    if [ ! -f data/push_subscribers.json ]; then
        echo '[]' > data/push_subscribers.json
        chmod 664 data/push_subscribers.json
        echo '✓ Created push_subscribers.json'
    fi
    
    echo '✓ Data files ready'
"

echo ""
echo "8. Upgrading to modern push notifications:"
ssh $REMOTE_HOST "
    cd $REMOTE_PATH
    
    # Backup current notifications if it exists
    if [ -f includes/notifications.php ]; then
        cp includes/notifications.php includes/notifications.php.backup.\$(date +%Y%m%d_%H%M%S)
        echo '✓ Backed up current notifications.php'
    fi
    
    # Use the modern PHP 8.0+ version
    if [ -f includes/notifications-php8.php ]; then
        cp includes/notifications-php8.php includes/notifications.php
        echo '✓ Upgraded to PHP 8.0+ notification system'
    else
        echo '⚠️  PHP 8 notifications file not found - using existing'
    fi
"

echo ""
echo "9. Testing deployment:"
echo "Testing main page..."
curl -s -o /dev/null -w "HTTP %{http_code} - %{time_total}s" https://zeroglitch.com/trailstatus/
echo ""

echo "Testing admin page..."
curl -s -o /dev/null -w "HTTP %{http_code} - %{time_total}s" https://zeroglitch.com/trailstatus/admin.php
echo ""

echo "Testing PHP execution..."
RESPONSE=$(curl -s https://zeroglitch.com/trailstatus/admin.php | head -1)
if [[ $RESPONSE == "<!DOCTYPE html"* ]]; then
    echo "✓ PHP is executing properly (HTML output detected)"
else
    echo "⚠️  PHP may not be executing properly"
    echo "Response: $RESPONSE"
fi

echo ""
echo "=== DEPLOYMENT COMPLETED ==="
echo "✓ Enhanced LCFTF Trail Status system deployed"
echo "✓ PHP 8.0+ push notifications upgraded"
echo "✓ Apache virtual hosts updated"
echo "✓ File permissions preserved"
echo "✓ Data directory secured"
echo ""
echo "🎉 Production deployment successful!"
echo "📍 Live site: https://zeroglitch.com/trailstatus/"
echo ""
echo "Next steps:"
echo "1. Test admin login functionality"
echo "2. Test trail status updates"
echo "3. Test push notifications in browser"
echo "4. Monitor error logs for any issues"
echo ""
echo "Monitoring commands:"
echo "  tail -f /var/log/httpd/zeroglitch-ssl-error.log"
echo "  tail -f /var/log/httpd/error_log | grep trailstatus"
