#!/bin/bash
# Quick Apache 403 Diagnostic
# Finds the exact cause of 403 Forbidden errors

echo "=== APACHE 403 FORBIDDEN DIAGNOSTIC ==="
echo "Diagnosing https://zeroglitch.com/trailstatus/ access..."
echo ""

# 1. Check if the virtual host config is being loaded
echo "1. Virtual Host Configuration Check:"
echo "Domains directory contents:"
ls -la /etc/httpd/domains.d/

echo ""
echo "Is domains.d included in main config?"
grep -r "domains.d" /etc/httpd/conf/httpd.conf || echo "❌ domains.d not included in main config!"

echo ""
echo "Include statements in main config:"
grep -i "include" /etc/httpd/conf/httpd.conf | grep -v "^#"

# 2. Test which virtual host is being used
echo ""
echo "2. Virtual Host Test:"
echo "Testing with Host header:"
curl -I http://localhost/trailstatus/ -H "Host: zeroglitch.com" 2>/dev/null | head -3

echo ""
echo "Testing direct HTTPS:"
curl -I https://zeroglitch.com/trailstatus/ 2>/dev/null | head -3

# 3. Check directory permissions step by step
echo ""
echo "3. Directory Permission Chain:"
for dir in "/home" "/home/jamie" "/home/jamie/www" "/home/jamie/www/zeroglitch.com" "/home/jamie/www/zeroglitch.com/trailstatus"; do
    if [ -d "$dir" ]; then
        perms=$(stat -c '%a' "$dir")
        owner=$(stat -c '%U:%G' "$dir")
        echo "$dir -> $perms ($owner)"
        
        # Check if apache can access
        if [ "$perms" -lt 701 ]; then
            echo "  ⚠️  May be inaccessible to apache (needs at least 701)"
        fi
    else
        echo "$dir -> MISSING"
    fi
done

# 4. Check SELinux contexts
echo ""
echo "4. SELinux Context Check:"
echo "Current enforcement: $(getenforce)"
echo "Home directory contexts:"
ls -lZ /home/jamie/www/zeroglitch.com/trailstatus/ | head -5

echo ""
echo "SELinux booleans for home directories:"
getsebool httpd_enable_homedirs
getsebool httpd_read_user_content

# 5. Check recent Apache errors for this site
echo ""
echo "5. Recent Apache Errors:"
echo "Main error log:"
tail -n 10 /var/log/httpd/error_log | grep -E "(Forbidden|403|zeroglitch|trailstatus)"

echo ""
echo "Site-specific error log:"
if [ -f /var/log/httpd/zeroglitch-error.log ]; then
    tail -n 10 /var/log/httpd/zeroglitch-error.log
else
    echo "No site-specific error log found"
fi

if [ -f /var/log/httpd/zeroglitch-ssl-error.log ]; then
    echo "SSL error log:"
    tail -n 10 /var/log/httpd/zeroglitch-ssl-error.log
else
    echo "No SSL error log found"
fi

# 6. Test if index.php exists and is readable
echo ""
echo "6. Index File Check:"
INDEX_FILE="/home/jamie/www/zeroglitch.com/trailstatus/index.php"
if [ -f "$INDEX_FILE" ]; then
    echo "index.php exists: ✓"
    echo "Permissions: $(stat -c '%a %U:%G' "$INDEX_FILE")"
    echo "Size: $(stat -c '%s bytes' "$INDEX_FILE")"
    echo "First few lines:"
    head -n 3 "$INDEX_FILE"
else
    echo "❌ index.php not found at $INDEX_FILE"
    echo "Contents of trailstatus directory:"
    ls -la /home/jamie/www/zeroglitch.com/trailstatus/
fi

# 7. Apache virtual host test
echo ""
echo "7. Apache Virtual Host Test:"
echo "Testing Apache configuration:"
httpd -S 2>&1 | grep -A5 -B5 zeroglitch

echo ""
echo "=== QUICK FIXES TO TRY ==="
echo ""
echo "1. Fix home directory permissions:"
echo "   chmod 701 /home/jamie"
echo "   chmod 755 /home/jamie/www"
echo "   chmod 755 /home/jamie/www/zeroglitch.com"
echo ""
echo "2. Enable SELinux home directory access:"
echo "   setsebool -P httpd_enable_homedirs 1"
echo "   setsebool -P httpd_read_user_content 1"
echo ""
echo "3. Include domains.d in main config (if missing):"
echo "   echo 'Include /etc/httpd/domains.d/*.conf' >> /etc/httpd/conf/httpd.conf"
echo ""
echo "4. Test after each fix:"
echo "   systemctl restart httpd"
echo "   curl -I https://zeroglitch.com/trailstatus/"
