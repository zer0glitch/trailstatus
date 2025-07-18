#!/bin/bash
# Firewalld Configuration Script for Web Server and DNS
# Configures ports 80 (HTTP), 443 (HTTPS), and 53 (DNS)

echo "=== Firewalld Configuration Script ==="
echo "Configuring ports 80, 443, and 53..."
echo ""

# Check if firewalld is installed and running
if ! systemctl is-active --quiet firewalld; then
    echo "Starting firewalld service..."
    systemctl start firewalld
    systemctl enable firewalld
fi

# Check firewalld status
echo "Firewalld status:"
firewall-cmd --state
echo ""

# Show current zone
echo "Current default zone:"
firewall-cmd --get-default-zone
echo ""

# Show current active zones
echo "Active zones:"
firewall-cmd --get-active-zones
echo ""

# Configure HTTP (port 80)
echo "Configuring HTTP (port 80)..."
firewall-cmd --permanent --add-service=http
firewall-cmd --permanent --add-port=80/tcp
echo "✓ HTTP (port 80) configured"

# Configure HTTPS (port 443)
echo "Configuring HTTPS (port 443)..."
firewall-cmd --permanent --add-service=https
firewall-cmd --permanent --add-port=443/tcp
echo "✓ HTTPS (port 443) configured"

# Configure DNS (port 53)
echo "Configuring DNS (port 53)..."
firewall-cmd --permanent --add-service=dns
firewall-cmd --permanent --add-port=53/tcp
firewall-cmd --permanent --add-port=53/udp
echo "✓ DNS (port 53 TCP/UDP) configured"

# Optional: Configure SSH (port 22) if not already configured
echo "Checking SSH configuration..."
if firewall-cmd --list-services | grep -q ssh; then
    echo "✓ SSH already configured"
else
    echo "Configuring SSH (port 22)..."
    firewall-cmd --permanent --add-service=ssh
    echo "✓ SSH (port 22) configured"
fi

# Reload firewall to apply changes
echo ""
echo "Reloading firewall configuration..."
firewall-cmd --reload
echo "✓ Firewall configuration reloaded"

# Show final configuration
echo ""
echo "=== FINAL CONFIGURATION ==="
echo "Permanent services:"
firewall-cmd --permanent --list-services

echo ""
echo "Permanent ports:"
firewall-cmd --permanent --list-ports

echo ""
echo "Current active services:"
firewall-cmd --list-services

echo ""
echo "Current active ports:"
firewall-cmd --list-ports

echo ""
echo "=== CONFIGURATION COMPLETE ==="
echo "✓ HTTP (port 80) - Web traffic"
echo "✓ HTTPS (port 443) - Secure web traffic"
echo "✓ DNS (port 53) - Domain name resolution"
echo "✓ SSH (port 22) - Remote access"
echo ""
echo "Your web server should now be accessible on ports 80 and 443"
echo "DNS services are configured on port 53"
echo ""
echo "To verify configuration:"
echo "  firewall-cmd --list-all"
echo ""
echo "To test web connectivity:"
echo "  curl -I http://your-domain.com"
echo "  curl -I https://your-domain.com"
