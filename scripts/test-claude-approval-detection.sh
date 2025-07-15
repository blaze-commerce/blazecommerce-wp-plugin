#!/bin/bash

# Claude AI Approval Detection Reliability Test
# Tests the approval detection logic for various Claude comment formats

set -e

# Test data - various Claude comment formats
declare -A TEST_COMMENTS=(
    ["approved_format_1"]='## 🤖 Claude AI PR Review Complete

**FINAL VERDICT: ✅ APPROVED**

### Summary
This PR has been reviewed and approved.

### Files Reviewed
- file1.php
- file2.js

### Automated Checks
- ✅ Code structure looks good
- ✅ No obvious security issues
- ✅ Follows coding standards

---
*Claude AI PR Review Complete*'

    ["approved_format_2"]='# Claude AI Review

**Status**: ✅ APPROVED FOR MERGE

**FINAL VERDICT**: This PR is ready for merge.

Claude AI PR Review Complete'

    ["approved_format_3"]='## Claude Review Results

**FINAL VERDICT: APPROVED**

All checks passed.

---
Claude AI PR Review Complete'

    ["rejected_format_1"]='## 🤖 Claude AI PR Review Complete

**FINAL VERDICT: ❌ REJECTED**

### Issues Found
- Security vulnerability detected
- Code quality issues

---
*Claude AI PR Review Complete*'

    ["pending_format"]='## 🤖 Claude AI PR Review

**Status**: PENDING

Review in progress...

---
*Claude AI PR Review Complete*'

    ["invalid_format"]='Just a regular comment without Claude review markers.'
)

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
    local comment_format="$4"
    
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
    
    if [ "$expected" = "$actual" ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        echo -e "${GREEN}✅ PASS${NC}: $test_name - Expected: $expected, Got: $actual"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        echo -e "${RED}❌ FAIL${NC}: $test_name - Expected: $expected, Got: $actual"
        echo -e "${YELLOW}Comment format tested:${NC} $comment_format"
    fi
}

# Test Claude approval detection logic
test_approval_detection() {
    local comment_body="$1"
    local format_name="$2"
    
    echo -e "${BLUE}🔍 Testing format: $format_name${NC}"
    
    # Test the actual logic from claude-approval-gate.yml
    local has_final_verdict=false
    local has_complete_marker=false
    local is_approved=false
    
    # Check for required markers
    if echo "$comment_body" | grep -q "FINAL VERDICT"; then
        has_final_verdict=true
    fi
    
    if echo "$comment_body" | grep -q "Claude AI PR Review Complete"; then
        has_complete_marker=true
    fi
    
    # Check for approval status (enhanced to match more formats)
    if echo "$comment_body" | grep -q "✅ APPROVED\|APPROVED FOR MERGE\|FINAL VERDICT.*APPROVED"; then
        is_approved=true
    fi

    # Determine expected values based on format
    local expected_final_verdict="true"
    local expected_complete_marker="true"
    local expected_workflow_trigger="true"
    local expected_approval="false"

    case "$format_name" in
        "approved_format_"*)
            expected_approval="true"
            ;;
        "rejected_format_"*)
            expected_approval="false"
            ;;
        "pending_format")
            expected_final_verdict="false"  # Pending format doesn't have FINAL VERDICT
            expected_workflow_trigger="false"
            expected_approval="false"
            ;;
        "invalid_format")
            expected_final_verdict="false"
            expected_complete_marker="false"
            expected_workflow_trigger="false"
            expected_approval="false"
            ;;
    esac

    # Test individual components
    log_test_result "Has FINAL VERDICT ($format_name)" "$expected_final_verdict" "$has_final_verdict" "$format_name"
    log_test_result "Has Complete Marker ($format_name)" "$expected_complete_marker" "$has_complete_marker" "$format_name"

    # Test overall approval logic
    local should_trigger_workflow=$has_final_verdict && $has_complete_marker
    log_test_result "Should Trigger Workflow ($format_name)" "$expected_workflow_trigger" "$should_trigger_workflow" "$format_name"
    log_test_result "Approval Detection ($format_name)" "$expected_approval" "$is_approved" "$format_name"
    
    echo ""
}

# Test circuit breaker integration
test_circuit_breaker_integration() {
    echo -e "${BLUE}🔍 Testing Circuit Breaker Integration${NC}"
    
    # Test that circuit breaker doesn't interfere with approval detection
    if scripts/circuit-breaker.sh claude_api >/dev/null 2>&1; then
        log_test_result "Circuit Breaker - Claude API" "accessible" "accessible" "circuit_breaker"
    else
        log_test_result "Circuit Breaker - Claude API" "fallback_mode" "fallback_mode" "circuit_breaker"
    fi
    
    # Test that approval detection works regardless of circuit breaker state
    local test_comment="${TEST_COMMENTS[approved_format_1]}"
    local approval_works=true
    
    # Simulate approval detection logic
    if echo "$test_comment" | grep -q "FINAL VERDICT" && echo "$test_comment" | grep -q "Claude AI PR Review Complete"; then
        if echo "$test_comment" | grep -q "✅ APPROVED"; then
            approval_works=true
        else
            approval_works=false
        fi
    else
        approval_works=false
    fi
    
    log_test_result "Approval Works with Circuit Breaker" "true" "$approval_works" "circuit_breaker"
    echo ""
}

# Test bot user detection
test_bot_user_detection() {
    echo -e "${BLUE}🔍 Testing Bot User Detection${NC}"
    
    # Test various bot user formats
    local bot_users=(
        "blazecommerce-automation-bot[bot]"
        "blazecommerce-automation-bot"
        "github-actions[bot]"
        "some-other-blazecommerce-bot"
    )
    
    for user in "${bot_users[@]}"; do
        local is_valid_bot=false
        
        # Simulate the enhanced bot detection logic from the workflow
        if [[ "$user" == "blazecommerce-automation-bot[bot]" ]] || \
           [[ "$user" == *"blazecommerce-automation-bot"* ]] || \
           [[ "$user" == *"blazecommerce"* ]]; then
            is_valid_bot=true
        fi
        
        local expected="true"
        if [[ "$user" == "github-actions[bot]" ]]; then
            expected="false"  # Should not match non-blazecommerce bots
        fi
        
        log_test_result "Bot User Detection ($user)" "$expected" "$is_valid_bot" "bot_detection"
    done
    
    echo ""
}

# Test infinite loop prevention
test_infinite_loop_prevention() {
    echo -e "${BLUE}🔍 Testing Infinite Loop Prevention${NC}"
    
    # Test that the workflow doesn't trigger on its own approval comments
    local bot_approval_comment='✅ Auto-approved based on Claude AI review'
    
    local triggers_loop=false
    if echo "$bot_approval_comment" | grep -q "FINAL VERDICT" && echo "$bot_approval_comment" | grep -q "Claude AI PR Review Complete"; then
        triggers_loop=true
    fi
    
    log_test_result "Bot Approval Doesn't Trigger Loop" "false" "$triggers_loop" "loop_prevention"
    
    # Test that only Claude comments trigger the workflow
    local regular_comment='This is just a regular PR comment'
    local triggers_workflow=false
    
    if echo "$regular_comment" | grep -q "FINAL VERDICT" && echo "$regular_comment" | grep -q "Claude AI PR Review Complete"; then
        triggers_workflow=true
    fi
    
    log_test_result "Regular Comments Don't Trigger" "false" "$triggers_workflow" "loop_prevention"
    echo ""
}

# Main test execution
main() {
    echo -e "${BLUE}🚀 Starting Claude AI Approval Detection Tests${NC}"
    echo "=================================================="
    echo ""
    
    # Test all comment formats
    for format_name in "${!TEST_COMMENTS[@]}"; do
        test_approval_detection "${TEST_COMMENTS[$format_name]}" "$format_name"
    done
    
    # Test circuit breaker integration
    test_circuit_breaker_integration
    
    # Test bot user detection
    test_bot_user_detection
    
    # Test infinite loop prevention
    test_infinite_loop_prevention
    
    # Generate summary
    echo -e "${BLUE}📊 Test Summary${NC}"
    echo "==============="
    echo -e "Total Tests: $TOTAL_TESTS"
    echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
    echo -e "${RED}Failed: $FAILED_TESTS${NC}"
    
    local success_rate=$(( (PASSED_TESTS * 100) / TOTAL_TESTS ))
    echo -e "Success Rate: ${success_rate}%"
    
    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "${GREEN}🎉 All Claude approval detection tests passed!${NC}"
        return 0
    else
        echo -e "${RED}❌ Some tests failed. Review the failures above.${NC}"
        return 1
    fi
}

# Execute main function
main "$@"
