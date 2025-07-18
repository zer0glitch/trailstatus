#!/bin/bash
# Fix SSL Virtual Host for Trail Status
# Adds trailstatus directory configuration to HTTPS virtual host

echo "=== SSL VIRTUAL HOST FIX ==="
echo "Adding trailstatus configuration to HTTPS virtual host..."
echo ""

SSL_CONF="/etc/httpd/domains.d/zeroglitch.com-le-ssl.conf"
HTTP_CONF="/etc/httpd/domains.d/zeroglitch.com.conf"

# 1. Backup the SSL config
echo "1. Backing up SSL configuration..."
cp "$SSL_CONF" "$SSL_CONF.backup-$(date +%Y%m%d-%H%M)"
echo "✓ Backup created"

# 2. Show current SSL config
echo ""
echo "2. Current SSL Virtual Host Configuration:"
cat "$SSL_CONF"
echo ""

# 3. Check if trailstatus config already exists in SSL
if grep -q "trailstatus" "$SSL_CONF"; then
    echo "3. Trailstatus configuration already exists in SSL config"
else
    echo "3. Adding trailstatus configuration to SSL virtual host..."
    
    # Extract the trailstatus directory config from HTTP config
    echo "Extracting trailstatus config from HTTP virtual host..."
    
    # Create temporary file with the directory config
    cat > /tmp/trailstatus-ssl-config << 'EOF'

    # Access settings for the trailstatus directory
    <Directory "/home/jamie/www/zeroglitch.com/trailstatus">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security: restrict sensitive files
        <FilesMatch "^(config|setup|add_user)\.php$">
            Require all denied
        </FilesMatch>

        <FilesMatch "\.json$">
            Require all denied
        </FilesMatch>
    </Directory>
EOF

    # Insert before the closing </VirtualHost> tag
    sed -i '/<\/VirtualHost>/i\\n    # Access settings for the trailstatus directory\n    <Directory "/home/jamie/www/zeroglitch.com/trailstatus">\n        Options -Indexes +FollowSymLinks\n        AllowOverride All\n        Require all granted\n\n        # Security: restrict sensitive files\n        <FilesMatch "^(config|setup|add_user)\\.php$">\n            Require all denied\n        </FilesMatch>\n\n        <FilesMatch "\\.json$">\n            Require all denied\n        </FilesMatch>\n    </Directory>' "$SSL_CONF"
    
    echo "✓ Trailstatus configuration added to SSL virtual host"
fi

# 4. Test Apache configuration
echo ""
echo "4. Testing Apache configuration..."
if httpd -t; then
    echo "✓ Apache configuration is valid"
else
    echo "❌ Apache configuration has errors!"
    echo "Restoring backup..."
    cp "$SSL_CONF.backup-$(date +%Y%m%d-%H%M)" "$SSL_CONF"
    exit 1
fi

# 5. Show the updated SSL config
echo ""
echo "5. Updated SSL Virtual Host Configuration:"
cat "$SSL_CONF"

# 6. Restart Apache
echo ""
echo "6. Restarting Apache..."
systemctl restart httpd

if systemctl is-active httpd >/dev/null 2>&1; then
    echo "✓ Apache restarted successfully"
else
    echo "❌ Apache failed to restart!"
    systemctl status httpd --no-pager
    exit 1
fi

# 7. Test both HTTP and HTTPS
echo ""
echo "7. Testing website access..."
echo "HTTP test:"
curl -I http://zeroglitch.com/trailstatus/ 2>/dev/null | head -3

echo ""
echo "HTTPS test:"
curl -I https://zeroglitch.com/trailstatus/ 2>/dev/null | head -3

echo ""
echo "=== FIX COMPLETE ==="
if curl -s -o /dev/null -w "%{http_code}" https://zeroglitch.com/trailstatus/ | grep -q "200\|302"; then
    echo "✅ HTTPS website is now accessible!"
    echo "Visit: https://zeroglitch.com/trailstatus/"
else
    echo "⚠️  Still having issues. Check the SSL configuration:"
    echo "   cat $SSL_CONF"
    echo "   tail -f /var/log/httpd/error_log"
fi
