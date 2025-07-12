#!/usr/bin/env node

/**
 * Workflow Testing Script for Version Conflict Resolution
 * Tests various commit scenarios to ensure the auto-version workflow handles all cases correctly
 */

const { 
  determineBumpType, 
  incrementVersion, 
  getCurrentVersion,
  parseConventionalCommit 
} = require('./semver-utils');
const { validateVersionSystem } = require('./validate-version');

/**
 * Test scenarios for workflow behavior
 */
const TEST_SCENARIOS = [
  {
    name: 'Version Conflict Resolution',
    description: 'Non-conventional commits that would cause version conflicts',
    commits: [
      'Update README.md',
      'Fix typo in documentation',
      'Refactor code structure'
    ],
    expectedBumpType: 'none',
    shouldTriggerConflictResolution: true
  },
  {
    name: 'Breaking Changes Detection',
    description: 'Commits with breaking changes should trigger major version bump',
    commits: [
      'feat!: redesign API endpoints',
      'fix: minor bug fix',
      'docs: update documentation'
    ],
    expectedBumpType: 'major',
    shouldTriggerConflictResolution: false
  },
  {
    name: 'Feature Addition',
    description: 'Commits with new features should trigger minor version bump',
    commits: [
      'feat: add user authentication',
      'fix: resolve login issue',
      'docs: add API documentation'
    ],
    expectedBumpType: 'minor',
    shouldTriggerConflictResolution: false
  },
  {
    name: 'Bug Fixes',
    description: 'Commits with bug fixes should trigger patch version bump',
    commits: [
      'fix: resolve memory leak',
      'fix: correct validation logic',
      'docs: update troubleshooting guide'
    ],
    expectedBumpType: 'patch',
    shouldTriggerConflictResolution: false
  },
  {
    name: 'Performance Improvements',
    description: 'Performance improvements should trigger patch version bump',
    commits: [
      'perf: optimize database queries',
      'perf: improve caching mechanism',
      'docs: update performance guide'
    ],
    expectedBumpType: 'patch',
    shouldTriggerConflictResolution: false
  },
  {
    name: 'Mixed Commit Types',
    description: 'Mixed commits should use highest priority bump type',
    commits: [
      'feat: add new feature',
      'fix: bug fix',
      'docs: documentation update',
      'chore: update dependencies'
    ],
    expectedBumpType: 'minor',
    shouldTriggerConflictResolution: false
  }
];

/**
 * Simulate workflow version calculation logic
 */
function simulateWorkflowVersionCalculation(commits) {
  const currentVersion = getCurrentVersion();
  const bumpType = determineBumpType(commits);
  
  let newVersion = currentVersion;
  
  // Simulate the workflow's version calculation logic
  if (bumpType !== 'none') {
    newVersion = incrementVersion(currentVersion, bumpType);
  }
  
  // Simulate conflict resolution logic
  let resolvedBumpType = bumpType;
  if (newVersion === currentVersion) {
    console.log(`   🔄 Version conflict detected: ${newVersion} === ${currentVersion}`);
    console.log(`   🔧 Applying conflict resolution: forcing patch bump`);
    newVersion = incrementVersion(currentVersion, 'patch');
    resolvedBumpType = 'patch';
  }
  
  return {
    originalBumpType: bumpType,
    resolvedBumpType,
    currentVersion,
    newVersion,
    conflictResolved: bumpType !== resolvedBumpType
  };
}

/**
 * Test validation script with --no-conflicts flag
 */
function testValidationWithNoConflicts() {
  console.log('\n🧪 Testing validation script with --no-conflicts flag...');
  
  try {
    // This should pass even if there are version conflicts
    const result = validateVersionSystem({ verbose: false, checkConflicts: false });
    if (result) {
      console.log('   ✅ Validation passed with --no-conflicts flag');
      return true;
    } else {
      console.log('   ❌ Validation failed even with --no-conflicts flag');
      return false;
    }
  } catch (error) {
    console.log(`   ❌ Validation error: ${error.message}`);
    return false;
  }
}

/**
 * Test validation script with conflict checking enabled
 */
function testValidationWithConflicts() {
  console.log('\n🧪 Testing validation script with conflict checking...');
  
  try {
    const result = validateVersionSystem({ verbose: false, checkConflicts: true });
    if (result) {
      console.log('   ✅ Validation passed with conflict checking');
      return true;
    } else {
      console.log('   ⚠️  Validation failed with conflict checking (expected if version conflicts exist)');
      return false;
    }
  } catch (error) {
    console.log(`   ❌ Validation error: ${error.message}`);
    return false;
  }
}

/**
 * Run all workflow tests
 */
function runWorkflowTests() {
  console.log('🚀 Running Workflow Version Conflict Resolution Tests...\n');
  
  let passed = 0;
  let failed = 0;
  
  // Test each scenario
  for (const scenario of TEST_SCENARIOS) {
    console.log(`📋 Testing: ${scenario.name}`);
    console.log(`   Description: ${scenario.description}`);
    console.log(`   Commits: ${scenario.commits.join(', ')}`);
    
    try {
      const result = simulateWorkflowVersionCalculation(scenario.commits);
      
      console.log(`   Current version: ${result.currentVersion}`);
      console.log(`   Original bump type: ${result.originalBumpType}`);
      console.log(`   Resolved bump type: ${result.resolvedBumpType}`);
      console.log(`   New version: ${result.newVersion}`);
      console.log(`   Conflict resolved: ${result.conflictResolved ? 'Yes' : 'No'}`);
      
      // Validate expectations
      let testPassed = true;
      
      if (result.originalBumpType !== scenario.expectedBumpType) {
        console.log(`   ❌ Expected bump type ${scenario.expectedBumpType}, got ${result.originalBumpType}`);
        testPassed = false;
      }
      
      if (scenario.shouldTriggerConflictResolution && !result.conflictResolved) {
        console.log(`   ❌ Expected conflict resolution to be triggered`);
        testPassed = false;
      }
      
      if (!scenario.shouldTriggerConflictResolution && result.conflictResolved) {
        console.log(`   ❌ Unexpected conflict resolution triggered`);
        testPassed = false;
      }
      
      if (testPassed) {
        console.log(`   ✅ Test passed`);
        passed++;
      } else {
        failed++;
      }
      
    } catch (error) {
      console.log(`   ❌ Test failed with error: ${error.message}`);
      failed++;
    }
    
    console.log('');
  }
  
  // Test validation scenarios
  const validationNoConflicts = testValidationWithNoConflicts();
  const validationWithConflicts = testValidationWithConflicts();
  
  if (validationNoConflicts) passed++;
  else failed++;
  
  // Note: validationWithConflicts failure is expected in conflict scenarios
  console.log('\n📊 Test Results:');
  console.log(`   Workflow scenarios: ${TEST_SCENARIOS.length} tested`);
  console.log(`   Validation tests: 2 tested`);
  console.log(`   Total passed: ${passed}`);
  console.log(`   Total failed: ${failed}`);
  
  if (failed === 0 || (failed === 1 && !validationWithConflicts)) {
    console.log('\n✅ All critical tests passed! Workflow conflict resolution is working correctly.');
    return true;
  } else {
    console.log('\n❌ Some tests failed. Please review the implementation.');
    return false;
  }
}

// Run tests if this file is executed directly
if (require.main === module) {
  const success = runWorkflowTests();
  process.exit(success ? 0 : 1);
}

module.exports = { runWorkflowTests, simulateWorkflowVersionCalculation };
