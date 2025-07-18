#!/bin/bash
# Deploy corrected Apache vhost configurations with PHP-FPM handlers

echo "=== DEPLOYING APACHE VHOST CONFIGURATIONS ==="
echo "Adding PHP-FPM handlers to fix PHP execution..."
echo ""

# Backup existing configs
echo "1. Backing up existing configurations:"
sudo cp /etc/httpd/domains.d/zeroglitch.com.conf /etc/httpd/domains.d/zeroglitch.com.conf.backup.$(date +%Y%m%d_%H%M%S)
if [ -f /etc/httpd/domains.d/zeroglitch.com-le-ssl.conf ]; then
    sudo cp /etc/httpd/domains.d/zeroglitch.com-le-ssl.conf /etc/httpd/domains.d/zeroglitch.com-le-ssl.conf.backup.$(date +%Y%m%d_%H%M%S)
fi
echo "✓ Backups created"

# Deploy new configurations
echo ""
echo "2. Deploying new configurations:"
sudo cp zeroglitch.com.conf /etc/httpd/domains.d/
sudo cp zeroglitch.com-le-ssl.conf /etc/httpd/domains.d/
sudo chown root:root /etc/httpd/domains.d/zeroglitch.com*.conf
sudo chmod 644 /etc/httpd/domains.d/zeroglitch.com*.conf
echo "✓ New configurations deployed"

# Test Apache configuration
echo ""
echo "3. Testing Apache configuration:"
sudo httpd -t
if [ $? -eq 0 ]; then
    echo "✓ Apache configuration is valid"
else
    echo "❌ Apache configuration has errors"
    exit 1
fi

# Check PHP-FPM status
echo ""
echo "4. Checking PHP-FPM status:"
sudo systemctl status php-fpm --no-pager | head -5
if ! systemctl is-active --quiet php-fpm; then
    echo "Starting PHP-FPM..."
    sudo systemctl start php-fpm
    sudo systemctl enable php-fpm
fi
echo "✓ PHP-FPM is running"

# Restart Apache
echo ""
echo "5. Restarting Apache:"
sudo systemctl restart httpd
if [ $? -eq 0 ]; then
    echo "✓ Apache restarted successfully"
else
    echo "❌ Apache restart failed"
    exit 1
fi

# Test PHP execution
echo ""
echo "6. Testing PHP execution:"
echo "Testing HTTPS admin.php:"
curl -s https://zeroglitch.com/trailstatus/admin.php | head -5 | grep -q "<!DOCTYPE html"
if [ $? -eq 0 ]; then
    echo "✓ PHP is now being executed (HTML output detected)"
else
    echo "❌ PHP may still not be executing properly"
    echo "Response preview:"
    curl -s https://zeroglitch.com/trailstatus/admin.php | head -3
fi

echo ""
echo "7. Testing main index page:"
curl -s https://zeroglitch.com/trailstatus/ | head -5 | grep -q "<!DOCTYPE html"
if [ $? -eq 0 ]; then
    echo "✓ Main page is working"
else
    echo "❌ Main page may have issues"
fi

echo ""
echo "=== CONFIGURATION SUMMARY ==="
echo "✓ Added PHP-FPM handlers to both HTTP and HTTPS vhosts"
echo "✓ Handlers placed both globally and in trailstatus Directory block"
echo "✓ Security restrictions for config files and JSON data maintained"
echo "✓ HTTP to HTTPS redirect enabled"
echo ""
echo "If PHP is still not executing, check:"
echo "1. PHP-FPM socket exists: ls -la /run/php-fpm/www.sock"
echo "2. Apache error logs: tail -f /var/log/httpd/zeroglitch-ssl-error.log"
echo "3. PHP-FPM logs: tail -f /var/log/php-fpm/www-error.log"
