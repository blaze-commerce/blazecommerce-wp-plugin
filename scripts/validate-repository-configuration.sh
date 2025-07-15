#!/bin/bash

# Repository Configuration Validation Script
# Validates that repository settings are properly configured for workflow fixes

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_OWNER="blaze-commerce"
REPO_NAME="blazecommerce-wp-plugin"

# Test counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0
WARNING_CHECKS=0

# Check result logging
log_check_result() {
    local check_name="$1"
    local status="$2"
    local message="$3"
    
    TOTAL_CHECKS=$((TOTAL_CHECKS + 1))
    
    case "$status" in
        "PASS")
            PASSED_CHECKS=$((PASSED_CHECKS + 1))
            echo -e "${GREEN}✅ PASS${NC}: $check_name - $message"
            ;;
        "FAIL")
            FAILED_CHECKS=$((FAILED_CHECKS + 1))
            echo -e "${RED}❌ FAIL${NC}: $check_name - $message"
            ;;
        "WARN")
            WARNING_CHECKS=$((WARNING_CHECKS + 1))
            echo -e "${YELLOW}⚠️  WARN${NC}: $check_name - $message"
            ;;
    esac
}

# Check GitHub CLI availability
check_github_cli() {
    echo -e "${BLUE}🔍 Checking GitHub CLI availability...${NC}"
    
    if command -v gh &> /dev/null; then
        if gh auth status &> /dev/null; then
            log_check_result "GitHub CLI" "PASS" "Available and authenticated"
        else
            log_check_result "GitHub CLI" "WARN" "Available but not authenticated"
        fi
    else
        log_check_result "GitHub CLI" "FAIL" "Not installed"
    fi
    echo ""
}

# Check workflow files
check_workflow_files() {
    echo -e "${BLUE}🔍 Checking workflow files...${NC}"
    
    local required_workflows=(
        ".github/workflows/tests.yml:Tests"
        ".github/workflows/claude-approval-gate.yml:Claude AI Approval Gate"
        ".github/workflows/auto-version.yml:Auto Version"
        ".github/workflows/release.yml:Create Release"
    )
    
    for workflow_info in "${required_workflows[@]}"; do
        local file=$(echo "$workflow_info" | cut -d: -f1)
        local name=$(echo "$workflow_info" | cut -d: -f2)
        
        if [ -f "$file" ]; then
            # Check YAML syntax
            if python3 -c "import yaml; yaml.safe_load(open('$file'))" 2>/dev/null; then
                # Check for required elements
                if grep -q "name: \"$name\"" "$file" && grep -q "on:" "$file"; then
                    log_check_result "Workflow: $name" "PASS" "File exists and valid"
                else
                    log_check_result "Workflow: $name" "WARN" "File exists but may have issues"
                fi
            else
                log_check_result "Workflow: $name" "FAIL" "Invalid YAML syntax"
            fi
        else
            log_check_result "Workflow: $name" "FAIL" "File missing"
        fi
    done
    echo ""
}

# Check repository secrets
check_repository_secrets() {
    echo -e "${BLUE}🔍 Checking repository secrets...${NC}"
    
    if command -v gh &> /dev/null && gh auth status &> /dev/null; then
        local required_secrets=(
            "BC_GITHUB_APP_ID"
            "BC_GITHUB_APP_PRIVATE_KEY"
        )
        
        for secret in "${required_secrets[@]}"; do
            if gh secret list --repo "$REPO_OWNER/$REPO_NAME" 2>/dev/null | grep -q "$secret"; then
                log_check_result "Secret: $secret" "PASS" "Configured"
            else
                log_check_result "Secret: $secret" "FAIL" "Missing"
            fi
        done
        
        # Check for GITHUB_TOKEN (should be auto-provided)
        log_check_result "Secret: GITHUB_TOKEN" "PASS" "Auto-provided by GitHub"
    else
        log_check_result "Repository Secrets" "WARN" "Cannot check - GitHub CLI not available"
    fi
    echo ""
}

# Check repository variables
check_repository_variables() {
    echo -e "${BLUE}🔍 Checking repository variables...${NC}"
    
    if command -v gh &> /dev/null && gh auth status &> /dev/null; then
        local recommended_variables=(
            "TEST_TIMEOUT"
            "CIRCUIT_BREAKER_TIMEOUT"
            "HEALTH_CHECK_RETRIES"
            "WORDPRESS_VERSION"
            "PHP_VERSION"
        )
        
        for variable in "${recommended_variables[@]}"; do
            if gh variable list --repo "$REPO_OWNER/$REPO_NAME" 2>/dev/null | grep -q "$variable"; then
                log_check_result "Variable: $variable" "PASS" "Configured"
            else
                log_check_result "Variable: $variable" "WARN" "Not configured (will use defaults)"
            fi
        done
    else
        log_check_result "Repository Variables" "WARN" "Cannot check - GitHub CLI not available"
    fi
    echo ""
}

# Check workflow permissions
check_workflow_permissions() {
    echo -e "${BLUE}🔍 Checking workflow permissions...${NC}"
    
    local workflows_to_check=(
        ".github/workflows/tests.yml"
        ".github/workflows/claude-approval-gate.yml"
        ".github/workflows/auto-version.yml"
        ".github/workflows/release.yml"
    )
    
    for workflow in "${workflows_to_check[@]}"; do
        if [ -f "$workflow" ]; then
            if grep -q "permissions:" "$workflow"; then
                log_check_result "Permissions: $(basename "$workflow")" "PASS" "Permissions specified"
            else
                log_check_result "Permissions: $(basename "$workflow")" "WARN" "No explicit permissions (using defaults)"
            fi
        fi
    done
    echo ""
}

# Check for security issues
check_security_issues() {
    echo -e "${BLUE}🔍 Checking for security issues...${NC}"
    
    # Check for hardcoded secrets
    local hardcoded_secrets=false
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            if grep -q "password.*:\|token.*:\|key.*:" "$workflow" | grep -v "secrets\." 2>/dev/null; then
                hardcoded_secrets=true
                break
            fi
        fi
    done
    
    if [ "$hardcoded_secrets" = false ]; then
        log_check_result "Hardcoded Secrets" "PASS" "No hardcoded secrets found"
    else
        log_check_result "Hardcoded Secrets" "FAIL" "Potential hardcoded secrets detected"
    fi
    
    # Check for proper secret usage
    local proper_secret_usage=true
    for workflow in .github/workflows/*.yml; do
        if [ -f "$workflow" ]; then
            if grep -q "secrets\." "$workflow"; then
                if ! grep -q "\${{ secrets\." "$workflow"; then
                    proper_secret_usage=false
                    break
                fi
            fi
        fi
    done
    
    if [ "$proper_secret_usage" = true ]; then
        log_check_result "Secret Usage" "PASS" "Proper secret referencing"
    else
        log_check_result "Secret Usage" "FAIL" "Improper secret usage detected"
    fi
    echo ""
}

# Check circuit breaker configuration
check_circuit_breaker_config() {
    echo -e "${BLUE}🔍 Checking circuit breaker configuration...${NC}"
    
    # Check if circuit breaker script exists
    if [ -f "scripts/circuit-breaker.sh" ]; then
        if [ -x "scripts/circuit-breaker.sh" ]; then
            log_check_result "Circuit Breaker Script" "PASS" "Exists and executable"
        else
            log_check_result "Circuit Breaker Script" "WARN" "Exists but not executable"
        fi
    else
        log_check_result "Circuit Breaker Script" "FAIL" "Missing"
    fi
    
    # Check if health check script exists
    if [ -f "scripts/health-check.sh" ]; then
        if [ -x "scripts/health-check.sh" ]; then
            log_check_result "Health Check Script" "PASS" "Exists and executable"
        else
            log_check_result "Health Check Script" "WARN" "Exists but not executable"
        fi
    else
        log_check_result "Health Check Script" "FAIL" "Missing"
    fi
    
    # Check if fallback setup script exists
    if [ -f "scripts/setup-local-fallbacks.sh" ]; then
        if [ -x "scripts/setup-local-fallbacks.sh" ]; then
            log_check_result "Fallback Setup Script" "PASS" "Exists and executable"
        else
            log_check_result "Fallback Setup Script" "WARN" "Exists but not executable"
        fi
    else
        log_check_result "Fallback Setup Script" "FAIL" "Missing"
    fi
    echo ""
}

# Check documentation
check_documentation() {
    echo -e "${BLUE}🔍 Checking documentation...${NC}"
    
    local required_docs=(
        "docs/github-repository-settings-configuration-guide.md"
        "docs/github-app-configuration-guide.md"
        "docs/repository-settings-analysis-and-recommendations.md"
    )
    
    for doc in "${required_docs[@]}"; do
        if [ -f "$doc" ]; then
            local word_count=$(wc -w < "$doc")
            if [ "$word_count" -gt 100 ]; then
                log_check_result "Documentation: $(basename "$doc")" "PASS" "$word_count words"
            else
                log_check_result "Documentation: $(basename "$doc")" "WARN" "May be incomplete ($word_count words)"
            fi
        else
            log_check_result "Documentation: $(basename "$doc")" "FAIL" "Missing"
        fi
    done
    echo ""
}

# Generate recommendations
generate_recommendations() {
    echo -e "${BLUE}📋 Configuration Recommendations${NC}"
    echo "=================================="
    
    if [ $FAILED_CHECKS -gt 0 ]; then
        echo -e "${RED}🚨 Critical Issues Found:${NC}"
        echo "  - $FAILED_CHECKS critical configuration issues detected"
        echo "  - These must be resolved before deployment"
        echo ""
    fi
    
    if [ $WARNING_CHECKS -gt 0 ]; then
        echo -e "${YELLOW}⚠️  Warnings Found:${NC}"
        echo "  - $WARNING_CHECKS configuration warnings detected"
        echo "  - These should be addressed for optimal functionality"
        echo ""
    fi
    
    echo "🔧 Next Steps:"
    if [ $FAILED_CHECKS -gt 0 ]; then
        echo "  1. Address critical issues above"
        echo "  2. Run configuration script: scripts/configure-repository-settings.sh"
        echo "  3. Follow GitHub App setup guide: docs/github-app-configuration-guide.md"
        echo "  4. Re-run this validation script"
    else
        echo "  1. Address any warnings above"
        echo "  2. Test workflow functionality"
        echo "  3. Monitor performance after deployment"
    fi
    echo ""
    
    echo "📚 Documentation:"
    echo "  - Configuration Guide: docs/github-repository-settings-configuration-guide.md"
    echo "  - GitHub App Setup: docs/github-app-configuration-guide.md"
    echo "  - Analysis Report: docs/repository-settings-analysis-and-recommendations.md"
}

# Main execution
main() {
    echo -e "${BLUE}🚀 Repository Configuration Validation${NC}"
    echo "======================================="
    echo ""
    
    # Run all checks
    check_github_cli
    check_workflow_files
    check_repository_secrets
    check_repository_variables
    check_workflow_permissions
    check_security_issues
    check_circuit_breaker_config
    check_documentation
    
    # Generate summary
    echo -e "${BLUE}📊 Validation Summary${NC}"
    echo "====================="
    echo -e "Total Checks: $TOTAL_CHECKS"
    echo -e "${GREEN}Passed: $PASSED_CHECKS${NC}"
    echo -e "${YELLOW}Warnings: $WARNING_CHECKS${NC}"
    echo -e "${RED}Failed: $FAILED_CHECKS${NC}"
    
    local success_rate=$(( (PASSED_CHECKS * 100) / TOTAL_CHECKS ))
    echo -e "Success Rate: ${success_rate}%"
    echo ""
    
    # Generate recommendations
    generate_recommendations
    
    # Return appropriate exit code
    if [ $FAILED_CHECKS -eq 0 ]; then
        if [ $WARNING_CHECKS -eq 0 ]; then
            echo -e "${GREEN}🎉 All configuration checks passed!${NC}"
            return 0
        else
            echo -e "${YELLOW}⚠️  Configuration mostly ready with minor warnings${NC}"
            return 0
        fi
    else
        echo -e "${RED}❌ Critical configuration issues found${NC}"
        return 1
    fi
}

# Execute main function
main "$@"
