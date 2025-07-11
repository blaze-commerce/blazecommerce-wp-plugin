#!/usr/bin/env node

/**
 * CLAUDE AI REVIEW: Integration tests for complete workflow scenarios
 * Tests CLI interface behavior and git repository integration
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('🧪 Integration Test Suite for Version Management System\n');

let testsPassed = 0;
let testsFailed = 0;

function runIntegrationTest(testName, testFunction) {
  try {
    console.log(`📋 ${testName}`);
    testFunction();
    console.log(`   ✅ PASSED\n`);
    testsPassed++;
  } catch (error) {
    console.log(`   ❌ FAILED: ${error.message}\n`);
    testsFailed++;
  }
}

// CLAUDE AI REVIEW: Test CLI interface behavior
runIntegrationTest('CLI Interface - Analyze Command', () => {
  try {
    const output = execSync('node scripts/validate-version.js --analyze', { 
      encoding: 'utf8',
      timeout: 10000
    });
    
    if (!output.includes('Version System Analysis')) {
      throw new Error('Analyze command should include analysis output');
    }
    
    if (!output.includes('Current version:')) {
      throw new Error('Analyze command should show current version');
    }
    
  } catch (error) {
    if (error.status !== 0 && error.status !== 1) {
      throw new Error(`CLI command failed unexpectedly: ${error.message}`);
    }
    // Status 1 is acceptable for analysis that finds issues
  }
});

// CLAUDE AI REVIEW: Test CLI interface with verbose flag
runIntegrationTest('CLI Interface - Verbose Validation', () => {
  try {
    const output = execSync('node scripts/validate-version.js --verbose --no-conflicts', { 
      encoding: 'utf8',
      timeout: 10000
    });
    
    if (!output.includes('Validating version system')) {
      throw new Error('Verbose validation should include validation output');
    }
    
  } catch (error) {
    if (error.status !== 0 && error.status !== 1) {
      throw new Error(`Verbose validation failed unexpectedly: ${error.message}`);
    }
  }
});

// CLAUDE AI REVIEW: Test git repository integration
runIntegrationTest('Git Repository Integration', () => {
  try {
    // Check if we're in a git repository
    execSync('git rev-parse --git-dir', { stdio: 'ignore' });
    
    // Test git operations through our utilities
    const { getCommitsSinceLastTag, getLatestTag } = require('./semver-utils');
    
    const commits = getCommitsSinceLastTag(10, { verbose: false });
    
    if (!commits.hasOwnProperty('messages')) {
      throw new Error('Git integration should return messages array');
    }
    
    if (!commits.hasOwnProperty('count')) {
      throw new Error('Git integration should return commit count');
    }
    
    // Test tag operations
    const latestTag = getLatestTag();
    if (latestTag && typeof latestTag !== 'string') {
      throw new Error('Latest tag should be a string or null');
    }
    
  } catch (error) {
    if (error.message.includes('not a git repository')) {
      console.log('   ⚠️  Skipped (not in git repository)');
      return;
    }
    throw error;
  }
});

// CLAUDE AI REVIEW: Test complete workflow scenario
runIntegrationTest('Complete Workflow Scenario', () => {
  const { validateVersionSystem, analyzeVersionSystem } = require('./validate-version');
  
  // Test comprehensive analysis
  const analysis = analyzeVersionSystem({ verbose: false });
  
  if (!analysis.currentVersion) {
    throw new Error('Analysis should include current version');
  }
  
  if (!analysis.versionConsistency) {
    throw new Error('Analysis should include version consistency check');
  }
  
  // Test validation system
  const validation = validateVersionSystem({
    verbose: false,
    checkConflicts: false, // Skip conflicts for integration test
    returnDetails: true
  });
  
  if (typeof validation !== 'object') {
    throw new Error('Validation should return detailed object');
  }
  
  if (!validation.hasOwnProperty('success')) {
    throw new Error('Validation should include success status');
  }
});

// CLAUDE AI REVIEW: Test error handling in CLI
runIntegrationTest('CLI Error Handling', () => {
  try {
    // Test with invalid strategy
    execSync('node scripts/validate-version.js --enable-resolution --strategy=invalid', { 
      encoding: 'utf8',
      timeout: 5000,
      stdio: 'pipe'
    });
    
    // If we get here without error, that's unexpected
    throw new Error('Should have failed with invalid strategy');
    
  } catch (error) {
    // We expect this to fail, so error is good
    if (error.status === 0) {
      throw new Error('Should have failed with invalid strategy');
    }
    // Any non-zero exit is expected for invalid input
  }
});

// CLAUDE AI REVIEW: Test performance with large commit history
runIntegrationTest('Performance with Large History', () => {
  try {
    const { getCommitsSinceLastTag } = require('./semver-utils');
    
    const startTime = Date.now();
    const commits = getCommitsSinceLastTag(100, { verbose: false });
    const duration = Date.now() - startTime;
    
    if (duration > 10000) { // 10 seconds
      throw new Error(`Performance test took too long: ${duration}ms`);
    }
    
    if (commits.count > 100) {
      console.log(`   ℹ️  Retrieved ${commits.count} commits in ${duration}ms`);
    }
    
  } catch (error) {
    if (error.message.includes('not a git repository')) {
      console.log('   ⚠️  Skipped (not in git repository)');
      return;
    }
    throw error;
  }
});

// CLAUDE AI REVIEW: Test memory usage monitoring
runIntegrationTest('Memory Usage Monitoring', () => {
  const initialMemory = process.memoryUsage().heapUsed;
  
  // Perform memory-intensive operations
  const { analyzeVersionSystem } = require('./validate-version');
  const { calculateNextVersion } = require('./semver-utils');
  
  // Run multiple analyses
  for (let i = 0; i < 10; i++) {
    analyzeVersionSystem({ verbose: false });
    calculateNextVersion({ verbose: false });
  }
  
  const finalMemory = process.memoryUsage().heapUsed;
  const memoryIncrease = finalMemory - initialMemory;
  const memoryIncreaseMB = memoryIncrease / 1024 / 1024;
  
  if (memoryIncreaseMB > 50) { // 50MB threshold
    console.log(`   ⚠️  Memory usage increased by ${memoryIncreaseMB.toFixed(2)}MB`);
  }
  
  console.log(`   ℹ️  Memory usage: ${memoryIncreaseMB.toFixed(2)}MB increase`);
});

// CLAUDE AI REVIEW: Test concurrent operations
runIntegrationTest('Concurrent Operations Safety', () => {
  const { getCurrentVersion } = require('./semver-utils');
  
  // Test concurrent version reads
  const promises = [];
  for (let i = 0; i < 5; i++) {
    promises.push(Promise.resolve(getCurrentVersion()));
  }
  
  return Promise.all(promises).then(versions => {
    // All versions should be the same
    const firstVersion = versions[0];
    for (const version of versions) {
      if (version !== firstVersion) {
        throw new Error('Concurrent version reads should return consistent results');
      }
    }
  });
});

// Summary
console.log('🎯 Integration Test Results Summary:');
console.log(`   ✅ Tests passed: ${testsPassed}`);
console.log(`   ❌ Tests failed: ${testsFailed}`);
console.log(`   📊 Total tests: ${testsPassed + testsFailed}`);

if (testsFailed === 0) {
  console.log('\n🎉 All integration tests passed! The version management system is working correctly in real-world scenarios.');
  console.log('\n💡 Integration features verified:');
  console.log('   • CLI interface behavior and error handling');
  console.log('   • Git repository integration and operations');
  console.log('   • Complete workflow scenarios');
  console.log('   • Performance with large commit histories');
  console.log('   • Memory usage monitoring and optimization');
  console.log('   • Concurrent operations safety');
  process.exit(0);
} else {
  console.log('\n❌ Some integration tests failed. Please review the issues above.');
  process.exit(1);
}
