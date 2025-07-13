#!/bin/bash

# WordPress Test Environment Setup Verification Script
# This script tests the enhanced WordPress test environment setup

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local status=$1
    local message=$2
    case $status in
        "SUCCESS")
            echo -e "${GREEN}✅ SUCCESS:${NC} $message"
            ;;
        "ERROR")
            echo -e "${RED}❌ ERROR:${NC} $message"
            ;;
        "WARNING")
            echo -e "${YELLOW}⚠️  WARNING:${NC} $message"
            ;;
        "INFO")
            echo -e "${BLUE}ℹ️  INFO:${NC} $message"
            ;;
    esac
}

print_header() {
    echo ""
    echo -e "${BLUE}===========================================${NC}"
    echo -e "${BLUE} $1${NC}"
    echo -e "${BLUE}===========================================${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Test dependency checking
test_dependencies() {
    print_header "Testing System Dependencies"
    
    local deps=("svn" "curl" "wget" "unzip" "tar" "mysql" "mysqladmin" "sed" "grep")
    local missing_deps=()
    
    for dep in "${deps[@]}"; do
        if command_exists "$dep"; then
            print_status "SUCCESS" "$dep is available"
        else
            print_status "ERROR" "$dep is missing"
            missing_deps+=("$dep")
        fi
    done
    
    if [ ${#missing_deps[@]} -eq 0 ]; then
        print_status "SUCCESS" "All system dependencies are available"
        return 0
    else
        print_status "ERROR" "Missing dependencies: ${missing_deps[*]}"
        return 1
    fi
}

# Test SVN connectivity
test_svn_connectivity() {
    print_header "Testing SVN Connectivity"
    
    if ! command_exists svn; then
        print_status "ERROR" "SVN not available, skipping connectivity test"
        return 1
    fi
    
    print_status "INFO" "Testing connection to WordPress SVN repository..."
    
    if svn info https://develop.svn.wordpress.org/trunk/ >/dev/null 2>&1; then
        print_status "SUCCESS" "SVN connectivity to WordPress repository verified"
        return 0
    else
        print_status "ERROR" "Cannot connect to WordPress SVN repository"
        return 1
    fi
}

# Test script syntax and permissions
test_script_validation() {
    print_header "Testing Script Validation"
    
    local script_path="bin/install-wp-tests.sh"
    
    # Check if script exists
    if [ ! -f "$script_path" ]; then
        print_status "ERROR" "WordPress test installation script not found: $script_path"
        return 1
    fi
    print_status "SUCCESS" "WordPress test installation script found"
    
    # Check if script is executable
    if [ -x "$script_path" ]; then
        print_status "SUCCESS" "Script is executable"
    else
        print_status "WARNING" "Script is not executable, making it executable..."
        chmod +x "$script_path"
        if [ -x "$script_path" ]; then
            print_status "SUCCESS" "Script made executable"
        else
            print_status "ERROR" "Failed to make script executable"
            return 1
        fi
    fi
    
    # Test script syntax (basic bash syntax check)
    if bash -n "$script_path" 2>/dev/null; then
        print_status "SUCCESS" "Script syntax is valid"
    else
        print_status "ERROR" "Script has syntax errors"
        return 1
    fi
    
    return 0
}

# Test workflow file syntax
test_workflow_syntax() {
    print_header "Testing Workflow File Syntax"
    
    local workflow_path=".github/workflows/tests.yml"
    
    # Check if workflow file exists
    if [ ! -f "$workflow_path" ]; then
        print_status "ERROR" "Workflow file not found: $workflow_path"
        return 1
    fi
    print_status "SUCCESS" "Workflow file found"
    
    # Test YAML syntax if yamllint is available
    if command_exists yamllint; then
        if yamllint "$workflow_path" >/dev/null 2>&1; then
            print_status "SUCCESS" "Workflow YAML syntax is valid"
        else
            print_status "WARNING" "Workflow YAML has syntax issues (check with yamllint)"
        fi
    else
        print_status "INFO" "yamllint not available, skipping YAML syntax validation"
    fi
    
    return 0
}

# Test MySQL connectivity (if available)
test_mysql_connectivity() {
    print_header "Testing MySQL Connectivity"
    
    if ! command_exists mysql; then
        print_status "WARNING" "MySQL client not available, skipping connectivity test"
        return 0
    fi
    
    # Try to connect to local MySQL (common development setup)
    local mysql_hosts=("localhost" "127.0.0.1")
    local mysql_ports=("3306")
    local connected=false
    
    for host in "${mysql_hosts[@]}"; do
        for port in "${mysql_ports[@]}"; do
            print_status "INFO" "Testing MySQL connection to $host:$port..."
            
            # Try common credentials
            local credentials=("root:" "root:root" "root:password")
            
            for cred in "${credentials[@]}"; do
                local user="${cred%:*}"
                local pass="${cred#*:}"
                
                if [ -z "$pass" ]; then
                    if mysql -h "$host" -P "$port" -u "$user" -e "SELECT 1" >/dev/null 2>&1; then
                        print_status "SUCCESS" "MySQL connection successful ($host:$port, user: $user, no password)"
                        connected=true
                        break 2
                    fi
                else
                    if mysql -h "$host" -P "$port" -u "$user" -p"$pass" -e "SELECT 1" >/dev/null 2>&1; then
                        print_status "SUCCESS" "MySQL connection successful ($host:$port, user: $user)"
                        connected=true
                        break 2
                    fi
                fi
            done
        done
    done
    
    if [ "$connected" = false ]; then
        print_status "WARNING" "No MySQL connection available (this is normal if MySQL is not running locally)"
    fi
    
    return 0
}

# Test enhanced script functionality
test_enhanced_script() {
    print_header "Testing Enhanced Script Functionality"
    
    local script_path="bin/install-wp-tests.sh"
    
    # Test dependency checking function
    print_status "INFO" "Testing dependency checking function..."
    
    # Create a temporary script to test just the dependency checking
    local temp_script=$(mktemp)
    
    # Extract the dependency checking function from the main script
    sed -n '/^command_exists()/,/^check_dependencies$/p' "$script_path" > "$temp_script"
    echo "check_dependencies" >> "$temp_script"
    
    if bash "$temp_script" >/dev/null 2>&1; then
        print_status "SUCCESS" "Dependency checking function works correctly"
    else
        print_status "WARNING" "Dependency checking function may have issues"
    fi
    
    rm -f "$temp_script"
    
    return 0
}

# Main test execution
main() {
    print_header "WordPress Test Environment Setup Verification"
    print_status "INFO" "Starting comprehensive test suite..."
    
    local test_results=()
    
    # Run all tests
    if test_dependencies; then
        test_results+=("dependencies:PASS")
    else
        test_results+=("dependencies:FAIL")
    fi
    
    if test_svn_connectivity; then
        test_results+=("svn_connectivity:PASS")
    else
        test_results+=("svn_connectivity:FAIL")
    fi
    
    if test_script_validation; then
        test_results+=("script_validation:PASS")
    else
        test_results+=("script_validation:FAIL")
    fi
    
    if test_workflow_syntax; then
        test_results+=("workflow_syntax:PASS")
    else
        test_results+=("workflow_syntax:FAIL")
    fi
    
    if test_mysql_connectivity; then
        test_results+=("mysql_connectivity:PASS")
    else
        test_results+=("mysql_connectivity:FAIL")
    fi
    
    if test_enhanced_script; then
        test_results+=("enhanced_script:PASS")
    else
        test_results+=("enhanced_script:FAIL")
    fi
    
    # Print summary
    print_header "Test Results Summary"
    
    local passed=0
    local failed=0
    
    for result in "${test_results[@]}"; do
        local test_name="${result%:*}"
        local test_status="${result#*:}"
        
        if [ "$test_status" = "PASS" ]; then
            print_status "SUCCESS" "$test_name"
            ((passed++))
        else
            print_status "ERROR" "$test_name"
            ((failed++))
        fi
    done
    
    echo ""
    print_status "INFO" "Tests completed: $passed passed, $failed failed"
    
    if [ $failed -eq 0 ]; then
        print_status "SUCCESS" "All tests passed! WordPress test environment setup is ready."
        return 0
    else
        print_status "WARNING" "Some tests failed. Please review the issues above."
        return 1
    fi
}

# Run the main function
main "$@"
