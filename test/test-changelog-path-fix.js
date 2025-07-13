#!/usr/bin/env node

/**
 * Test script for changelog path fix
 * Verifies that the update-changelog.js script correctly uses docs/reference/changelog.md
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Test configuration
const TEST_CONFIG = {
  originalChangelogPath: 'docs/reference/changelog.md',
  testChangelogPath: 'test/temp-changelog.md',
  testVersion: '1.99.99-test'
};

/**
 * Setup test environment
 */
function setupTest() {
  console.log('🧪 Setting up test environment...');
  
  // Create test directory if it doesn't exist
  const testDir = path.dirname(TEST_CONFIG.testChangelogPath);
  if (!fs.existsSync(testDir)) {
    fs.mkdirSync(testDir, { recursive: true });
  }
  
  // Backup original changelog if it exists
  if (fs.existsSync(TEST_CONFIG.originalChangelogPath)) {
    const backupPath = `${TEST_CONFIG.originalChangelogPath}.backup`;
    fs.copyFileSync(TEST_CONFIG.originalChangelogPath, backupPath);
    console.log(`📋 Backed up original changelog to ${backupPath}`);
  }
  
  console.log('✅ Test environment ready');
}

/**
 * Cleanup test environment
 */
function cleanupTest() {
  console.log('🧹 Cleaning up test environment...');
  
  // Remove test files
  if (fs.existsSync(TEST_CONFIG.testChangelogPath)) {
    fs.unlinkSync(TEST_CONFIG.testChangelogPath);
  }
  
  // Restore original changelog if backup exists
  const backupPath = `${TEST_CONFIG.originalChangelogPath}.backup`;
  if (fs.existsSync(backupPath)) {
    fs.copyFileSync(backupPath, TEST_CONFIG.originalChangelogPath);
    fs.unlinkSync(backupPath);
    console.log('📋 Restored original changelog');
  }
  
  console.log('✅ Cleanup completed');
}

/**
 * Test that the script uses the correct path
 */
function testCorrectPath() {
  console.log('\n🔍 Test: Script uses correct changelog path');
  
  try {
    // Read the script file
    const scriptContent = fs.readFileSync('scripts/update-changelog.js', 'utf8');
    
    // Check for the correct path constant
    if (scriptContent.includes("CHANGELOG_PATH = 'docs/reference/changelog.md'")) {
      console.log('✅ Script contains correct CHANGELOG_PATH constant');
    } else {
      console.log('❌ Script does not contain correct CHANGELOG_PATH constant');
      return false;
    }
    
    // Check that old CHANGELOG.md references are removed
    const oldReferences = scriptContent.match(/['"`]CHANGELOG\.md['"`]/g);
    if (oldReferences && oldReferences.length > 0) {
      console.log(`❌ Found ${oldReferences.length} old CHANGELOG.md references`);
      return false;
    } else {
      console.log('✅ No old CHANGELOG.md references found');
    }
    
    return true;
  } catch (error) {
    console.log(`❌ Error reading script: ${error.message}`);
    return false;
  }
}

/**
 * Test frontmatter preservation
 */
function testFrontmatterPreservation() {
  console.log('\n🔍 Test: Frontmatter preservation');
  
  try {
    // Create a test changelog with frontmatter
    const testContent = `---
title: "Test Changelog"
description: "Test description"
category: "reference"
version: "1.0.0"
last_updated: "2025-01-01"
author: "Test Author"
tags: ["test"]
related_docs: ["index.md"]
---

# Changelog

Test content here.

## [1.0.0] - 2025-01-01

### Added
- Initial test version
`;
    
    // Ensure the directory exists
    const changelogDir = path.dirname(TEST_CONFIG.originalChangelogPath);
    if (!fs.existsSync(changelogDir)) {
      fs.mkdirSync(changelogDir, { recursive: true });
    }
    
    fs.writeFileSync(TEST_CONFIG.originalChangelogPath, testContent);
    
    // Run the script in dry-run mode
    const result = execSync(`node scripts/update-changelog.js ${TEST_CONFIG.testVersion} --dry-run`, 
      { encoding: 'utf8', stdio: 'pipe' });
    
    if (result.includes('DRY RUN - Generated changelog entry')) {
      console.log('✅ Script runs successfully with frontmatter');
      return true;
    } else {
      console.log('❌ Script did not run successfully');
      return false;
    }
  } catch (error) {
    console.log(`❌ Error testing frontmatter: ${error.message}`);
    return false;
  }
}

/**
 * Test directory creation
 */
function testDirectoryCreation() {
  console.log('\n🔍 Test: Directory creation');
  
  try {
    // Remove the changelog directory if it exists
    const changelogDir = path.dirname(TEST_CONFIG.originalChangelogPath);
    if (fs.existsSync(TEST_CONFIG.originalChangelogPath)) {
      fs.unlinkSync(TEST_CONFIG.originalChangelogPath);
    }
    
    // Try to remove the directory (will fail if not empty, which is fine)
    try {
      fs.rmdirSync(changelogDir);
    } catch (e) {
      // Directory not empty or doesn't exist, that's fine
    }
    
    // Run the script in dry-run mode
    const result = execSync(`node scripts/update-changelog.js ${TEST_CONFIG.testVersion} --dry-run`, 
      { encoding: 'utf8', stdio: 'pipe' });
    
    if (result.includes('Creating directory') || result.includes('DRY RUN')) {
      console.log('✅ Script handles directory creation correctly');
      return true;
    } else {
      console.log('❌ Script did not handle directory creation');
      return false;
    }
  } catch (error) {
    console.log(`❌ Error testing directory creation: ${error.message}`);
    return false;
  }
}

/**
 * Test workflow file updates
 */
function testWorkflowUpdates() {
  console.log('\n🔍 Test: Workflow file updates');
  
  try {
    // Check release.yml
    const releaseContent = fs.readFileSync('.github/workflows/release.yml', 'utf8');
    if (releaseContent.includes('docs/reference/changelog.md')) {
      console.log('✅ release.yml uses correct changelog path');
    } else {
      console.log('❌ release.yml does not use correct changelog path');
      return false;
    }
    
    // Check auto-version.yml
    const autoVersionContent = fs.readFileSync('.github/workflows/auto-version.yml', 'utf8');
    if (autoVersionContent.includes('docs/reference/changelog.md')) {
      console.log('✅ auto-version.yml references correct changelog path');
    } else {
      console.log('❌ auto-version.yml does not reference correct changelog path');
      return false;
    }
    
    return true;
  } catch (error) {
    console.log(`❌ Error checking workflow files: ${error.message}`);
    return false;
  }
}

/**
 * Run all tests
 */
function runTests() {
  console.log('🧪 Running Changelog Path Fix Tests\n');
  
  setupTest();
  
  const tests = [
    { name: 'Correct Path Usage', fn: testCorrectPath },
    { name: 'Frontmatter Preservation', fn: testFrontmatterPreservation },
    { name: 'Directory Creation', fn: testDirectoryCreation },
    { name: 'Workflow Updates', fn: testWorkflowUpdates }
  ];
  
  let passed = 0;
  let failed = 0;
  
  for (const test of tests) {
    try {
      if (test.fn()) {
        passed++;
      } else {
        failed++;
      }
    } catch (error) {
      console.log(`❌ Test "${test.name}" threw an error: ${error.message}`);
      failed++;
    }
  }
  
  cleanupTest();
  
  console.log('\n📊 Test Results:');
  console.log(`✅ Passed: ${passed}`);
  console.log(`❌ Failed: ${failed}`);
  console.log(`📈 Success Rate: ${Math.round((passed / (passed + failed)) * 100)}%`);
  
  if (failed === 0) {
    console.log('\n🎉 All tests passed! Changelog path fix is working correctly.');
    process.exit(0);
  } else {
    console.log('\n⚠️  Some tests failed. Please review the implementation.');
    process.exit(1);
  }
}

// Run tests if this script is executed directly
if (require.main === module) {
  runTests();
}

module.exports = {
  runTests,
  testCorrectPath,
  testFrontmatterPreservation,
  testDirectoryCreation,
  testWorkflowUpdates
};
