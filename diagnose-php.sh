#!/bin/bash
# PHP and Database Diagnostic Script
# Checks for PHP errors and database file issues

echo "=== PHP AND DATABASE DIAGNOSTIC ==="
echo "Checking PHP configuration and database file permissions..."
echo ""

TRAILSTATUS_DIR="/home/jamie/www/zeroglitch.com/trailstatus"
DATA_DIR="$TRAILSTATUS_DIR/data"

# 1. Test basic PHP functionality
echo "1. PHP Basic Test:"
echo "<?php phpinfo(); ?>" > /tmp/test-php.php
php /tmp/test-php.php > /tmp/php-output.txt 2>&1
if [ $? -eq 0 ]; then
    echo "✓ PHP CLI is working"
else
    echo "❌ PHP CLI has issues"
    cat /tmp/php-output.txt
fi

# 2. Test PHP-FPM
echo ""
echo "2. PHP-FPM Status:"
systemctl status php-fpm --no-pager -l
echo ""
echo "PHP-FPM processes:"
ps aux | grep php-fpm | head -5

# 3. Check data directory and files
echo ""
echo "3. Data Directory Analysis:"
if [ -d "$DATA_DIR" ]; then
    echo "Data directory exists: ✓"
    echo "Permissions: $(stat -c '%a %U:%G' "$DATA_DIR")"
    echo "SELinux context: $(ls -ldZ "$DATA_DIR" | awk '{print $4}')"
    echo ""
    echo "Data files:"
    ls -la "$DATA_DIR"
else
    echo "❌ Data directory missing: $DATA_DIR"
fi

# 4. Check specific database files
echo ""
echo "4. Database Files Check:"
DB_FILES=("users.json" "trails.json" "push_subscribers.json")
for file in "${DB_FILES[@]}"; do
    filepath="$DATA_DIR/$file"
    if [ -f "$filepath" ]; then
        echo "$file:"
        echo "  Permissions: $(stat -c '%a %U:%G' "$filepath")"
        echo "  Size: $(stat -c '%s bytes' "$filepath")"
        echo "  Readable by apache: $(sudo -u apache test -r "$filepath" && echo "✓" || echo "❌")"
        echo "  Writable by apache: $(sudo -u apache test -w "$filepath" && echo "✓" || echo "❌")"
        echo "  Content preview:"
        head -n 3 "$filepath" 2>/dev/null || echo "    Cannot read file"
    else
        echo "❌ Missing: $file"
    fi
    echo ""
done

# 5. Check includes directory
echo "5. Includes Directory:"
INCLUDES_DIR="$TRAILSTATUS_DIR/includes"
if [ -d "$INCLUDES_DIR" ]; then
    echo "Includes directory: ✓"
    echo "Permissions: $(stat -c '%a %U:%G' "$INCLUDES_DIR")"
    echo "Files:"
    ls -la "$INCLUDES_DIR"
else
    echo "❌ Includes directory missing"
fi

# 6. Test PHP file syntax
echo ""
echo "6. PHP Syntax Check:"
PHP_FILES=("$TRAILSTATUS_DIR/admin.php" "$INCLUDES_DIR/config.php" "$INCLUDES_DIR/notifications.php")
for phpfile in "${PHP_FILES[@]}"; do
    if [ -f "$phpfile" ]; then
        echo "Checking $(basename "$phpfile"):"
        php -l "$phpfile" 2>&1 | grep -v "No syntax errors detected" || echo "  ✓ Syntax OK"
    else
        echo "❌ Missing: $(basename "$phpfile")"
    fi
done

# 7. Check Apache error logs for PHP errors
echo ""
echo "7. Recent PHP/Apache Errors:"
echo "Main error log (PHP related):"
tail -n 20 /var/log/httpd/error_log | grep -E "(PHP|Fatal|Warning|Notice|trailstatus|admin\.php)"

echo ""
echo "Site-specific error log:"
if [ -f /var/log/httpd/zeroglitch-ssl-error.log ]; then
    tail -n 10 /var/log/httpd/zeroglitch-ssl-error.log
else
    echo "No SSL error log found"
fi

# 8. Test curl to admin.php
echo ""
echo "8. Testing admin.php access:"
echo "HTTP test:"
curl -I http://localhost/trailstatus/admin.php -H "Host: zeroglitch.com" 2>/dev/null | head -5

echo ""
echo "HTTPS test:"
curl -I https://zeroglitch.com/trailstatus/admin.php 2>/dev/null | head -5

echo ""
echo "Full response test (first 10 lines):"
curl -s https://zeroglitch.com/trailstatus/admin.php | head -10

# 9. Check if config files exist and are readable
echo ""
echo "9. Configuration Files:"
CONFIG_FILES=("$TRAILSTATUS_DIR/config.local.php" "$INCLUDES_DIR/config.php")
for config in "${CONFIG_FILES[@]}"; do
    if [ -f "$config" ]; then
        echo "$(basename "$config"): ✓"
        echo "  Permissions: $(stat -c '%a %U:%G' "$config")"
        echo "  Readable by apache: $(sudo -u apache test -r "$config" && echo "✓" || echo "❌")"
    else
        echo "$(basename "$config"): Missing"
    fi
done

# 10. Check PHP error logging
echo ""
echo "10. PHP Error Logging:"
echo "PHP error_log setting:"
php -i | grep error_log | head -3

echo ""
echo "Recent PHP errors:"
if [ -f /var/log/httpd/php_errors.log ]; then
    tail -n 10 /var/log/httpd/php_errors.log
else
    echo "No PHP error log found at /var/log/httpd/php_errors.log"
fi

# 11. Suggested fixes
echo ""
echo "=== SUGGESTED FIXES ==="
echo ""
echo "If data directory issues:"
echo "  sudo chown -R apache:apache $DATA_DIR"
echo "  sudo chmod 755 $DATA_DIR"
echo "  sudo chmod 664 $DATA_DIR/*.json"
echo ""
echo "If PHP-FPM issues:"
echo "  sudo systemctl restart php-fpm"
echo "  sudo systemctl restart httpd"
echo ""
echo "If file permissions:"
echo "  sudo chown -R apache:jamie $TRAILSTATUS_DIR"
echo "  sudo chmod -R 644 $TRAILSTATUS_DIR/*.php"
echo "  sudo chmod 755 $TRAILSTATUS_DIR"
echo ""
echo "Check detailed curl output:"
echo "  curl -v https://zeroglitch.com/trailstatus/admin.php"
