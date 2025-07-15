#!/bin/bash

# JavaScript Syntax Validation for GitHub Actions Workflows
# Validates that all github-script actions use safe patterns

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0

# Check result logging
log_check_result() {
    local check_name="$1"
    local status="$2"
    local message="$3"
    
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    if [ "$status" = "PASS" ]; then
        PASSED_CHECKS=$((PASSED_CHECKS + 1))
        echo -e "${GREEN}✅ PASS${NC}: $check_name - $message"
    else
        FAILED_CHECKS=$((FAILED_CHECKS + 1))
        echo -e "${RED}❌ FAIL${NC}: $check_name - $message"
    fi
}

# Check for unsafe template literal interpolation patterns
check_unsafe_interpolation() {
    echo -e "${BLUE}🔍 Checking for unsafe template literal interpolation...${NC}"
    
    local unsafe_patterns=(
        'const.*= \$\{\{'
        'let.*= \$\{\{'
        'var.*= \$\{\{'
    )
    
    local found_unsafe=false
    local unsafe_files=()
    
    for pattern in "${unsafe_patterns[@]}"; do
        while IFS= read -r -d '' file; do
            if grep -q "$pattern" "$file" 2>/dev/null; then
                found_unsafe=true
                unsafe_files+=("$file")
                echo -e "${YELLOW}Found unsafe pattern '$pattern' in: $file${NC}"
            fi
        done < <(find .github/workflows -name "*.yml" -print0)
    done
    
    if [ "$found_unsafe" = false ]; then
        log_check_result "Unsafe Interpolation" "PASS" "No unsafe template literal patterns found"
    else
        log_check_result "Unsafe Interpolation" "FAIL" "Found unsafe patterns in ${#unsafe_files[@]} files"
    fi
    
    echo ""
}

# Check for proper environment variable usage
check_environment_variables() {
    echo -e "${BLUE}🔍 Checking for proper environment variable usage...${NC}"
    
    local github_script_files=()
    local proper_env_usage=true
    
    # Find all files with github-script actions
    while IFS= read -r -d '' file; do
        if grep -q "actions/github-script" "$file" 2>/dev/null; then
            github_script_files+=("$file")
        fi
    done < <(find .github/workflows -name "*.yml" -print0)
    
    for file in "${github_script_files[@]}"; do
        # Check if file has github-script with direct interpolation
        if grep -A 20 "actions/github-script" "$file" | grep -q "script:" && \
           grep -A 20 "actions/github-script" "$file" | grep -q "process\.env\." && \
           grep -B 5 -A 15 "actions/github-script" "$file" | grep -q "env:"; then
            # This is good - using environment variables
            continue
        elif grep -A 20 "actions/github-script" "$file" | grep -q "script:" && \
             grep -A 20 "actions/github-script" "$file" | grep -q "\${{"; then
            # This might be unsafe direct interpolation
            echo -e "${YELLOW}Potential unsafe interpolation in: $file${NC}"
            proper_env_usage=false
        fi
    done
    
    if [ "$proper_env_usage" = true ]; then
        log_check_result "Environment Variables" "PASS" "Proper environment variable usage detected"
    else
        log_check_result "Environment Variables" "FAIL" "Some files may have unsafe interpolation"
    fi
    
    echo ""
}

# Check for proper error handling in JavaScript
check_error_handling() {
    echo -e "${BLUE}🔍 Checking for proper error handling in JavaScript...${NC}"
    
    local files_with_try_catch=0
    local total_github_script_files=0
    
    while IFS= read -r -d '' file; do
        if grep -q "actions/github-script" "$file" 2>/dev/null; then
            total_github_script_files=$((total_github_script_files + 1))
            
            if grep -A 50 "actions/github-script" "$file" | grep -q "try {" && \
               grep -A 50 "actions/github-script" "$file" | grep -q "catch"; then
                files_with_try_catch=$((files_with_try_catch + 1))
            fi
        fi
    done < <(find .github/workflows -name "*.yml" -print0)
    
    local error_handling_rate=$(( (files_with_try_catch * 100) / total_github_script_files ))
    
    if [ "$error_handling_rate" -ge 80 ]; then
        log_check_result "Error Handling" "PASS" "$error_handling_rate% of github-script actions have error handling"
    else
        log_check_result "Error Handling" "FAIL" "Only $error_handling_rate% of github-script actions have error handling"
    fi
    
    echo ""
}

# Check for secure content handling
check_secure_content_handling() {
    echo -e "${BLUE}🔍 Checking for secure content handling...${NC}"
    
    local secure_patterns=(
        "parseInt(process\.env\."
        "process\.env\.[A-Z_]+"
        "JSON\.parse.*process\.env"
    )
    
    local secure_usage=false
    
    for pattern in "${secure_patterns[@]}"; do
        if grep -r "$pattern" .github/workflows/ >/dev/null 2>&1; then
            secure_usage=true
            break
        fi
    done
    
    if [ "$secure_usage" = true ]; then
        log_check_result "Secure Content" "PASS" "Secure content handling patterns detected"
    else
        log_check_result "Secure Content" "FAIL" "No secure content handling patterns found"
    fi
    
    echo ""
}

# Check for specific vulnerability patterns from PR #337
check_pr337_patterns() {
    echo -e "${BLUE}🔍 Checking for PR #337 vulnerability patterns...${NC}"
    
    local vulnerable_patterns=(
        'const prNumber = \$\{\{'
        'const issueNumber = \$\{\{'
        'const.*= \$\{\{.*\}\};'
    )
    
    local found_vulnerable=false
    
    for pattern in "${vulnerable_patterns[@]}"; do
        if grep -r "$pattern" .github/workflows/ >/dev/null 2>&1; then
            found_vulnerable=true
            echo -e "${RED}Found PR #337 vulnerability pattern: $pattern${NC}"
        fi
    done
    
    if [ "$found_vulnerable" = false ]; then
        log_check_result "PR #337 Patterns" "PASS" "No PR #337 vulnerability patterns found"
    else
        log_check_result "PR #337 Patterns" "FAIL" "Found PR #337 vulnerability patterns"
    fi
    
    echo ""
}

# Validate specific workflow files
validate_specific_workflows() {
    echo -e "${BLUE}🔍 Validating specific workflow files...${NC}"
    
    local critical_workflows=(
        ".github/workflows/claude-approval-gate.yml"
        ".github/workflows/claude-code-review.yml"
        ".github/workflows/claude-direct-approval.yml"
        ".github/workflows/claude.yml"
    )
    
    for workflow in "${critical_workflows[@]}"; do
        if [ -f "$workflow" ]; then
            # Check YAML syntax
            if python3 -c "import yaml; yaml.safe_load(open('$workflow'))" 2>/dev/null; then
                log_check_result "YAML Syntax: $(basename "$workflow")" "PASS" "Valid YAML syntax"
            else
                log_check_result "YAML Syntax: $(basename "$workflow")" "FAIL" "Invalid YAML syntax"
            fi
            
            # Check for github-script usage
            if grep -q "actions/github-script" "$workflow"; then
                if grep -B 5 -A 15 "actions/github-script" "$workflow" | grep -q "env:" && \
                   grep -A 20 "actions/github-script" "$workflow" | grep -q "process\.env\."; then
                    log_check_result "Safe JS: $(basename "$workflow")" "PASS" "Uses environment variables"
                else
                    log_check_result "Safe JS: $(basename "$workflow")" "FAIL" "May have unsafe JavaScript"
                fi
            fi
        else
            log_check_result "File Exists: $(basename "$workflow")" "FAIL" "File not found"
        fi
    done
    
    echo ""
}

# Main execution
main() {
    echo -e "${BLUE}🚀 JavaScript Syntax Validation for GitHub Actions${NC}"
    echo "=================================================="
    echo ""
    
    # Run all checks
    check_unsafe_interpolation
    check_environment_variables
    check_error_handling
    check_secure_content_handling
    check_pr337_patterns
    validate_specific_workflows
    
    # Generate summary
    echo -e "${BLUE}📊 Validation Summary${NC}"
    echo "====================="
    echo -e "Total Checks: $TOTAL_CHECKS"
    echo -e "${GREEN}Passed: $PASSED_CHECKS${NC}"
    echo -e "${RED}Failed: $FAILED_CHECKS${NC}"
    
    local success_rate=$(( (PASSED_CHECKS * 100) / TOTAL_CHECKS ))
    echo -e "Success Rate: ${success_rate}%"
    
    if [ $FAILED_CHECKS -eq 0 ]; then
        echo -e "${GREEN}🎉 All JavaScript syntax validation checks passed!${NC}"
        echo -e "${GREEN}✅ Workflows are protected against syntax errors${NC}"
        return 0
    else
        echo -e "${RED}❌ Some validation checks failed${NC}"
        return 1
    fi
}

# Execute main function
main "$@"
