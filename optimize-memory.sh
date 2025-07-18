#!/bin/bash
# Memory Optimization Script for Low-Memory VPS
# Addresses OOM killer issues on 1GB RAM servers

echo "=== Memory Optimization Script ==="
echo "Configuring system for low-memory environment..."
echo ""

# Check current memory usage
echo "Current memory usage:"
free -h
echo ""

# Check swap status
echo "Current swap status:"
swapon --show
echo ""

# 1. Create swap file if none exists (helps prevent OOM)
if ! swapon --show | grep -q swap; then
    echo "Creating 2GB swap file..."
    
    # Create swap file
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    
    # Make swap permanent
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
    
    echo "✓ 2GB swap file created and activated"
else
    echo "✓ Swap already configured"
fi

# 2. Optimize swap usage (reduce swappiness)
echo "Optimizing swap settings..."
echo 'vm.swappiness=10' >> /etc/sysctl.conf
echo 'vm.vfs_cache_pressure=50' >> /etc/sysctl.conf
sysctl vm.swappiness=10
sysctl vm.vfs_cache_pressure=50
echo "✓ Swap optimization configured"

# 3. Configure DNF for low memory usage
echo "Configuring DNF for low memory usage..."
cat > /etc/dnf/dnf.conf.d/low-memory.conf << 'EOF'
[main]
# Reduce memory usage for DNF
max_parallel_downloads=1
deltarpm=0
install_weak_deps=0
clean_requirements_on_remove=1
keepcache=0
EOF
echo "✓ DNF optimized for low memory"

# 4. Clean package cache to free space
echo "Cleaning package cache..."
dnf clean all
echo "✓ Package cache cleaned"

# 5. Configure systemd for low memory
echo "Configuring systemd for low memory..."
mkdir -p /etc/systemd/system.conf.d
cat > /etc/systemd/system.conf.d/low-memory.conf << 'EOF'
[Manager]
DefaultMemoryAccounting=yes
DefaultLimitNOFILE=1024
EOF
systemctl daemon-reload
echo "✓ Systemd configured for low memory"

# 6. Disable unnecessary services to save memory
echo "Checking for memory-heavy services to disable..."

# List of services that can be safely disabled on a web server
SERVICES_TO_CHECK=(
    "cups"
    "bluetooth"
    "avahi-daemon"
    "ModemManager"
    "NetworkManager-wait-online"
)

for service in "${SERVICES_TO_CHECK[@]}"; do
    if systemctl is-enabled $service >/dev/null 2>&1; then
        echo "Disabling $service..."
        systemctl disable $service
        systemctl stop $service
    fi
done

# 7. Configure kernel OOM behavior
echo "Configuring OOM killer behavior..."
echo 'vm.panic_on_oom=0' >> /etc/sysctl.conf
echo 'vm.oom_kill_allocating_task=1' >> /etc/sysctl.conf
sysctl vm.panic_on_oom=0
sysctl vm.oom_kill_allocating_task=1
echo "✓ OOM killer configured"

# 8. Create memory monitoring script
echo "Creating memory monitoring script..."
cat > /usr/local/bin/check-memory.sh << 'EOF'
#!/bin/bash
# Memory monitoring script

MEMORY_THRESHOLD=90
SWAP_THRESHOLD=80

# Get memory usage percentage
MEM_USAGE=$(free | grep Mem | awk '{printf("%.0f", $3/$2 * 100.0)}')
SWAP_USAGE=$(free | grep Swap | awk '{if($2>0) printf("%.0f", $3/$2 * 100.0); else print "0"}')

echo "$(date): Memory: ${MEM_USAGE}%, Swap: ${SWAP_USAGE}%" >> /var/log/memory-usage.log

if [ "$MEM_USAGE" -gt "$MEMORY_THRESHOLD" ]; then
    echo "$(date): WARNING - High memory usage: ${MEM_USAGE}%" >> /var/log/memory-alerts.log
    # Clean caches
    echo 1 > /proc/sys/vm/drop_caches
fi

if [ "$SWAP_USAGE" -gt "$SWAP_THRESHOLD" ]; then
    echo "$(date): WARNING - High swap usage: ${SWAP_USAGE}%" >> /var/log/memory-alerts.log
fi
EOF

chmod +x /usr/local/bin/check-memory.sh
echo "✓ Memory monitoring script created"

# 9. Set up cron job for memory monitoring
echo "Setting up memory monitoring cron job..."
(crontab -l 2>/dev/null; echo "*/5 * * * * /usr/local/bin/check-memory.sh") | crontab -
echo "✓ Memory monitoring scheduled every 5 minutes"

# 10. Configure Apache/httpd for low memory
if systemctl is-active httpd >/dev/null 2>&1; then
    echo "Optimizing Apache for low memory..."
    
    # Backup existing configuration
    cp /etc/httpd/conf/httpd.conf /etc/httpd/conf/httpd.conf.backup-$(date +%Y%m%d)
    
    # Create safe low-memory Apache configuration
    cat > /etc/httpd/conf.d/low-memory.conf << 'EOF'
# Low memory Apache configuration for 1GB RAM systems
# Safe settings that won't break Apache

# Prefork MPM settings (safer for low memory)
<IfModule mpm_prefork_module>
    StartServers          2
    MinSpareServers       2
    MaxSpareServers       4
    MaxRequestWorkers     50
    MaxConnectionsPerChild 1000
</IfModule>

# Event MPM settings (if using event MPM)
<IfModule mpm_event_module>
    StartServers             2
    MinSpareThreads          25
    MaxSpareThreads          75
    ThreadsPerChild          25
    MaxRequestWorkers        50
    MaxConnectionsPerChild   1000
</IfModule>

# Worker MPM settings (if using worker MPM)
<IfModule mpm_worker_module>
    StartServers             2
    MinSpareThreads          25
    MaxSpareThreads          75
    ThreadsPerChild          25
    MaxRequestWorkers        50
    MaxConnectionsPerChild   1000
</IfModule>

# Connection settings
KeepAlive On
KeepAliveTimeout 5
MaxKeepAliveRequests 100

# Timeout settings
Timeout 300
EOF
    
    # Test configuration before applying
    echo "Testing Apache configuration..."
    if httpd -t; then
        echo "✓ Apache configuration test passed"
        systemctl reload httpd
        if systemctl is-active httpd >/dev/null 2>&1; then
            echo "✓ Apache optimized for low memory and reloaded successfully"
        else
            echo "⚠️  Apache reload failed, attempting restart..."
            systemctl restart httpd
            if systemctl is-active httpd >/dev/null 2>&1; then
                echo "✓ Apache restarted successfully"
            else
                echo "❌ Apache failed to start, reverting configuration..."
                rm -f /etc/httpd/conf.d/low-memory.conf
                systemctl restart httpd
                echo "Configuration reverted. Check Apache logs for issues."
            fi
        fi
    else
        echo "❌ Apache configuration test failed, not applying changes"
        rm -f /etc/httpd/conf.d/low-memory.conf
    fi
else
    echo "Apache is not currently running, skipping Apache optimization"
fi

# Show final status
echo ""
echo "=== MEMORY OPTIMIZATION COMPLETE ==="
echo "Current memory and swap status:"
free -h
echo ""
echo "Swap devices:"
swapon --show
echo ""
echo "=== RECOMMENDATIONS ==="
echo "1. Monitor memory usage: cat /var/log/memory-usage.log"
echo "2. Check memory alerts: cat /var/log/memory-alerts.log"
echo "3. Run large operations during low-traffic times"
echo "4. Consider upgrading to 2GB RAM if budget allows"
echo "5. Use 'dnf --enablerepo=* --best --allowerasing' for updates"
echo ""
echo "=== PREVENTIVE MEASURES ==="
echo "• Swap file created to prevent OOM kills"
echo "• DNF configured for low memory usage"
echo "• Memory monitoring enabled"
echo "• System optimized for 1GB RAM environment"
echo ""
echo "To check current memory usage anytime:"
echo "  free -h"
echo "  ps aux --sort=-%mem | head -10"
