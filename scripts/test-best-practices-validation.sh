#!/bin/bash

# GitHub Actions Best Practices and Stability Validation
# Validates that workflows follow GitHub Actions best practices

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

# Test 1: Timeout Settings
test_timeout_settings() {
    echo -e "${BLUE}🔍 Testing Timeout Settings${NC}"
    
    local workflows_with_timeouts=0
    local total_workflows=0
    
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            total_workflows=$((total_workflows + 1))
            
            if grep -q "timeout-minutes:" "$workflow"; then
                workflows_with_timeouts=$((workflows_with_timeouts + 1))
                
                # Check for reasonable timeout values
                local max_timeout=$(grep "timeout-minutes:" "$workflow" | sed 's/.*timeout-minutes: *\([0-9]*\).*/\1/' | sort -n | tail -1)
                if [ "$max_timeout" -gt 60 ]; then
                    echo -e "${YELLOW}Warning: $(basename "$workflow") has timeout > 60 minutes: $max_timeout${NC}"
                fi
            fi
        fi
    done
    
    local timeout_coverage=$(( (workflows_with_timeouts * 100) / total_workflows ))
    local has_good_coverage=$( [ "$timeout_coverage" -ge 80 ] && echo "true" || echo "false" )
    
    log_test_result "Timeout Coverage" "true" "$has_good_coverage" "$timeout_coverage% of workflows have timeouts"
    echo ""
}

# Test 2: Resource Limits and Permissions
test_resource_limits() {
    echo -e "${BLUE}🔍 Testing Resource Limits and Permissions${NC}"
    
    local workflows_with_permissions=0
    local workflows_with_minimal_permissions=0
    local total_workflows=0
    
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            total_workflows=$((total_workflows + 1))
            
            if grep -q "permissions:" "$workflow"; then
                workflows_with_permissions=$((workflows_with_permissions + 1))
                
                # Check for minimal permissions (not using 'write-all' or overly broad permissions)
                if ! grep -q "write-all\|contents: write.*pull-requests: write.*issues: write" "$workflow"; then
                    workflows_with_minimal_permissions=$((workflows_with_minimal_permissions + 1))
                fi
            fi
        fi
    done
    
    local permission_coverage=$(( (workflows_with_permissions * 100) / total_workflows ))
    local minimal_permission_rate=$(( (workflows_with_minimal_permissions * 100) / workflows_with_permissions ))
    
    local has_good_permissions=$( [ "$permission_coverage" -ge 70 ] && echo "true" || echo "false" )
    local has_minimal_permissions=$( [ "$minimal_permission_rate" -ge 80 ] && echo "true" || echo "false" )
    
    log_test_result "Permission Coverage" "true" "$has_good_permissions" "$permission_coverage% of workflows specify permissions"
    log_test_result "Minimal Permissions" "true" "$has_minimal_permissions" "$minimal_permission_rate% use minimal permissions"
    echo ""
}

# Test 3: Error Handling Standards
test_error_handling_standards() {
    echo -e "${BLUE}🔍 Testing Error Handling Standards${NC}"
    
    local workflows_with_error_handling=0
    local total_workflows=0
    
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            total_workflows=$((total_workflows + 1))
            
            # Check for error handling patterns
            if grep -q "if:.*failure\|if:.*always\|continue-on-error\||| echo\||| true" "$workflow"; then
                workflows_with_error_handling=$((workflows_with_error_handling + 1))
            fi
        fi
    done
    
    local error_handling_coverage=$(( (workflows_with_error_handling * 100) / total_workflows ))
    local has_good_error_handling=$( [ "$error_handling_coverage" -ge 60 ] && echo "true" || echo "false" )
    
    log_test_result "Error Handling Coverage" "true" "$has_good_error_handling" "$error_handling_coverage% of workflows have error handling"
    
    # Check for standardized error handling script usage
    local uses_error_handler=false
    for workflow in .github/workflows/*.yml; do
        if grep -q "error-handler.sh\|scripts/error-handler" "$workflow" 2>/dev/null; then
            uses_error_handler=true
            break
        fi
    done
    
    log_test_result "Standardized Error Handler" "true" "$uses_error_handler" "Uses centralized error handling"
    echo ""
}

# Test 4: Complexity Reduction Validation
test_complexity_reduction() {
    echo -e "${BLUE}🔍 Testing Complexity Reduction${NC}"
    
    # Measure current workflow complexity
    local total_lines=0
    local workflow_count=0
    local complex_workflows=0
    
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            workflow_count=$((workflow_count + 1))
            local lines=$(wc -l < "$workflow")
            total_lines=$((total_lines + lines))
            
            if [ "$lines" -gt 300 ]; then
                complex_workflows=$((complex_workflows + 1))
                echo -e "${YELLOW}Complex workflow: $(basename "$workflow") - $lines lines${NC}"
            fi
        fi
    done
    
    local avg_lines=$(( total_lines / workflow_count ))
    local complexity_acceptable=$( [ "$avg_lines" -lt 250 ] && echo "true" || echo "false" )
    local few_complex_workflows=$( [ "$complex_workflows" -lt 2 ] && echo "true" || echo "false" )
    
    log_test_result "Average Complexity" "true" "$complexity_acceptable" "Average $avg_lines lines per workflow"
    log_test_result "Few Complex Workflows" "true" "$few_complex_workflows" "$complex_workflows workflows > 300 lines"
    echo ""
}

# Test 5: External Dependency Management
test_external_dependency_management() {
    echo -e "${BLUE}🔍 Testing External Dependency Management${NC}"
    
    # Check for circuit breaker usage
    local uses_circuit_breakers=false
    local has_fallback_mechanisms=false
    
    for workflow in .github/workflows/*.yml; do
        if grep -q "circuit-breaker\|health-check" "$workflow" 2>/dev/null; then
            uses_circuit_breakers=true
        fi
        
        if grep -q "fallback\||| echo\||| true\|continue-on-error" "$workflow" 2>/dev/null; then
            has_fallback_mechanisms=true
        fi
    done
    
    log_test_result "Circuit Breaker Usage" "true" "$uses_circuit_breakers" "Workflows use circuit breakers"
    log_test_result "Fallback Mechanisms" "true" "$has_fallback_mechanisms" "Fallback mechanisms present"
    
    # Check for external service dependencies
    local external_deps=0
    local protected_deps=0
    
    for workflow in .github/workflows/*.yml; do
        if grep -q "curl\|wget\|api\|svn\|mysql" "$workflow" 2>/dev/null; then
            external_deps=$((external_deps + 1))
            
            if grep -q "timeout\|circuit-breaker\|fallback" "$workflow" 2>/dev/null; then
                protected_deps=$((protected_deps + 1))
            fi
        fi
    done
    
    local protection_rate=100
    if [ "$external_deps" -gt 0 ]; then
        protection_rate=$(( (protected_deps * 100) / external_deps ))
    fi
    
    local well_protected=$( [ "$protection_rate" -ge 80 ] && echo "true" || echo "false" )
    log_test_result "External Dependency Protection" "true" "$well_protected" "$protection_rate% of external deps are protected"
    echo ""
}

# Test 6: Performance and Caching
test_performance_optimization() {
    echo -e "${BLUE}🔍 Testing Performance Optimization${NC}"
    
    # Check for caching usage
    local uses_caching=false
    local uses_performance_optimization=false
    
    for workflow in .github/workflows/*.yml; do
        if grep -q "cache\|Cache\|performance-optimizer" "$workflow" 2>/dev/null; then
            uses_caching=true
        fi
        
        if grep -q "performance\|optimize\|parallel" "$workflow" 2>/dev/null; then
            uses_performance_optimization=true
        fi
    done
    
    log_test_result "Caching Usage" "true" "$uses_caching" "Workflows use caching mechanisms"
    log_test_result "Performance Optimization" "true" "$uses_performance_optimization" "Performance optimizations present"
    
    # Check for reasonable job parallelization
    local parallel_jobs=0
    local total_jobs=0
    
    for workflow in .github/workflows/*.yml; do
        local jobs_in_workflow=$(grep -c "^  [a-zA-Z].*:$" "$workflow" 2>/dev/null || echo "0")
        total_jobs=$((total_jobs + jobs_in_workflow))
        
        if [ "$jobs_in_workflow" -gt 1 ]; then
            parallel_jobs=$((parallel_jobs + 1))
        fi
    done
    
    local parallelization_rate=$(( (parallel_jobs * 100) / $(ls .github/workflows/*.yml | wc -l) ))
    local good_parallelization=$( [ "$parallelization_rate" -ge 30 ] && echo "true" || echo "false" )
    
    log_test_result "Job Parallelization" "true" "$good_parallelization" "$parallelization_rate% of workflows use parallel jobs"
    echo ""
}

# Test 7: Security Best Practices
test_security_practices() {
    echo -e "${BLUE}🔍 Testing Security Best Practices${NC}"
    
    # Check for secure secret usage
    local secure_secret_usage=true
    local hardcoded_secrets=false
    
    for workflow in .github/workflows/*.yml; do
        # Check for hardcoded secrets (basic patterns)
        if grep -q "password.*:\|token.*:\|key.*:" "$workflow" | grep -v "secrets\." 2>/dev/null; then
            hardcoded_secrets=true
        fi
        
        # Check for proper secret usage
        if grep -q "secrets\." "$workflow" 2>/dev/null; then
            if ! grep -q "\${{ secrets\." "$workflow" 2>/dev/null; then
                secure_secret_usage=false
            fi
        fi
    done
    
    log_test_result "No Hardcoded Secrets" "false" "$hardcoded_secrets" "No hardcoded credentials found"
    log_test_result "Secure Secret Usage" "true" "$secure_secret_usage" "Secrets properly referenced"
    
    # Check for input validation
    local has_input_validation=false
    for workflow in .github/workflows/*.yml; do
        if grep -q "required: true\|type: string\|type: number" "$workflow" 2>/dev/null; then
            has_input_validation=true
            break
        fi
    done
    
    log_test_result "Input Validation" "true" "$has_input_validation" "Workflow inputs are validated"
    echo ""
}

# Main test execution
main() {
    echo -e "${BLUE}🚀 Starting Best Practices and Stability Validation${NC}"
    echo "===================================================="
    echo ""
    
    # Run all tests
    test_timeout_settings
    test_resource_limits
    test_error_handling_standards
    test_complexity_reduction
    test_external_dependency_management
    test_performance_optimization
    test_security_practices
    
    # Generate summary
    echo -e "${BLUE}📊 Validation Summary${NC}"
    echo "====================="
    echo -e "Total Tests: $TOTAL_TESTS"
    echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
    echo -e "${RED}Failed: $FAILED_TESTS${NC}"
    
    local success_rate=$(( (PASSED_TESTS * 100) / TOTAL_TESTS ))
    echo -e "Success Rate: ${success_rate}%"
    
    if [ $FAILED_TESTS -eq 0 ]; then
        echo -e "${GREEN}🎉 All best practices validation tests passed!${NC}"
        echo -e "${GREEN}✅ Workflows follow GitHub Actions best practices${NC}"
        return 0
    elif [ "$success_rate" -ge 85 ]; then
        echo -e "${YELLOW}⚠️  Most tests passed with minor issues (${success_rate}% success rate)${NC}"
        return 0
    else
        echo -e "${RED}❌ Significant issues found. Review the failures above.${NC}"
        return 1
    fi
}

# Execute main function
main "$@"
