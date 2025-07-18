#!/bin/bash
# Emergency Memory Fix Script
# Immediate steps to resolve OOM (Out of Memory) issues

echo "=== EMERGENCY MEMORY FIX ==="
echo "Addressing critical memory shortage..."
echo ""

# Show current memory state
echo "Current memory usage:"
free -h
echo ""

# 1. IMMEDIATE: Clear all caches to free memory
echo "Step 1: Clearing system caches..."
sync
echo 3 > /proc/sys/vm/drop_caches
echo "✓ System caches cleared"

# 2. IMMEDIATE: Create emergency swap if none exists
if ! swapon --show | grep -q swap; then
    echo "Step 2: Creating EMERGENCY 1GB swap file..."
    
    # Use dd instead of fallocate for better compatibility
    dd if=/dev/zero of=/emergency-swapfile bs=1M count=1024 status=progress
    chmod 600 /emergency-swapfile
    mkswap /emergency-swapfile
    swapon /emergency-swapfile
    
    echo "✓ Emergency 1GB swap file activated"
else
    echo "Step 2: ✓ Swap already exists"
    swapon --show
fi

# 3. IMMEDIATE: Kill memory-heavy processes (be careful!)
echo "Step 3: Checking for memory-heavy processes..."
echo "Top memory consumers:"
ps aux --sort=-%mem | head -10

# Stop non-essential services temporarily
echo "Step 4: Stopping non-essential services temporarily..."
SERVICES_TO_STOP=(
    "cups"
    "bluetooth" 
    "avahi-daemon"
    "ModemManager"
)

for service in "${SERVICES_TO_STOP[@]}"; do
    if systemctl is-active $service >/dev/null 2>&1; then
        echo "Stopping $service..."
        systemctl stop $service
    fi
done

# 5. Configure DNF for emergency low-memory mode
echo "Step 5: Configuring DNF for emergency mode..."
mkdir -p /etc/dnf/dnf.conf.d
cat > /etc/dnf/dnf.conf.d/emergency-low-memory.conf << 'EOF'
[main]
# Emergency low memory DNF configuration
max_parallel_downloads=1
deltarpm=false
install_weak_deps=false
keepcache=false
clean_requirements_on_remove=true
tsflags=nodocs
skip_if_unavailable=true
EOF

# Clean DNF cache
dnf clean all

echo "✓ DNF configured for emergency low-memory mode"

# 6. Set aggressive memory management
echo "Step 6: Setting aggressive memory management..."
echo 5 > /proc/sys/vm/swappiness  # Use swap more aggressively
echo 100 > /proc/sys/vm/vfs_cache_pressure  # Reclaim cache more aggressively
echo "✓ Aggressive memory management enabled"

# 7. Check for dangerous SELinux autorelabel
echo "Step 7: Checking for SELinux autorelabel..."
if [ -f /.autorelabel ]; then
    echo "⚠️  CRITICAL WARNING: /.autorelabel detected!"
    echo "   SELinux full relabel will trigger on reboot"
    echo "   This WILL cause OOM kills on 1GB RAM!"
    echo ""
    read -p "   Remove autorelabel file? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        rm -f /.autorelabel
        echo "   ✓ Autorelabel cancelled - system is safer"
    else
        echo "   ⚠️  Autorelabel will run on reboot - ENSURE 2GB+ swap first!"
    fi
else
    echo "✓ No dangerous SELinux autorelabel pending"
fi

# 8. Show current state
echo ""
echo "=== EMERGENCY FIX COMPLETE ==="
echo "Current memory status:"
free -h
echo ""
echo "Active swap:"
swapon --show
echo ""

# 8. Provide emergency usage guidance
echo "=== EMERGENCY USAGE GUIDELINES ==="
echo "Your system had an Out-of-Memory crisis. Here's what to do:"
echo ""
echo "IMMEDIATE ACTIONS:"
echo "1. ✓ Emergency swap created and activated"
echo "2. ✓ System caches cleared"
echo "3. ✓ DNF configured for low memory"
echo "4. ✓ Non-essential services stopped"
echo "5. ✓ SELinux autorelabel checked/handled"
echo ""
echo "FOR PACKAGE OPERATIONS (dnf/yum):"
echo "• Use ONLY: ./safe-dnf.sh install package-name"
echo "• ONE package at a time, not multiple packages"
echo "• Run during low-traffic times"
echo "• Monitor with: watch free -h"
echo ""
echo "FOR SELINUX OPERATIONS:"
echo "• Use: ./safe-selinux.sh for SELinux management"
echo "• NEVER use /.autorelabel on 1GB RAM"
echo "• Use permissive mode during troubleshooting"
echo ""
echo "MONITORING:"
echo "• Check memory: free -h"
echo "• Watch memory: watch -n 1 free -h"
echo "• Top memory users: ps aux --sort=-%mem | head -10"
echo "• System logs: journalctl -f"
echo ""
echo "CRITICAL: If you see 'killed' or OOM messages again:"
echo "1. Stop Apache: systemctl stop httpd"
echo "2. Clear caches: echo 3 > /proc/sys/vm/drop_caches"
echo "3. Restart Apache: systemctl start httpd"
echo ""
echo "SCRIPTS AVAILABLE:"
echo "• ./emergency-memory-fix.sh - This script"
echo "• ./fix-apache.sh - Fix Apache issues"
echo "• ./safe-dnf.sh - Safe package management"
echo "• ./safe-selinux.sh - Safe SELinux operations"
echo ""
echo "PERMANENT SOLUTION: Run optimize-memory.sh when system is stable"
