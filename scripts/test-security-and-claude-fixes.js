#!/usr/bin/env node

/**
 * Test Suite for Security and Claude Bot Fixes
 * 
 * Validates that all implemented fixes are working correctly:
 * 1. Security scan functionality
 * 2. Claude bot workflow configuration
 * 3. Duplicate comment prevention logic
 * 4. Version pinning verification
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

class SecurityAndClaudeFixesTest {
  constructor() {
    this.testResults = [];
    this.passedTests = 0;
    this.failedTests = 0;
  }

  log(message, type = 'info') {
    const timestamp = new Date().toISOString();
    const prefix = {
      'info': '📋',
      'success': '✅',
      'error': '❌',
      'warning': '⚠️'
    }[type] || '📋';
    
    console.log(`${prefix} [${timestamp}] ${message}`);
  }

  runTest(testName, testFunction) {
    try {
      this.log(`Running test: ${testName}`, 'info');
      testFunction();
      this.log(`PASSED: ${testName}`, 'success');
      this.testResults.push({ name: testName, status: 'PASSED' });
      this.passedTests++;
    } catch (error) {
      this.log(`FAILED: ${testName} - ${error.message}`, 'error');
      this.testResults.push({ name: testName, status: 'FAILED', error: error.message });
      this.failedTests++;
    }
  }

  // Test 1: Security Scanner Functionality
  testSecurityScanner() {
    // Check if security scanner exists
    const scannerPath = 'scripts/security-scan.js';
    assert(fs.existsSync(scannerPath), 'Security scanner script should exist');
    
    // Check if scanner can be required
    const SecurityScanner = require('../scripts/security-scan.js');
    assert(typeof SecurityScanner === 'function', 'Security scanner should be a class');
    
    // Test scanner instantiation
    const scanner = new SecurityScanner();
    assert(scanner instanceof SecurityScanner, 'Should be able to instantiate scanner');
    
    // Check if scanner has required methods
    assert(typeof scanner.run === 'function', 'Scanner should have run method');
    assert(typeof scanner.scanFile === 'function', 'Scanner should have scanFile method');
    assert(typeof scanner.generateReport === 'function', 'Scanner should have generateReport method');
  }

  // Test 2: Claude Workflow Version Pinning
  testClaudeWorkflowVersions() {
    const workflowFiles = [
      '.github/workflows/claude-pr-review.yml',
      '.github/workflows/claude.yml'
    ];

    workflowFiles.forEach(filePath => {
      if (fs.existsSync(filePath)) {
        const content = fs.readFileSync(filePath, 'utf8');
        
        // Check that beta version is not used
        assert(!content.includes('@beta'), `${filePath} should not use @beta version`);
        
        // Check that v1.0.0 is used
        assert(content.includes('@v1.0.0'), `${filePath} should use @v1.0.0 version`);
        
        // Check for proper secret usage
        assert(content.includes('secrets.ANTHROPIC_API_KEY'), `${filePath} should use secrets for API key`);
      }
    });
  }

  // Test 3: Duplicate Comment Prevention Logic
  testDuplicateCommentPrevention() {
    const workflowPath = '.github/workflows/claude-pr-review.yml';
    
    if (fs.existsSync(workflowPath)) {
      const content = fs.readFileSync(workflowPath, 'utf8');
      
      // Check for duplicate prevention step
      assert(content.includes('Check for Existing Error Comments'), 
        'Workflow should include duplicate comment prevention');
      
      // Check for recent error detection logic
      assert(content.includes('has_recent_errors'), 
        'Workflow should check for recent error comments');
      
      // Check for conditional error posting
      assert(content.includes('steps.check-existing-errors.outputs.has_recent_errors != \'true\''), 
        'Error posting should be conditional on recent errors check');
      
      // Check for duplicate prevention logging
      assert(content.includes('Log Duplicate Prevention'), 
        'Workflow should log when duplicates are prevented');
    }
  }

  // Test 4: Hardcoded Credentials Removal
  testHardcodedCredentialsRemoval() {
    const testFilePath = 'scripts/test-claude-bot.js';
    
    if (fs.existsSync(testFilePath)) {
      const content = fs.readFileSync(testFilePath, 'utf8');
      
      // Check that hardcoded test-token is not present
      assert(!content.includes("'test-token'"), 
        'Test file should not contain hardcoded test tokens');
      
      // Check for environment variable usage
      assert(content.includes('process.env.TEST_GITHUB_TOKEN'), 
        'Test file should use environment variables for tokens');
      
      // Check for secure placeholder
      assert(content.includes('[REPLACE_WITH_ACTUAL_VALUE_FROM_USER_CREDENTIALS]'), 
        'Test file should use secure placeholders');
    }
  }

  // Test 5: Documentation Completeness
  testDocumentationCompleteness() {
    const docPath = 'docs/development/security-and-claude-bot-fixes.md';
    
    assert(fs.existsSync(docPath), 'Security and Claude bot fixes documentation should exist');
    
    const content = fs.readFileSync(docPath, 'utf8');
    
    // Check for key sections
    const requiredSections = [
      'Security Improvements',
      'Claude PR Review Bot Fixes',
      'Technical Implementation Details',
      'Impact Analysis',
      'Testing & Validation'
    ];
    
    requiredSections.forEach(section => {
      assert(content.includes(section), `Documentation should include ${section} section`);
    });
  }

  // Test 6: Workflow File Syntax
  testWorkflowSyntax() {
    const workflowDir = '.github/workflows';
    
    if (fs.existsSync(workflowDir)) {
      const workflowFiles = fs.readdirSync(workflowDir)
        .filter(file => file.endsWith('.yml') || file.endsWith('.yaml'));
      
      workflowFiles.forEach(file => {
        const filePath = path.join(workflowDir, file);
        const content = fs.readFileSync(filePath, 'utf8');
        
        // Basic YAML syntax checks
        assert(!content.includes('\t'), `${file} should use spaces, not tabs`);
        assert(content.includes('name:'), `${file} should have a name field`);
        assert(content.includes('on:'), `${file} should have an on field`);
        assert(content.includes('jobs:'), `${file} should have jobs field`);
      });
    }
  }

  // Test 7: Security Scan Integration
  testSecurityScanIntegration() {
    // Test that security scan can run without errors
    const SecurityScanner = require('../scripts/security-scan.js');
    const scanner = new SecurityScanner();

    // Test basic functionality without full scan
    assert(typeof scanner.shouldIgnoreFile === 'function', 'Scanner should have shouldIgnoreFile method');
    assert(typeof scanner.shouldScanFile === 'function', 'Scanner should have shouldScanFile method');
    assert(typeof scanner.isWhitelisted === 'function', 'Scanner should have isWhitelisted method');

    // Test ignore patterns
    assert(scanner.shouldIgnoreFile('node_modules/test.js'), 'Should ignore node_modules files');
    assert(scanner.shouldIgnoreFile('.git/config'), 'Should ignore .git files');
    assert(!scanner.shouldIgnoreFile('src/test.js'), 'Should not ignore regular source files');

    // Test file extension filtering
    assert(scanner.shouldScanFile('test.js'), 'Should scan JavaScript files');
    assert(scanner.shouldScanFile('config.php'), 'Should scan PHP files');
    assert(scanner.shouldScanFile('.env'), 'Should scan environment files');
    assert(!scanner.shouldScanFile('image.png'), 'Should not scan image files');

    // Test whitelist functionality
    assert(scanner.isWhitelisted('test', '${{ secrets.API_KEY }}'), 'Should whitelist GitHub secrets');
    assert(scanner.isWhitelisted('test', 'process.env.API_KEY'), 'Should whitelist environment variables');

    // Test report generation with empty findings
    scanner.findings = [];
    const isClean = scanner.generateReport();
    assert(typeof isClean === 'boolean', 'Security scan should return boolean result');
    assert(isClean === true, 'Empty findings should return clean result');
  }

  // Test 8: Environment Variable Configuration
  testEnvironmentVariableConfiguration() {
    const workflowPath = '.github/workflows/claude-pr-review.yml';
    
    if (fs.existsSync(workflowPath)) {
      const content = fs.readFileSync(workflowPath, 'utf8');
      
      // Check for proper secret references
      const secretReferences = [
        'secrets.ANTHROPIC_API_KEY',
        'secrets.BOT_GITHUB_TOKEN'
      ];
      
      secretReferences.forEach(secretRef => {
        assert(content.includes(secretRef), 
          `Workflow should reference ${secretRef}`);
      });
      
      // Check that secrets are not hardcoded
      assert(!content.match(/sk-[a-zA-Z0-9]{20,}/), 
        'Workflow should not contain hardcoded API keys');
    }
  }

  // Run all tests
  runAllTests() {
    this.log('🚀 Starting Security and Claude Bot Fixes Test Suite', 'info');
    this.log('=' .repeat(60), 'info');
    
    const tests = [
      { name: 'Security Scanner Functionality', method: this.testSecurityScanner },
      { name: 'Claude Workflow Version Pinning', method: this.testClaudeWorkflowVersions },
      { name: 'Duplicate Comment Prevention Logic', method: this.testDuplicateCommentPrevention },
      { name: 'Hardcoded Credentials Removal', method: this.testHardcodedCredentialsRemoval },
      { name: 'Documentation Completeness', method: this.testDocumentationCompleteness },
      { name: 'Workflow File Syntax', method: this.testWorkflowSyntax },
      { name: 'Security Scan Integration', method: this.testSecurityScanIntegration },
      { name: 'Environment Variable Configuration', method: this.testEnvironmentVariableConfiguration }
    ];
    
    tests.forEach(test => {
      this.runTest(test.name, test.method.bind(this));
    });
    
    this.generateSummary();
  }

  generateSummary() {
    this.log('=' .repeat(60), 'info');
    this.log('🎯 Test Suite Summary', 'info');
    this.log('=' .repeat(60), 'info');
    
    this.log(`Total Tests: ${this.testResults.length}`, 'info');
    this.log(`Passed: ${this.passedTests}`, 'success');
    this.log(`Failed: ${this.failedTests}`, this.failedTests > 0 ? 'error' : 'success');
    
    if (this.failedTests > 0) {
      this.log('\n❌ Failed Tests:', 'error');
      this.testResults
        .filter(result => result.status === 'FAILED')
        .forEach(result => {
          this.log(`  - ${result.name}: ${result.error}`, 'error');
        });
    }
    
    const successRate = ((this.passedTests / this.testResults.length) * 100).toFixed(1);
    this.log(`\n📊 Success Rate: ${successRate}%`, successRate === '100.0' ? 'success' : 'warning');
    
    if (this.failedTests === 0) {
      this.log('\n🎉 All tests passed! Security and Claude bot fixes are working correctly.', 'success');
    } else {
      this.log('\n⚠️ Some tests failed. Please review and fix the issues above.', 'warning');
    }
    
    return this.failedTests === 0;
  }
}

// Run the test suite
if (require.main === module) {
  const testSuite = new SecurityAndClaudeFixesTest();
  const allTestsPassed = testSuite.runAllTests();
  
  // Exit with appropriate code
  process.exit(allTestsPassed ? 0 : 1);
}

module.exports = SecurityAndClaudeFixesTest;
