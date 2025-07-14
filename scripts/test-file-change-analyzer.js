#!/usr/bin/env node

/**
 * Test Suite for File Change Analyzer
 * Tests the file change detection logic to ensure ignored files don't trigger version bumps
 */

const { FileChangeAnalyzer, Logger } = require('../.github/scripts/file-change-analyzer');
const fs = require('fs');
const path = require('path');

/**
 * Test cases for file change detection
 */
const TEST_CASES = [
  // Files that SHOULD be ignored (should NOT trigger version bump)
  {
    name: 'GitHub workflow files',
    files: ['.github/workflows/auto-version.yml', '.github/scripts/test.js'],
    shouldIgnore: true
  },
  {
    name: 'Documentation files',
    files: ['docs/README.md', 'docs/api/guide.md', 'README.md', 'CHANGELOG.md'],
    shouldIgnore: true
  },
  {
    name: 'Test files',
    files: ['test/unit/test.js', 'tests/integration/api.test.js', 'scripts/test-version.js'],
    shouldIgnore: true
  },
  {
    name: 'Lock files',
    files: ['package-lock.json', 'yarn.lock', 'blocks/package-lock.json'],
    shouldIgnore: true
  },
  {
    name: 'IDE and system files',
    files: ['.vscode/settings.json', '.idea/workspace.xml', '.DS_Store'],
    shouldIgnore: true
  },
  {
    name: 'Scripts and tooling',
    files: ['scripts/build.js', 'bin/install.sh', 'setup-templates/config.json'],
    shouldIgnore: true
  },
  
  // Files that should NOT be ignored (SHOULD trigger version bump)
  {
    name: 'Source code files',
    files: ['app/BlazeWooless.php', 'lib/functions.php', 'views/settings.php'],
    shouldIgnore: false
  },
  {
    name: 'Package configuration',
    files: ['package.json', 'composer.json', 'blocks/package.json'],
    shouldIgnore: false
  },
  {
    name: 'Plugin main file',
    files: ['blaze-wooless.php'],
    shouldIgnore: false
  },
  {
    name: 'Assets and blocks',
    files: ['assets/css/style.css', 'blocks/src/index.js', 'assets/js/app.js'],
    shouldIgnore: false
  }
];

/**
 * Run comprehensive tests
 */
function runTests() {
  console.log('🧪 Testing File Change Analyzer...\n');
  
  let passed = 0;
  let failed = 0;
  const failures = [];
  
  // Create analyzer instance
  const analyzer = new FileChangeAnalyzer();
  
  // Test each case
  TEST_CASES.forEach(testCase => {
    console.log(`\n📋 Testing: ${testCase.name}`);
    
    testCase.files.forEach(filePath => {
      const shouldIgnore = analyzer.shouldIgnoreFile(filePath);
      const expected = testCase.shouldIgnore;
      
      if (shouldIgnore === expected) {
        console.log(`  ✅ ${filePath} - ${expected ? 'ignored' : 'significant'} (correct)`);
        passed++;
      } else {
        console.log(`  ❌ ${filePath} - expected ${expected ? 'ignored' : 'significant'}, got ${shouldIgnore ? 'ignored' : 'significant'}`);
        failed++;
        failures.push({
          file: filePath,
          expected: expected ? 'ignored' : 'significant',
          actual: shouldIgnore ? 'ignored' : 'significant'
        });
      }
    });
  });
  
  // Test ignore patterns loading
  console.log('\n📋 Testing ignore patterns loading...');
  const patterns = analyzer.ignorePatterns;
  if (patterns && patterns.length > 0) {
    console.log(`  ✅ Loaded ${patterns.length} ignore patterns`);
    console.log(`  📝 Sample patterns: ${patterns.slice(0, 5).join(', ')}`);
    passed++;
  } else {
    console.log(`  ❌ Failed to load ignore patterns`);
    failed++;
    failures.push({
      file: 'ignore-patterns',
      expected: 'loaded patterns',
      actual: 'no patterns loaded'
    });
  }
  
  // Summary
  console.log('\n' + '='.repeat(50));
  console.log(`📊 Test Results:`);
  console.log(`  ✅ Passed: ${passed}`);
  console.log(`  ❌ Failed: ${failed}`);
  console.log(`  📈 Success Rate: ${((passed / (passed + failed)) * 100).toFixed(1)}%`);
  
  if (failures.length > 0) {
    console.log('\n❌ Failures:');
    failures.forEach(failure => {
      console.log(`  - ${failure.file}: expected ${failure.expected}, got ${failure.actual}`);
    });
  }
  
  if (failed === 0) {
    console.log('\n🎉 All tests passed! File change detection is working correctly.');
    process.exit(0);
  } else {
    console.log('\n💥 Some tests failed. Please review the file change detection logic.');
    process.exit(1);
  }
}

/**
 * Test with simulated file changes
 */
function testWithSimulatedChanges() {
  console.log('\n🔄 Testing with simulated file changes...');
  
  // Mock environment for testing
  process.env.GITHUB_EVENT_BEFORE = '1234567890abcdef1234567890abcdef12345678';
  process.env.DEBUG = 'true';
  
  const analyzer = new FileChangeAnalyzer();
  
  // Test different scenarios
  const scenarios = [
    {
      name: 'Only documentation changes',
      files: ['docs/api.md', 'README.md', '.github/workflows/test.yml'],
      expectedBump: false
    },
    {
      name: 'Mixed changes with significant files',
      files: ['docs/api.md', 'app/BlazeWooless.php', 'README.md'],
      expectedBump: true
    },
    {
      name: 'Only significant changes',
      files: ['app/BlazeWooless.php', 'package.json'],
      expectedBump: true
    }
  ];
  
  scenarios.forEach(scenario => {
    console.log(`\n📋 Scenario: ${scenario.name}`);
    
    const ignoredFiles = [];
    const significantFiles = [];
    
    scenario.files.forEach(file => {
      if (analyzer.shouldIgnoreFile(file)) {
        ignoredFiles.push(file);
      } else {
        significantFiles.push(file);
      }
    });
    
    const shouldBump = significantFiles.length > 0;
    
    console.log(`  📁 Files: ${scenario.files.join(', ')}`);
    console.log(`  🚫 Ignored: ${ignoredFiles.length} files`);
    console.log(`  ⚡ Significant: ${significantFiles.length} files`);
    console.log(`  🔄 Should bump version: ${shouldBump}`);
    
    if (shouldBump === scenario.expectedBump) {
      console.log(`  ✅ Correct decision`);
    } else {
      console.log(`  ❌ Wrong decision - expected ${scenario.expectedBump}`);
    }
  });
}

// Run tests
if (require.main === module) {
  try {
    runTests();
    testWithSimulatedChanges();
  } catch (error) {
    console.error(`\n💥 Test execution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = { runTests, testWithSimulatedChanges };
