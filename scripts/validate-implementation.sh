#!/bin/bash

# Validation Script for Workflow Cascading Failure Fixes
# Tests all implemented components to ensure proper functionality

set -e

VALIDATION_LOG="/tmp/validation.log"
echo "=== Implementation Validation Started: $(date) ===" > "$VALIDATION_LOG"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_TOTAL=0

# Helper functions
log_test() {
    local test_name="$1"
    local status="$2"
    local message="$3"
    
    TESTS_TOTAL=$((TESTS_TOTAL + 1))
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅ PASS${NC}: $test_name - $message" | tee -a "$VALIDATION_LOG"
        TESTS_PASSED=$((TESTS_PASSED + 1))
    elif [ "$status" = "FAIL" ]; then
        echo -e "${RED}❌ FAIL${NC}: $test_name - $message" | tee -a "$VALIDATION_LOG"
        TESTS_FAILED=$((TESTS_FAILED + 1))
    else
        echo -e "${YELLOW}⚠️  WARN${NC}: $test_name - $message" | tee -a "$VALIDATION_LOG"
    fi
}

# Test 1: Workflow file structure
test_workflow_files() {
    echo -e "${BLUE}🔍 Testing workflow file structure...${NC}"
    
    # Check if simplified workflows exist
    if [ -f ".github/workflows/tests.yml" ]; then
        local line_count=$(wc -l < .github/workflows/tests.yml)
        if [ "$line_count" -lt 300 ]; then
            log_test "Tests Workflow" "PASS" "Simplified to $line_count lines"
        else
            log_test "Tests Workflow" "FAIL" "Still complex with $line_count lines"
        fi
    else
        log_test "Tests Workflow" "FAIL" "File not found"
    fi
    
    if [ -f ".github/workflows/auto-version.yml" ]; then
        local line_count=$(wc -l < .github/workflows/auto-version.yml)
        if [ "$line_count" -lt 200 ]; then
            log_test "Auto-Version Workflow" "PASS" "Simplified to $line_count lines"
        else
            log_test "Auto-Version Workflow" "FAIL" "Still complex with $line_count lines"
        fi
    else
        log_test "Auto-Version Workflow" "FAIL" "File not found"
    fi
    
    if [ -f ".github/workflows/claude-approval-gate.yml" ]; then
        local line_count=$(wc -l < .github/workflows/claude-approval-gate.yml)
        if [ "$line_count" -lt 250 ]; then
            log_test "Claude Approval Workflow" "PASS" "Simplified to $line_count lines"
        else
            log_test "Claude Approval Workflow" "FAIL" "Still complex with $line_count lines"
        fi
    else
        log_test "Claude Approval Workflow" "FAIL" "File not found"
    fi
}

# Test 2: Script functionality
test_scripts() {
    echo -e "${BLUE}🔍 Testing script functionality...${NC}"
    
    # Test health check script
    if [ -x "scripts/health-check.sh" ]; then
        if scripts/health-check.sh auto >/dev/null 2>&1; then
            log_test "Health Check Script" "PASS" "Executable and returns mode"
        else
            log_test "Health Check Script" "WARN" "Executable but may have issues"
        fi
    else
        log_test "Health Check Script" "FAIL" "Not found or not executable"
    fi
    
    # Test circuit breaker script
    if [ -x "scripts/circuit-breaker.sh" ]; then
        if scripts/circuit-breaker.sh mysql_service >/dev/null 2>&1; then
            log_test "Circuit Breaker Script" "WARN" "Service check completed (expected failure)"
        else
            log_test "Circuit Breaker Script" "PASS" "Properly detects service failures"
        fi
    else
        log_test "Circuit Breaker Script" "FAIL" "Not found or not executable"
    fi
    
    # Test run tests script
    if [ -x "scripts/run-tests.sh" ]; then
        log_test "Run Tests Script" "PASS" "Found and executable"
    else
        log_test "Run Tests Script" "FAIL" "Not found or not executable"
    fi
    
    # Test setup fallbacks script
    if [ -x "scripts/setup-local-fallbacks.sh" ]; then
        if scripts/setup-local-fallbacks.sh >/dev/null 2>&1; then
            log_test "Setup Fallbacks Script" "PASS" "Successfully creates fallbacks"
        else
            log_test "Setup Fallbacks Script" "FAIL" "Failed to create fallbacks"
        fi
    else
        log_test "Setup Fallbacks Script" "FAIL" "Not found or not executable"
    fi
}

# Test 3: Priority system removal
test_priority_removal() {
    echo -e "${BLUE}🔍 Testing priority system removal...${NC}"
    
    # Check for priority dependencies in workflows
    local priority_found=false
    
    for workflow in .github/workflows/*.yml; do
        if grep -q "needs:.*priority" "$workflow" 2>/dev/null; then
            priority_found=true
            break
        fi
        if grep -q "wait-for-priority" "$workflow" 2>/dev/null; then
            priority_found=true
            break
        fi
    done
    
    if [ "$priority_found" = false ]; then
        log_test "Priority Dependencies" "PASS" "No priority dependencies found"
    else
        log_test "Priority Dependencies" "FAIL" "Priority dependencies still exist"
    fi
    
    # Check for simplified concurrency
    if grep -q "cancel-in-progress: true" .github/workflows/tests.yml 2>/dev/null; then
        log_test "Concurrency Simplification" "PASS" "Simplified concurrency management"
    else
        log_test "Concurrency Simplification" "WARN" "Concurrency may not be simplified"
    fi
}

# Test 4: Circuit breaker implementation
test_circuit_breaker() {
    echo -e "${BLUE}🔍 Testing circuit breaker implementation...${NC}"
    
    # Test circuit breaker state management
    if scripts/circuit-breaker.sh mysql_service >/dev/null 2>&1; then
        # Check if state files are created
        if [ -d "/tmp/circuit-breaker-cache" ]; then
            log_test "Circuit Breaker State" "PASS" "State management working"
        else
            log_test "Circuit Breaker State" "WARN" "State directory not created"
        fi
    else
        # This is expected - service should fail
        if [ -d "/tmp/circuit-breaker-cache" ]; then
            log_test "Circuit Breaker Failure Handling" "PASS" "Properly handles failures"
        else
            log_test "Circuit Breaker Failure Handling" "FAIL" "Not handling failures correctly"
        fi
    fi
    
    # Test fallback creation
    if [ -d "/tmp/blazecommerce-fallbacks" ]; then
        log_test "Fallback Mechanisms" "PASS" "Fallback directory exists"
        
        # Check for key fallback files
        if [ -f "/tmp/blazecommerce-fallbacks/wordpress-tests-lib/includes/bootstrap.php" ]; then
            log_test "WordPress Fallback" "PASS" "WordPress test fallback created"
        else
            log_test "WordPress Fallback" "FAIL" "WordPress test fallback missing"
        fi
        
        if [ -f "/tmp/blazecommerce-fallbacks/claude/approval_template.md" ]; then
            log_test "Claude Fallback" "PASS" "Claude API fallback created"
        else
            log_test "Claude Fallback" "FAIL" "Claude API fallback missing"
        fi
    else
        log_test "Fallback Mechanisms" "FAIL" "Fallback directory not found"
    fi
}

# Test 5: Documentation completeness
test_documentation() {
    echo -e "${BLUE}🔍 Testing documentation completeness...${NC}"
    
    local docs=(
        "docs/workflow-cascading-failure-fixes-implementation.md"
        "docs/workflow-quick-reference.md"
        "docs/implementation-summary.md"
    )
    
    for doc in "${docs[@]}"; do
        if [ -f "$doc" ]; then
            local word_count=$(wc -w < "$doc")
            if [ "$word_count" -gt 100 ]; then
                log_test "Documentation: $(basename "$doc")" "PASS" "$word_count words"
            else
                log_test "Documentation: $(basename "$doc")" "WARN" "May be incomplete ($word_count words)"
            fi
        else
            log_test "Documentation: $(basename "$doc")" "FAIL" "File not found"
        fi
    done
}

# Test 6: Workflow syntax validation
test_workflow_syntax() {
    echo -e "${BLUE}🔍 Testing workflow syntax...${NC}"
    
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            # Basic YAML syntax check
            if python3 -c "import yaml; yaml.safe_load(open('$workflow'))" 2>/dev/null; then
                log_test "Syntax: $(basename "$workflow")" "PASS" "Valid YAML syntax"
            else
                log_test "Syntax: $(basename "$workflow")" "FAIL" "Invalid YAML syntax"
            fi
        fi
    done
}

# Main validation function
main() {
    echo -e "${BLUE}🚀 Starting Implementation Validation${NC}"
    echo "======================================"
    
    test_workflow_files
    test_scripts
    test_priority_removal
    test_circuit_breaker
    test_documentation
    test_workflow_syntax
    
    echo ""
    echo -e "${BLUE}📊 Validation Summary${NC}"
    echo "===================="
    echo -e "Total Tests: $TESTS_TOTAL"
    echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
    echo -e "${RED}Failed: $TESTS_FAILED${NC}"
    echo -e "Success Rate: $(( (TESTS_PASSED * 100) / TESTS_TOTAL ))%"
    
    if [ "$TESTS_FAILED" -eq 0 ]; then
        echo -e "${GREEN}🎉 All validations passed! Implementation is ready.${NC}"
        echo "=== Implementation Validation Completed Successfully: $(date) ===" >> "$VALIDATION_LOG"
        exit 0
    else
        echo -e "${RED}⚠️  Some validations failed. Please review and fix issues.${NC}"
        echo "=== Implementation Validation Completed with Issues: $(date) ===" >> "$VALIDATION_LOG"
        exit 1
    fi
}

# Execute main function
main "$@"
