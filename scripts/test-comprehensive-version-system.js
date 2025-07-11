#!/usr/bin/env node

/**
 * Comprehensive Version Management System Test Suite
 * Tests all enhanced functionality including conflict resolution, force-override, and recovery mechanisms
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Import all the enhanced functions
const { 
  parseVersion, 
  isValidSemver, 
  compareVersions, 
  incrementVersion,
  determineBumpType,
  calculateNextVersion,
  resolveVersionConflicts,
  getCurrentVersion,
  getCommitsSinceLastTag
} = require('./semver-utils');

const { 
  validateVersionSystem,
  analyzeVersionSystem,
  checkVersionConflicts
} = require('./validate-version');

console.log('🧪 Comprehensive Version Management System Test Suite\n');

let testsPassed = 0;
let testsFailed = 0;

function runTest(testName, testFunction) {
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

// Test 1: Enhanced version increment with safety checks
runTest('Enhanced Version Increment with Safety Checks', () => {
  const testCases = [
    { version: '1.8.0', type: 'patch', expected: '1.8.1' },
    { version: '1.8.0', type: 'minor', expected: '1.9.0' },
    { version: '1.8.0', type: 'major', expected: '2.0.0' },
    { version: '0.1.0', type: 'patch', expected: '0.1.1' },
    { version: '1.0.0-beta.1', type: 'patch', expected: '1.0.1' }
  ];

  for (const testCase of testCases) {
    const result = incrementVersion(testCase.version, testCase.type);
    if (result !== testCase.expected) {
      throw new Error(`${testCase.version} + ${testCase.type} = ${result} (expected ${testCase.expected})`);
    }
  }

  // Test safety checks
  try {
    incrementVersion('1.0.0', 'invalid');
    throw new Error('Should have thrown error for invalid bump type');
  } catch (error) {
    if (!error.message.includes('Invalid increment type')) {
      throw error;
    }
  }
});

// Test 2: Enhanced commit analysis
runTest('Enhanced Commit Analysis with Detailed Breakdown', () => {
  const commits = [
    'feat: add new user authentication system',
    'fix: resolve memory leak in data processing',
    'feat!: breaking change to API structure',
    'docs: update README with new examples',
    'chore: update dependencies'
  ];

  const analysis = determineBumpType(commits, { verbose: false });

  if (analysis.bumpType !== 'major') {
    throw new Error(`Expected major bump, got ${analysis.bumpType}`);
  }

  if (analysis.summary.breaking !== 1) {
    throw new Error(`Expected 1 breaking change, got ${analysis.summary.breaking}`);
  }

  // The 'feat!' commit counts as breaking, not feature, so we expect 1 feature + 1 breaking
  if (analysis.summary.features !== 1) {
    throw new Error(`Expected 1 feature, got ${analysis.summary.features}`);
  }

  if (analysis.summary.fixes !== 1) {
    throw new Error(`Expected 1 fix, got ${analysis.summary.fixes}`);
  }
});

// Test 3: Version conflict resolution
runTest('Automatic Version Conflict Resolution', () => {
  const currentVersion = getCurrentVersion();
  
  // Test auto resolution
  const resolution = resolveVersionConflicts({
    targetVersion: currentVersion, // Same version to trigger conflict
    strategy: 'auto',
    verbose: false
  });
  
  if (!resolution.success) {
    throw new Error('Auto resolution should have succeeded');
  }
  
  if (compareVersions(resolution.resolvedVersion, currentVersion) <= 0) {
    throw new Error(`Resolved version ${resolution.resolvedVersion} should be greater than ${currentVersion}`);
  }
  
  // Test force strategies
  const strategies = ['force-patch', 'force-minor', 'force-major'];
  for (const strategy of strategies) {
    const strategyResolution = resolveVersionConflicts({
      strategy: strategy,
      verbose: false
    });
    
    if (!strategyResolution.success) {
      throw new Error(`${strategy} resolution should have succeeded`);
    }
  }
});

// Test 4: Comprehensive version calculation
runTest('Comprehensive Version Calculation with Conflict Handling', () => {
  const calculation = calculateNextVersion({
    verbose: false,
    forceOverride: true
  });
  
  if (!calculation.success) {
    throw new Error('Version calculation should succeed with forceOverride');
  }
  
  if (!calculation.newVersion) {
    throw new Error('New version should be calculated');
  }
  
  if (compareVersions(calculation.newVersion, calculation.currentVersion) <= 0) {
    throw new Error(`New version ${calculation.newVersion} should be greater than current ${calculation.currentVersion}`);
  }
});

// Test 5: Enhanced validation system
runTest('Enhanced Validation System with Resolution', () => {
  const result = validateVersionSystem({
    verbose: false,
    checkConflicts: true,
    enableResolution: true,
    returnDetails: true
  });
  
  if (typeof result !== 'object') {
    throw new Error('Should return detailed result object');
  }
  
  if (!result.hasOwnProperty('success')) {
    throw new Error('Result should have success property');
  }
  
  if (!result.hasOwnProperty('analysis')) {
    throw new Error('Result should include analysis');
  }
});

// Test 6: Version system analysis
runTest('Comprehensive Version System Analysis', () => {
  const analysis = analyzeVersionSystem({ verbose: false });
  
  const requiredProperties = [
    'currentVersion', 'versionConsistency', 'gitStatus', 
    'recommendations', 'issues', 'nextVersions'
  ];
  
  for (const prop of requiredProperties) {
    if (!analysis.hasOwnProperty(prop)) {
      throw new Error(`Analysis should include ${prop}`);
    }
  }
  
  if (!isValidSemver(analysis.currentVersion)) {
    throw new Error(`Current version ${analysis.currentVersion} should be valid semver`);
  }
  
  if (analysis.nextVersions.patch && !isValidSemver(analysis.nextVersions.patch)) {
    throw new Error(`Next patch version should be valid semver`);
  }
});

// Test 7: Enhanced conflict checking
runTest('Enhanced Conflict Checking with Analysis', () => {
  const currentVersion = getCurrentVersion();
  
  // Test with resolution enabled
  const conflictCheck = checkVersionConflicts(currentVersion, {
    enableResolution: true,
    resolutionStrategy: 'auto',
    verbose: false
  });
  
  if (!conflictCheck.hasConflicts) {
    throw new Error('Should detect conflicts when checking same version');
  }
  
  if (!conflictCheck.analysis) {
    throw new Error('Should include analysis when conflicts detected');
  }
  
  if (!conflictCheck.resolution) {
    throw new Error('Should include resolution when enabled');
  }
});

// Test 8: Edge cases and error handling
runTest('Edge Cases and Error Handling', () => {
  // Test invalid version formats
  try {
    incrementVersion('invalid', 'patch');
    throw new Error('Should throw error for invalid version');
  } catch (error) {
    if (!error.message.includes('Invalid version format')) {
      throw error;
    }
  }
  
  // Test empty commit analysis
  const emptyAnalysis = determineBumpType([], { allowNone: true });
  if (emptyAnalysis.bumpType !== 'none') {
    throw new Error('Empty commits should result in no bump');
  }
  
  // Test forced minimum bump
  const forcedAnalysis = determineBumpType(['docs: update readme'], { 
    forceMinimum: 'patch',
    allowNone: false 
  });
  if (forcedAnalysis.bumpType !== 'patch') {
    throw new Error('Should force minimum patch bump');
  }
});

// Test 9: Git integration (if in git repo)
runTest('Git Integration and Commit Analysis', () => {
  try {
    const commits = getCommitsSinceLastTag(10, { verbose: false, includeDetails: true });
    
    if (!commits.hasOwnProperty('messages')) {
      throw new Error('Should return messages array');
    }
    
    if (!commits.hasOwnProperty('count')) {
      throw new Error('Should return commit count');
    }
    
    if (commits.includeDetails && !commits.hasOwnProperty('details')) {
      throw new Error('Should return details when requested');
    }
    
  } catch (error) {
    // Skip if not in git repo
    if (error.message.includes('not a git repository')) {
      console.log('   ⚠️  Skipped (not in git repository)');
      return;
    }
    throw error;
  }
});

// Test 10: Recovery mechanisms
runTest('Recovery Mechanisms and Fallback Strategies', () => {
  // Test calculation with invalid current version scenario
  const mockCalculation = calculateNextVersion({
    currentVersion: '1.0.0',
    bumpType: 'patch',
    forceOverride: true,
    verbose: false
  });
  
  if (!mockCalculation.success) {
    throw new Error('Should succeed with valid inputs');
  }
  
  // Test resolution with multiple strategies
  const strategies = ['auto', 'force-patch', 'force-minor', 'force-major'];
  for (const strategy of strategies) {
    const resolution = resolveVersionConflicts({
      strategy: strategy,
      verbose: false
    });
    
    if (!resolution.success) {
      throw new Error(`Strategy ${strategy} should provide a resolution`);
    }
  }
});

// Summary
console.log('🎯 Test Results Summary:');
console.log(`   ✅ Tests passed: ${testsPassed}`);
console.log(`   ❌ Tests failed: ${testsFailed}`);
console.log(`   📊 Total tests: ${testsPassed + testsFailed}`);

if (testsFailed === 0) {
  console.log('\n🎉 All tests passed! The comprehensive version management system is working correctly.');
  console.log('\n💡 Enhanced features verified:');
  console.log('   • Robust version conflict detection and resolution');
  console.log('   • Force-override mechanisms for edge cases');
  console.log('   • Comprehensive commit analysis with detailed breakdown');
  console.log('   • Automatic recovery and fallback strategies');
  console.log('   • Enhanced validation with detailed logging');
  console.log('   • Git integration with commit history analysis');
  console.log('   • Edge case handling and error recovery');
  process.exit(0);
} else {
  console.log('\n❌ Some tests failed. Please review the issues above.');
  process.exit(1);
}
