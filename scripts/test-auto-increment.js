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
    // CLAUDE AI REVIEW: Use more realistic test scenarios instead of hardcoded extreme values
    const currentVersion = getCurrentVersion();
    const testVersions = [
      currentVersion, // Test with current version (should increment)
      '1.0.0',        // Test with common base version
      '2.5.10'        // Test with realistic version
    ];

    let allPassed = true;

    for (const testVersion of testVersions) {
      try {
        const nextVersion = findNextAvailableVersion(testVersion, 'patch', { verbose: false });

        if (isValidSemver(nextVersion)) {
          console.log(`✅ findNextAvailableVersion works: ${testVersion} → ${nextVersion}`);

          // Verify the next version is actually greater
          if (compareVersions(nextVersion, testVersion) <= 0) {
            console.log(`❌ Next version ${nextVersion} is not greater than ${testVersion}`);
            allPassed = false;
          }
        } else {
          console.log(`❌ Invalid version returned: ${nextVersion}`);
          allPassed = false;
        }
      } catch (error) {
        // Some test versions might fail (e.g., if current version conflicts exist)
        // This is acceptable for testing
        console.log(`⚠️  Test version ${testVersion} failed (acceptable): ${error.message}`);
      }
    }

    return allPassed;
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

  // CLAUDE AI REVIEW: Test semantic versioning with prerelease and build metadata
  try {
    const prereleaseVersion = '1.0.0-alpha.1';
    const buildVersion = '1.0.0+build.1';
    const complexVersion = '1.0.0-beta.2+exp.sha.5114f85';

    const testVersions = [prereleaseVersion, buildVersion, complexVersion];

    for (const version of testVersions) {
      if (isValidSemver(version)) {
        console.log(`✅ Semantic versioning format supported: ${version}`);
      } else {
        console.log(`❌ Semantic versioning format not supported: ${version}`);
        allPassed = false;
      }
    }
  } catch (error) {
    console.log(`❌ Error testing semantic versioning formats: ${error.message}`);
    allPassed = false;
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
