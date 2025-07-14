#!/bin/bash
# Comprehensive test script for version bump logic
# This script validates that the auto-version workflow correctly identifies
# when version bumps should and should not be triggered.

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0

# Helper function for logging
log() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

success() {
    echo -e "${GREEN}✅ PASS:${NC} $1"
    ((TESTS_PASSED++))
}

failure() {
    echo -e "${RED}❌ FAIL:${NC} $1"
    ((TESTS_FAILED++))
}

warning() {
    echo -e "${YELLOW}⚠️  WARN:${NC} $1"
}

# Test function
run_test() {
    local test_name="$1"
    local test_files="$2"
    local expected_exit_code="$3"
    local description="$4"
    
    log "Running test: $test_name"
    log "Description: $description"
    log "Expected exit code: $expected_exit_code ($([ $expected_exit_code -eq 0 ] && echo "version bump needed" || echo "version bump skipped"))"
    
    # Run the file detection script
    set +e
    result=$(echo "$test_files" | bash scripts/check-file-changes.sh /dev/stdin 2>&1)
    actual_exit_code=$?
    set -e
    
    echo "Files tested:"
    echo "$test_files" | sed 's/^/  - /'
    echo ""
    echo "Script output:"
    echo "$result" | sed 's/^/  /'
    echo ""
    
    if [ $actual_exit_code -eq $expected_exit_code ]; then
        success "$test_name - Exit code matches expected ($actual_exit_code)"
    else
        failure "$test_name - Exit code mismatch. Expected: $expected_exit_code, Got: $actual_exit_code"
    fi
    
    echo "----------------------------------------"
    echo ""
}

echo "🧪 BlazeCommerce Auto-Version Workflow Test Suite"
echo "=================================================="
echo ""

# Test 1: Core plugin files should trigger version bump
run_test "Core Plugin Files" \
"app/BlazeWooless.php
app/Features/PluginIntegrationUrlManager.php
lib/blaze-wooless-functions.php
blaze-wooless.php" \
0 \
"Core plugin functionality changes should trigger version bumps"

# Test 2: Test files should not trigger version bump
run_test "Test Files Only" \
"test/test-plugin-integration-url-manager.php
tests/unit-test.php
phpunit.xml" \
1 \
"Test files and configuration should not trigger version bumps"

# Test 3: Documentation files should not trigger version bump
run_test "Documentation Files" \
"README.md
CHANGELOG.md
CONTRIBUTING.md
DOCUMENTATION_GUIDELINES.md" \
1 \
"Documentation changes should not trigger version bumps"

# Test 4: Mixed files - should trigger due to core files
run_test "Mixed Files (Core + Docs)" \
"README.md
app/BlazeWooless.php
CHANGELOG.md
test/unit-test.php" \
0 \
"Mixed changes should trigger version bump when core files are included"

# Test 5: Asset files should trigger version bump
run_test "Asset Files" \
"assets/css/blaze-wooless.css
assets/js/blaze-wooless.js
views/draggable-content.php" \
0 \
"Asset and template changes should trigger version bumps"

# Test 6: Block files should trigger version bump
run_test "Block Files" \
"blocks/src/index.js
blocks/src/block.json
blocks/build/index.js
blocks/blocks.php" \
0 \
"Gutenberg block changes should trigger version bumps"

# Test 7: Development files should not trigger version bump
run_test "Development Files" \
".github/workflows/test.yml
scripts/build.sh
.vscode/settings.json
.augment/rules/test.md" \
1 \
"Development and tooling files should not trigger version bumps"

# Test 8: Dependency files should not trigger version bump
run_test "Dependency Files" \
"composer.json
package.json
blocks/package.json
vendor/autoload.php" \
1 \
"Dependency configuration should not trigger version bumps"

# Test 9: System files should not trigger version bump
run_test "System Files" \
".DS_Store
Thumbs.db
.gitignore
.editorconfig" \
1 \
"System and configuration files should not trigger version bumps"

# Test 10: The actual workflow run files (regression test)
run_test "Workflow Run Regression Test" \
"app/BlazeWooless.php
app/Features/PluginIntegrationUrlManager.php
lib/blaze-wooless-functions.php
test/test-plugin-integration-url-manager.php" \
0 \
"The specific files from the failed workflow run should trigger version bump"

# Summary
echo "🏁 Test Suite Complete"
echo "======================"
echo ""
echo "Results:"
echo "  ✅ Passed: $TESTS_PASSED"
echo "  ❌ Failed: $TESTS_FAILED"
echo "  📊 Total:  $((TESTS_PASSED + TESTS_FAILED))"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 All tests passed! The version bump logic is working correctly.${NC}"
    exit 0
else
    echo -e "${RED}💥 Some tests failed. Please review the version bump logic.${NC}"
    exit 1
fi
