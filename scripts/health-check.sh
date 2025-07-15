#!/bin/bash

# Health Check Script for External Dependencies
# Implements circuit breaker pattern for external services

set -e

# Configuration
TIMEOUT_SECONDS=10
MAX_RETRIES=2
HEALTH_CHECK_LOG="/tmp/health-check.log"

# Initialize log
echo "=== Health Check Started: $(date) ===" > "$HEALTH_CHECK_LOG"

# Health check functions
check_mysql_service() {
    echo "Checking MySQL service availability..." >> "$HEALTH_CHECK_LOG"

    for i in $(seq 1 $MAX_RETRIES); do
        if timeout $TIMEOUT_SECONDS mysqladmin ping -h 127.0.0.1 -P 3306 -u root -proot --silent 2>/dev/null; then
            echo "✅ MySQL service is available" >> "$HEALTH_CHECK_LOG"
            return 0
        fi
        echo "⚠️  MySQL check attempt $i/$MAX_RETRIES failed" >> "$HEALTH_CHECK_LOG"
        sleep 2
    done

    echo "❌ MySQL service is unavailable" >> "$HEALTH_CHECK_LOG"
    return 1
}

check_wordpress_svn() {
    echo "Checking WordPress SVN availability..." >> "$HEALTH_CHECK_LOG"

    local svn_endpoints=(
        "https://develop.svn.wordpress.org/trunk/"
        "https://core.svn.wordpress.org/trunk/"
    )

    for endpoint in "${svn_endpoints[@]}"; do
        if timeout $TIMEOUT_SECONDS svn info "$endpoint" &>/dev/null; then
            echo "✅ WordPress SVN is available ($endpoint)" >> "$HEALTH_CHECK_LOG"
            return 0
        fi
    done

    echo "❌ WordPress SVN is unavailable" >> "$HEALTH_CHECK_LOG"
    return 1
}

check_wordpress_api() {
    echo "Checking WordPress.org API availability..." >> "$HEALTH_CHECK_LOG"

    if timeout $TIMEOUT_SECONDS curl -s --head "https://api.wordpress.org/core/version-check/1.7/" &>/dev/null; then
        echo "✅ WordPress.org API is available" >> "$HEALTH_CHECK_LOG"
        return 0
    fi

    echo "❌ WordPress.org API is unavailable" >> "$HEALTH_CHECK_LOG"
    return 1
}

check_internet_connectivity() {
    echo "Checking internet connectivity..." >> "$HEALTH_CHECK_LOG"

    if timeout $TIMEOUT_SECONDS ping -c 3 8.8.8.8 &>/dev/null; then
        echo "✅ Internet connectivity is available" >> "$HEALTH_CHECK_LOG"
        return 0
    fi

    echo "❌ Internet connectivity is unavailable" >> "$HEALTH_CHECK_LOG"
    return 1
}

# Determine test mode based on service availability
determine_test_mode() {
    local mysql_available=false
    local wordpress_available=false
    local internet_available=false
    
    # Check services
    check_mysql_service && mysql_available=true
    check_internet_connectivity && internet_available=true
    
    if [ "$internet_available" = true ]; then
        (check_wordpress_svn || check_wordpress_api) && wordpress_available=true
    fi
    
    # Determine mode
    if [ "$mysql_available" = true ] && [ "$wordpress_available" = true ]; then
        echo "full"
    elif [ "$mysql_available" = true ]; then
        echo "basic"
    else
        echo "minimal"
    fi
}

# Main execution
main() {
    local requested_mode="${1:-auto}"

    echo "Health check requested mode: $requested_mode" >> "$HEALTH_CHECK_LOG"

    if [ "$requested_mode" = "auto" ]; then
        local detected_mode
        detected_mode=$(determine_test_mode)
        echo "Auto-detected test mode: $detected_mode" >> "$HEALTH_CHECK_LOG"
        echo "$detected_mode"
    else
        echo "Using requested test mode: $requested_mode" >> "$HEALTH_CHECK_LOG"
        echo "$requested_mode"
    fi

    echo "=== Health Check Completed: $(date) ===" >> "$HEALTH_CHECK_LOG"
}

# Execute main function
main "$@"
