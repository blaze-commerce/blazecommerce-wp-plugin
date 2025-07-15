#!/usr/bin/env node

/**
 * Comprehensive Error Prevention Script for GitHub Actions Workflows
 * This script proactively identifies and fixes common issues that cause workflow failures
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Color codes for console output
const colors = {
  red: '\x1b[31m',
  green: '\x1b[32m',
  yellow: '\x1b[33m',
  blue: '\x1b[34m',
  cyan: '\x1b[36m',
  reset: '\x1b[0m'
};

function log(message, color = 'reset') {
  console.log(`${colors[color]}${message}${colors.reset}`);
}

function logHeader(message) {
  log(`\n🔧 ${message}`, 'blue');
  log('='.repeat(50), 'blue');
}

class WorkflowErrorPrevention {
  constructor() {
    this.errors = [];
    this.warnings = [];
    this.fixes = [];
  }

  // Check and fix package.json issues
  validatePackageJson() {
    logHeader('Validating package.json');
    
    if (!fs.existsSync('package.json')) {
      this.errors.push('package.json not found');
      return false;
    }

    try {
      const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
      
      // Check version field
      if (!packageJson.version) {
        this.errors.push('package.json missing version field');
        return false;
      }

      // Validate version format
      const versionRegex = /^\d+\.\d+\.\d+(-[a-zA-Z0-9.-]+)?$/;
      if (!versionRegex.test(packageJson.version)) {
        this.errors.push(`Invalid version format: ${packageJson.version}`);
        return false;
      }

      // Check required scripts
      const requiredScripts = [
        'version:patch',
        'version:minor',
        'version:major',
        'update-plugin-version',
        'changelog'
      ];

      const missingScripts = requiredScripts.filter(script => 
        !packageJson.scripts || !packageJson.scripts[script]
      );

      if (missingScripts.length > 0) {
        this.warnings.push(`Missing npm scripts: ${missingScripts.join(', ')}`);
      }

      log('✅ package.json validation passed', 'green');
      return true;

    } catch (error) {
      this.errors.push(`Error parsing package.json: ${error.message}`);
      return false;
    }
  }

  // Check and fix composer.json issues
  validateComposerJson() {
    logHeader('Validating composer.json');
    
    if (!fs.existsSync('composer.json')) {
      this.errors.push('composer.json not found');
      return false;
    }

    try {
      const composerJson = JSON.parse(fs.readFileSync('composer.json', 'utf8'));
      
      // Check required sections
      if (!composerJson.require) {
        this.errors.push('composer.json missing require section');
        return false;
      }

      if (!composerJson['require-dev']) {
        this.warnings.push('composer.json missing require-dev section');
      }

      // Validate composer.json using composer (if available)
      try {
        execSync('which composer', { stdio: 'pipe' });
        // If we get here, composer is available
        try {
          execSync('composer validate --no-check-publish --no-check-all', { stdio: 'pipe' });
          log('✅ composer.json validation passed', 'green');
          return true;
        } catch (validationError) {
          this.errors.push('composer.json validation failed');
          return false;
        }
      } catch (whichError) {
        // composer command not found
        this.warnings.push('composer command not available - skipping validation');
        log('⚠️  composer not available, skipping validation', 'yellow');
        return true;
      }

    } catch (error) {
      this.errors.push(`Error parsing composer.json: ${error.message}`);
      return false;
    }
  }

  // Fix script permissions
  fixScriptPermissions() {
    logHeader('Fixing script permissions');
    
    const scriptFiles = [
      'scripts/check-file-changes.sh',
      'scripts/get-ignore-patterns.sh',
      'scripts/test-workflow-fixes.sh',
      'bin/install-wp-tests.sh'
    ];

    let fixed = 0;
    for (const scriptFile of scriptFiles) {
      if (fs.existsSync(scriptFile)) {
        try {
          const stats = fs.statSync(scriptFile);
          if (!(stats.mode & parseInt('111', 8))) {
            execSync(`chmod +x ${scriptFile}`);
            this.fixes.push(`Made ${scriptFile} executable`);
            fixed++;
          }
        } catch (error) {
          this.warnings.push(`Could not fix permissions for ${scriptFile}: ${error.message}`);
        }
      }
    }

    if (fixed > 0) {
      log(`✅ Fixed permissions for ${fixed} script files`, 'green');
    } else {
      log('✅ All script permissions are correct', 'green');
    }
  }

  // Validate workflow files
  validateWorkflowFiles() {
    logHeader('Validating workflow files');
    
    const workflowFiles = [
      '.github/workflows/tests.yml',
      '.github/workflows/auto-version.yml',
      '.github/workflows/claude-code-review.yml',
      '.github/workflows/claude-approval-gate.yml'
    ];

    let valid = true;
    for (const file of workflowFiles) {
      if (!fs.existsSync(file)) {
        this.errors.push(`Workflow file missing: ${file}`);
        valid = false;
      } else {
        // Basic YAML syntax check
        try {
          const content = fs.readFileSync(file, 'utf8');
          if (!content.includes('name:') || !content.includes('on:')) {
            this.warnings.push(`Workflow file may be malformed: ${file}`);
          }
        } catch (error) {
          this.errors.push(`Could not read workflow file ${file}: ${error.message}`);
          valid = false;
        }
      }
    }

    if (valid) {
      log('✅ All workflow files are present', 'green');
    }
    return valid;
  }

  // Create missing directories
  createMissingDirectories() {
    logHeader('Creating missing directories');
    
    const requiredDirs = [
      'tests/coverage',
      'tests/coverage/html',
      'docs',
      'scripts'
    ];

    let created = 0;
    for (const dir of requiredDirs) {
      if (!fs.existsSync(dir)) {
        try {
          fs.mkdirSync(dir, { recursive: true });
          this.fixes.push(`Created directory: ${dir}`);
          created++;
        } catch (error) {
          this.warnings.push(`Could not create directory ${dir}: ${error.message}`);
        }
      }
    }

    if (created > 0) {
      log(`✅ Created ${created} missing directories`, 'green');
    } else {
      log('✅ All required directories exist', 'green');
    }
  }

  // Validate test environment
  validateTestEnvironment() {
    logHeader('Validating test environment');
    
    const testFiles = [
      'phpunit.xml',
      'bin/install-wp-tests.sh',
      'tests/bootstrap.php'
    ];

    let valid = true;
    for (const file of testFiles) {
      if (!fs.existsSync(file)) {
        this.warnings.push(`Test file missing: ${file}`);
        valid = false;
      }
    }

    // Check if PHPUnit is configured correctly
    if (fs.existsSync('phpunit.xml')) {
      try {
        const content = fs.readFileSync('phpunit.xml', 'utf8');
        if (!content.includes('testsuites') || !content.includes('bootstrap')) {
          this.warnings.push('phpunit.xml may be misconfigured');
        }
      } catch (error) {
        this.warnings.push(`Could not validate phpunit.xml: ${error.message}`);
      }
    }

    if (valid) {
      log('✅ Test environment validation passed', 'green');
    }
    return valid;
  }

  // Generate summary report
  generateReport() {
    logHeader('Error Prevention Summary');
    
    log(`\n📊 Results:`, 'cyan');
    log(`   Errors: ${this.errors.length}`, this.errors.length > 0 ? 'red' : 'green');
    log(`   Warnings: ${this.warnings.length}`, this.warnings.length > 0 ? 'yellow' : 'green');
    log(`   Fixes Applied: ${this.fixes.length}`, this.fixes.length > 0 ? 'green' : 'reset');

    if (this.errors.length > 0) {
      log('\n❌ Errors Found:', 'red');
      this.errors.forEach(error => log(`   • ${error}`, 'red'));
    }

    if (this.warnings.length > 0) {
      log('\n⚠️  Warnings:', 'yellow');
      this.warnings.forEach(warning => log(`   • ${warning}`, 'yellow'));
    }

    if (this.fixes.length > 0) {
      log('\n✅ Fixes Applied:', 'green');
      this.fixes.forEach(fix => log(`   • ${fix}`, 'green'));
    }

    const success = this.errors.length === 0;
    
    if (success) {
      log('\n🎉 All checks passed! Workflows should run successfully.', 'green');
    } else {
      log('\n❌ Please fix the errors above before running workflows.', 'red');
    }

    return success;
  }

  // Run all validations
  runAll() {
    log('🚀 Starting comprehensive workflow error prevention...', 'blue');
    
    this.validatePackageJson();
    this.validateComposerJson();
    this.fixScriptPermissions();
    this.validateWorkflowFiles();
    this.createMissingDirectories();
    this.validateTestEnvironment();
    
    return this.generateReport();
  }
}

// Run if called directly
if (require.main === module) {
  const prevention = new WorkflowErrorPrevention();
  const success = prevention.runAll();
  process.exit(success ? 0 : 1);
}

module.exports = WorkflowErrorPrevention;
