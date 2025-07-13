#!/bin/bash

# Test script to validate all workflow fixes are working correctly
# This script should be run before committing the workflow fixes

set -euo pipefail

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

print_header() {
    echo ""
    print_status $BLUE "🔍 $1"
    echo "=================================="
}

print_success() {
    print_status $GREEN "✅ $1"
}

print_warning() {
    print_status $YELLOW "⚠️  $1"
}

print_error() {
    print_status $RED "❌ $1"
}

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

run_test() {
    local test_name=$1
    local test_command=$2
    
    echo -n "Testing $test_name... "
    
    if eval "$test_command" >/dev/null 2>&1; then
        print_success "PASSED"
        ((TESTS_PASSED++))
        return 0
    else
        print_error "FAILED"
        ((TESTS_FAILED++))
        return 1
    fi
}

print_header "Workflow Fixes Validation"

# Test 1: Validate package.json structure
print_header "Testing package.json validation"
run_test "package.json exists" "[ -f package.json ]"
run_test "package.json is valid JSON" "node -e 'JSON.parse(require(\"fs\").readFileSync(\"package.json\", \"utf8\"))'"
run_test "package.json has version field" "node -e 'const pkg = require(\"./package.json\"); if (!pkg.version) throw new Error(\"No version\")'"
run_test "version format is valid" "node -e 'const pkg = require(\"./package.json\"); if (!/^\d+\.\d+\.\d+/.test(pkg.version)) throw new Error(\"Invalid version\")'"

# Test 2: Validate required npm scripts
print_header "Testing npm scripts"
run_test "version:patch script exists" "npm run | grep -q 'version:patch'"
run_test "version:minor script exists" "npm run | grep -q 'version:minor'"
run_test "version:major script exists" "npm run | grep -q 'version:major'"
run_test "update-plugin-version script exists" "npm run | grep -q 'update-plugin-version'"
run_test "changelog script exists" "npm run | grep -q 'changelog'"

# Test 3: Validate composer.json
print_header "Testing composer.json validation"
run_test "composer.json exists" "[ -f composer.json ]"
run_test "composer.json is valid" "composer validate --no-check-publish --no-check-all"

# Test 4: Validate required scripts
print_header "Testing required scripts"
run_test "semver-utils.js exists" "[ -f scripts/semver-utils.js ]"
run_test "update-version.js exists" "[ -f scripts/update-version.js ]"
run_test "validate-version.js exists" "[ -f scripts/validate-version.js ]"
run_test "check-file-changes.sh exists" "[ -f scripts/check-file-changes.sh ]"
run_test "get-ignore-patterns.sh exists" "[ -f scripts/get-ignore-patterns.sh ]"
run_test "validate-workflow-environment.js exists" "[ -f scripts/validate-workflow-environment.js ]"

# Test 5: Validate workflow files
print_header "Testing workflow files"
run_test "tests.yml exists" "[ -f .github/workflows/tests.yml ]"
run_test "auto-version.yml exists" "[ -f .github/workflows/auto-version.yml ]"
run_test "claude-pr-review.yml exists" "[ -f .github/workflows/claude-pr-review.yml ]"
run_test "claude-approval-gate.yml exists" "[ -f .github/workflows/claude-approval-gate.yml ]"

# Test 6: Test script functionality
print_header "Testing script functionality"
run_test "environment validation script" "node scripts/validate-workflow-environment.js"
run_test "semver-utils can be loaded" "node -e 'require(\"./scripts/semver-utils.js\")'"
run_test "ignore patterns script works" "bash scripts/get-ignore-patterns.sh | wc -l | grep -q '[0-9]'"

# Test 7: Test version bump logic
print_header "Testing version bump logic"
run_test "version increment (patch)" "node -e 'const semver = require(\"./scripts/semver-utils.js\"); const result = semver.incrementVersion(\"1.0.0\", \"patch\"); if (result !== \"1.0.1\") throw new Error(\"Expected 1.0.1, got \" + result)'"
run_test "version increment (minor)" "node -e 'const semver = require(\"./scripts/semver-utils.js\"); const result = semver.incrementVersion(\"1.0.0\", \"minor\"); if (result !== \"1.1.0\") throw new Error(\"Expected 1.1.0, got \" + result)'"
run_test "version increment (major)" "node -e 'const semver = require(\"./scripts/semver-utils.js\"); const result = semver.incrementVersion(\"1.0.0\", \"major\"); if (result !== \"2.0.0\") throw new Error(\"Expected 2.0.0, got \" + result)'"
run_test "version parsing validation" "node -e 'const semver = require(\"./scripts/semver-utils.js\"); const result = semver.parseVersion(\"1.2.3-alpha.1\"); if (!result || result.major !== 1) throw new Error(\"Version parsing failed\")'"
run_test "invalid version handling" "node -e 'const semver = require(\"./scripts/semver-utils.js\"); try { semver.incrementVersion(\"invalid\", \"patch\"); throw new Error(\"Should have thrown\"); } catch(e) { if (!e.message.includes(\"Invalid version format\")) throw e; }'"

# Test 8: Test file change detection
print_header "Testing file change detection"
run_test "file change script is executable" "[ -x scripts/check-file-changes.sh ]"
run_test "ignore patterns script works" "bash scripts/get-ignore-patterns.sh | grep -q 'vendor/'"
run_test "file change script works with PHP file" "echo 'app/test.php' | bash scripts/check-file-changes.sh /dev/stdin"
run_test "file change script ignores vendor files" "! echo 'vendor/test.php' | bash scripts/check-file-changes.sh /dev/stdin"

# Test 9: Test WordPress test environment
print_header "Testing WordPress test environment"
run_test "phpunit.xml exists" "[ -f phpunit.xml ]"
run_test "WordPress test install script exists" "[ -f bin/install-wp-tests.sh ]"
run_test "test bootstrap exists" "[ -f tests/bootstrap.php ]"

# Test 10: Validate YAML syntax
print_header "Testing YAML syntax"
if command -v yamllint >/dev/null 2>&1; then
    run_test "tests.yml YAML syntax" "yamllint .github/workflows/tests.yml"
    run_test "auto-version.yml YAML syntax" "yamllint .github/workflows/auto-version.yml"
else
    print_warning "yamllint not available, skipping YAML syntax validation"
fi

# Test 11: Test workflow environment validation
print_header "Testing comprehensive workflow validation"
run_test "workflow environment validation" "node scripts/validate-workflow-environment.js"
run_test "npm scripts can be listed" "npm run | grep -q 'available via'"
run_test "composer dependencies can be validated" "composer validate --no-check-publish --no-check-all"

# Test 12: Test error handling scenarios
print_header "Testing error handling scenarios"
run_test "semver handles empty input" "node -e 'const semver = require(\"./scripts/semver-utils.js\"); try { semver.incrementVersion(\"\", \"patch\"); throw new Error(\"Should fail\"); } catch(e) { if (!e.message.includes(\"non-empty string\")) throw e; }'"
run_test "semver handles invalid bump type" "node -e 'const semver = require(\"./scripts/semver-utils.js\"); try { semver.incrementVersion(\"1.0.0\", \"invalid\"); throw new Error(\"Should fail\"); } catch(e) { if (!e.message.includes(\"Invalid increment type\")) throw e; }'"
run_test "file change script handles empty input" "echo '' | bash scripts/check-file-changes.sh /dev/stdin; [ $? -eq 1 ]"

# Summary
print_header "Test Results Summary"
echo ""
print_status $BLUE "📊 Test Results:"
print_success "Tests Passed: $TESTS_PASSED"
if [ $TESTS_FAILED -gt 0 ]; then
    print_error "Tests Failed: $TESTS_FAILED"
    echo ""
    print_error "❌ Some tests failed. Please fix the issues above before proceeding."
    exit 1
else
    print_success "Tests Failed: $TESTS_FAILED"
    echo ""
    print_success "🎉 All tests passed! Workflow fixes are ready for deployment."
    echo ""
    print_status $BLUE "Next steps:"
    echo "1. Commit the workflow fixes"
    echo "2. Push to the feature branch"
    echo "3. Monitor the first few workflow runs"
    echo "4. Verify all PHP/WordPress combinations work correctly"
    exit 0
fi
