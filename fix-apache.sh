#!/bin/bash
# Apache Recovery Script
# Fixes Apache issues caused by memory optimization

echo "=== APACHE RECOVERY SCRIPT ==="
echo "Diagnosing and fixing Apache issues..."
echo ""

# Check Apache status
echo "Apache service status:"
systemctl status httpd --no-pager
echo ""

# Check Apache configuration
echo "Testing Apache configuration..."
httpd -t
echo ""

# Check for problematic configuration
if [ -f /etc/httpd/conf.d/low-memory.conf ]; then
    echo "Found low-memory.conf, checking if it's causing issues..."
    
    # Backup and remove the problematic config
    mv /etc/httpd/conf.d/low-memory.conf /etc/httpd/conf.d/low-memory.conf.backup
    echo "Backed up low-memory.conf"
    
    # Test configuration without the file
    echo "Testing configuration without low-memory.conf..."
    if httpd -t; then
        echo "✓ Configuration is valid without low-memory.conf"
        echo "Attempting to start Apache..."
        
        systemctl start httpd
        if systemctl is-active httpd >/dev/null 2>&1; then
            echo "✅ Apache started successfully!"
            echo ""
            echo "Creating safer low-memory configuration..."
            
            # Create a safer configuration
            cat > /etc/httpd/conf.d/safe-low-memory.conf << 'EOF'
# Safe low-memory Apache configuration
# Only essential memory optimizations

# Connection limits
KeepAlive On
KeepAliveTimeout 5
MaxKeepAliveRequests 100

# Timeout settings  
Timeout 300

# Basic memory optimization
<IfModule mpm_prefork_module>
    StartServers          2
    MinSpareServers       2
    MaxSpareServers       4
    MaxRequestWorkers     50
    MaxConnectionsPerChild 1000
</IfModule>
EOF
            
            # Test the new configuration
            if httpd -t; then
                systemctl reload httpd
                echo "✅ Safe low-memory configuration applied and Apache reloaded"
            else
                echo "⚠️  New configuration has issues, removing it"
                rm -f /etc/httpd/conf.d/safe-low-memory.conf
            fi
        else
            echo "❌ Apache still won't start. Checking logs..."
            journalctl -xeu httpd.service --no-pager -n 20
        fi
    else
        echo "❌ Configuration still has issues. Restoring original..."
        mv /etc/httpd/conf.d/low-memory.conf.backup /etc/httpd/conf.d/low-memory.conf
    fi
else
    echo "No low-memory.conf found. Checking other issues..."
    
    # Try to start Apache
    systemctl start httpd
    if ! systemctl is-active httpd >/dev/null 2>&1; then
        echo "Apache won't start. Checking logs..."
        journalctl -xeu httpd.service --no-pager -n 20
        echo ""
        echo "Common fixes to try:"
        echo "1. Check syntax: httpd -t"
        echo "2. Check ports: netstat -tlnp | grep :80"
        echo "3. Check SELinux: getenforce"
        echo "4. Check disk space: df -h"
    fi
fi

echo ""
echo "=== FINAL STATUS ==="
echo "Apache status:"
systemctl status httpd --no-pager -l
echo ""

if systemctl is-active httpd >/dev/null 2>&1; then
    echo "✅ Apache is running"
    echo "Testing web server response..."
    if curl -s -o /dev/null -w "%{http_code}" http://localhost/ | grep -q "200\|403"; then
        echo "✅ Web server is responding"
    else
        echo "⚠️  Web server may not be responding properly"
    fi
else
    echo "❌ Apache is not running"
    echo ""
    echo "MANUAL RECOVERY STEPS:"
    echo "1. Check configuration: httpd -t"
    echo "2. View detailed logs: journalctl -xeu httpd.service"
    echo "3. Check error log: tail -n 50 /var/log/httpd/error_log"
    echo "4. Try manual start: systemctl start httpd"
fi
