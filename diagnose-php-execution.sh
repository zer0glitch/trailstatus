#!/bin/bash
# PHP Execution Diagnostic
# Checks why PHP is not being executed by Apache

echo "=== PHP EXECUTION DIAGNOSTIC ==="
echo "Checking why PHP code is displayed instead of executed..."
echo ""

# 1. Check Apache PHP modules
echo "1. Apache PHP Module Check:"
echo "Loaded Apache modules:"
httpd -M 2>/dev/null | grep -i php

echo ""
echo "Available PHP modules in /etc/httpd/modules/:"
ls -la /etc/httpd/modules/ | grep -i php

# 2. Check PHP-FPM status and configuration
echo ""
echo "2. PHP-FPM Status:"
systemctl status php-fpm --no-pager -l

echo ""
echo "PHP-FPM listening sockets:"
ss -tlnp | grep php

# 3. Check Apache configuration for PHP handling
echo ""
echo "3. Apache PHP Configuration:"
echo "Main config PHP handlers:"
grep -r "php" /etc/httpd/conf/httpd.conf | grep -v "^#"

echo ""
echo "PHP configuration in conf.d:"
ls -la /etc/httpd/conf.d/ | grep -i php
if [ -f /etc/httpd/conf.d/php.conf ]; then
    echo "PHP.conf contents:"
    cat /etc/httpd/conf.d/php.conf
fi

# 4. Check if mod_php or php-fpm is configured
echo ""
echo "4. PHP Handler Configuration:"
echo "Checking for PHP handler directives:"
grep -r "SetHandler\|AddHandler\|ProxyPassMatch" /etc/httpd/conf* /etc/httpd/conf.d/ 2>/dev/null | grep -i php

# 5. Test PHP info file
echo ""
echo "5. PHP Test File:"
TEST_PHP="/tmp/phpinfo.php"
echo "<?php phpinfo(); ?>" > $TEST_PHP
echo "Testing PHP CLI execution:"
php $TEST_PHP | head -5

echo ""
echo "Creating test file in web directory:"
WEB_TEST="/home/jamie/www/zeroglitch.com/trailstatus/test-php.php"
echo "<?php echo 'PHP is working: ' . date('Y-m-d H:i:s'); ?>" > $WEB_TEST
chmod 644 $WEB_TEST
echo "Test file created: $WEB_TEST"

echo ""
echo "Testing web access to PHP file:"
curl -s https://zeroglitch.com/trailstatus/test-php.php | head -3

# 6. Check virtual host PHP configuration
echo ""
echo "6. Virtual Host PHP Configuration:"
echo "Checking SSL vhost for PHP directives:"
if [ -f /etc/httpd/domains.d/zeroglitch.com-le-ssl.conf ]; then
    grep -A10 -B10 "Directory.*trailstatus" /etc/httpd/domains.d/zeroglitch.com-le-ssl.conf
fi

# 7. Check PHP package installation
echo ""
echo "7. PHP Package Check:"
echo "Installed PHP packages:"
rpm -qa | grep -i php | sort

echo ""
echo "PHP version:"
php --version | head -1

# 8. Check file associations
echo ""
echo "8. File Type Check:"
echo "MIME type for .php files:"
file --mime-type /home/jamie/www/zeroglitch.com/trailstatus/admin.php

echo ""
echo "=== QUICK FIXES ==="
echo ""
echo "If mod_php is missing:"
echo "  dnf install php-fpm"
echo "  systemctl enable --now php-fpm"
echo ""
echo "If PHP-FPM handler is missing, add to SSL vhost:"
echo '  <FilesMatch "\.php$">'
echo '    SetHandler "proxy:unix:/run/php-fpm/www.sock|fcgi://localhost"'
echo '  </FilesMatch>'
echo ""
echo "Then restart services:"
echo "  systemctl restart php-fpm"
echo "  systemctl restart httpd"
