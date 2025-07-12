#!/usr/bin/env node

/**
 * Test Suite for CODEOWNERS Security Enhancement
 * 
 * Validates that the CODEOWNERS file enhancement is properly implemented:
 * 1. File structure and syntax validation
 * 2. Security-sensitive file pattern coverage
 * 3. Team assignment validation
 * 4. Pattern matching verification
 * 5. Documentation completeness
 */

const fs = require('fs');
const assert = require('assert');

class CodeownersEnhancementTest {
  constructor() {
    this.testResults = [];
    this.passedTests = 0;
    this.failedTests = 0;
    this.codeownersContent = '';
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

  // Test 1: Verify CODEOWNERS file exists and is readable
  testCodeownersFileExists() {
    const filePath = '.github/CODEOWNERS';
    assert(fs.existsSync(filePath), 'CODEOWNERS file should exist');
    
    this.codeownersContent = fs.readFileSync(filePath, 'utf8');
    assert(this.codeownersContent.length > 0, 'CODEOWNERS file should not be empty');
    assert(this.codeownersContent.length > 1000, 'CODEOWNERS file should be comprehensive (>1000 chars)');
  }

  // Test 2: Verify security-sensitive file patterns
  testSecuritySensitivePatterns() {
    const requiredSecurityPatterns = [
      'lib/blaze-wooless-functions.php',
      'lib/setting-helper.php',
      'app/Settings/',
      'scripts/security-scan.js',
      '*klaviyo*',
      '*payment*',
      '*woocommerce*'
    ];

    requiredSecurityPatterns.forEach(pattern => {
      assert(this.codeownersContent.includes(pattern), 
        `Security pattern '${pattern}' should be included in CODEOWNERS`);
    });

    // Verify security lead assignment
    assert(this.codeownersContent.includes('@lanz-2024'), 
      'Security lead (@lanz-2024) should be assigned to security-sensitive files');
  }

  // Test 3: Verify team assignments
  testTeamAssignments() {
    const requiredTeams = [
      '@blaze-commerce/qa',
      '@blaze-commerce/dev',
      '@lanz-2024'
    ];

    requiredTeams.forEach(team => {
      assert(this.codeownersContent.includes(team), 
        `Team '${team}' should be assigned in CODEOWNERS`);
    });

    // Verify default ownership
    assert(this.codeownersContent.includes('*                                   @blaze-commerce/qa'), 
      'Default ownership should be assigned to QA team');
  }

  // Test 4: Verify workflow file security
  testWorkflowSecurity() {
    const workflowPatterns = [
      '.github/workflows/',
      '.github/workflows/claude*.yml',
      'scripts/claude-bot-*.js'
    ];

    workflowPatterns.forEach(pattern => {
      assert(this.codeownersContent.includes(pattern), 
        `Workflow pattern '${pattern}' should be included for security review`);
    });

    // Verify security lead is assigned to workflow files
    const workflowLines = this.codeownersContent.split('\n').filter(line => 
      line.includes('.github/workflows/') && !line.startsWith('#')
    );
    
    assert(workflowLines.length > 0, 'Should have workflow ownership assignments');
    workflowLines.forEach(line => {
      assert(line.includes('@lanz-2024'), 
        `Workflow line should include security lead: ${line}`);
    });
  }

  // Test 5: Verify API integration patterns
  testApiIntegrationPatterns() {
    const apiPatterns = [
      '*klaviyo*',
      '*payment*',
      '*analytics*',
      '*tracking*',
      '*judgeme*',
      '*yotpo*'
    ];

    apiPatterns.forEach(pattern => {
      assert(this.codeownersContent.includes(pattern), 
        `API integration pattern '${pattern}' should be included`);
    });

    // Verify dual review for API integrations
    const apiLines = this.codeownersContent.split('\n').filter(line => 
      apiPatterns.some(pattern => line.includes(pattern)) && !line.startsWith('#')
    );

    apiLines.forEach(line => {
      assert(line.includes('@lanz-2024') && line.includes('@blaze-commerce/qa'), 
        `API integration should have dual review: ${line}`);
    });
  }

  // Test 6: Verify documentation ownership
  testDocumentationOwnership() {
    const docPatterns = [
      'docs/',
      '*.md',
      'README*'
    ];

    docPatterns.forEach(pattern => {
      assert(this.codeownersContent.includes(pattern), 
        `Documentation pattern '${pattern}' should be included`);
    });

    // Verify security documentation has additional security review
    assert(this.codeownersContent.includes('docs/development/security*.md'), 
      'Security documentation should have security lead review');
    assert(this.codeownersContent.includes('docs/security/'), 
      'Security directory should have security lead review');
  }

  // Test 7: Verify emergency override patterns
  testEmergencyOverrides() {
    const emergencyPatterns = [
      'scripts/security-scan.js               @blaze-commerce/dev @blaze-commerce/qa',
      'blazecommerce-wp-plugin.php            @lanz-2024 @blaze-commerce/qa'
    ];

    emergencyPatterns.forEach(pattern => {
      assert(this.codeownersContent.includes(pattern),
        `Emergency override pattern should be included: ${pattern}`);
    });
  }

  // Test 8: Verify file structure and comments
  testFileStructureAndComments() {
    // Check for main sections
    const requiredSections = [
      'DEFAULT OWNERSHIP',
      'SECURITY-SENSITIVE FILES',
      'API INTEGRATIONS',
      'GITHUB WORKFLOWS',
      'DOCUMENTATION',
      'CONFIGURATION FILES',
      'EMERGENCY OVERRIDES'
    ];

    requiredSections.forEach(section => {
      assert(this.codeownersContent.includes(section), 
        `Required section '${section}' should be included`);
    });

    // Check for comprehensive comments
    assert(this.codeownersContent.includes('BlazeCommerce WordPress Plugin'), 
      'Should include repository identification');
    assert(this.codeownersContent.includes('OWNERSHIP HIERARCHY'), 
      'Should include ownership hierarchy explanation');
    assert(this.codeownersContent.includes('TEAM ASSIGNMENTS'), 
      'Should include team assignment explanation');
  }

  // Test 9: Verify configuration file patterns
  testConfigurationFilePatterns() {
    const configPatterns = [
      '.env*',
      'config/',
      '*.config.js',
      '*.config.php',
      'composer.json',
      'package.json'
    ];

    configPatterns.forEach(pattern => {
      assert(this.codeownersContent.includes(pattern), 
        `Configuration pattern '${pattern}' should be included`);
    });
  }

  // Test 10: Verify testing file patterns
  testTestingFilePatterns() {
    const testPatterns = [
      'tests/',
      '*test*.php',
      '*test*.js',
      'scripts/test-security*.js'
    ];

    testPatterns.forEach(pattern => {
      assert(this.codeownersContent.includes(pattern), 
        `Testing pattern '${pattern}' should be included`);
    });

    // Verify security tests have security lead review
    assert(this.codeownersContent.includes('scripts/test-security*.js         @lanz-2024'), 
      'Security test files should have security lead review');
  }

  // Run all tests
  runAllTests() {
    this.log('🚀 Starting CODEOWNERS Enhancement Test Suite', 'info');
    this.log('=' .repeat(60), 'info');
    
    const tests = [
      { name: 'CODEOWNERS File Exists', method: this.testCodeownersFileExists },
      { name: 'Security-Sensitive Patterns', method: this.testSecuritySensitivePatterns },
      { name: 'Team Assignments', method: this.testTeamAssignments },
      { name: 'Workflow Security', method: this.testWorkflowSecurity },
      { name: 'API Integration Patterns', method: this.testApiIntegrationPatterns },
      { name: 'Documentation Ownership', method: this.testDocumentationOwnership },
      { name: 'Emergency Overrides', method: this.testEmergencyOverrides },
      { name: 'File Structure and Comments', method: this.testFileStructureAndComments },
      { name: 'Configuration File Patterns', method: this.testConfigurationFilePatterns },
      { name: 'Testing File Patterns', method: this.testTestingFilePatterns }
    ];
    
    tests.forEach(test => {
      this.runTest(test.name, test.method.bind(this));
    });
    
    this.generateSummary();
  }

  generateSummary() {
    this.log('=' .repeat(60), 'info');
    this.log('🎯 CODEOWNERS Enhancement Test Summary', 'info');
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
      this.log('\n🎉 All tests passed! CODEOWNERS enhancement is working correctly.', 'success');
      this.log('✅ Security-sensitive file patterns implemented', 'success');
      this.log('✅ Team assignments properly configured', 'success');
      this.log('✅ Workflow security controls in place', 'success');
      this.log('✅ API integration review requirements set', 'success');
      this.log('✅ Documentation ownership established', 'success');
      this.log('✅ Emergency override protections active', 'success');
    } else {
      this.log('\n⚠️ Some tests failed. Please review and fix the issues above.', 'warning');
    }
    
    // Additional statistics
    if (this.codeownersContent) {
      const lines = this.codeownersContent.split('\n');
      const nonCommentLines = lines.filter(line => line.trim() && !line.trim().startsWith('#'));
      const securityPatterns = nonCommentLines.filter(line => line.includes('@lanz-2024'));
      
      this.log('\n📈 CODEOWNERS Statistics:', 'info');
      this.log(`Total Lines: ${lines.length}`, 'info');
      this.log(`Active Patterns: ${nonCommentLines.length}`, 'info');
      this.log(`Security Patterns: ${securityPatterns.length}`, 'info');
      this.log(`Security Coverage: ${((securityPatterns.length / nonCommentLines.length) * 100).toFixed(1)}%`, 'info');
    }
    
    return this.failedTests === 0;
  }
}

// Run the test suite
if (require.main === module) {
  const testSuite = new CodeownersEnhancementTest();
  const allTestsPassed = testSuite.runAllTests();
  
  // Exit with appropriate code
  process.exit(allTestsPassed ? 0 : 1);
}

module.exports = CodeownersEnhancementTest;
