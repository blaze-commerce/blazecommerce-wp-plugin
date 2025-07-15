#!/bin/bash
# Test suite for file detection logic
# Tests the check-file-changes.sh script with various scenarios

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Test counters
TESTS_RUN=0
TESTS_PASSED=0
TESTS_FAILED=0

# Function to run a test
run_test() {
    local test_name="$1"
    local test_files="$2"
    local expected_exit_code="$3"
    local description="$4"
    
    ((TESTS_RUN++))
    
    echo -e "${YELLOW}Test $TESTS_RUN: $test_name${NC}"
    echo "  Description: $description"
    echo "  Files: $test_files"
    echo "  Expected: $([ "$expected_exit_code" -eq 0 ] && echo "Action needed" || echo "Skip action")"
    
    # Run the test
    local actual_exit_code=0
    if ! echo "$test_files" | bash scripts/check-file-changes.sh /dev/stdin >/dev/null 2>&1; then
        actual_exit_code=$?
    fi
    
    # Check result
    if [ "$actual_exit_code" -eq "$expected_exit_code" ]; then
        echo -e "  ${GREEN}✅ PASSED${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "  ${RED}❌ FAILED${NC}"
        echo "    Expected exit code: $expected_exit_code"
        echo "    Actual exit code: $actual_exit_code"
        ((TESTS_FAILED++))
    fi
    echo ""
}

# Function to setup test environment
setup_tests() {
    echo "🔧 Setting up test environment..."
    
    # Ensure we're in the right directory
    if [ ! -f "scripts/check-file-changes.sh" ]; then
        echo "❌ Error: scripts/check-file-changes.sh not found"
        echo "   Please run this test from the repository root"
        exit 1
    fi
    
    if [ ! -f "scripts/get-ignore-patterns.sh" ]; then
        echo "❌ Error: scripts/get-ignore-patterns.sh not found"
        exit 1
    fi
    
    echo "✅ Test environment ready"
    echo ""
}

# Function to run all tests
run_all_tests() {
    echo "🧪 Running File Detection Tests"
    echo "==============================="
    echo ""
    
    # Test 1: Documentation files should be ignored
    run_test "Documentation Changes" \
        "docs/README.md
docs/api.md
CONTRIBUTING.md" \
        1 \
        "Documentation changes should not trigger version bump"
    
    # Test 2: Source code changes should trigger action
    run_test "Source Code Changes" \
        "src/main.php
lib/utils.js
components/Header.tsx" \
        0 \
        "Source code changes should trigger version bump"
    
    # Test 3: Mixed changes with source code should trigger action
    run_test "Mixed Changes with Code" \
        "docs/README.md
src/main.php
CONTRIBUTING.md" \
        0 \
        "Mixed changes including source code should trigger version bump"
    
    # Test 4: Only ignored files should skip action
    run_test "Only Ignored Files" \
        "CHANGELOG.md
package.json
.github/workflows/test.yml
docs/guide.md" \
        1 \
        "Only ignored files should skip version bump"
    
    # Test 5: Files with spaces in names
    run_test "Files with Spaces" \
        "my file.txt
another file.js" \
        0 \
        "Files with spaces should be handled correctly"
    
    # Test 6: Directory patterns
    run_test "Directory Patterns" \
        "tests/unit/test.js
test/integration/api.test.js
.github/workflows/ci.yml" \
        1 \
        "Directory patterns should work correctly"
    
    # Test 7: File extension patterns
    run_test "File Extension Patterns" \
        ".DS_Store
some/path/.DS_Store
.gitignore" \
        1 \
        "File extension patterns should match precisely"
    
    # Test 8: False positive prevention
    run_test "False Positive Prevention" \
        "mytest/file.js
testing-utils.js" \
        0 \
        "Should not falsely match 'test/' pattern with 'mytest/'"
    
    # Test 9: Exact file matches
    run_test "Exact File Matches" \
        "package.json
composer.lock
vendor/autoload.php" \
        1 \
        "Exact file matches should be ignored"
    
    # Test 10: Empty input
    run_test "Empty Input" \
        "" \
        1 \
        "Empty input should skip action"
}

# Function to display test results
show_results() {
    echo "📊 Test Results"
    echo "==============="
    echo "Tests run: $TESTS_RUN"
    echo -e "Tests passed: ${GREEN}$TESTS_PASSED${NC}"
    echo -e "Tests failed: ${RED}$TESTS_FAILED${NC}"
    echo ""
    
    if [ "$TESTS_FAILED" -eq 0 ]; then
        echo -e "${GREEN}🎉 All tests passed!${NC}"
        exit 0
    else
        echo -e "${RED}❌ Some tests failed!${NC}"
        exit 1
    fi
}

# Main execution
main() {
    setup_tests
    run_all_tests
    show_results
}

# Run main function
main "$@"
