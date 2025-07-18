#!/bin/bash
# Website Deployment and Permission Fix Script for AlmaLinux 9
# Fixes Apache access issues and deploys trail status site

echo "=== WEBSITE DEPLOYMENT & PERMISSION FIX ==="
echo "Deploying for AlmaLinux 9 + Apache 2.4 + PHP 8.0..."
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (sudo)"
    exit 1
fi

# Show current Apache and PHP versions
echo "System Information:"
httpd -v | head -1
php -v | head -1
echo ""

# Show current Apache status
echo "Current Apache status:"
systemctl status httpd --no-pager -l
echo ""

# Check Apache error logs for recent issues
echo "Recent Apache errors:"
tail -n 10 /var/log/httpd/error_log
echo ""

# 1. Identify correct web directory
echo "Step 1: Identifying web directory structure..."

# Common web directories to check
WEB_DIRS=(
    "/var/www/html"
    "/home/jamie/www/zeroglitch.com"
    "/home/jamie/public_html"
    "/var/www"
)

CURRENT_DIR="/home/jwhetsel/dev/lcftf/trailstatus"
TARGET_DIR=""

for dir in "${WEB_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "Found web directory: $dir"
        if [ "$dir" = "/var/www/html" ]; then
            TARGET_DIR="$dir"
            break
        elif [ -z "$TARGET_DIR" ]; then
            TARGET_DIR="$dir"
        fi
    fi
done

if [ -z "$TARGET_DIR" ]; then
    echo "No web directory found, creating /var/www/html..."
    mkdir -p /var/www/html
    TARGET_DIR="/var/www/html"
fi

echo "Using web directory: $TARGET_DIR"
echo ""

# 2. Deploy trail status files
echo "Step 2: Deploying trail status website..."

# Create trailstatus subdirectory if needed
if [[ "$TARGET_DIR" != *"/trailstatus"* ]]; then
    TARGET_DIR="$TARGET_DIR/trailstatus"
fi

mkdir -p "$TARGET_DIR"
echo "Target directory: $TARGET_DIR"

# Copy files from development directory (exclude scripts and tests)
echo "Copying website files..."
rsync -av \
    --exclude='*.sh' \
    --exclude='test-*.php' \
    --exclude='generate-*.php' \
    --exclude='fix-*.sh' \
    --exclude='configure-*.sh' \
    --exclude='optimize-*.sh' \
    --exclude='safe-*.sh' \
    --exclude='emergency-*.sh' \
    --exclude='troubleshoot-*.sh' \
    --exclude='.git*' \
    "$CURRENT_DIR/" "$TARGET_DIR/"

# Ensure critical files are copied
CRITICAL_FILES=(
    "index.php"
    "admin.php"
    "login.php"
    "logout.php"
    "404.php"
    "notifications.php"
    "push-subscribe.php"
    "sw.js"
    ".htaccess"
)

for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$CURRENT_DIR/$file" ]; then
        cp "$CURRENT_DIR/$file" "$TARGET_DIR/"
        echo "✓ Copied $file"
    fi
done

# Copy directories
CRITICAL_DIRS=("css" "images" "includes" "data")
for dir in "${CRITICAL_DIRS[@]}"; do
    if [ -d "$CURRENT_DIR/$dir" ]; then
        cp -r "$CURRENT_DIR/$dir" "$TARGET_DIR/"
        echo "✓ Copied $dir/"
    fi
done

echo "✓ Website files copied"

# 3. Set correct ownership
echo "Step 3: Setting correct file ownership..."
chown -R apache:apache "$TARGET_DIR"
echo "✓ Files owned by apache:apache"

# 4. Set correct permissions
echo "Step 4: Setting correct file permissions..."

# Directories: 755 (readable/executable by all, writable by owner)
find "$TARGET_DIR" -type d -exec chmod 755 {} \;

# PHP files: 644 (readable by all, writable by owner)
find "$TARGET_DIR" -name "*.php" -exec chmod 644 {} \;

# CSS/JS files: 644
find "$TARGET_DIR" -name "*.css" -exec chmod 644 {} \;
find "$TARGET_DIR" -name "*.js" -exec chmod 644 {} \;

# Images: 644
find "$TARGET_DIR" -name "*.jpg" -name "*.png" -name "*.gif" -exec chmod 644 {} \;

# Data directory: 755, data files: 644 but writable by apache
chmod 755 "$TARGET_DIR/data"
chmod 664 "$TARGET_DIR/data"/*.json 2>/dev/null || true

# .htaccess: 644
chmod 644 "$TARGET_DIR/.htaccess" 2>/dev/null || true

echo "✓ File permissions set"

# 5. Fix SELinux contexts
echo "Step 5: Setting SELinux contexts..."
if command -v restorecon >/dev/null 2>&1; then
    # Set web content context
    restorecon -R "$TARGET_DIR"
    
    # Set specific contexts for writable data
    semanage fcontext -a -t httpd_exec_t "$TARGET_DIR/data(/.*)?" 2>/dev/null || true
    restorecon -R "$TARGET_DIR/data"
    
    echo "✓ SELinux contexts set"
else
    echo "⚠️  SELinux tools not available, skipping context setting"
fi

# 6. Configure Apache document root
echo "Step 6: Checking Apache configuration..."

# Find main Apache config
APACHE_CONF="/etc/httpd/conf/httpd.conf"
if [ -f "$APACHE_CONF" ]; then
    # Check current DocumentRoot
    CURRENT_ROOT=$(grep "^DocumentRoot" "$APACHE_CONF" | awk '{print $2}' | tr -d '"')
    echo "Current DocumentRoot: $CURRENT_ROOT"
    
    # If not pointing to our target, suggest change
    if [[ "$TARGET_DIR" != "$CURRENT_ROOT"* ]]; then
        echo "⚠️  DocumentRoot may need adjustment"
        echo "   Current: $CURRENT_ROOT"
        echo "   Website: $TARGET_DIR"
        echo ""
        echo "Consider updating DocumentRoot in $APACHE_CONF"
    fi
fi

# 7. Create Apache virtual host configuration for AlmaLinux 9
echo "Step 7: Creating Apache configuration for AlmaLinux 9..."
cat > /etc/httpd/conf.d/trailstatus.conf << EOF
# Trail Status Website Configuration for AlmaLinux 9 + Apache 2.4

# Main website directory
<Directory "$TARGET_DIR">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
    
    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    
    # PHP settings
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value memory_limit 256M
    php_flag log_errors on
    php_value error_log /var/log/httpd/php_errors.log
</Directory>

# Data directory - restrict access but allow PHP to read/write
<Directory "$TARGET_DIR/data">
    Options -Indexes -FollowSymLinks
    AllowOverride None
    Require all denied
    
    # Allow PHP to access
    <FilesMatch "\.json$">
        Require all denied
    </FilesMatch>
</Directory>

# Allow access to CSS, JS, images
<DirectoryMatch "$TARGET_DIR/(css|images|js)">
    Options -Indexes
    AllowOverride None
    Require all granted
    
    # Cache static assets
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</DirectoryMatch>

# Service Worker
<Files "sw.js">
    Header set Service-Worker-Allowed "/"
    Header set Cache-Control "no-cache"
</Files>

# Alias for trail status
Alias /trailstatus "$TARGET_DIR"
EOF

echo "✓ Apache configuration created for AlmaLinux 9"

# 8. Test Apache configuration
echo "Step 8: Testing Apache configuration..."
if httpd -t; then
    echo "✓ Apache configuration test passed"
    
    # Reload Apache
    systemctl reload httpd
    if systemctl is-active httpd >/dev/null 2>&1; then
        echo "✓ Apache reloaded successfully"
    else
        echo "❌ Apache failed to reload"
        systemctl status httpd --no-pager
    fi
else
    echo "❌ Apache configuration test failed"
    httpd -t
fi

# 9. Show final status
echo ""
echo "=== DEPLOYMENT COMPLETE ==="
echo "Website deployed to: $TARGET_DIR"
echo "Website URL: http://your-domain.com/trailstatus/"
echo ""

# Test local access
echo "Testing local access..."
if curl -s -o /dev/null -w "%{http_code}" http://localhost/trailstatus/ | grep -q "200\|301\|302"; then
    echo "✅ Local access working"
else
    echo "⚠️  Local access may have issues"
fi

echo ""
echo "File structure:"
ls -la "$TARGET_DIR"

echo ""
echo "=== TROUBLESHOOTING ==="
echo "If website still not accessible:"
echo "1. Check Apache error log: tail -f /var/log/httpd/error_log"
echo "2. Check file permissions: ls -la $TARGET_DIR"
echo "3. Check SELinux: getenforce && ls -lZ $TARGET_DIR"
echo "4. Test configuration: httpd -t"
echo "5. Check DocumentRoot in: $APACHE_CONF"
echo ""
echo "Access your website at:"
echo "  http://your-domain.com/trailstatus/"
echo "  http://your-server-ip/trailstatus/"
