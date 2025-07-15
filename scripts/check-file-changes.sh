#!/bin/bash
# File change detection script for GitHub workflows
# This script analyzes changed files and determines if version bump/release is needed
# Returns exit code 0 if action needed, 1 if should skip

set -euo pipefail

# Function to display usage
usage() {
    echo "Usage: $0 <changed_files_list> [--performance-mode]"
    echo ""
    echo "Arguments:"
    echo "  changed_files_list    Newline-separated list of changed files"
    echo "  --performance-mode    Use git pathspec for large changesets (optional)"
    echo ""
    echo "Exit codes:"
    echo "  0 - Action needed (version bump/release should proceed)"
    echo "  1 - Skip action (only ignored files changed)"
    echo "  2 - Error occurred"
    exit 2
}

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

# Function to check if file should be ignored using precise pattern matching
check_file_against_patterns() {
    local file="$1"
    shift
    local patterns=("$@")
    
    for pattern in "${patterns[@]}"; do
        # Handle directory patterns (ending with /)
        if [[ "$pattern" == */ ]]; then
            # Use safe prefix matching for directories
            if [[ "$file" == "$pattern"* ]]; then
                return 0  # File should be ignored
            fi
        # Handle exact file matches (most common case)
        elif [[ "$file" == "$pattern" ]]; then
            return 0  # File should be ignored
        # Handle file basename matches (file in any directory)
        elif [[ "$file" == */"$pattern" ]]; then
            return 0  # File should be ignored
        # Handle file extension patterns with precise matching
        elif [[ "$pattern" == .* ]] && [[ "${file##*/}" == "$pattern" ]]; then
            # Only match exact filename, not as substring (fixes .DS_Store issue)
            return 0  # File should be ignored
        fi
    done
    
    return 1  # File should NOT be ignored
}

# Function to use git pathspec for performance optimization
check_with_git_pathspec() {
    local changed_files="$1"
    local ignore_patterns_file="$2"
    
    log "🚀 Using performance-optimized git pathspec method"
    
    # Create temporary pathspec file
    local pathspec_file
    pathspec_file=$(mktemp)
    trap "rm -f '$pathspec_file'" EXIT
    
    # Convert ignore patterns to pathspec format
    while IFS= read -r pattern; do
        if [[ "$pattern" == */ ]]; then
            echo ":!$pattern*" >> "$pathspec_file"
        else
            echo ":!$pattern" >> "$pathspec_file"
            echo ":!*/$pattern" >> "$pathspec_file"
        fi
    done < "$ignore_patterns_file"
    
    # Check if any files remain after applying pathspec filters
    local remaining_files
    remaining_files=$(echo "$changed_files" | git check-ignore --stdin --non-matching --verbose 2>/dev/null | wc -l)
    
    if [ "$remaining_files" -gt 0 ]; then
        log "✅ Found $remaining_files non-ignored files - action needed"
        return 0  # Action needed
    else
        log "⏭️  All files are ignored - skipping action"
        return 1  # Skip action
    fi
}

# Main function
main() {
    local changed_files_input="$1"
    local performance_mode=false

    # Parse arguments
    if [ $# -lt 1 ]; then
        usage
    fi

    if [ $# -gt 1 ] && [ "$2" = "--performance-mode" ]; then
        performance_mode=true
    fi

    log "🔍 Starting file change analysis..."

    # Handle stdin input vs direct file list
    local changed_files=""
    if [ "$changed_files_input" = "/dev/stdin" ]; then
        # Read from stdin
        changed_files=$(cat)
    else
        # Use provided file list
        changed_files="$changed_files_input"
    fi

    # Validate input
    if [ -z "$changed_files" ]; then
        log "⚠️  No changed files provided - skipping action"
        exit 1
    fi
    
    # Load ignore patterns
    local ignore_patterns_script="scripts/get-ignore-patterns.sh"
    if [ ! -f "$ignore_patterns_script" ]; then
        log "❌ Required script '$ignore_patterns_script' not found"
        exit 2
    fi
    
    if ! IGNORE_PATTERNS=($(bash "$ignore_patterns_script")); then
        log "❌ Failed to load ignore patterns from script"
        exit 2
    fi
    
    log "✅ Loaded ${#IGNORE_PATTERNS[@]} ignore patterns"
    
    # Performance optimization for large changesets
    local file_count
    file_count=$(echo "$changed_files" | wc -l)
    
    if [ "$performance_mode" = true ] && [ "$file_count" -gt 50 ]; then
        log "📊 Large changeset detected ($file_count files) - using optimized method"
        check_with_git_pathspec "$changed_files" <(printf '%s\n' "${IGNORE_PATTERNS[@]}")
        return $?
    fi
    
    # Standard method for smaller changesets
    log "📁 Analyzing $file_count changed files..."
    
    local should_skip=true
    local ignored_count=0
    local non_ignored_count=0

    # Process files line by line (newline-separated)
    # Disable error handling for the loop to prevent early exit
    set +e
    set +o pipefail

    while IFS= read -r file; do
        [ -z "$file" ] && continue

        # Check if file should be ignored
        local file_ignored=false
        for pattern in "${IGNORE_PATTERNS[@]}"; do
            # Handle directory patterns (ending with /)
            if [[ "$pattern" == */ ]]; then
                if [[ "$file" == "$pattern"* ]]; then
                    file_ignored=true
                    break
                fi
            # Handle exact file matches
            elif [[ "$file" == "$pattern" ]]; then
                file_ignored=true
                break
            # Handle file basename matches (file in any directory)
            elif [[ "$file" == */"$pattern" ]]; then
                file_ignored=true
                break
            # Handle file extension patterns
            elif [[ "$pattern" == .* ]] && [[ "${file##*/}" == "$pattern" ]]; then
                file_ignored=true
                break
            fi
        done

        if [ "$file_ignored" = true ]; then
            log "⏭️  File '$file' is ignored"
            ((ignored_count++))
        else
            log "✅ File '$file' requires action"
            should_skip=false
            ((non_ignored_count++))
        fi
    done <<< "$changed_files"

    # Re-enable error handling
    set -e
    set -o pipefail
    
    # Summary
    log "📊 Analysis complete:"
    log "   - Ignored files: $ignored_count"
    log "   - Non-ignored files: $non_ignored_count"
    
    if [ "$should_skip" = true ]; then
        log "⏭️  All files are ignored - skipping action"
        exit 1
    else
        log "🚀 Non-ignored files found - action needed"
        exit 0
    fi
}

# Run main function with all arguments
main "$@"
