#!/bin/bash

# Quick validation script for workflow fixes
set -euo pipefail

echo "🚀 Quick Validation of Workflow Fixes"
echo "======================================"

# Test 1: Validate package.json
echo -n "📦 Testing package.json... "
if [ -f "package.json" ] && node -e 'JSON.parse(require("fs").readFileSync("package.json", "utf8"))' >/dev/null 2>&1; then
    echo "✅ PASSED"
else
    echo "❌ FAILED"
    exit 1
fi

# Test 2: Validate composer.json
echo -n "🎼 Testing composer.json... "
if [ -f "composer.json" ] && node -e 'JSON.parse(require("fs").readFileSync("composer.json", "utf8"))' >/dev/null 2>&1; then
    echo "✅ PASSED"
else
    echo "❌ FAILED"
    exit 1
fi

# Test 3: Test semver-utils.js
echo -n "🔢 Testing semver-utils.js... "
if node -e 'const semver = require("./scripts/semver-utils.js"); console.log(semver.incrementVersion("1.0.0", "patch"))' >/dev/null 2>&1; then
    echo "✅ PASSED"
else
    echo "❌ FAILED"
    exit 1
fi

# Test 4: Test version increment logic
echo -n "⬆️  Testing version increment... "
RESULT=$(node -e 'const semver = require("./scripts/semver-utils.js"); console.log(semver.incrementVersion("1.0.0", "patch"))')
if [ "$RESULT" = "1.0.1" ]; then
    echo "✅ PASSED"
else
    echo "❌ FAILED (got $RESULT, expected 1.0.1)"
    exit 1
fi

# Test 5: Test workflow files exist
echo -n "📋 Testing workflow files... "
if [ -f ".github/workflows/tests.yml" ] && [ -f ".github/workflows/auto-version.yml" ]; then
    echo "✅ PASSED"
else
    echo "❌ FAILED"
    exit 1
fi

# Test 6: Test script permissions
echo -n "🔐 Testing script permissions... "
if [ -x "scripts/check-file-changes.sh" ] && [ -x "scripts/get-ignore-patterns.sh" ]; then
    echo "✅ PASSED"
else
    echo "❌ FAILED"
    exit 1
fi

# Test 7: Test ignore patterns
echo -n "🚫 Testing ignore patterns... "
if bash scripts/get-ignore-patterns.sh | grep -q "vendor/" >/dev/null 2>&1; then
    echo "✅ PASSED"
else
    echo "❌ FAILED"
    exit 1
fi

# Test 8: Test environment validation
echo -n "🌍 Testing environment validation... "
if node scripts/validate-workflow-environment.js >/dev/null 2>&1; then
    echo "✅ PASSED"
else
    echo "❌ FAILED"
    exit 1
fi

echo ""
echo "🎉 All quick validation tests passed!"
echo ""
echo "✅ Key fixes validated:"
echo "   • package.json and composer.json are valid"
echo "   • semver-utils.js works correctly"
echo "   • Version increment logic is functional"
echo "   • Workflow files are present"
echo "   • Script permissions are correct"
echo "   • Ignore patterns are working"
echo "   • Environment validation passes"
echo ""
echo "🚀 Workflows should now run successfully!"
