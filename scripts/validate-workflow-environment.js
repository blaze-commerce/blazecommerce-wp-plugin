#!/usr/bin/env node

/**
 * Workflow Environment Validation Script
 * Validates that all required files, scripts, and dependencies are present
 * for GitHub Actions workflows to run successfully.
 */

const fs = require('fs');
const path = require('path');

// Color codes for console output
const colors = {
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  reset: '\x1b[0m'
};

function log(message, color = 'reset') {
  console.log(`${colors[color]}${message}${colors.reset}`);
}

function validateFile(filePath, description) {
  if (fs.existsSync(filePath)) {
    log(`✅ ${description}: ${filePath}`, 'green');
    return true;
  } else {
    log(`❌ ${description}: ${filePath} - NOT FOUND`, 'red');
    return false;
  }
}

function validatePackageJson() {
  log('\n🔍 Validating package.json...', 'blue');
  
  if (!fs.existsSync('package.json')) {
    log('❌ package.json not found', 'red');
    return false;
  }

  try {
    const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
    
    // Check version field
    if (!packageJson.version) {
      log('❌ package.json missing version field', 'red');
      return false;
    }
    
    // Validate version format
    const versionRegex = /^\d+\.\d+\.\d+(-[a-zA-Z0-9.-]+)?$/;
    if (!versionRegex.test(packageJson.version)) {
      log(`❌ Invalid version format: ${packageJson.version}`, 'red');
      return false;
    }
    
    log(`✅ package.json version: ${packageJson.version}`, 'green');
    
    // Check required scripts
    const requiredScripts = [
      'version:patch',
      'version:minor', 
      'version:major',
      'update-plugin-version',
      'changelog'
    ];
    
    let scriptsValid = true;
    for (const script of requiredScripts) {
      if (!packageJson.scripts || !packageJson.scripts[script]) {
        log(`❌ Missing npm script: ${script}`, 'red');
        scriptsValid = false;
      } else {
        log(`✅ npm script found: ${script}`, 'green');
      }
    }
    
    return scriptsValid;
    
  } catch (error) {
    log(`❌ Error parsing package.json: ${error.message}`, 'red');
    return false;
  }
}

function validateComposerJson() {
  log('\n🔍 Validating composer.json...', 'blue');
  
  if (!fs.existsSync('composer.json')) {
    log('❌ composer.json not found', 'red');
    return false;
  }

  try {
    const composerJson = JSON.parse(fs.readFileSync('composer.json', 'utf8'));
    
    // Check required sections
    if (!composerJson.require) {
      log('❌ composer.json missing require section', 'red');
      return false;
    }
    
    if (!composerJson['require-dev']) {
      log('❌ composer.json missing require-dev section', 'red');
      return false;
    }
    
    log('✅ composer.json structure is valid', 'green');
    return true;
    
  } catch (error) {
    log(`❌ Error parsing composer.json: ${error.message}`, 'red');
    return false;
  }
}

function validateWorkflowFiles() {
  log('\n🔍 Validating workflow files...', 'blue');
  
  const workflowFiles = [
    '.github/workflows/workflow-preflight-check.yml',
    '.github/workflows/claude-code-review.yml',
    '.github/workflows/claude-approval-gate.yml',
    '.github/workflows/auto-version.yml',
    '.github/workflows/tests.yml'
  ];
  
  let allValid = true;
  for (const file of workflowFiles) {
    if (!validateFile(file, 'Workflow file')) {
      allValid = false;
    }
  }
  
  return allValid;
}

function validateScripts() {
  log('\n🔍 Validating required scripts...', 'blue');
  
  const requiredScripts = [
    'scripts/semver-utils.js',
    'scripts/update-version.js',
    'scripts/validate-version.js',
    'scripts/update-changelog.js',
    'scripts/check-file-changes.sh',
    'scripts/get-ignore-patterns.sh'
  ];
  
  let allValid = true;
  for (const script of requiredScripts) {
    if (!validateFile(script, 'Script file')) {
      allValid = false;
    } else {
      // Check if shell scripts are executable
      if (script.endsWith('.sh')) {
        try {
          const stats = fs.statSync(script);
          if (!(stats.mode & parseInt('111', 8))) {
            log(`⚠️  Script not executable: ${script}`, 'yellow');
          }
        } catch (error) {
          log(`⚠️  Could not check permissions for: ${script}`, 'yellow');
        }
      }
    }
  }
  
  return allValid;
}

function validateTestEnvironment() {
  log('\n🔍 Validating test environment...', 'blue');
  
  const testFiles = [
    'phpunit.xml',
    'bin/install-wp-tests.sh',
    'tests/bootstrap.php'
  ];
  
  let allValid = true;
  for (const file of testFiles) {
    if (!validateFile(file, 'Test file')) {
      allValid = false;
    }
  }
  
  return allValid;
}

function main() {
  log('🚀 Starting workflow environment validation...', 'blue');
  
  const validations = [
    validatePackageJson(),
    validateComposerJson(),
    validateWorkflowFiles(),
    validateScripts(),
    validateTestEnvironment()
  ];
  
  const allValid = validations.every(v => v);
  
  log('\n📊 Validation Summary:', 'blue');
  if (allValid) {
    log('✅ All validations passed! Workflow environment is ready.', 'green');
    process.exit(0);
  } else {
    log('❌ Some validations failed. Please fix the issues above.', 'red');
    process.exit(1);
  }
}

// Run validation if called directly
if (require.main === module) {
  main();
}

module.exports = {
  validatePackageJson,
  validateComposerJson,
  validateWorkflowFiles,
  validateScripts,
  validateTestEnvironment
};
