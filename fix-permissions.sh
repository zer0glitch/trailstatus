#!/bin/bash
# Fix permissions for LCFTF Trail Status website
# Run this script on your server: bash fix-permissions.sh

echo "🚵‍♂️ LCFTF Trail Status - Permission Fixer"
echo "=========================================="
echo ""

# Set the correct path for your server
TRAIL_DIR="/home/jamie/www/zeroglitch.com/trailstatus"

echo "Checking current permissions..."
ls -la "$TRAIL_DIR"
echo ""

echo "Setting correct permissions..."

# Make sure the directory is owned by the web server user
# Common web server users: apache, www-data, nginx, httpd
WEB_USER="apache"  # Change this if your web server uses a different user

# Set directory permissions
chmod 755 "$TRAIL_DIR"
echo "✓ Set directory permissions to 755"

# Create data directory if it doesn't exist and set permissions
mkdir -p "$TRAIL_DIR/data"
chmod 755 "$TRAIL_DIR/data"
echo "✓ Created and set data directory permissions"

# Set ownership to web server user (requires root)
if [ "$EUID" -eq 0 ]; then
    chown -R $WEB_USER:$WEB_USER "$TRAIL_DIR"
    echo "✓ Set ownership to $WEB_USER"
else
    echo "⚠ Warning: Not running as root, cannot change ownership"
    echo "  You may need to run: sudo chown -R $WEB_USER:$WEB_USER $TRAIL_DIR"
fi

# Set file permissions
find "$TRAIL_DIR" -type f -name "*.php" -exec chmod 644 {} \;
find "$TRAIL_DIR" -type f -name "*.css" -exec chmod 644 {} \;
find "$TRAIL_DIR" -type f -name "*.html" -exec chmod 644 {} \;
echo "✓ Set file permissions to 644"

echo ""
echo "Final permissions:"
ls -la "$TRAIL_DIR"
echo ""
echo "Testing write access..."
if [ -w "$TRAIL_DIR" ]; then
    echo "✓ Directory is writable"
    
    # Test creating a file
    TEST_FILE="$TRAIL_DIR/test_write.tmp"
    echo "test" > "$TEST_FILE" 2>/dev/null
    if [ $? -eq 0 ]; then
        echo "✓ Can create files"
        rm -f "$TEST_FILE"
    else
        echo "✗ Cannot create files"
    fi
else
    echo "✗ Directory is not writable"
fi

echo ""
echo "Permission fix complete!"
echo "You can now run the setup script again."
