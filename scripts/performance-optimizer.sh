#!/bin/bash

# Performance Optimization Script
# Implements smart caching and performance improvements for workflows

set -e

# Source error handler
source "$(dirname "$0")/error-handler.sh" 2>/dev/null || {
    echo "Warning: Error handler not available, using basic logging"
    log_info() { echo "[INFO] $1"; }
    log_warn() { echo "[WARN] $1"; }
    log_error() { echo "[ERROR] $1"; }
    start_timer() { echo "Starting timer: $1"; }
    end_timer() { echo "Ending timer: $1"; }
}

# Configuration
CACHE_DIR="/tmp/blazecommerce-cache"
PERFORMANCE_CACHE="$CACHE_DIR/performance"
DEPENDENCY_CACHE="$CACHE_DIR/dependencies"
CACHE_TTL=3600  # 1 hour

# Initialize cache directories
mkdir -p "$CACHE_DIR" "$PERFORMANCE_CACHE" "$DEPENDENCY_CACHE"

# Cache management functions
is_cache_valid() {
    local cache_file="$1"
    local ttl="${2:-$CACHE_TTL}"
    
    if [ ! -f "$cache_file" ]; then
        return 1
    fi
    
    local file_age=$(($(date +%s) - $(stat -c %Y "$cache_file" 2>/dev/null || echo "0")))
    
    if [ "$file_age" -gt "$ttl" ]; then
        log_info "Cache expired: $cache_file (age: ${file_age}s)"
        return 1
    fi
    
    log_info "Cache valid: $cache_file (age: ${file_age}s)"
    return 0
}

cache_composer_dependencies() {
    local project_dir="${1:-.}"
    local cache_key="composer-$(md5sum "$project_dir/composer.json" 2>/dev/null | cut -d' ' -f1 || echo 'default')"
    local cache_file="$DEPENDENCY_CACHE/$cache_key.tar.gz"
    
    start_timer "composer-cache-check"
    
    if is_cache_valid "$cache_file" 7200; then  # 2 hour TTL for composer
        log_info "Restoring Composer dependencies from cache"
        cd "$project_dir"
        tar -xzf "$cache_file" 2>/dev/null || {
            log_warn "Failed to restore cache, will install fresh"
            rm -f "$cache_file"
            return 1
        }
        end_timer "composer-cache-check"
        return 0
    fi
    
    end_timer "composer-cache-check"
    return 1
}

save_composer_cache() {
    local project_dir="${1:-.}"
    local cache_key="composer-$(md5sum "$project_dir/composer.json" 2>/dev/null | cut -d' ' -f1 || echo 'default')"
    local cache_file="$DEPENDENCY_CACHE/$cache_key.tar.gz"
    
    if [ -d "$project_dir/vendor" ]; then
        start_timer "composer-cache-save"
        cd "$project_dir"
        tar -czf "$cache_file" vendor/ 2>/dev/null || {
            log_warn "Failed to save Composer cache"
            return 1
        }
        log_info "Composer dependencies cached"
        end_timer "composer-cache-save"
    fi
}

optimize_composer_install() {
    local project_dir="${1:-.}"
    
    log_info "Optimizing Composer installation"
    
    # Try to restore from cache first
    if cache_composer_dependencies "$project_dir"; then
        return 0
    fi
    
    # Install with optimizations
    start_timer "composer-install"
    cd "$project_dir"
    
    # Configure Composer for performance
    composer config --global process-timeout 600
    composer config --global cache-ttl 86400
    composer config --global optimize-autoloader true
    
    # Install with performance flags
    if composer install \
        --prefer-dist \
        --no-progress \
        --no-interaction \
        --optimize-autoloader \
        --classmap-authoritative \
        --no-dev; then
        
        log_info "Composer installation completed"
        save_composer_cache "$project_dir"
        end_timer "composer-install"
        return 0
    else
        log_error "Composer installation failed"
        end_timer "composer-install"
        return 1
    fi
}

cache_wordpress_environment() {
    local wp_version="${1:-latest}"
    local cache_key="wordpress-$wp_version"
    local cache_file="$DEPENDENCY_CACHE/$cache_key.tar.gz"
    local wp_tests_dir="/tmp/wordpress-tests-lib"
    local wp_core_dir="/tmp/wordpress"
    
    start_timer "wordpress-cache-check"
    
    if is_cache_valid "$cache_file" 86400; then  # 24 hour TTL for WordPress
        log_info "Restoring WordPress environment from cache"
        cd /tmp
        tar -xzf "$cache_file" 2>/dev/null || {
            log_warn "Failed to restore WordPress cache"
            rm -f "$cache_file"
            end_timer "wordpress-cache-check"
            return 1
        }
        end_timer "wordpress-cache-check"
        return 0
    fi
    
    end_timer "wordpress-cache-check"
    return 1
}

save_wordpress_cache() {
    local wp_version="${1:-latest}"
    local cache_key="wordpress-$wp_version"
    local cache_file="$DEPENDENCY_CACHE/$cache_key.tar.gz"
    local wp_tests_dir="/tmp/wordpress-tests-lib"
    local wp_core_dir="/tmp/wordpress"
    
    if [ -d "$wp_tests_dir" ] && [ -d "$wp_core_dir" ]; then
        start_timer "wordpress-cache-save"
        cd /tmp
        tar -czf "$cache_file" wordpress-tests-lib/ wordpress/ 2>/dev/null || {
            log_warn "Failed to save WordPress cache"
            return 1
        }
        log_info "WordPress environment cached"
        end_timer "wordpress-cache-save"
    fi
}

optimize_test_execution() {
    local test_mode="${1:-auto}"
    local php_version="${2:-8.1}"
    
    log_info "Optimizing test execution for $test_mode mode"
    
    # Set PHP memory and execution limits
    export PHP_MEMORY_LIMIT="512M"
    export PHP_MAX_EXECUTION_TIME="300"
    
    # Configure PHPUnit for performance
    export PHPUNIT_CACHE_DIR="/tmp/phpunit-cache"
    mkdir -p "$PHPUNIT_CACHE_DIR"
    
    # Optimize based on test mode
    case "$test_mode" in
        "full")
            log_info "Full mode: Enabling all optimizations"
            export PHPUNIT_ARGS="--configuration phpunit.xml --stop-on-failure --cache-result-file=$PHPUNIT_CACHE_DIR/result-cache"
            ;;
        "basic")
            log_info "Basic mode: Optimizing for speed"
            export PHPUNIT_ARGS="--configuration phpunit.xml --no-coverage --stop-on-failure"
            ;;
        "minimal")
            log_info "Minimal mode: Maximum speed optimizations"
            export PHPUNIT_ARGS="--no-configuration --no-coverage --stop-on-error"
            ;;
    esac
}

parallel_processing() {
    local max_jobs="${1:-4}"
    local commands=("${@:2}")
    
    log_info "Running $((${#commands[@]})) commands in parallel (max $max_jobs jobs)"
    
    start_timer "parallel-processing"
    
    # Use xargs for parallel processing
    printf '%s\n' "${commands[@]}" | xargs -n 1 -P "$max_jobs" -I {} bash -c '{}'
    
    end_timer "parallel-processing"
}

cleanup_performance_cache() {
    local max_age_hours="${1:-24}"
    
    log_info "Cleaning up performance cache older than $max_age_hours hours"
    
    # Clean up old cache files
    find "$CACHE_DIR" -type f -mmin +$((max_age_hours * 60)) -delete 2>/dev/null || true
    
    # Clean up empty directories
    find "$CACHE_DIR" -type d -empty -delete 2>/dev/null || true
    
    log_info "Performance cache cleanup completed"
}

monitor_performance() {
    local operation="$1"
    local start_time=$(date +%s.%N)
    
    # Execute the operation
    shift
    "$@"
    local exit_code=$?
    
    local end_time=$(date +%s.%N)
    local duration=$(awk "BEGIN {print $end_time - $start_time}")

    # Log performance metrics
    echo "$(date '+%Y-%m-%d %H:%M:%S') [$operation] Duration: ${duration}s, Exit Code: $exit_code" >> "$PERFORMANCE_LOG"

    # Performance thresholds
    local slow_threshold=30.0
    if (( $(awk "BEGIN {print ($duration > $slow_threshold)}") )); then
        log_warn "Slow operation detected: $operation took ${duration}s (threshold: ${slow_threshold}s)"
    fi
    
    return $exit_code
}

optimize_system_resources() {
    log_info "Optimizing system resources"
    
    # Increase file descriptor limits
    ulimit -n 4096 2>/dev/null || log_warn "Could not increase file descriptor limit"
    
    # Set optimal umask
    umask 022
    
    # Configure Git for performance
    git config --global core.preloadindex true 2>/dev/null || true
    git config --global core.fscache true 2>/dev/null || true
    git config --global gc.auto 0 2>/dev/null || true
    
    log_info "System resource optimization completed"
}

# Main optimization function
main() {
    local operation="${1:-all}"
    
    case "$operation" in
        "composer")
            optimize_composer_install "${2:-.}"
            ;;
        "wordpress")
            cache_wordpress_environment "${2:-latest}"
            ;;
        "test")
            optimize_test_execution "${2:-auto}" "${3:-8.1}"
            ;;
        "cleanup")
            cleanup_performance_cache "${2:-24}"
            ;;
        "system")
            optimize_system_resources
            ;;
        "all")
            optimize_system_resources
            optimize_composer_install
            optimize_test_execution
            ;;
        *)
            echo "Usage: $0 {composer|wordpress|test|cleanup|system|all} [args...]"
            exit 1
            ;;
    esac
}

# Execute main function if script is run directly
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    main "$@"
fi
