#!/bin/bash
# Fix Home Directory Website Permissions
# Fixes 403 Forbidden for websites in /home directories

echo "=== HOME DIRECTORY WEBSITE FIX ==="
echo "Fixing permissions for website in /home/jamie/www/zeroglitch.com/trailstatus"
echo ""

WEBSITE_DIR="/home/jamie/www/zeroglitch.com"
TRAILSTATUS_DIR="/home/jamie/www/zeroglitch.com/trailstatus"

# 1. Check current permissions
echo "1. Current permissions:"
echo "Website root: $(ls -ld $WEBSITE_DIR)"
echo "Trail status: $(ls -ld $TRAILSTATUS_DIR)"
echo ""

# 2. Fix directory permissions - home directories need special handling
echo "2. Fixing directory permissions for home directory websites..."

# The home directory chain needs execute permissions for apache
chmod o+x /home/jamie
chmod o+x /home/jamie/www
chmod o+x /home/jamie/www/zeroglitch.com

# Set proper permissions for the website
chown -R jamie:apache "$WEBSITE_DIR"
find "$WEBSITE_DIR" -type d -exec chmod 755 {} \;
find "$WEBSITE_DIR" -type f -exec chmod 644 {} \;

# Make sure Apache can execute PHP files
find "$TRAILSTATUS_DIR" -name "*.php" -exec chmod 644 {} \;

echo "✓ Directory permissions fixed"

# 3. Fix SELinux contexts for home directory websites
echo "3. Fixing SELinux contexts..."

# Set the correct SELinux context for home directory websites
setsebool -P httpd_enable_homedirs 1
setsebool -P httpd_read_user_content 1

# Set correct file contexts
semanage fcontext -a -t httpd_exec_t "$WEBSITE_DIR(/.*)?" 2>/dev/null || echo "Context already exists"
restorecon -R "$WEBSITE_DIR"

echo "✓ SELinux contexts fixed"

# 4. Check Apache configuration is loaded
echo "4. Checking Apache configuration..."
httpd -t
if [ $? -eq 0 ]; then
    echo "✓ Apache configuration is valid"
else
    echo "❌ Apache configuration has errors"
    exit 1
fi

# 5. Restart Apache to ensure all configs are loaded
echo "5. Restarting Apache..."
systemctl restart httpd
systemctl status httpd --no-pager -l

# 6. Test access
echo ""
echo "6. Testing website access..."
echo "HTTP test:"
curl -I http://zeroglitch.com/trailstatus/ 2>/dev/null | head -3

echo ""
echo "HTTPS test:"
curl -I https://zeroglitch.com/trailstatus/ 2>/dev/null | head -3

echo ""
echo "Localhost test:"
curl -I http://localhost/trailstatus/ -H "Host: zeroglitch.com" 2>/dev/null | head -3

# 7. Show final permissions
echo ""
echo "7. Final permissions:"
echo "Jamie home: $(ls -ld /home/jamie)"
echo "WWW dir: $(ls -ld /home/jamie/www)" 
echo "Site dir: $(ls -ld /home/jamie/www/zeroglitch.com)"
echo "Trail dir: $(ls -ld /home/jamie/www/zeroglitch.com/trailstatus)"

# 8. Check for any remaining issues
echo ""
echo "8. Checking for issues..."
echo "Recent Apache errors:"
tail -n 5 /var/log/httpd/error_log

echo ""
echo "Recent zeroglitch errors:"
tail -n 5 /var/log/httpd/zeroglitch-error.log 2>/dev/null || echo "No zeroglitch error log found"

echo ""
echo "=== FIX COMPLETE ==="
if curl -s -o /dev/null -w "%{http_code}" https://zeroglitch.com/trailstatus/ | grep -q "200\|302"; then
    echo "✅ Website is now accessible!"
    echo "Visit: https://zeroglitch.com/trailstatus/"
else
    echo "⚠️  Still having issues. Check the error logs above."
    echo ""
    echo "Manual debugging:"
    echo "1. Check: tail -f /var/log/httpd/zeroglitch-error.log"
    echo "2. Check: tail -f /var/log/httpd/error_log"
    echo "3. Test: curl -v https://zeroglitch.com/trailstatus/"
fi
