#!/bin/bash
# Apache Troubleshooting Script for AlmaLinux 9
# Diagnoses common Apache access and permission issues

echo "=== APACHE TROUBLESHOOTING - ALMALINUX 9 ==="
echo "Diagnosing Apache 2.4 + PHP 8.0 issues..."
echo ""

# Show system information
echo "System Information:"
cat /etc/os-release | grep PRETTY_NAME
httpd -v | head -1
php -v | head -1
echo ""

# 1. Check Apache status
echo "1. Apache Service Status:"
systemctl status httpd --no-pager
echo ""

# 2. Check Apache processes
echo "2. Apache Processes:"
ps aux | grep httpd | head -5
echo ""

# 3. Check Apache error logs
echo "3. Recent Apache Errors (last 20 lines):"
tail -n 20 /var/log/httpd/error_log
echo ""

# 4. Check Apache configuration
echo "4. Apache Configuration Test:"
httpd -t
echo ""

# 5. Check listening ports and PHP-FPM
echo "5. Apache Listening Ports:"
ss -tlnp | grep httpd || netstat -tlnp | grep httpd
echo ""

echo "PHP-FPM Status:"
systemctl status php-fpm --no-pager
echo ""

# 6. Check DocumentRoot and web directories
echo "6. Web Directory Analysis:"
APACHE_CONF="/etc/httpd/conf/httpd.conf"
if [ -f "$APACHE_CONF" ]; then
    DOC_ROOT=$(grep "^DocumentRoot" "$APACHE_CONF" | awk '{print $2}' | tr -d '"')
    echo "DocumentRoot: $DOC_ROOT"
    
    if [ -d "$DOC_ROOT" ]; then
        echo "DocumentRoot exists: ✓"
        ls -la "$DOC_ROOT" | head -10
    else
        echo "DocumentRoot missing: ❌"
    fi
else
    echo "Apache config not found: ❌"
fi

echo ""

# 7. Check specific directories mentioned in errors
echo "7. Checking directories from error logs:"
ERROR_DIRS=(
    "/home/jamie/www/zeroglitch.com"
    "/home/jamie/www/zeroglitch.com/trailstatus"
    "/var/www/html"
    "/var/www/html/trailstatus"
)

for dir in "${ERROR_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo "Found: $dir"
        echo "  Owner: $(stat -c '%U:%G' "$dir")"
        echo "  Perms: $(stat -c '%a' "$dir")"
        echo "  SELinux: $(ls -ldZ "$dir" 2>/dev/null | awk '{print $4}' || echo 'N/A')"
        echo "  Contents:"
        ls -la "$dir" | head -5
    else
        echo "Missing: $dir"
    fi
    echo ""
done

# 8. Check Apache configuration files
echo "8. Apache Configuration Files:"
echo "Main config: $APACHE_CONF"
echo "Config directory: /etc/httpd/conf.d/"
ls -la /etc/httpd/conf.d/ | grep -v "^total"
echo ""

# 9. Check for .htaccess issues
echo "9. Checking for .htaccess files:"
find /var/www -name ".htaccess" -exec echo "Found: {}" \; -exec cat {} \; 2>/dev/null || echo "No .htaccess files found in /var/www"
find /home/jamie -name ".htaccess" -exec echo "Found: {}" \; -exec cat {} \; 2>/dev/null || echo "No .htaccess files found in /home/jamie"
echo ""

# 10. Check SELinux status
echo "10. SELinux Status:"
getenforce 2>/dev/null || echo "SELinux not available"
if command -v ausearch >/dev/null 2>&1; then
    echo "Recent SELinux denials:"
    ausearch -m AVC -ts recent 2>/dev/null | tail -5 || echo "No recent SELinux denials"
fi
echo ""

# 11. Test web access
echo "11. Testing Web Access:"
echo "Testing localhost..."
curl -I http://localhost/ 2>/dev/null | head -3 || echo "Localhost test failed"

echo "Testing localhost/trailstatus..."
curl -I http://localhost/trailstatus/ 2>/dev/null | head -3 || echo "Trailstatus test failed"
echo ""

# 12. Show suggested fixes
echo "=== SUGGESTED FIXES ==="
echo ""
echo "Based on the error logs, try these fixes:"
echo ""
echo "1. DEPLOY WEBSITE PROPERLY:"
echo "   sudo ./deploy-website.sh"
echo ""
echo "2. FIX PERMISSIONS:"
echo "   sudo chown -R apache:apache /var/www/html/"
echo "   sudo find /var/www/html -type d -exec chmod 755 {} \\;"
echo "   sudo find /var/www/html -type f -exec chmod 644 {} \\;"
echo ""
echo "3. FIX SELINUX (if enabled):"
echo "   sudo restorecon -R /var/www/html/"
echo "   sudo setsebool -P httpd_can_network_connect 1"
echo ""
echo "4. APACHE CONFIGURATION:"
echo "   Add to /etc/httpd/conf.d/website.conf:"
echo "   <Directory \"/var/www/html\">"
echo "       Options Indexes FollowSymLinks"
echo "       AllowOverride All"
echo "       Require all granted"
echo "   </Directory>"
echo ""
echo "5. RESTART APACHE:"
echo "   sudo systemctl restart httpd"
echo ""
echo "6. CHECK LOGS AFTER CHANGES:"
echo "   sudo tail -f /var/log/httpd/error_log"
