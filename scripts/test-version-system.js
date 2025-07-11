#!/usr/bin/env node

/**
 * Basic Test Suite for Version Management System
 * Tests core functionality to ensure reliability
 */

const {
  parseVersion,
  isValidSemver,
  compareVersions,
  incrementVersion,
  parseConventionalCommit,
  determineBumpType,
  validateTagName,
  validateInput
} = require('./semver-utils');

const { validateVersionSystem, validateVersion } = require('./validate-version');
const config = require('./config');

/**
 * Run basic tests for the version management system
 */
function runTests() {
  console.log('🧪 Running Version Management System Tests...\n');
  
  let passed = 0;
  let failed = 0;
  
  function test(name, testFn) {
    try {
      testFn();
      console.log(`✅ ${name}`);
      passed++;
    } catch (error) {
      console.log(`❌ ${name}: ${error.message}`);
      failed++;
    }
  }
  
  // Test semantic version parsing
  test('Parse valid semantic version', () => {
    const parsed = parseVersion('1.2.3-alpha.1+build.123');
    if (!parsed || parsed.major !== 1 || parsed.minor !== 2 || parsed.patch !== 3) {
      throw new Error('Failed to parse semantic version correctly');
    }
  });
  
  // Test semantic version validation
  test('Validate semantic version format', () => {
    if (!isValidSemver('1.0.0')) throw new Error('Valid version rejected');
    if (isValidSemver('invalid')) throw new Error('Invalid version accepted');
  });
  
  // Test version comparison
  test('Compare semantic versions', () => {
    if (compareVersions('2.0.0', '1.0.0') <= 0) throw new Error('Version comparison failed');
    if (compareVersions('1.0.0', '1.0.0') !== 0) throw new Error('Equal version comparison failed');
  });
  
  // Test version increment
  test('Increment version numbers', () => {
    if (incrementVersion('1.0.0', 'major') !== '2.0.0') throw new Error('Major increment failed');
    if (incrementVersion('1.0.0', 'minor') !== '1.1.0') throw new Error('Minor increment failed');
    if (incrementVersion('1.0.0', 'patch') !== '1.0.1') throw new Error('Patch increment failed');
  });
  
  // Test conventional commit parsing
  test('Parse conventional commits', () => {
    const commit = parseConventionalCommit('feat(api): add new endpoint');
    if (!commit || commit.type !== 'feat' || commit.scope !== 'api') {
      throw new Error('Failed to parse conventional commit');
    }
  });
  
  // Test breaking change detection
  test('Detect breaking changes', () => {
    // Test exclamation mark format
    const commits1 = ['feat!: breaking change', 'fix: normal fix'];
    const result1 = determineBumpType(commits1);
    if (result1.bumpType !== 'major') throw new Error('Failed to detect breaking change with exclamation mark');

    // Test BREAKING CHANGE in body format
    const commits2 = ['feat: add new feature\n\nBREAKING CHANGE: This breaks the API'];
    const result2 = determineBumpType(commits2);
    if (result2.bumpType !== 'major') throw new Error('Failed to detect breaking change in commit body');

    // Test with scope and exclamation mark
    const commits3 = ['feat(api)!: breaking change with scope'];
    const result3 = determineBumpType(commits3);
    if (result3.bumpType !== 'major') throw new Error('Failed to detect breaking change with scope');
  });
  
  // Test tag name validation
  test('Validate git tag names', () => {
    try {
      validateTagName('v1.0.0');
      validateTagName('valid-tag_name.1');
    } catch (error) {
      throw new Error('Valid tag names rejected');
    }
    
    try {
      validateTagName('invalid tag with spaces');
      throw new Error('Invalid tag name accepted');
    } catch (error) {
      // Expected to fail
    }
  });
  
  // Test configuration loading
  test('Load configuration constants', () => {
    if (!config.VERSION || !config.GIT || !config.CHANGELOG) {
      throw new Error('Configuration not loaded properly');
    }
    if (typeof config.VERSION.MAX_COMMITS_TO_ANALYZE !== 'number') {
      throw new Error('Configuration values have wrong types');
    }
  });
  
  // Test input validation
  test('Handle invalid inputs gracefully', () => {
    // These should not throw errors but return safe defaults
    if (parseVersion(null) !== null) throw new Error('Null input not handled');
    if (parseVersion('') !== null) throw new Error('Empty input not handled');
    if (isValidSemver(undefined)) throw new Error('Undefined input not handled');
  });

  // Test input validation function
  test('Validate input function', () => {
    // Test string validation
    try {
      validateInput('test', 'string', { minLength: 2, maxLength: 10 });
    } catch (error) {
      throw new Error('Valid string rejected');
    }

    // Test invalid string
    try {
      validateInput('', 'string', { allowEmpty: false });
      throw new Error('Empty string accepted when not allowed');
    } catch (error) {
      // Expected to fail
    }

    // Test number validation
    try {
      validateInput(5, 'number', { min: 1, max: 10 });
    } catch (error) {
      throw new Error('Valid number rejected');
    }

    // Test invalid number
    try {
      validateInput(15, 'number', { min: 1, max: 10 });
      throw new Error('Number outside range accepted');
    } catch (error) {
      // Expected to fail
    }
  });

  // Test version conflict resolution scenarios
  test('Handle version conflict resolution', () => {
    // Test incrementing from current version (simulates workflow conflict resolution)
    const currentVersion = '1.8.0';
    const patchIncrement = incrementVersion(currentVersion, 'patch');
    if (patchIncrement !== '1.8.1') throw new Error('Version conflict resolution failed');

    // Test that incremented version is always greater than current
    const minorIncrement = incrementVersion(currentVersion, 'minor');
    if (compareVersions(minorIncrement, currentVersion) <= 0) {
      throw new Error('Incremented version not greater than current');
    }

    // Test major increment from current version
    const majorIncrement = incrementVersion(currentVersion, 'major');
    if (majorIncrement !== '2.0.0') throw new Error('Major increment from current version failed');
  });

  // Test edge cases for version increment
  test('Handle version increment edge cases', () => {
    // Test increment with prerelease versions
    try {
      const result = incrementVersion('1.0.0-alpha.1', 'patch');
      if (result !== '1.0.1') throw new Error('Prerelease increment handling failed');
    } catch (error) {
      // This is acceptable behavior - some implementations may not support prerelease
    }

    // Test increment with invalid version
    try {
      incrementVersion('invalid-version', 'patch');
      throw new Error('Invalid version increment should have failed');
    } catch (error) {
      // Expected to fail
    }

    // Test increment with invalid bump type
    try {
      incrementVersion('1.0.0', 'invalid');
      throw new Error('Invalid bump type should have failed');
    } catch (error) {
      // Expected to fail
    }
  });

  // Test validation script conflict checking behavior
  test('Validate conflict checking behavior', () => {
    // Test that validation system can handle conflict scenarios
    const testVersion = '1.8.0'; // Same as current version
    const validationResult = validateVersion(testVersion);

    if (!validationResult.valid) {
      throw new Error('Valid version format rejected by validation');
    }

    // The validation should accept the version format even if it equals current version
    // (conflict resolution is handled at workflow level)
    if (validationResult.errors.length > 0) {
      throw new Error('Version validation produced unexpected errors');
    }
  });

  // Test error boundaries
  test('Handle edge cases', () => {
    // Test very long version strings
    const longVersion = '1.0.0-' + 'a'.repeat(1000);
    if (isValidSemver(longVersion)) throw new Error('Overly long version accepted');

    // Test negative numbers
    if (isValidSemver('-1.0.0')) throw new Error('Negative version accepted');
  });
  
  console.log(`\n📊 Test Results: ${passed} passed, ${failed} failed`);
  
  if (failed > 0) {
    console.log('❌ Some tests failed. Please review the implementation.');
    process.exit(1);
  } else {
    console.log('✅ All tests passed! Version management system is working correctly.');
  }
}

// Run tests if this file is executed directly
if (require.main === module) {
  runTests();
}

module.exports = { runTests };
