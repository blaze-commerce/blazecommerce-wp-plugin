#!/usr/bin/env node

/**
 * Verification Script: Version Tag Fix
 * 
 * This script verifies that the version tag issue has been resolved.
 * Run this after deploying the fix to ensure everything works correctly.
 * 
 * @author BlazeCommerce Investigation Team
 * @version 1.0.0
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

class VersionTagFixVerification {
  constructor() {
    this.results = [];
    this.issues = [];
  }

  log(message, type = 'INFO') {
    const timestamp = new Date().toISOString();
    console.log(`[${timestamp}] ${type}: ${message}`);
  }

  success(message) {
    this.log(message, 'SUCCESS');
  }

  error(message) {
    this.log(message, 'ERROR');
    this.issues.push(message);
  }

  /**
   * Verify the fix is properly deployed
   */
  verifyFixDeployment() {
    this.log('=== Verifying Fix Deployment ===');
    
    let allGood = true;

    // Check 1: File change analyzer exists and has the fix
    try {
      const analyzerPath = '.github/scripts/file-change-analyzer.js';
      if (!fs.existsSync(analyzerPath)) {
        this.error('File change analyzer script not found');
        allGood = false;
      } else {
        const content = fs.readFileSync(analyzerPath, 'utf8');
        if (content.includes('trimmedPattern.endsWith(\'/\')')) {
          this.success('✅ File change analyzer has the pattern matching fix');
        } else {
          this.error('❌ File change analyzer does not have the pattern matching fix');
          allGood = false;
        }
      }
    } catch (error) {
      this.error(`Failed to check file change analyzer: ${error.message}`);
      allGood = false;
    }

    // Check 2: Ignore patterns include docs/
    try {
      const patterns = execSync('bash scripts/get-ignore-patterns.sh', { encoding: 'utf8' });
      if (patterns.includes('docs/')) {
        this.success('✅ Ignore patterns include docs/ directory');
      } else {
        this.error('❌ Ignore patterns missing docs/ directory');
        allGood = false;
      }
    } catch (error) {
      this.error(`Failed to check ignore patterns: ${error.message}`);
      allGood = false;
    }

    this.results.push({ test: 'Fix Deployment', passed: allGood });
    return allGood;
  }

  /**
   * Test workflow file detection
   */
  testWorkflowFileDetection() {
    this.log('=== Testing Workflow File Detection ===');
    
    const workflowFiles = [
      '.github/workflows/auto-version.yml',
      '.github/workflows/release.yml',
      '.github/scripts/file-change-analyzer.js',
      '.github/config/claude-patterns.json'
    ];

    let allIgnored = true;

    try {
      const { FileChangeAnalyzer } = require(path.resolve('.github/scripts/file-change-analyzer.js'));
      const analyzer = new FileChangeAnalyzer();

      workflowFiles.forEach(file => {
        const shouldIgnore = analyzer.shouldIgnoreFile(file);
        if (shouldIgnore) {
          this.success(`✅ ${file} correctly ignored`);
        } else {
          this.error(`❌ ${file} NOT ignored (should be ignored)`);
          allIgnored = false;
        }
      });

      // Test check-file-changes.sh script
      const fileList = workflowFiles.join('\n');
      try {
        execSync(`echo "${fileList}" | bash scripts/check-file-changes.sh /dev/stdin`, { 
          encoding: 'utf8',
          stdio: 'pipe'
        });
        this.error('❌ check-file-changes.sh returned exit code 0 (should be 1)');
        allIgnored = false;
      } catch (error) {
        if (error.status === 1) {
          this.success('✅ check-file-changes.sh correctly returned exit code 1');
        } else {
          this.error(`❌ check-file-changes.sh unexpected exit code: ${error.status}`);
          allIgnored = false;
        }
      }

    } catch (error) {
      this.error(`Failed to test workflow file detection: ${error.message}`);
      allIgnored = false;
    }

    this.results.push({ test: 'Workflow File Detection', passed: allIgnored });
    return allIgnored;
  }

  /**
   * Test significant file detection
   */
  testSignificantFileDetection() {
    this.log('=== Testing Significant File Detection ===');
    
    const significantFiles = [
      'blaze-wooless.php',
      'package.json',
      'app/BlazeWooless.php',
      'blocks/src/index.js'
    ];

    let allDetected = true;

    try {
      const { FileChangeAnalyzer } = require(path.resolve('.github/scripts/file-change-analyzer.js'));
      const analyzer = new FileChangeAnalyzer();

      significantFiles.forEach(file => {
        const shouldIgnore = analyzer.shouldIgnoreFile(file);
        if (!shouldIgnore) {
          this.success(`✅ ${file} correctly NOT ignored`);
        } else {
          this.error(`❌ ${file} incorrectly ignored (should trigger version bump)`);
          allDetected = false;
        }
      });

      // Test check-file-changes.sh script
      const fileList = significantFiles.join('\n');
      try {
        execSync(`echo "${fileList}" | bash scripts/check-file-changes.sh /dev/stdin`, { 
          encoding: 'utf8',
          stdio: 'pipe'
        });
        this.success('✅ check-file-changes.sh correctly returned exit code 0');
      } catch (error) {
        this.error(`❌ check-file-changes.sh failed for significant files: exit code ${error.status}`);
        allDetected = false;
      }

    } catch (error) {
      this.error(`Failed to test significant file detection: ${error.message}`);
      allDetected = false;
    }

    this.results.push({ test: 'Significant File Detection', passed: allDetected });
    return allDetected;
  }

  /**
   * Generate verification report
   */
  generateReport() {
    this.log('');
    this.log('📊 VERSION TAG FIX VERIFICATION REPORT');
    this.log('=====================================');

    const totalTests = this.results.length;
    const passedTests = this.results.filter(r => r.passed).length;

    this.log(`Tests Passed: ${passedTests}/${totalTests}`);
    this.log('');

    this.results.forEach(result => {
      const status = result.passed ? '✅ PASSED' : '❌ FAILED';
      this.log(`  ${status}: ${result.test}`);
    });

    this.log('');
    if (passedTests === totalTests) {
      this.success('🎉 VERIFICATION SUCCESSFUL!');
      this.log('');
      this.log('✅ The version tag fix is working correctly:');
      this.log('   - Workflow file changes will NOT trigger version bumps');
      this.log('   - Release creation will be SKIPPED for workflow-only changes');
      this.log('   - Version tags will NOT be created for workflow modifications');
      this.log('   - Significant files still trigger version bumps correctly');
      this.log('');
      this.log('🚀 The fix is ready for production use!');
      this.log('');
      this.log('💡 Next steps:');
      this.log('   1. Create a test PR with only workflow file changes');
      this.log('   2. Verify no version tag is created');
      this.log('   3. Monitor subsequent PRs for correct behavior');
    } else {
      this.error('❌ VERIFICATION FAILED');
      this.log('');
      this.log('🚨 Issues found:');
      this.issues.forEach((issue, index) => {
        this.log(`   ${index + 1}. ${issue}`);
      });
      this.log('');
      this.log('🔧 Please fix these issues before deploying to production.');
    }

    return passedTests === totalTests;
  }

  /**
   * Run complete verification
   */
  runVerification() {
    this.log('🔍 Starting Version Tag Fix Verification...');
    this.log('===========================================');

    const deploymentOk = this.verifyFixDeployment();
    const workflowDetectionOk = this.testWorkflowFileDetection();
    const significantDetectionOk = this.testSignificantFileDetection();

    const success = this.generateReport();
    
    return success;
  }
}

// Run verification if called directly
if (require.main === module) {
  const verification = new VersionTagFixVerification();
  const success = verification.runVerification();
  process.exit(success ? 0 : 1);
}

module.exports = { VersionTagFixVerification };
