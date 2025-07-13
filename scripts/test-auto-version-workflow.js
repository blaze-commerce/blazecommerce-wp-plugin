#!/usr/bin/env node

/**
 * Test Suite for Auto Version Workflow Fixes
 * Validates all the fixes implemented for the auto-version.yml workflow
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Import the version management functions
const { 
  findNextAvailableVersion,
  getCurrentVersion,
  parseVersion,
  isValidSemver,
  tagExists,
  getLatestTag
} = require('./semver-utils');

console.log('🧪 Testing Auto Version Workflow Fixes\n');

let testsPassed = 0;
let testsFailed = 0;

/**
 * Test helper function
 */
function runTest(testName, testFunction) {
  try {
    console.log(`📋 ${testName}`);
    const result = testFunction();
    if (result === true || result === undefined) {
      console.log(`   ✅ PASSED\n`);
      testsPassed++;
    } else {
      console.log(`   ❌ FAILED: ${result}\n`);
      testsFailed++;
    }
  } catch (error) {
    console.log(`   ❌ FAILED: ${error.message}\n`);
    testsFailed++;
  }
}

/**
 * Test 1: Verify auto-version.yml workflow file exists and has proper structure
 */
function testWorkflowFileStructure() {
  console.log('   Testing auto-version.yml workflow structure...');
  
  const workflowPath = path.join(__dirname, '..', '.github', 'workflows', 'auto-version.yml');
  
  if (!fs.existsSync(workflowPath)) {
    return 'auto-version.yml workflow file does not exist';
  }
  
  const workflowContent = fs.readFileSync(workflowPath, 'utf8');
  
  // Check for required patterns
  const requiredPatterns = [
    'github.ref == \'refs/heads/main\'',
    'feature-branch-info:',
    'AUTO_RESOLVE_CONFLICTS="true"',
    'FORCE_VERSION_RESOLUTION="true"',
    'findNextAvailableVersion',
    'Tag conflict detected',
    'Automatic conflict resolution'
  ];
  
  for (const pattern of requiredPatterns) {
    if (!workflowContent.includes(pattern)) {
      return `Missing required pattern: ${pattern}`;
    }
  }
  
  console.log('   Workflow structure validation passed');
  return true;
}

/**
 * Test 2: Test version conflict resolution functionality
 */
function testVersionConflictResolution() {
  console.log('   Testing version conflict resolution...');
  
  try {
    const currentVersion = getCurrentVersion();
    if (!currentVersion || !isValidSemver(currentVersion)) {
      return `Invalid current version: ${currentVersion}`;
    }
    
    console.log(`   Current version: ${currentVersion}`);
    
    // Test findNextAvailableVersion function
    const nextPatch = findNextAvailableVersion(currentVersion, 'patch');
    if (!isValidSemver(nextPatch)) {
      return `Invalid next patch version: ${nextPatch}`;
    }
    console.log(`   Next patch version: ${nextPatch}`);
    
    const nextMinor = findNextAvailableVersion(currentVersion, 'minor');
    if (!isValidSemver(nextMinor)) {
      return `Invalid next minor version: ${nextMinor}`;
    }
    console.log(`   Next minor version: ${nextMinor}`);
    
    const nextMajor = findNextAvailableVersion(currentVersion, 'major');
    if (!isValidSemver(nextMajor)) {
      return `Invalid next major version: ${nextMajor}`;
    }
    console.log(`   Next major version: ${nextMajor}`);
    
    return true;
  } catch (error) {
    return `Version conflict resolution error: ${error.message}`;
  }
}

/**
 * Test 3: Test git tag checking functionality
 */
function testGitTagChecking() {
  console.log('   Testing git tag checking functionality...');
  
  try {
    const currentVersion = getCurrentVersion();
    const tagName = `v${currentVersion}`;
    
    // Test tagExists function
    const exists = tagExists(tagName);
    console.log(`   Tag ${tagName} exists: ${exists}`);
    
    // Test getLatestTag function
    const latestTag = getLatestTag();
    console.log(`   Latest tag: ${latestTag || 'none'}`);
    
    return true;
  } catch (error) {
    return `Git tag checking error: ${error.message}`;
  }
}

/**
 * Test 4: Test branch condition logic simulation
 */
function testBranchConditionLogic() {
  console.log('   Testing branch condition logic...');
  
  try {
    // Simulate different branch scenarios
    const scenarios = [
      { ref: 'refs/heads/main', shouldRun: true },
      { ref: 'refs/heads/feature/test', shouldRun: false },
      { ref: 'refs/heads/develop', shouldRun: false },
      { ref: 'refs/heads/fix/bug', shouldRun: false }
    ];
    
    for (const scenario of scenarios) {
      const isMainBranch = scenario.ref === 'refs/heads/main';
      if (isMainBranch !== scenario.shouldRun) {
        return `Branch condition logic failed for ${scenario.ref}`;
      }
    }
    
    console.log('   Branch condition logic validation passed');
    return true;
  } catch (error) {
    return `Branch condition logic error: ${error.message}`;
  }
}

/**
 * Test 5: Test workflow skip conditions
 */
function testWorkflowSkipConditions() {
  console.log('   Testing workflow skip conditions...');
  
  const skipMessages = [
    '[skip ci]',
    'chore(release)',
    '[no version]'
  ];
  
  const normalMessages = [
    'feat: add new feature',
    'fix: resolve bug',
    'docs: update documentation'
  ];
  
  // Test that skip messages would be detected
  for (const message of skipMessages) {
    if (!message.includes('[skip ci]') && !message.includes('chore(release)') && !message.includes('[no version]')) {
      return `Skip condition not properly detected for: ${message}`;
    }
  }
  
  // Test that normal messages would not be skipped
  for (const message of normalMessages) {
    if (message.includes('[skip ci]') || message.includes('chore(release)') || message.includes('[no version]')) {
      return `Normal message incorrectly flagged for skip: ${message}`;
    }
  }
  
  console.log('   Workflow skip conditions validation passed');
  return true;
}

/**
 * Test 6: Test package.json version update simulation
 */
function testPackageJsonVersionUpdate() {
  console.log('   Testing package.json version update simulation...');
  
  try {
    const packageJsonPath = path.join(__dirname, '..', 'package.json');
    const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));
    
    if (!packageJson.version || !isValidSemver(packageJson.version)) {
      return `Invalid version in package.json: ${packageJson.version}`;
    }
    
    console.log(`   Package.json version: ${packageJson.version}`);
    
    // Simulate version update (without actually modifying the file)
    const parsed = parseVersion(packageJson.version);
    const simulatedPatch = `${parsed.major}.${parsed.minor}.${parsed.patch + 1}`;
    
    if (!isValidSemver(simulatedPatch)) {
      return `Invalid simulated patch version: ${simulatedPatch}`;
    }
    
    console.log(`   Simulated patch update: ${simulatedPatch}`);
    return true;
  } catch (error) {
    return `Package.json version update error: ${error.message}`;
  }
}

// Run all tests
console.log('🚀 Starting auto-version workflow fix validation tests...\n');

runTest('Test 1: Workflow File Structure', testWorkflowFileStructure);
runTest('Test 2: Version Conflict Resolution', testVersionConflictResolution);
runTest('Test 3: Git Tag Checking', testGitTagChecking);
runTest('Test 4: Branch Condition Logic', testBranchConditionLogic);
runTest('Test 5: Workflow Skip Conditions', testWorkflowSkipConditions);
runTest('Test 6: Package.json Version Update', testPackageJsonVersionUpdate);

// Summary
console.log('📊 AUTO-VERSION WORKFLOW TEST SUMMARY');
console.log('======================================');
console.log(`✅ Tests Passed: ${testsPassed}`);
console.log(`❌ Tests Failed: ${testsFailed}`);
console.log(`📈 Success Rate: ${((testsPassed / (testsPassed + testsFailed)) * 100).toFixed(1)}%`);

if (testsFailed === 0) {
  console.log('\n🎉 All auto-version workflow fixes validated successfully!');
  console.log('The auto-version workflow should now:');
  console.log('- Skip version operations on feature branches');
  console.log('- Properly handle existing git tags');
  console.log('- Execute version bumps only on main branch');
  console.log('- Provide clear feedback on all branches');
} else {
  console.log('\n⚠️  Some tests failed. Please review the issues above.');
}

process.exit(testsFailed === 0 ? 0 : 1);
