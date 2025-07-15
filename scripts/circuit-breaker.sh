#!/bin/bash

# Circuit Breaker for External Dependencies
# Implements circuit breaker pattern to prevent cascading failures

set -e

# Source error handler if available
SCRIPT_DIR="$(dirname "$0")"
source "$SCRIPT_DIR/error-handler.sh" 2>/dev/null || {
    # Fallback logging functions
    log_info() { echo "[INFO] $1"; }
    log_warn() { echo "[WARN] $1"; }
    log_error() { echo "[ERROR] $1"; }
    log_success() { echo "[SUCCESS] $1"; }
}

# Configuration
CIRCUIT_BREAKER_LOG="/tmp/circuit-breaker.log"
CACHE_DIR="/tmp/circuit-breaker-cache"
TIMEOUT_SECONDS=15
MAX_FAILURES=3
RECOVERY_TIMEOUT=300  # 5 minutes

# Initialize
mkdir -p "$CACHE_DIR"
echo "=== Circuit Breaker Started: $(date) ===" > "$CIRCUIT_BREAKER_LOG"

# Circuit breaker state management
get_circuit_state() {
    local service="$1"
    local state_file="$CACHE_DIR/${service}_state"
    
    if [ -f "$state_file" ]; then
        cat "$state_file"
    else
        echo "CLOSED"
    fi
}

set_circuit_state() {
    local service="$1"
    local state="$2"
    local state_file="$CACHE_DIR/${service}_state"

    echo "$state" > "$state_file"
    echo "$(date)" > "$CACHE_DIR/${service}_last_change"
    log_info "Circuit breaker for $service: $state"
    echo "🔄 Circuit breaker for $service: $state" >> "$CIRCUIT_BREAKER_LOG"
}

get_failure_count() {
    local service="$1"
    local count_file="$CACHE_DIR/${service}_failures"
    
    if [ -f "$count_file" ]; then
        cat "$count_file"
    else
        echo "0"
    fi
}

increment_failure_count() {
    local service="$1"
    local count_file="$CACHE_DIR/${service}_failures"
    local current_count=$(get_failure_count "$service")
    local new_count=$((current_count + 1))
    
    echo "$new_count" > "$count_file"
    echo "⚠️  Failure count for $service: $new_count" | tee -a "$CIRCUIT_BREAKER_LOG"
    
    if [ "$new_count" -ge "$MAX_FAILURES" ]; then
        set_circuit_state "$service" "OPEN"
    fi
}

reset_failure_count() {
    local service="$1"
    local count_file="$CACHE_DIR/${service}_failures"
    
    echo "0" > "$count_file"
    set_circuit_state "$service" "CLOSED"
    echo "✅ Circuit breaker for $service reset" | tee -a "$CIRCUIT_BREAKER_LOG"
}

should_attempt_recovery() {
    local service="$1"
    local last_change_file="$CACHE_DIR/${service}_last_change"
    
    if [ ! -f "$last_change_file" ]; then
        return 0  # No previous change, allow attempt
    fi
    
    local last_change=$(cat "$last_change_file")
    local last_change_epoch=$(date -d "$last_change" +%s 2>/dev/null || echo "0")
    local current_epoch=$(date +%s)
    local time_diff=$((current_epoch - last_change_epoch))
    
    if [ "$time_diff" -gt "$RECOVERY_TIMEOUT" ]; then
        return 0  # Recovery timeout passed, allow attempt
    else
        return 1  # Still in recovery timeout
    fi
}

# Service health check functions
check_wordpress_svn() {
    local service="wordpress_svn"
    local state=$(get_circuit_state "$service")
    
    echo "🔍 Checking WordPress SVN (circuit: $state)..." | tee -a "$CIRCUIT_BREAKER_LOG"
    
    if [ "$state" = "OPEN" ]; then
        if should_attempt_recovery "$service"; then
            echo "🔄 Attempting recovery for $service..." | tee -a "$CIRCUIT_BREAKER_LOG"
            set_circuit_state "$service" "HALF_OPEN"
        else
            echo "❌ Circuit breaker OPEN for $service" | tee -a "$CIRCUIT_BREAKER_LOG"
            return 1
        fi
    fi
    
    local endpoints=(
        "https://develop.svn.wordpress.org/trunk/"
        "https://core.svn.wordpress.org/trunk/"
    )
    
    for endpoint in "${endpoints[@]}"; do
        if timeout "$TIMEOUT_SECONDS" svn info "$endpoint" &>/dev/null; then
            echo "✅ WordPress SVN accessible: $endpoint" | tee -a "$CIRCUIT_BREAKER_LOG"
            reset_failure_count "$service"
            return 0
        fi
    done
    
    echo "❌ WordPress SVN not accessible" | tee -a "$CIRCUIT_BREAKER_LOG"
    increment_failure_count "$service"
    return 1
}

check_wordpress_api() {
    local service="wordpress_api"
    local state=$(get_circuit_state "$service")
    
    echo "🔍 Checking WordPress API (circuit: $state)..." | tee -a "$CIRCUIT_BREAKER_LOG"
    
    if [ "$state" = "OPEN" ]; then
        if should_attempt_recovery "$service"; then
            set_circuit_state "$service" "HALF_OPEN"
        else
            echo "❌ Circuit breaker OPEN for $service" | tee -a "$CIRCUIT_BREAKER_LOG"
            return 1
        fi
    fi
    
    if timeout "$TIMEOUT_SECONDS" curl -s --head "https://api.wordpress.org/core/version-check/1.7/" &>/dev/null; then
        echo "✅ WordPress API accessible" | tee -a "$CIRCUIT_BREAKER_LOG"
        reset_failure_count "$service"
        return 0
    fi
    
    echo "❌ WordPress API not accessible" | tee -a "$CIRCUIT_BREAKER_LOG"
    increment_failure_count "$service"
    return 1
}

check_claude_api() {
    local service="claude_api"
    local state=$(get_circuit_state "$service")
    
    echo "🔍 Checking Claude API (circuit: $state)..." | tee -a "$CIRCUIT_BREAKER_LOG"
    
    if [ "$state" = "OPEN" ]; then
        if should_attempt_recovery "$service"; then
            set_circuit_state "$service" "HALF_OPEN"
        else
            echo "❌ Circuit breaker OPEN for $service" | tee -a "$CIRCUIT_BREAKER_LOG"
            return 1
        fi
    fi
    
    # Simple connectivity check to Anthropic's API
    if timeout "$TIMEOUT_SECONDS" curl -s --head "https://api.anthropic.com" &>/dev/null; then
        echo "✅ Claude API accessible" | tee -a "$CIRCUIT_BREAKER_LOG"
        reset_failure_count "$service"
        return 0
    fi
    
    echo "❌ Claude API not accessible" | tee -a "$CIRCUIT_BREAKER_LOG"
    increment_failure_count "$service"
    return 1
}

check_mysql_service() {
    local service="mysql_service"
    local state=$(get_circuit_state "$service")
    
    echo "🔍 Checking MySQL service (circuit: $state)..." | tee -a "$CIRCUIT_BREAKER_LOG"
    
    if [ "$state" = "OPEN" ]; then
        if should_attempt_recovery "$service"; then
            set_circuit_state "$service" "HALF_OPEN"
        else
            echo "❌ Circuit breaker OPEN for $service" | tee -a "$CIRCUIT_BREAKER_LOG"
            return 1
        fi
    fi
    
    if timeout "$TIMEOUT_SECONDS" mysqladmin ping -h 127.0.0.1 -P 3306 -u root -proot --silent 2>/dev/null; then
        echo "✅ MySQL service accessible" | tee -a "$CIRCUIT_BREAKER_LOG"
        reset_failure_count "$service"
        return 0
    fi
    
    echo "❌ MySQL service not accessible" | tee -a "$CIRCUIT_BREAKER_LOG"
    increment_failure_count "$service"
    return 1
}

# Main function
main() {
    local service="${1:-all}"
    local exit_code=0
    
    echo "🔧 Circuit breaker check for: $service" | tee -a "$CIRCUIT_BREAKER_LOG"
    
    case "$service" in
        "wordpress_svn")
            check_wordpress_svn || exit_code=1
            ;;
        "wordpress_api")
            check_wordpress_api || exit_code=1
            ;;
        "claude_api")
            check_claude_api || exit_code=1
            ;;
        "mysql_service")
            check_mysql_service || exit_code=1
            ;;
        "all")
            check_mysql_service || exit_code=1
            check_wordpress_svn || exit_code=1
            check_wordpress_api || exit_code=1
            check_claude_api || exit_code=1
            ;;
        *)
            echo "❌ Unknown service: $service" | tee -a "$CIRCUIT_BREAKER_LOG"
            echo "Available services: wordpress_svn, wordpress_api, claude_api, mysql_service, all"
            exit 1
            ;;
    esac
    
    echo "=== Circuit Breaker Completed: $(date) ===" >> "$CIRCUIT_BREAKER_LOG"
    exit $exit_code
}

# Execute main function
main "$@"
