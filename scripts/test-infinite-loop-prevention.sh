#!/bin/bash

# Infinite Loop Prevention Analysis
# Tests that workflow changes prevent circular dependencies and endless loops

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Test result logging
log_test_result() {
    local test_name="$1"
    local expected="$2"
    local actual="$3"
    local details="$4"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$expected" = "$actual" ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo -e "${GREEN}✅ PASS${NC}: $test_name - $details"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo -e "${RED}❌ FAIL${NC}: $test_name - Expected: $expected, Got: $actual - $details"
    fi
}

# Test 1: Priority Dependencies Removal
test_priority_dependencies_removal() {
    echo -e "${BLUE}🔍 Testing Priority Dependencies Removal${NC}"
    
    local priority_found=false
    local workflows_with_priority=()
    
    # Check all workflow files for priority dependencies
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            if grep -q "needs:.*priority\|wait-for-priority\|Priority.*:" "$workflow" 2>/dev/null; then
                priority_found=true
                workflows_with_priority+=("$(basename "$workflow")")
            fi
        fi
    done
    
    log_test_result "Priority Dependencies Removed" "false" "$priority_found" "No priority dependencies found in workflows"
    
    if [ "$priority_found" = true ]; then
        echo -e "${YELLOW}Workflows with priority dependencies: ${workflows_with_priority[*]}${NC}"
    fi
    
    echo ""
}

# Test 2: Circular Dependency Detection
test_circular_dependency_detection() {
    echo -e "${BLUE}🔍 Testing Circular Dependency Detection${NC}"
    
    # Check for circular dependencies in workflow triggers
    local circular_deps=false
    local workflow_triggers=()
    
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            local workflow_name=$(basename "$workflow" .yml)
            
            # Check if workflow triggers other workflows that could trigger it back
            if grep -q "workflow_run:" "$workflow" 2>/dev/null; then
                local triggered_by=$(grep -A 5 "workflow_run:" "$workflow" | grep "workflows:" | sed 's/.*workflows: *\[\(.*\)\].*/\1/' | tr -d '"[]')
                workflow_triggers+=("$workflow_name <- $triggered_by")
            fi
        fi
    done
    
    # Simple circular dependency check (A triggers B, B triggers A)
    for trigger in "${workflow_triggers[@]}"; do
        local workflow=$(echo "$trigger" | cut -d' ' -f1)
        local triggered_by=$(echo "$trigger" | cut -d' ' -f3-)
        
        # Check if any triggered workflow also triggers this one
        for reverse_trigger in "${workflow_triggers[@]}"; do
            local reverse_workflow=$(echo "$reverse_trigger" | cut -d' ' -f1)
            local reverse_triggered_by=$(echo "$reverse_trigger" | cut -d' ' -f3-)
            
            if [[ "$workflow" == "$reverse_triggered_by" && "$triggered_by" == "$reverse_workflow" ]]; then
                circular_deps=true
                echo -e "${YELLOW}Potential circular dependency: $workflow <-> $reverse_workflow${NC}"
            fi
        done
    done
    
    log_test_result "No Circular Dependencies" "false" "$circular_deps" "Workflows are independent"
    echo ""
}

# Test 3: Auto-Version Workflow Independence
test_auto_version_independence() {
    echo -e "${BLUE}🔍 Testing Auto-Version Workflow Independence${NC}"
    
    # Check that auto-version doesn't create recursive triggers
    local auto_version_file=".github/workflows/auto-version.yml"
    local recursive_trigger=false
    
    if [ -f "$auto_version_file" ]; then
        # Check if auto-version triggers on its own commits
        if grep -q "push:" "$auto_version_file" && grep -q "main" "$auto_version_file"; then
            # This is expected - but check if it has protection against its own commits
            if grep -q "skip.*version\|version.*skip\|\[skip.*\]" "$auto_version_file"; then
                recursive_trigger=false  # Has protection
            else
                # Check for other protection mechanisms
                if grep -q "paths-ignore:\|if:.*contains.*skip" "$auto_version_file"; then
                    recursive_trigger=false  # Has protection
                else
                    recursive_trigger=true   # No protection found
                fi
            fi
        fi
    fi
    
    log_test_result "Auto-Version Has Loop Protection" "false" "$recursive_trigger" "Protected against self-triggering"
    
    # Check for simplified trigger conditions
    local simplified_triggers=true
    if [ -f "$auto_version_file" ]; then
        local line_count=$(wc -l < "$auto_version_file")
        if [ "$line_count" -gt 300 ]; then
            simplified_triggers=false
        fi
    fi
    
    log_test_result "Auto-Version Simplified" "true" "$simplified_triggers" "Workflow is simplified"
    echo ""
}

# Test 4: Event-Driven Trigger Safety
test_event_driven_triggers() {
    echo -e "${BLUE}🔍 Testing Event-Driven Trigger Safety${NC}"
    
    # Check that workflows use safe event triggers
    local unsafe_triggers=false
    local unsafe_workflows=()
    
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            local workflow_name=$(basename "$workflow")
            
            # Check for potentially unsafe trigger combinations
            if grep -q "on:" "$workflow"; then
                # Check for overly broad triggers
                if grep -A 10 "on:" "$workflow" | grep -q "schedule:\|repository_dispatch:" 2>/dev/null; then
                    # These can be safe if properly configured
                    continue
                fi
                
                # Check for missing conditions on broad triggers
                if grep -A 10 "on:" "$workflow" | grep -q "push:" 2>/dev/null; then
                    if ! grep -q "if:" "$workflow" && ! grep -q "paths:" "$workflow"; then
                        # Push trigger without conditions could be unsafe
                        unsafe_triggers=true
                        unsafe_workflows+=("$workflow_name")
                    fi
                fi
            fi
        fi
    done
    
    log_test_result "Safe Event Triggers" "false" "$unsafe_triggers" "All triggers have appropriate conditions"
    
    if [ "$unsafe_triggers" = true ]; then
        echo -e "${YELLOW}Workflows with potentially unsafe triggers: ${unsafe_workflows[*]}${NC}"
    fi
    
    echo ""
}

# Test 5: Circuit Breaker Exit Conditions
test_circuit_breaker_exit_conditions() {
    echo -e "${BLUE}🔍 Testing Circuit Breaker Exit Conditions${NC}"
    
    # Test that circuit breaker has proper exit conditions
    local circuit_breaker_file="scripts/circuit-breaker.sh"
    local has_exit_conditions=false
    local has_timeout_protection=false
    
    if [ -f "$circuit_breaker_file" ]; then
        # Check for timeout mechanisms
        if grep -q "timeout\|TIMEOUT\|sleep" "$circuit_breaker_file"; then
            has_timeout_protection=true
        fi
        
        # Check for exit conditions
        if grep -q "exit\|return" "$circuit_breaker_file"; then
            has_exit_conditions=true
        fi
    fi
    
    log_test_result "Circuit Breaker Has Exit Conditions" "true" "$has_exit_conditions" "Proper exit handling"
    log_test_result "Circuit Breaker Has Timeout Protection" "true" "$has_timeout_protection" "Timeout mechanisms present"
    
    # Test circuit breaker state management
    local state_management=false
    if [ -f "$circuit_breaker_file" ]; then
        if grep -q "state\|STATE" "$circuit_breaker_file"; then
            state_management=true
        fi
    fi
    
    log_test_result "Circuit Breaker State Management" "true" "$state_management" "State tracking implemented"
    echo ""
}

# Test 6: Error Handling Exit Conditions
test_error_handling_exit_conditions() {
    echo -e "${BLUE}🔍 Testing Error Handling Exit Conditions${NC}"
    
    # Check that error handling has proper exit conditions
    local error_handler_file="scripts/error-handler.sh"
    local has_error_limits=false
    local has_retry_limits=false
    
    if [ -f "$error_handler_file" ]; then
        # Check for retry limits
        if grep -q "max.*attempt\|attempt.*max\|retry.*limit" "$error_handler_file"; then
            has_retry_limits=true
        fi
        
        # Check for error thresholds
        if grep -q "threshold\|limit\|max.*error" "$error_handler_file"; then
            has_error_limits=true
        fi
    fi
    
    log_test_result "Error Handler Has Retry Limits" "true" "$has_retry_limits" "Retry mechanisms are bounded"
    log_test_result "Error Handler Has Error Limits" "true" "$has_error_limits" "Error thresholds implemented"
    echo ""
}

# Main test execution
main() {
    echo -e "${BLUE}🚀 Starting Infinite Loop Prevention Analysis${NC}"
    echo "=============================================="
    echo ""
    
    # Run all tests
    test_priority_dependencies_removal
    test_circular_dependency_detection
    test_auto_version_independence
    test_event_driven_triggers
    test_circuit_breaker_exit_conditions
    test_error_handling_exit_conditions
    
    # Generate summary
    echo -e "${BLUE}📊 Analysis Summary${NC}"
    echo "==================="
    echo -e "Total Tests: $TOTAL_TESTS"
    echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
    echo -e "${RED}Failed: $FAILED_TESTS${NC}"
    
    local success_rate=$(( (PASSED_TESTS * 100) / TOTAL_TESTS ))
    echo -e "Success Rate: ${success_rate}%"
    
    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "${GREEN}🎉 All infinite loop prevention tests passed!${NC}"
        echo -e "${GREEN}✅ Workflows are protected against circular dependencies and endless loops${NC}"
        return 0
    else
        echo -e "${RED}❌ Some tests failed. Review the failures above.${NC}"
        return 1
    fi
}

# Execute main function
main "$@"
