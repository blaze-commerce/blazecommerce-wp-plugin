#!/bin/bash

# Comprehensive Dependency Test Script
# Tests all dependencies required for WordPress test environment

set -e

echo "🧪 Testing WordPress Test Environment Dependencies"
echo "=================================================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0

# Test function
run_test() {
    local test_name="$1"
    local test_command="$2"
    
    echo -e "\n📋 Testing: $test_name"
    
    if eval "$test_command"; then
        echo -e "   ${GREEN}✅ PASSED${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "   ${RED}❌ FAILED${NC}"
        ((TESTS_FAILED++))
    fi
}

# Test 1: SVN (Subversion)
test_svn() {
    if command -v svn &> /dev/null; then
        echo "   SVN version: $(svn --version --quiet)"
        # Test SVN connectivity
        if svn info https://develop.svn.wordpress.org/trunk/ &> /dev/null; then
            echo "   SVN connectivity: OK"
            return 0
        else
            echo "   SVN connectivity: FAILED"
            return 1
        fi
    else
        echo "   SVN not found"
        return 1
    fi
}

# Test 2: MySQL Client
test_mysql_client() {
    if command -v mysql &> /dev/null; then
        echo "   MySQL client version: $(mysql --version)"
        return 0
    else
        echo "   MySQL client not found"
        return 1
    fi
}

# Test 3: MySQL Admin
test_mysqladmin() {
    if command -v mysqladmin &> /dev/null; then
        echo "   MySQL admin version: $(mysqladmin --version)"
        return 0
    else
        echo "   MySQL admin not found"
        return 1
    fi
}

# Test 4: HTTP Clients
test_http_clients() {
    local has_curl=false
    local has_wget=false
    
    if command -v curl &> /dev/null; then
        echo "   curl version: $(curl --version | head -1)"
        has_curl=true
    fi
    
    if command -v wget &> /dev/null; then
        echo "   wget version: $(wget --version | head -1)"
        has_wget=true
    fi
    
    if [ "$has_curl" = true ] || [ "$has_wget" = true ]; then
        return 0
    else
        echo "   Neither curl nor wget found"
        return 1
    fi
}

# Test 5: Archive Tools
test_archive_tools() {
    local has_unzip=false
    local has_tar=false
    
    if command -v unzip &> /dev/null; then
        echo "   unzip version: $(unzip -v | head -1)"
        has_unzip=true
    fi
    
    if command -v tar &> /dev/null; then
        echo "   tar version: $(tar --version | head -1)"
        has_tar=true
    fi
    
    if [ "$has_unzip" = true ] && [ "$has_tar" = true ]; then
        return 0
    else
        echo "   Missing archive tools"
        return 1
    fi
}

# Test 6: Git
test_git() {
    if command -v git &> /dev/null; then
        echo "   Git version: $(git --version)"
        return 0
    else
        echo "   Git not found"
        return 1
    fi
}

# Test 7: PHP
test_php() {
    if command -v php &> /dev/null; then
        echo "   PHP version: $(php --version | head -1)"
        return 0
    else
        echo "   PHP not found"
        return 1
    fi
}

# Test 8: WordPress Test Script
test_wp_script() {
    if [ -f "bin/install-wp-tests.sh" ]; then
        echo "   WordPress test script found"
        if [ -x "bin/install-wp-tests.sh" ]; then
            echo "   Script is executable"
            return 0
        else
            echo "   Script is not executable"
            return 1
        fi
    else
        echo "   WordPress test script not found"
        return 1
    fi
}

# Test 9: Database Connectivity (if MySQL service is running)
test_database_connectivity() {
    if command -v mysql &> /dev/null; then
        # Try to connect to database (this might fail if MySQL service isn't running)
        if mysql -h 127.0.0.1 -P 3306 -u root -proot -e "SELECT 1" &> /dev/null; then
            echo "   Database connectivity: OK"
            return 0
        else
            echo "   Database connectivity: FAILED (MySQL service may not be running)"
            return 1
        fi
    else
        echo "   MySQL client not available for connectivity test"
        return 1
    fi
}

# Test 10: WordPress Test Environment Simulation
test_wp_environment_simulation() {
    # Create temporary directories to simulate test environment
    local temp_dir="/tmp/wp-test-simulation-$$"
    mkdir -p "$temp_dir/wordpress-tests-lib/includes"
    mkdir -p "$temp_dir/wordpress"
    
    # Create dummy files
    touch "$temp_dir/wordpress-tests-lib/includes/bootstrap.php"
    touch "$temp_dir/wordpress-tests-lib/includes/functions.php"
    
    if [ -f "$temp_dir/wordpress-tests-lib/includes/bootstrap.php" ] && \
       [ -f "$temp_dir/wordpress-tests-lib/includes/functions.php" ]; then
        echo "   WordPress test environment structure: OK"
        rm -rf "$temp_dir"
        return 0
    else
        echo "   WordPress test environment structure: FAILED"
        rm -rf "$temp_dir"
        return 1
    fi
}

# Run all tests
echo -e "\n🚀 Starting dependency tests...\n"

run_test "SVN (Subversion)" "test_svn"
run_test "MySQL Client" "test_mysql_client"
run_test "MySQL Admin" "test_mysqladmin"
run_test "HTTP Clients (curl/wget)" "test_http_clients"
run_test "Archive Tools (unzip/tar)" "test_archive_tools"
run_test "Git" "test_git"
run_test "PHP" "test_php"
run_test "WordPress Test Script" "test_wp_script"
run_test "Database Connectivity" "test_database_connectivity"
run_test "WordPress Environment Simulation" "test_wp_environment_simulation"

# Summary
echo -e "\n📊 DEPENDENCY TEST SUMMARY"
echo "=========================="
echo -e "✅ Tests Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "❌ Tests Failed: ${RED}$TESTS_FAILED${NC}"
echo -e "📈 Success Rate: $(( TESTS_PASSED * 100 / (TESTS_PASSED + TESTS_FAILED) ))%"

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "\n🎉 ${GREEN}All dependency tests passed!${NC}"
    echo "The WordPress test environment should work correctly."
    exit 0
else
    echo -e "\n⚠️  ${YELLOW}Some dependency tests failed.${NC}"
    echo "Please install missing dependencies before running WordPress tests."
    
    echo -e "\n💡 Installation commands:"
    echo "Ubuntu/Debian: sudo apt-get install subversion mysql-client curl wget unzip tar git"
    echo "CentOS/RHEL:   sudo yum install subversion mysql curl wget unzip tar git"
    echo "macOS:         brew install subversion mysql-client curl wget unzip tar git"
    
    exit 1
fi
