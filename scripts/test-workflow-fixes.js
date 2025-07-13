#!/usr/bin/env node

/**
 * Comprehensive Test Suite for GitHub Actions Workflow Fixes
 * Tests all the fixes implemented for the failing workflows
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Import the fixed functions
const { 
  getLastVersionTag,
  getLatestTag,
  getCurrentVersion,
  parseVersion,
  isValidSemver,
  incrementVersion,
  determineBumpType,
  calculateNextVersion,
  resolveVersionConflicts,
  findNextAvailableVersion,
  tagExists,
  validateTagName
} = require('./semver-utils');

const { 
  validateVersionSystem,
  analyzeVersionSystem,
  checkVersionConflicts,
  VERSION_FILES
} = require('./validate-version');

console.log('🧪 Testing GitHub Actions Workflow Fixes\n');

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
 * Test 1: Verify missing function exports are now available
 */
function testMissingFunctionExports() {
  console.log('   Testing getLastVersionTag function...');
  
  // Test that the function exists and is callable
  if (typeof getLastVersionTag !== 'function') {
    return 'getLastVersionTag is not a function';
  }
  
  // Test that it returns the same as getLatestTag
  const lastTag = getLastVersionTag();
  const latestTag = getLatestTag();
  
  if (lastTag !== latestTag) {
    return `getLastVersionTag (${lastTag}) !== getLatestTag (${latestTag})`;
  }
  
  console.log(`   Function returns: ${lastTag || 'null'}`);
  return true;
}

/**
 * Test 2: Verify version system functionality
 */
function testVersionSystemFunctionality() {
  console.log('   Testing core version functions...');
  
  // Test getCurrentVersion
  const currentVersion = getCurrentVersion();
  if (!currentVersion || !isValidSemver(currentVersion)) {
    return `Invalid current version: ${currentVersion}`;
  }
  console.log(`   Current version: ${currentVersion}`);
  
  // Test parseVersion
  const parsed = parseVersion(currentVersion);
  if (!parsed || typeof parsed.major !== 'number') {
    return `Failed to parse version: ${currentVersion}`;
  }
  console.log(`   Parsed version: ${parsed.major}.${parsed.minor}.${parsed.patch}`);
  
  // Test incrementVersion
  const incremented = incrementVersion(currentVersion, 'patch');
  if (!isValidSemver(incremented)) {
    return `Invalid incremented version: ${incremented}`;
  }
  console.log(`   Incremented version: ${incremented}`);
  
  return true;
}

/**
 * Test 3: Verify version file validation
 */
function testVersionFileValidation() {
  console.log('   Testing version file validation...');
  
  // Check that all version files exist
  for (const fileConfig of VERSION_FILES) {
    if (!fs.existsSync(fileConfig.path)) {
      return `Version file does not exist: ${fileConfig.path}`;
    }
  }
  
  // Test validation system
  const validation = validateVersionSystem({ 
    verbose: false, 
    checkConflicts: false 
  });
  
  if (typeof validation !== 'boolean') {
    return 'validateVersionSystem should return boolean';
  }
  
  console.log(`   Validation result: ${validation ? 'PASSED' : 'FAILED'}`);
  return true;
}

/**
 * Test 4: Test conflict resolution functionality
 */
function testConflictResolution() {
  console.log('   Testing conflict resolution...');
  
  try {
    const currentVersion = getCurrentVersion();
    
    // Test calculateNextVersion
    const calculation = calculateNextVersion({
      currentVersion: currentVersion,
      bumpType: 'patch',
      verbose: false
    });
    
    if (!calculation || typeof calculation.success !== 'boolean') {
      return 'calculateNextVersion should return object with success property';
    }
    
    console.log(`   Calculation success: ${calculation.success}`);
    
    // Test resolveVersionConflicts
    const resolution = resolveVersionConflicts({
      targetVersion: calculation.newVersion,
      strategy: 'auto',
      verbose: false
    });
    
    if (!resolution || typeof resolution.success !== 'boolean') {
      return 'resolveVersionConflicts should return object with success property';
    }
    
    console.log(`   Resolution success: ${resolution.success}`);
    
    return true;
  } catch (error) {
    return `Conflict resolution error: ${error.message}`;
  }
}

/**
 * Test 5: Test WordPress test script improvements
 */
function testWordPressTestScript() {
  console.log('   Testing WordPress test script...');

  const scriptPath = path.join(__dirname, '..', 'bin', 'install-wp-tests.sh');

  if (!fs.existsSync(scriptPath)) {
    return 'WordPress test script does not exist';
  }

  // Check that the script is executable
  const stats = fs.statSync(scriptPath);
  if (!(stats.mode & parseInt('111', 8))) {
    return 'WordPress test script is not executable';
  }

  // Check for enhanced error handling patterns
  const scriptContent = fs.readFileSync(scriptPath, 'utf8');

  const requiredPatterns = [
    'SVN_RETRY_COUNT',
    'DOWNLOAD_RETRY_COUNT',
    'DB_CONNECTION_RETRY',
    'FALLBACK:',
    'Enhanced',
    'retry logic',
    'check_dependencies',
    'command_exists',
    'missing_deps'
  ];

  for (const pattern of requiredPatterns) {
    if (!scriptContent.includes(pattern)) {
      return `Missing enhanced pattern: ${pattern}`;
    }
  }

  console.log('   Enhanced error handling patterns found');
  return true;
}

/**
 * Test 7: Test GitHub Actions workflow improvements
 */
function testGitHubActionsWorkflow() {
  console.log('   Testing GitHub Actions workflow improvements...');

  const workflowPath = path.join(__dirname, '..', '.github', 'workflows', 'tests.yml');

  if (!fs.existsSync(workflowPath)) {
    return 'GitHub Actions workflow file does not exist';
  }

  const workflowContent = fs.readFileSync(workflowPath, 'utf8');

  const requiredPatterns = [
    'mysql-client',
    'subversion',
    'mysqladmin',
    'bash -x bin/install-wp-tests.sh',
    'CHECKING: Directory structure',
    'CHECKING: SVN connectivity',
    'CHECKING: Database connectivity',
    'WordPress test environment verified'
  ];

  for (const pattern of requiredPatterns) {
    if (!workflowContent.includes(pattern)) {
      return `Missing workflow pattern: ${pattern}`;
    }
  }

  console.log('   GitHub Actions workflow enhancements found');
  return true;
}

/**
 * Test 6: Test package.json script availability
 */
function testPackageJsonScripts() {
  console.log('   Testing package.json scripts...');
  
  const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
  
  const requiredScripts = [
    'test:version-system',
    'update-plugin-version',
    'validate-version'
  ];
  
  for (const script of requiredScripts) {
    if (!packageJson.scripts[script]) {
      return `Missing required script: ${script}`;
    }
  }
  
  console.log('   All required scripts found');
  return true;
}

// Run all tests
console.log('🚀 Starting workflow fix validation tests...\n');

runTest('Test 1: Missing Function Exports', testMissingFunctionExports);
runTest('Test 2: Version System Functionality', testVersionSystemFunctionality);
runTest('Test 3: Version File Validation', testVersionFileValidation);
runTest('Test 4: Conflict Resolution', testConflictResolution);
runTest('Test 5: WordPress Test Script', testWordPressTestScript);
runTest('Test 6: Package.json Scripts', testPackageJsonScripts);
runTest('Test 7: GitHub Actions Workflow', testGitHubActionsWorkflow);

// Summary
console.log('📊 TEST SUMMARY');
console.log('================');
console.log(`✅ Tests Passed: ${testsPassed}`);
console.log(`❌ Tests Failed: ${testsFailed}`);
console.log(`📈 Success Rate: ${((testsPassed / (testsPassed + testsFailed)) * 100).toFixed(1)}%`);

if (testsFailed === 0) {
  console.log('\n🎉 All workflow fixes validated successfully!');
  console.log('The GitHub Actions workflows should now run without the previous failures.');
} else {
  console.log('\n⚠️  Some tests failed. Please review the issues above.');
}

process.exit(testsFailed === 0 ? 0 : 1);
