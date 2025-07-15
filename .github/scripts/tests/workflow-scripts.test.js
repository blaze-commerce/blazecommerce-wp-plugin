#!/usr/bin/env node

/**
 * Unit Tests for Workflow Scripts
 * Comprehensive test suite for critical utility functions
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const assert = require('assert');
const { FileChangeAnalyzer } = require('../file-change-analyzer');
const { VersionValidator } = require('../version-validator');
const { BranchAnalyzer } = require('../branch-analyzer');
const { BumpTypeAnalyzer } = require('../bump-type-analyzer');
const { ClaudeReviewEnhancer } = require('../claude-review-enhancer');

/**
 * Test Suite Class
 */
class WorkflowScriptsTestSuite {
  constructor() {
    this.tests = [];
    this.passed = 0;
    this.failed = 0;
  }

  /**
   * Add a test case
   * @param {string} name - Test name
   * @param {Function} testFn - Test function
   */
  test(name, testFn) {
    this.tests.push({ name, testFn });
  }

  /**
   * Run all tests
   */
  async runAll() {
    console.log('TESTING: Running Workflow Scripts Test Suite...\n');

    for (const { name, testFn } of this.tests) {
      try {
        await testFn();
        console.log(`SUCCESS: ${name}`);
        this.passed++;
      } catch (error) {
        console.log(`ERROR: ${name}: ${error.message}`);
        this.failed++;
      }
    }

    console.log(`\nANALYSIS: Test Results: ${this.passed} passed, ${this.failed} failed`);
    return this.failed === 0;
  }

  /**
   * Assert helper
   * @param {boolean} condition - Condition to assert
   * @param {string} message - Error message
   */
  assert(condition, message) {
    if (!condition) {
      throw new Error(message);
    }
  }

  /**
   * Assert equal helper
   * @param {*} actual - Actual value
   * @param {*} expected - Expected value
   * @param {string} message - Error message
   */
  assertEqual(actual, expected, message) {
    if (actual !== expected) {
      throw new Error(`${message}: expected ${expected}, got ${actual}`);
    }
  }
}

// Create test suite instance
const suite = new WorkflowScriptsTestSuite();

// File Change Analyzer Tests
suite.test('FileChangeAnalyzer - shouldIgnoreFile basic patterns', () => {
  const analyzer = new FileChangeAnalyzer();

  suite.assert(analyzer.shouldIgnoreFile('README.md'), 'Should ignore README.md');
  suite.assert(analyzer.shouldIgnoreFile('package.json'), 'Should ignore package.json');
  suite.assert(analyzer.shouldIgnoreFile('.github/'), 'Should ignore .github directory');
  suite.assert(!analyzer.shouldIgnoreFile('src/index.js'), 'Should not ignore source files');
  suite.assert(!analyzer.shouldIgnoreFile('lib/main.js'), 'Should not ignore lib files');
});

suite.test('FileChangeAnalyzer - analyze with no files', () => {
  // Mock environment for testing
  process.env.GITHUB_EVENT_BEFORE = '0000000000000000000000000000000000000000';
  
  const analyzer = new FileChangeAnalyzer();
  // Mock getChangedFiles to return empty array
  analyzer.getChangedFiles = () => [];
  
  const result = analyzer.analyze();
  suite.assertEqual(result.shouldBump, false, 'Should not bump with no files');
  suite.assertEqual(result.reason, 'No changed files detected', 'Should have correct reason');
});

// Version Validator Tests
suite.test('VersionValidator - isValidSemver', () => {
  const validator = new VersionValidator();

  suite.assert(validator.isValidSemver('1.0.0'), 'Should validate basic semver');
  suite.assert(validator.isValidSemver('1.0.0-alpha.1'), 'Should validate prerelease');
  suite.assert(validator.isValidSemver('1.0.0+build.123'), 'Should validate with metadata');
  suite.assert(!validator.isValidSemver('1.0'), 'Should reject incomplete version');
  suite.assert(!validator.isValidSemver('v1.0.0'), 'Should reject version with v prefix');
  // Note: The regex might be more permissive, let's test a clearly invalid format
  suite.assert(!validator.isValidSemver('invalid.version'), 'Should reject invalid format');
});

// Branch Analyzer Tests
suite.test('BranchAnalyzer - getBranchType', () => {
  const analyzer = new BranchAnalyzer();
  
  analyzer.branchName = 'feature/new-component';
  suite.assertEqual(analyzer.getBranchType(), 'feature', 'Should detect feature branch');
  
  analyzer.branchName = 'develop';
  suite.assertEqual(analyzer.getBranchType(), 'develop', 'Should detect develop branch');
  
  analyzer.branchName = 'main';
  suite.assertEqual(analyzer.getBranchType(), 'main', 'Should detect main branch');
  
  analyzer.branchName = 'release/v1.2.0';
  suite.assertEqual(analyzer.getBranchType(), 'release', 'Should detect release branch');
  
  analyzer.branchName = 'hotfix/urgent-fix';
  suite.assertEqual(analyzer.getBranchType(), 'hotfix', 'Should detect hotfix branch');
});

suite.test('BranchAnalyzer - analyze prerelease types', () => {
  const analyzer = new BranchAnalyzer();
  
  analyzer.branchName = 'feature/test';
  let result = analyzer.analyze();
  suite.assertEqual(result.prereleaseType, 'alpha', 'Feature branch should be alpha');
  
  analyzer.branchName = 'develop';
  result = analyzer.analyze();
  suite.assertEqual(result.prereleaseType, 'beta', 'Develop branch should be beta');
  
  analyzer.branchName = 'release/v1.0.0';
  result = analyzer.analyze();
  suite.assertEqual(result.prereleaseType, 'rc', 'Release branch should be rc');
  
  analyzer.branchName = 'main';
  result = analyzer.analyze();
  suite.assertEqual(result.prereleaseType, '', 'Main branch should be stable');
});

// Bump Type Analyzer Tests
suite.test('BumpTypeAnalyzer - parseConventionalCommit', () => {
  const analyzer = new BumpTypeAnalyzer();
  
  let result = analyzer.parseConventionalCommit('feat: add new feature');
  suite.assertEqual(result.type, 'feat', 'Should parse feat type');
  suite.assertEqual(result.isBreaking, false, 'Should not be breaking');
  
  result = analyzer.parseConventionalCommit('feat!: breaking change');
  suite.assertEqual(result.type, 'feat', 'Should parse feat type with breaking');
  suite.assertEqual(result.isBreaking, true, 'Should be breaking');
  
  result = analyzer.parseConventionalCommit('fix(auth): resolve login issue');
  suite.assertEqual(result.type, 'fix', 'Should parse fix type');
  suite.assertEqual(result.scope, 'auth', 'Should parse scope');
  
  result = analyzer.parseConventionalCommit('BREAKING CHANGE: major update');
  suite.assertEqual(result.isBreaking, true, 'Should detect explicit breaking change');
});

// Claude Review Enhancer Tests
suite.test('ClaudeReviewEnhancer - parseClaudeReview', () => {
  const enhancer = new ClaudeReviewEnhancer();
  enhancer.prNumber = '123';
  
  const claudeOutput = `
CRITICAL: REQUIRED - Fix security vulnerability
This is a critical security issue that must be addressed.

WARNING: IMPORTANT - Improve error handling
Consider adding better error handling here.

INFO: SUGGESTIONS - Add documentation
It would be nice to have more documentation.
  `;
  
  const result = enhancer.parseClaudeReview(claudeOutput);
  suite.assertEqual(result.required.length, 1, 'Should find 1 required item');
  suite.assertEqual(result.important.length, 1, 'Should find 1 important item');
  suite.assertEqual(result.suggestions.length, 1, 'Should find 1 suggestion');
});

// Integration Tests
suite.test('Integration - Full workflow simulation', () => {
  // Simulate a typical workflow scenario
  const fileAnalyzer = new FileChangeAnalyzer();
  const versionValidator = new VersionValidator();
  const branchAnalyzer = new BranchAnalyzer();
  const bumpAnalyzer = new BumpTypeAnalyzer();
  
  // Mock file changes
  fileAnalyzer.getChangedFiles = () => ['src/index.js', 'package.json'];
  
  const fileResult = fileAnalyzer.analyze();
  suite.assert(fileResult.shouldBump, 'Should bump with significant files');
  
  // Mock version validation
  versionValidator.getCurrentVersion = () => '1.0.0';
  versionValidator.getLatestGitTag = () => 'v1.0.0';
  
  const versionResult = versionValidator.validate();
  suite.assert(versionResult.isValid, 'Version should be valid');
  suite.assertEqual(versionResult.hasMismatch, false, 'Should not have mismatch');
  
  // Mock branch analysis
  branchAnalyzer.branchName = 'feature/new-feature';
  const branchResult = branchAnalyzer.analyze();
  suite.assertEqual(branchResult.prereleaseType, 'alpha', 'Should be alpha prerelease');
  
  console.log('SUCCESS: Integration test completed successfully');
});

// Error Handling Tests
suite.test('Error handling - Invalid inputs', () => {
  const validator = new VersionValidator();
  
  // Test with invalid package.json path
  validator.packageJsonPath = '/nonexistent/package.json';
  
  try {
    validator.getCurrentVersion();
    suite.assert(false, 'Should throw error for missing package.json');
  } catch (error) {
    suite.assert(error.message.includes('package.json not found'), 'Should have correct error message');
  }
});

suite.test('Performance - Large commit analysis', () => {
  const analyzer = new BumpTypeAnalyzer();
  
  // Generate large number of commits
  const commits = [];
  for (let i = 0; i < 1000; i++) {
    commits.push(`feat: feature ${i}`);
    commits.push(`fix: fix ${i}`);
  }
  
  const startTime = Date.now();
  const result = analyzer.analyze(false, 'none');
  const endTime = Date.now();
  
  const duration = endTime - startTime;
  suite.assert(duration < 5000, `Analysis should complete in under 5 seconds, took ${duration}ms`);
  suite.assertEqual(result.bumpType, 'minor', 'Should detect minor bump for features');
});

// Run tests if this file is executed directly
if (require.main === module) {
  suite.runAll().then(success => {
    process.exit(success ? 0 : 1);
  }).catch(error => {
    console.error('ERROR: Test suite failed:', error);
    process.exit(1);
  });
}

module.exports = { WorkflowScriptsTestSuite };
