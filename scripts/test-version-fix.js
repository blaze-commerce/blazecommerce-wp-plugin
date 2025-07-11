#!/usr/bin/env node

/**
 * Test script to verify the version bump fix works correctly
 * This script tests various scenarios that could cause version conflicts
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { 
  parseVersion, 
  isValidSemver, 
  compareVersions, 
  incrementVersion,
  getCurrentVersion 
} = require('./semver-utils');

console.log('🧪 Testing Version Bump Fix\n');

// Test 1: Verify incrementVersion function works correctly
console.log('📋 Test 1: Version increment functionality');
try {
  const testCases = [
    { version: '1.8.0', type: 'patch', expected: '1.8.1' },
    { version: '1.8.0', type: 'minor', expected: '1.9.0' },
    { version: '1.8.0', type: 'major', expected: '2.0.0' },
    { version: '1.9.0', type: 'patch', expected: '1.9.1' },
    { version: '0.1.0', type: 'patch', expected: '0.1.1' }
  ];

  for (const testCase of testCases) {
    const result = incrementVersion(testCase.version, testCase.type);
    if (result === testCase.expected) {
      console.log(`   ✅ ${testCase.version} + ${testCase.type} = ${result}`);
    } else {
      console.log(`   ❌ ${testCase.version} + ${testCase.type} = ${result} (expected ${testCase.expected})`);
      process.exit(1);
    }
  }
} catch (error) {
  console.log(`   ❌ Error in version increment test: ${error.message}`);
  process.exit(1);
}

// Test 2: Verify version comparison works correctly
console.log('\n📋 Test 2: Version comparison functionality');
try {
  const comparisons = [
    { v1: '1.9.0', v2: '1.8.0', expected: 1 },
    { v1: '1.8.0', v2: '1.9.0', expected: -1 },
    { v1: '1.9.0', v2: '1.9.0', expected: 0 },
    { v1: '2.0.0', v2: '1.9.9', expected: 1 }
  ];

  for (const test of comparisons) {
    const result = compareVersions(test.v1, test.v2);
    if (result === test.expected) {
      console.log(`   ✅ ${test.v1} vs ${test.v2} = ${result}`);
    } else {
      console.log(`   ❌ ${test.v1} vs ${test.v2} = ${result} (expected ${test.expected})`);
      process.exit(1);
    }
  }
} catch (error) {
  console.log(`   ❌ Error in version comparison test: ${error.message}`);
  process.exit(1);
}

// Test 3: Verify validation script works with --no-conflicts flag
console.log('\n📋 Test 3: Validation script with --no-conflicts flag');
try {
  // Run validation with --no-conflicts flag (should not check for version conflicts)
  execSync('node scripts/validate-version.js --no-conflicts', { 
    stdio: 'pipe',
    encoding: 'utf8'
  });
  console.log('   ✅ Validation with --no-conflicts flag passed');
} catch (error) {
  console.log(`   ❌ Validation with --no-conflicts flag failed: ${error.message}`);
  process.exit(1);
}

// Test 4: Verify current version detection
console.log('\n📋 Test 4: Current version detection');
try {
  const currentVersion = getCurrentVersion();
  if (isValidSemver(currentVersion)) {
    console.log(`   ✅ Current version detected: ${currentVersion}`);
  } else {
    console.log(`   ❌ Invalid current version format: ${currentVersion}`);
    process.exit(1);
  }
} catch (error) {
  console.log(`   ❌ Error detecting current version: ${error.message}`);
  process.exit(1);
}

// Test 5: Simulate the workflow scenario
console.log('\n📋 Test 5: Simulate workflow scenario');
try {
  const currentVersion = getCurrentVersion();
  console.log(`   📦 Current version: ${currentVersion}`);
  
  // Test each bump type
  const bumpTypes = ['patch', 'minor', 'major'];
  for (const bumpType of bumpTypes) {
    const newVersion = incrementVersion(currentVersion, bumpType);
    const comparison = compareVersions(newVersion, currentVersion);
    
    if (comparison > 0) {
      console.log(`   ✅ ${bumpType} bump: ${currentVersion} → ${newVersion} (comparison: ${comparison})`);
    } else {
      console.log(`   ❌ ${bumpType} bump failed: ${currentVersion} → ${newVersion} (comparison: ${comparison})`);
      process.exit(1);
    }
  }
} catch (error) {
  console.log(`   ❌ Error in workflow simulation: ${error.message}`);
  process.exit(1);
}

console.log('\n🎉 All tests passed! The version bump fix should work correctly.');
console.log('\n💡 Key improvements implemented:');
console.log('   • Post-bump validation now uses --no-conflicts flag');
console.log('   • Added default case to version calculation switch statement');
console.log('   • Improved error handling and logging throughout workflow');
console.log('   • Added safety checks to prevent same-version scenarios');
console.log('   • Enhanced validation error messages for better debugging');
