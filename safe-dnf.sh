#!/bin/bash
# Safe DNF Usage Script for 1GB RAM Systems
# Prevents OOM kills during package operations

echo "=== SAFE DNF PACKAGE MANAGER ==="
echo "Memory-safe package installation for 1GB RAM systems"
echo ""

# Function to check memory before operations
check_memory() {
    local mem_available=$(free -m | awk 'NR==2{printf "%.0f", $7}')
    local mem_total=$(free -m | awk 'NR==2{printf "%.0f", $2}')
    local mem_percent=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
    
    echo "Memory check:"
    echo "  Available: ${mem_available}MB"
    echo "  Usage: ${mem_percent}%"
    
    if [ "$mem_available" -lt 200 ]; then
        echo "⚠️  WARNING: Low available memory (${mem_available}MB)"
        echo "   Consider freeing memory before package operations"
        return 1
    fi
    
    return 0
}

# Function for safe DNF operations
safe_dnf() {
    local operation="$1"
    shift
    local packages="$@"
    
    echo "Preparing for safe DNF operation: $operation $packages"
    
    # Pre-operation memory check
    if ! check_memory; then
        echo "Clearing caches to free memory..."
        echo 3 > /proc/sys/vm/drop_caches
        sleep 2
    fi
    
    # Ensure swap is active
    if ! swapon --show | grep -q swap; then
        echo "❌ ERROR: No swap detected! Run emergency-memory-fix.sh first"
        exit 1
    fi
    
    echo "Starting memory-safe DNF operation..."
    echo "Command: dnf $operation --best --allowerasing $packages"
    echo ""
    
    # Monitor memory during operation
    (
        while true; do
            local mem_percent=$(free | awk 'NR==2{printf "%.0f", $3*100/$2}')
            local swap_percent=$(free | awk 'NR==3{if($2>0) printf "%.0f", $3*100/$2; else print "0"}')
            echo "$(date +'%H:%M:%S'): Memory: ${mem_percent}%, Swap: ${swap_percent}%"
            sleep 10
        done
    ) &
    local monitor_pid=$!
    
    # Run the actual DNF command
    dnf "$operation" --best --allowerasing $packages
    local dnf_exit_code=$?
    
    # Stop monitoring
    kill $monitor_pid 2>/dev/null
    
    # Post-operation cleanup
    echo ""
    echo "Cleaning up after DNF operation..."
    dnf clean all
    echo 1 > /proc/sys/vm/drop_caches
    
    echo "Final memory status:"
    free -h
    
    if [ $dnf_exit_code -eq 0 ]; then
        echo "✅ DNF operation completed successfully"
    else
        echo "❌ DNF operation failed (exit code: $dnf_exit_code)"
        echo "Check system logs: journalctl -n 50"
    fi
    
    return $dnf_exit_code
}

# Main script logic
if [ $# -eq 0 ]; then
    echo "Usage: $0 <dnf-operation> [packages...]"
    echo ""
    echo "Examples:"
    echo "  $0 install composer"
    echo "  $0 update"
    echo "  $0 install php-curl php-json"
    echo "  $0 search composer"
    echo ""
    echo "Safe operations for 1GB RAM:"
    echo "• Install ONE package at a time"
    echo "• Avoid large package groups"
    echo "• Run during low server load"
    echo "• Monitor memory with: watch free -h"
    echo ""
    exit 1
fi

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "❌ This script must be run as root (sudo)"
    exit 1
fi

# Show initial memory state
echo "Initial memory state:"
free -h
echo ""

# Run the safe DNF operation
safe_dnf "$@"
