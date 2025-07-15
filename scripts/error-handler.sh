#!/bin/bash

# Standardized Error Handling and Logging System
# Provides consistent error reporting across all workflow scripts

set -e

# Configuration
ERROR_LOG="/tmp/workflow-errors.log"
DEBUG_LOG="/tmp/workflow-debug.log"
PERFORMANCE_LOG="/tmp/workflow-performance.log"

# Initialize logs
mkdir -p "$(dirname "$ERROR_LOG")"
echo "=== Error Handler Initialized: $(date) ===" > "$ERROR_LOG"
echo "=== Debug Log Started: $(date) ===" > "$DEBUG_LOG"
echo "=== Performance Log Started: $(date) ===" > "$PERFORMANCE_LOG"

# Error levels
ERROR_LEVEL_INFO=0
ERROR_LEVEL_WARN=1
ERROR_LEVEL_ERROR=2
ERROR_LEVEL_FATAL=3

# Colors for output
RED='\033[0;31m'
YELLOW='\033[1;33m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Performance tracking
declare -A PERFORMANCE_TIMERS

# Logging functions
log_info() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${BLUE}[INFO]${NC} $message"
    echo "[$timestamp] [INFO] $message" >> "$DEBUG_LOG"
}

log_warn() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${YELLOW}[WARN]${NC} $message"
    echo "[$timestamp] [WARN] $message" >> "$ERROR_LOG"
    echo "[$timestamp] [WARN] $message" >> "$DEBUG_LOG"
}

log_error() {
    local message="$1"
    local context="${2:-unknown}"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${RED}[ERROR]${NC} $message"
    echo "[$timestamp] [ERROR] [$context] $message" >> "$ERROR_LOG"
    echo "[$timestamp] [ERROR] [$context] $message" >> "$DEBUG_LOG"
}

log_fatal() {
    local message="$1"
    local context="${2:-unknown}"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${RED}[FATAL]${NC} $message"
    echo "[$timestamp] [FATAL] [$context] $message" >> "$ERROR_LOG"
    echo "[$timestamp] [FATAL] [$context] $message" >> "$DEBUG_LOG"
}

log_success() {
    local message="$1"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    echo -e "${GREEN}[SUCCESS]${NC} $message"
    echo "[$timestamp] [SUCCESS] $message" >> "$DEBUG_LOG"
}

# Performance tracking functions
start_timer() {
    local timer_name="$1"
    PERFORMANCE_TIMERS["$timer_name"]=$(date +%s.%N)
    log_info "Started timer: $timer_name"
}

end_timer() {
    local timer_name="$1"
    local start_time="${PERFORMANCE_TIMERS[$timer_name]}"
    
    if [ -z "$start_time" ]; then
        log_warn "Timer '$timer_name' was not started"
        return 1
    fi
    
    local end_time=$(date +%s.%N)
    local duration=$(awk "BEGIN {print $end_time - $start_time}")
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')

    echo "[$timestamp] $timer_name: ${duration}s" >> "$PERFORMANCE_LOG"
    log_success "Timer '$timer_name' completed in ${duration}s"
    
    unset PERFORMANCE_TIMERS["$timer_name"]
}

# Error handling with context
handle_error() {
    local exit_code=$1
    local line_number=$2
    local command="$3"
    local context="${4:-script}"
    
    if [ $exit_code -ne 0 ]; then
        log_error "Command failed with exit code $exit_code at line $line_number: $command" "$context"
        
        # Add stack trace if available
        if [ -n "$BASH_LINENO" ]; then
            log_error "Stack trace: ${BASH_SOURCE[*]} at lines ${BASH_LINENO[*]}" "$context"
        fi
        
        return $exit_code
    fi
}

# Retry mechanism with exponential backoff
retry_with_backoff() {
    local max_attempts="$1"
    local base_delay="$2"
    local command="$3"
    local context="${4:-retry}"
    
    local attempt=1
    local delay="$base_delay"
    
    while [ $attempt -le $max_attempts ]; do
        log_info "Attempt $attempt/$max_attempts: $command"
        
        if eval "$command"; then
            log_success "Command succeeded on attempt $attempt"
            return 0
        fi
        
        if [ $attempt -eq $max_attempts ]; then
            log_error "Command failed after $max_attempts attempts" "$context"
            return 1
        fi
        
        log_warn "Attempt $attempt failed, retrying in ${delay}s..."
        sleep "$delay"
        
        # Exponential backoff
        delay=$(awk "BEGIN {print $delay * 2}")
        attempt=$((attempt + 1))
    done
}

# Circuit breaker integration
check_circuit_breaker() {
    local service="$1"
    local context="${2:-circuit-check}"
    
    if scripts/circuit-breaker.sh "$service" >/dev/null 2>&1; then
        log_success "Circuit breaker CLOSED for $service"
        return 0
    else
        log_warn "Circuit breaker OPEN for $service, using fallback" "$context"
        return 1
    fi
}

# Resource monitoring
monitor_resources() {
    local context="${1:-resource-monitor}"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    # Memory usage
    local memory_usage=$(free -m | awk 'NR==2{printf "%.1f%%", $3*100/$2}')
    
    # Disk usage
    local disk_usage=$(df /tmp | awk 'NR==2{print $5}' | sed 's/%//')
    
    # CPU load
    local cpu_load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | sed 's/,//')
    
    echo "[$timestamp] Memory: $memory_usage, Disk: $disk_usage%, CPU Load: $cpu_load" >> "$PERFORMANCE_LOG"
    
    # Warn if resources are high
    if [ "${disk_usage}" -gt 80 ]; then
        log_warn "High disk usage: $disk_usage%" "$context"
    fi
    
    if [ "${memory_usage%.*}" -gt 80 ]; then
        log_warn "High memory usage: $memory_usage" "$context"
    fi
}

# Cleanup function
cleanup_logs() {
    local max_age_days="${1:-7}"
    
    # Clean up old log files
    find /tmp -name "*.log" -type f -mtime +$max_age_days -delete 2>/dev/null || true
    find /tmp -name "*-cache" -type d -mtime +$max_age_days -exec rm -rf {} + 2>/dev/null || true
    
    log_info "Cleaned up logs older than $max_age_days days"
}

# Error summary
generate_error_summary() {
    local context="${1:-summary}"
    
    if [ -f "$ERROR_LOG" ]; then
        local error_count=$(grep -c "\[ERROR\]" "$ERROR_LOG" 2>/dev/null || echo "0")
        local warn_count=$(grep -c "\[WARN\]" "$ERROR_LOG" 2>/dev/null || echo "0")
        local fatal_count=$(grep -c "\[FATAL\]" "$ERROR_LOG" 2>/dev/null || echo "0")
        
        log_info "Error Summary - Errors: $error_count, Warnings: $warn_count, Fatal: $fatal_count"
        
        if [ "$fatal_count" -gt 0 ]; then
            return 3
        elif [ "$error_count" -gt 0 ]; then
            return 2
        elif [ "$warn_count" -gt 0 ]; then
            return 1
        else
            return 0
        fi
    fi
    
    return 0
}

# Export functions for use in other scripts
export -f log_info log_warn log_error log_fatal log_success
export -f start_timer end_timer handle_error retry_with_backoff
export -f check_circuit_breaker monitor_resources cleanup_logs generate_error_summary
