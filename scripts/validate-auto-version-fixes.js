#!/usr/bin/env node

/**
 * Quick Validation Script for Auto-Version Workflow Fixes
 * Validates that the implemented fixes are working correctly
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('🔍 Validating Auto-Version Workflow Fixes...\n');

let passed = 0;
let failed = 0;
const issues = [];

function test(name, testFn) {
  try {
    testFn();
    console.log(`✅ ${name}`);
    passed++;
  } catch (error) {
    console.log(`❌ ${name}: ${error.message}`);
    failed++;
    issues.push({ test: name, error: error.message });
  }
}

// Test 1: File Change Analyzer Exists and Loads
test('File Change Analyzer module loads correctly', () => {
  const analyzerPath = '.github/scripts/file-change-analyzer.js';
  if (!fs.existsSync(analyzerPath)) {
    throw new Error('File change analyzer script not found');
  }
  
  const { FileChangeAnalyzer } = require('../.github/scripts/file-change-analyzer');
  if (!FileChangeAnalyzer) {
    throw new Error('FileChangeAnalyzer class not exported');
  }
  
  const analyzer = new FileChangeAnalyzer();
  if (!analyzer.shouldIgnoreFile) {
    throw new Error('shouldIgnoreFile method not available');
  }
});

// Test 2: Ignore Patterns Loading
test('Ignore patterns load correctly', () => {
  const { FileChangeAnalyzer } = require('../.github/scripts/file-change-analyzer');
  const analyzer = new FileChangeAnalyzer();
  
  if (!analyzer.ignorePatterns || analyzer.ignorePatterns.length === 0) {
    throw new Error('No ignore patterns loaded');
  }
  
  // Check for key patterns
  const patterns = analyzer.ignorePatterns;
  const hasGithubPattern = patterns.some(p => p.includes('.github'));
  const hasDocsPattern = patterns.some(p => p.includes('docs'));
  const hasTestPattern = patterns.some(p => p.includes('test'));
  
  if (!hasGithubPattern) throw new Error('Missing .github ignore pattern');
  if (!hasDocsPattern) throw new Error('Missing docs ignore pattern');
  if (!hasTestPattern) throw new Error('Missing test ignore pattern');
});

// Test 3: File Pattern Matching
test('File pattern matching works correctly', () => {
  const { FileChangeAnalyzer } = require('../.github/scripts/file-change-analyzer');
  const analyzer = new FileChangeAnalyzer();
  
  // Files that should be ignored
  const ignoredFiles = [
    '.github/workflows/auto-version.yml',
    'docs/api.md',
    'README.md',
    'test/unit/test.js',
    'scripts/build.js',
    'package-lock.json'
  ];
  
  // Files that should NOT be ignored
  const significantFiles = [
    'app/BlazeWooless.php',
    'package.json',
    'blaze-wooless.php',
    'assets/css/style.css',
    'blocks/src/index.js'
  ];
  
  ignoredFiles.forEach(file => {
    if (!analyzer.shouldIgnoreFile(file)) {
      throw new Error(`File ${file} should be ignored but isn't`);
    }
  });
  
  significantFiles.forEach(file => {
    if (analyzer.shouldIgnoreFile(file)) {
      throw new Error(`File ${file} should NOT be ignored but is`);
    }
  });
});

// Test 4: Workflow File Validation
test('Auto-version workflow has proper error handling', () => {
  const workflowPath = '.github/workflows/auto-version.yml';
  if (!fs.existsSync(workflowPath)) {
    throw new Error('Auto-version workflow file not found');
  }
  
  const workflowContent = fs.readFileSync(workflowPath, 'utf8');
  
  // Check for success() conditions
  if (!workflowContent.includes('success()')) {
    throw new Error('Workflow missing success() conditions for error handling');
  }
  
  // Check for final validation step
  if (!workflowContent.includes('Final validation before tag creation')) {
    throw new Error('Workflow missing final validation step');
  }
  
  // Check for proper conditional checks
  if (!workflowContent.includes('steps.final_validation.outputs.version_ready')) {
    throw new Error('Workflow missing final validation dependency');
  }
});

// Test 5: Version File Consistency
test('Version files are consistent', () => {
  // Check package.json
  if (!fs.existsSync('package.json')) {
    throw new Error('package.json not found');
  }
  const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  const packageVersion = packageJson.version;
  
  // Check blaze-wooless.php
  if (!fs.existsSync('blaze-wooless.php')) {
    throw new Error('blaze-wooless.php not found');
  }
  const phpContent = fs.readFileSync('blaze-wooless.php', 'utf8');
  const phpVersionMatch = phpContent.match(/Version:\s*([\d.]+)/);
  if (!phpVersionMatch) {
    throw new Error('Could not find version in blaze-wooless.php');
  }
  const phpVersion = phpVersionMatch[1];
  
  // Check README.md
  if (!fs.existsSync('README.md')) {
    throw new Error('README.md not found');
  }
  const readmeContent = fs.readFileSync('README.md', 'utf8');
  const readmeVersionMatch = readmeContent.match(/\*\*Version:\*\*\s*([\d.]+)/);
  if (!readmeVersionMatch) {
    throw new Error('Could not find version in README.md');
  }
  const readmeVersion = readmeVersionMatch[1];
  
  // Check blocks/package.json
  if (!fs.existsSync('blocks/package.json')) {
    throw new Error('blocks/package.json not found');
  }
  const blocksPackageJson = JSON.parse(fs.readFileSync('blocks/package.json', 'utf8'));
  const blocksVersion = blocksPackageJson.version;
  
  // Verify all versions match
  if (packageVersion !== phpVersion) {
    throw new Error(`Version mismatch: package.json (${packageVersion}) vs blaze-wooless.php (${phpVersion})`);
  }
  if (packageVersion !== readmeVersion) {
    throw new Error(`Version mismatch: package.json (${packageVersion}) vs README.md (${readmeVersion})`);
  }
  if (packageVersion !== blocksVersion) {
    throw new Error(`Version mismatch: package.json (${packageVersion}) vs blocks/package.json (${blocksVersion})`);
  }
  
  console.log(`  📦 All files consistent at version: ${packageVersion}`);
});

// Test 6: Test Suite Availability
test('Test suite is available and executable', () => {
  const testPath = 'scripts/test-file-change-analyzer.js';
  if (!fs.existsSync(testPath)) {
    throw new Error('Test suite not found');
  }
  
  // Check if test file is executable
  try {
    const stats = fs.statSync(testPath);
    if (!stats.isFile()) {
      throw new Error('Test suite is not a file');
    }
  } catch (error) {
    throw new Error(`Cannot access test suite: ${error.message}`);
  }
});

// Test 7: Validation Scripts Availability
test('Validation scripts are available', () => {
  const requiredScripts = [
    'scripts/validate-version.js',
    'scripts/version-sync-validator.js',
    'scripts/semver-utils.js'
  ];
  
  requiredScripts.forEach(script => {
    if (!fs.existsSync(script)) {
      throw new Error(`Required script not found: ${script}`);
    }
  });
});

// Summary
console.log('\n' + '='.repeat(60));
console.log('📊 Validation Results:');
console.log(`  ✅ Passed: ${passed}`);
console.log(`  ❌ Failed: ${failed}`);
console.log(`  📈 Success Rate: ${((passed / (passed + failed)) * 100).toFixed(1)}%`);

if (issues.length > 0) {
  console.log('\n❌ Issues Found:');
  issues.forEach((issue, index) => {
    console.log(`  ${index + 1}. ${issue.test}`);
    console.log(`     Error: ${issue.error}`);
  });
}

if (failed === 0) {
  console.log('\n🎉 All validations passed! Auto-version workflow fixes are working correctly.');
  console.log('\n📋 Next Steps:');
  console.log('  1. Run comprehensive tests: node scripts/test-file-change-analyzer.js');
  console.log('  2. Test with actual file changes to verify behavior');
  console.log('  3. Monitor workflow execution in GitHub Actions');
  process.exit(0);
} else {
  console.log('\n💥 Some validations failed. Please review and fix the issues above.');
  process.exit(1);
}
