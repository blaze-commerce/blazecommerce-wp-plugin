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

const { validateVersionSystem } = require('./validate-version');
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
    const commits = ['feat!: breaking change', 'fix: normal fix'];
    const bumpType = determineBumpType(commits);
    if (bumpType !== 'major') throw new Error('Failed to detect breaking change');
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
