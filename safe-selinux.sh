#!/bin/bash
# Safe SELinux Management for Low-Memory Systems
# Handles SELinux issues without triggering OOM

echo "=== SAFE SELINUX MANAGEMENT ==="
echo "Managing SELinux on 1GB RAM system safely..."
echo ""

# Check current SELinux status
echo "Current SELinux status:"
getenforce
sestatus
echo ""

# Check if autorelabel was triggered
if [ -f /.autorelabel ]; then
    echo "⚠️  WARNING: /.autorelabel file detected!"
    echo "Full system relabel will occur on next reboot."
    echo "This is DANGEROUS on 1GB RAM - will likely cause OOM!"
    echo ""
    
    read -p "Do you want to cancel the autorelabel? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        rm -f /.autorelabel
        echo "✓ Autorelabel cancelled"
    else
        echo "⚠️  Autorelabel will proceed on reboot - ensure swap is active!"
    fi
    echo ""
fi

# Function to check memory before SELinux operations
check_memory_for_selinux() {
    local mem_available=$(free -m | awk 'NR==2{printf "%.0f", $7}')
    local swap_available=$(free -m | awk 'NR==3{printf "%.0f", $4}')
    
    echo "Memory check for SELinux operations:"
    echo "  Available RAM: ${mem_available}MB"
    echo "  Available Swap: ${swap_available}MB"
    
    if [ "$mem_available" -lt 300 ]; then
        echo "❌ Insufficient memory for SELinux operations!"
        echo "   Need at least 300MB free RAM"
        return 1
    fi
    
    if [ "$swap_available" -lt 500 ]; then
        echo "⚠️  Low swap space for SELinux operations"
        echo "   Recommend at least 500MB free swap"
    fi
    
    return 0
}

# Function for safe SELinux operations
safe_selinux_operation() {
    local operation="$1"
    shift
    local targets="$@"
    
    echo "Preparing for safe SELinux operation: $operation"
    
    # Pre-operation cleanup
    echo "Clearing caches before SELinux operation..."
    echo 3 > /proc/sys/vm/drop_caches
    
    # Check memory
    if ! check_memory_for_selinux; then
        echo "❌ Cannot proceed with SELinux operation - insufficient memory"
        echo "   Try stopping services first or adding more swap"
        return 1
    fi
    
    echo "Proceeding with SELinux operation..."
    case "$operation" in
        "restorecon")
            if [ -n "$targets" ]; then
                restorecon -R -v $targets
            else
                echo "Error: No targets specified for restorecon"
                return 1
            fi
            ;;
        "relabel-web")
            echo "Relabeling web directories only..."
            restorecon -R -v /var/www/
            restorecon -R -v /etc/httpd/
            ;;
        "check-contexts")
            echo "Checking SELinux contexts for web files..."
            ls -lZ /var/www/html/ 2>/dev/null || echo "No /var/www/html/ directory"
            ls -lZ /etc/httpd/conf.d/ 2>/dev/null || echo "No /etc/httpd/conf.d/ directory"
            ;;
        *)
            echo "Unknown operation: $operation"
            return 1
            ;;
    esac
}

# Main menu
echo "=== SELINUX OPTIONS ==="
echo "1. Check current SELinux status"
echo "2. Temporarily disable SELinux (permissive mode)"
echo "3. Re-enable SELinux (enforcing mode)"
echo "4. Fix web directory contexts only"
echo "5. Check file contexts"
echo "6. Cancel autorelabel (if set)"
echo "7. Safe minimal relabel (web files only)"
echo "8. Exit"
echo ""

read -p "Choose an option (1-8): " -n 1 -r
echo

case $REPLY in
    1)
        echo "Current SELinux status:"
        getenforce
        sestatus
        echo ""
        echo "Recent SELinux denials:"
        ausearch -m AVC -ts recent 2>/dev/null | tail -5 || echo "No recent denials found"
        ;;
    2)
        echo "Setting SELinux to permissive mode..."
        setenforce 0
        echo "✓ SELinux set to permissive (temporary)"
        echo "To make permanent, edit /etc/selinux/config"
        ;;
    3)
        echo "Setting SELinux to enforcing mode..."
        setenforce 1
        echo "✓ SELinux set to enforcing"
        ;;
    4)
        echo "Fixing web directory SELinux contexts..."
        safe_selinux_operation "relabel-web"
        ;;
    5)
        safe_selinux_operation "check-contexts"
        ;;
    6)
        if [ -f /.autorelabel ]; then
            rm -f /.autorelabel
            echo "✓ Autorelabel file removed"
        else
            echo "No autorelabel file found"
        fi
        ;;
    7)
        echo "Performing safe minimal relabel..."
        if check_memory_for_selinux; then
            echo "Relabeling critical web files only..."
            safe_selinux_operation "restorecon" "/var/www" "/etc/httpd"
            echo "✓ Minimal relabel complete"
        else
            echo "❌ Insufficient memory for relabel operation"
        fi
        ;;
    8)
        echo "Exiting..."
        exit 0
        ;;
    *)
        echo "Invalid option"
        exit 1
        ;;
esac

echo ""
echo "=== RECOMMENDATIONS FOR 1GB RAM SYSTEMS ==="
echo "• Use permissive mode during setup, then switch to enforcing"
echo "• Never use full system relabel (/.autorelabel) on 1GB RAM"
echo "• Use targeted restorecon instead of full relabel"
echo "• Monitor memory during SELinux operations"
echo "• Keep swap active before any SELinux operations"
echo ""
echo "For Apache issues, try:"
echo "  setsebool -P httpd_can_network_connect 1"
echo "  restorecon -R /var/www/html/"
