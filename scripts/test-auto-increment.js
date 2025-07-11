#!/usr/bin/env node

/**
 * Test Auto-Increment Version Logic
 * Validates the auto-increment conflict resolution functionality
 */

const { 
  tagExists, 
  findNextAvailableVersion, 
  incrementVersion,
  isValidSemver 
} = require('./semver-utils');

console.log('🧪 Testing Auto-Increment Version Logic\n');

/**
 * Test 1: Basic findNextAvailableVersion functionality
 */
function testFindNextAvailableVersion() {
  console.log('📋 Test 1: findNextAvailableVersion functionality');
  
  try {
    // Test with a version that likely doesn't exist
    const testVersion = '999.999.999';
    const nextVersion = findNextAvailableVersion(testVersion, 'patch', { verbose: false });
    
    if (isValidSemver(nextVersion)) {
      console.log(`✅ findNextAvailableVersion works: ${testVersion} → ${nextVersion}`);
      return true;
    } else {
      console.log(`❌ Invalid version returned: ${nextVersion}`);
      return false;
    }
  } catch (error) {
    console.log(`❌ Error in findNextAvailableVersion: ${error.message}`);
    return false;
  }
}

/**
 * Test 2: tagExists functionality
 */
function testTagExists() {
  console.log('\n📋 Test 2: tagExists functionality');
  
  try {
    // Test with a tag that definitely doesn't exist
    const nonExistentTag = 'v999.999.999';
    const exists = tagExists(nonExistentTag);
    
    if (exists === false) {
      console.log(`✅ tagExists correctly reports false for ${nonExistentTag}`);
      return true;
    } else {
      console.log(`❌ tagExists incorrectly reports true for ${nonExistentTag}`);
      return false;
    }
  } catch (error) {
    console.log(`❌ Error in tagExists: ${error.message}`);
    return false;
  }
}

/**
 * Test 3: incrementVersion functionality
 */
function testIncrementVersion() {
  console.log('\n📋 Test 3: incrementVersion functionality');
  
  const testCases = [
    { version: '1.0.0', type: 'patch', expected: '1.0.1' },
    { version: '1.0.0', type: 'minor', expected: '1.1.0' },
    { version: '1.0.0', type: 'major', expected: '2.0.0' },
    { version: '1.9.9', type: 'patch', expected: '1.9.10' }
  ];
  
  let allPassed = true;
  
  for (const testCase of testCases) {
    try {
      const result = incrementVersion(testCase.version, testCase.type);
      if (result === testCase.expected) {
        console.log(`✅ ${testCase.version} + ${testCase.type} = ${result}`);
      } else {
        console.log(`❌ ${testCase.version} + ${testCase.type} = ${result} (expected ${testCase.expected})`);
        allPassed = false;
      }
    } catch (error) {
      console.log(`❌ Error incrementing ${testCase.version}: ${error.message}`);
      allPassed = false;
    }
  }
  
  return allPassed;
}

/**
 * Test 4: Simulate workflow auto-increment logic
 */
function testWorkflowSimulation() {
  console.log('\n📋 Test 4: Workflow auto-increment simulation');
  
  try {
    // Simulate the workflow logic
    const currentVersion = '1.0.0';
    const bumpType = 'patch';
    
    // Step 1: Calculate new version
    let newVersion = incrementVersion(currentVersion, bumpType);
    console.log(`📦 Calculated version: ${currentVersion} → ${newVersion}`);
    
    // Step 2: Check for conflicts (simulate)
    const tagName = `v${newVersion}`;
    const hasConflict = tagExists(tagName);
    
    if (hasConflict) {
      console.log(`⚠️  Git tag conflict detected: ${tagName} already exists`);
      console.log(`🔄 Auto-resolving by finding next available version...`);
      
      // Step 3: Auto-resolve conflict
      const resolvedVersion = findNextAvailableVersion(newVersion, 'patch', { verbose: false });
      console.log(`✅ Conflict resolved: ${newVersion} → ${resolvedVersion}`);
      newVersion = resolvedVersion;
    } else {
      console.log(`✅ No conflicts detected for ${tagName}`);
    }
    
    console.log(`📦 Final version: ${newVersion}`);
    
    // Validate final version
    if (isValidSemver(newVersion)) {
      console.log(`✅ Workflow simulation successful`);
      return true;
    } else {
      console.log(`❌ Invalid final version: ${newVersion}`);
      return false;
    }
    
  } catch (error) {
    console.log(`❌ Workflow simulation failed: ${error.message}`);
    return false;
  }
}

/**
 * Test 5: Edge case handling
 */
function testEdgeCases() {
  console.log('\n📋 Test 5: Edge case handling');
  
  let allPassed = true;
  
  // Test invalid version input
  try {
    incrementVersion('invalid-version', 'patch');
    console.log(`❌ Should have thrown error for invalid version`);
    allPassed = false;
  } catch (error) {
    console.log(`✅ Correctly handles invalid version input`);
  }
  
  // Test invalid bump type
  try {
    incrementVersion('1.0.0', 'invalid-type');
    console.log(`❌ Should have thrown error for invalid bump type`);
    allPassed = false;
  } catch (error) {
    console.log(`✅ Correctly handles invalid bump type`);
  }
  
  return allPassed;
}

/**
 * Run all tests
 */
function runAllTests() {
  const tests = [
    { name: 'findNextAvailableVersion', fn: testFindNextAvailableVersion },
    { name: 'tagExists', fn: testTagExists },
    { name: 'incrementVersion', fn: testIncrementVersion },
    { name: 'workflowSimulation', fn: testWorkflowSimulation },
    { name: 'edgeCases', fn: testEdgeCases }
  ];
  
  let passedTests = 0;
  let totalTests = tests.length;
  
  for (const test of tests) {
    if (test.fn()) {
      passedTests++;
    }
  }
  
  console.log('\n' + '='.repeat(50));
  console.log(`📊 Test Results: ${passedTests}/${totalTests} tests passed`);
  
  if (passedTests === totalTests) {
    console.log('🎉 All auto-increment tests passed!');
    console.log('✅ The auto-increment version system is working correctly');
    process.exit(0);
  } else {
    console.log('❌ Some tests failed. Please check the implementation.');
    process.exit(1);
  }
}

// Run tests if this script is executed directly
if (require.main === module) {
  runAllTests();
}

module.exports = {
  testFindNextAvailableVersion,
  testTagExists,
  testIncrementVersion,
  testWorkflowSimulation,
  testEdgeCases,
  runAllTests
};
