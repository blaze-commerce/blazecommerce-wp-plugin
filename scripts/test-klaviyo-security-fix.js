#!/usr/bin/env node

/**
 * Test Suite for Klaviyo API Key Security Fix
 * 
 * Validates that the Klaviyo API key security vulnerability has been properly fixed:
 * 1. No hardcoded API keys in source code
 * 2. Proper settings integration
 * 3. Security scanner detection
 * 4. Function implementation validation
 */

const fs = require('fs');
const assert = require('assert');

class KlaviyoSecurityFixTest {
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

  // Test 1: Verify hardcoded API key removal
  testHardcodedApiKeyRemoval() {
    const filePath = 'lib/blaze-wooless-functions.php';
    assert(fs.existsSync(filePath), 'Functions file should exist');
    
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Check that hardcoded API key is removed
    assert(!content.includes('"W7A7kP"'), 'Hardcoded Klaviyo API key should be removed');
    assert(!content.includes("'W7A7kP'"), 'Hardcoded Klaviyo API key should be removed (single quotes)');
    
    // Check that secure function is used
    assert(content.includes('bw_get_klaviyo_api_key()'), 'Should use secure API key function');
  }

  // Test 2: Verify settings integration
  testSettingsIntegration() {
    const settingsPath = 'app/Settings/GeneralSettings.php';
    assert(fs.existsSync(settingsPath), 'General settings file should exist');
    
    const content = fs.readFileSync(settingsPath, 'utf8');
    
    // Check for Klaviyo API key field
    assert(content.includes('klaviyo_api_key'), 'Settings should include Klaviyo API key field');
    assert(content.includes('Klaviyo API Key'), 'Settings should have proper label');
    assert(content.includes("'type' => 'password'"), 'Should use password field type for security');
  }

  // Test 3: Verify helper function implementation
  testHelperFunction() {
    const helperPath = 'lib/setting-helper.php';
    assert(fs.existsSync(helperPath), 'Helper file should exist');
    
    const content = fs.readFileSync(helperPath, 'utf8');
    
    // Check for secure helper function
    assert(content.includes('bw_get_klaviyo_api_key'), 'Should have secure Klaviyo API key function');
    assert(content.includes('getenv(\'KLAVIYO_API_KEY\')'), 'Should support environment variables');
    assert(content.includes('sanitize_text_field'), 'Should sanitize input');
    assert(content.includes('klaviyo_api_key'), 'Should include klaviyo_api_key in default settings');
  }

  // Test 4: Verify security scanner enhancement
  testSecurityScannerEnhancement() {
    const scannerPath = 'scripts/security-scan.js';
    assert(fs.existsSync(scannerPath), 'Security scanner should exist');
    
    const content = fs.readFileSync(scannerPath, 'utf8');
    
    // Check for Klaviyo pattern detection
    assert(content.includes('Klaviyo API Key'), 'Scanner should detect Klaviyo API keys');
    assert(content.includes('klaviyo[_-]?api[_-]?key'), 'Scanner should have Klaviyo pattern');
  }

  // Test 5: Verify function security enhancements
  testFunctionSecurityEnhancements() {
    const filePath = 'lib/blaze-wooless-functions.php';
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Check for proper escaping in klaviyo_script function
    assert(content.includes('esc_attr'), 'Should use esc_attr for attribute escaping');
    assert(content.includes('esc_url'), 'Should use esc_url for URL escaping');
    
    // Check for security enhancements in is_klaviyo_connected function
    assert(content.includes('urlencode'), 'Should use urlencode for API parameters');
    assert(content.includes('CURLOPT_SSL_VERIFYPEER'), 'Should verify SSL certificates');
    assert(content.includes('CURLOPT_USERAGENT'), 'Should set proper user agent');
  }

  // Test 6: Verify export/import documentation
  testExportImportDocumentation() {
    const exportPath = 'app/Settings/ExportImportSettings.php';
    assert(fs.existsSync(exportPath), 'Export/Import settings should exist');
    
    const content = fs.readFileSync(exportPath, 'utf8');
    
    // Check for security documentation
    assert(content.includes('Klaviyo API key'), 'Should document Klaviyo API key handling');
    assert(content.includes('security considerations'), 'Should mention security considerations');
  }

  // Test 7: Verify documentation completeness
  testDocumentationCompleteness() {
    const docPath = 'docs/development/klaviyo-api-key-security-fix.md';
    assert(fs.existsSync(docPath), 'Security fix documentation should exist');
    
    const content = fs.readFileSync(docPath, 'utf8');
    
    // Check for key sections
    const requiredSections = [
      'Critical Security Vulnerability Resolved',
      'Comprehensive Security Fix Implementation',
      'Security Validation Results',
      'Configuration Instructions',
      'Testing & Verification'
    ];
    
    requiredSections.forEach(section => {
      assert(content.includes(section), `Documentation should include ${section} section`);
    });
  }

  // Test 8: Verify no other hardcoded API keys
  testNoOtherHardcodedKeys() {
    const filePath = 'lib/blaze-wooless-functions.php';
    const content = fs.readFileSync(filePath, 'utf8');
    
    // Check for common API key patterns that might be hardcoded
    const suspiciousPatterns = [
      /api[_-]?key\s*=\s*['"][A-Za-z0-9]{6,}['"]/gi,
      /token\s*=\s*['"][A-Za-z0-9]{6,}['"]/gi,
      /secret\s*=\s*['"][A-Za-z0-9]{6,}['"]/gi
    ];
    
    suspiciousPatterns.forEach((pattern, index) => {
      const matches = content.match(pattern);
      if (matches) {
        // Filter out the secure function calls
        const suspiciousMatches = matches.filter(match => 
          !match.includes('bw_get_') && 
          !match.includes('getenv') && 
          !match.includes('get_option')
        );
        
        assert(suspiciousMatches.length === 0, 
          `Found potentially hardcoded credentials: ${suspiciousMatches.join(', ')}`);
      }
    });
  }

  // Run all tests
  runAllTests() {
    this.log('🚀 Starting Klaviyo API Key Security Fix Test Suite', 'info');
    this.log('=' .repeat(60), 'info');
    
    const tests = [
      { name: 'Hardcoded API Key Removal', method: this.testHardcodedApiKeyRemoval },
      { name: 'Settings Integration', method: this.testSettingsIntegration },
      { name: 'Helper Function Implementation', method: this.testHelperFunction },
      { name: 'Security Scanner Enhancement', method: this.testSecurityScannerEnhancement },
      { name: 'Function Security Enhancements', method: this.testFunctionSecurityEnhancements },
      { name: 'Export/Import Documentation', method: this.testExportImportDocumentation },
      { name: 'Documentation Completeness', method: this.testDocumentationCompleteness },
      { name: 'No Other Hardcoded Keys', method: this.testNoOtherHardcodedKeys }
    ];
    
    tests.forEach(test => {
      this.runTest(test.name, test.method.bind(this));
    });
    
    this.generateSummary();
  }

  generateSummary() {
    this.log('=' .repeat(60), 'info');
    this.log('🎯 Klaviyo Security Fix Test Summary', 'info');
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
      this.log('\n🎉 All tests passed! Klaviyo API key security fix is working correctly.', 'success');
      this.log('✅ Hardcoded API key vulnerability resolved', 'success');
      this.log('✅ Secure configuration implemented', 'success');
      this.log('✅ Security scanner enhanced', 'success');
      this.log('✅ Documentation complete', 'success');
    } else {
      this.log('\n⚠️ Some tests failed. Please review and fix the issues above.', 'warning');
    }
    
    return this.failedTests === 0;
  }
}

// Run the test suite
if (require.main === module) {
  const testSuite = new KlaviyoSecurityFixTest();
  const allTestsPassed = testSuite.runAllTests();
  
  // Exit with appropriate code
  process.exit(allTestsPassed ? 0 : 1);
}

module.exports = KlaviyoSecurityFixTest;
