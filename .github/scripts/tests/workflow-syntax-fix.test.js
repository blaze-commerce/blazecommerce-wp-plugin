#!/usr/bin/env node

/**
 * Test Suite for GitHub Actions Workflow Syntax Fixes
 * Tests the fixes for JavaScript syntax errors in GitHub Actions workflows
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { EnhancedErrorHandler, ErrorSeverity, ErrorCategory } = require('../enhanced-error-handler');
const { ClaudeReviewEnhancer } = require('../claude-review-enhancer');

/**
 * Test runner class
 */
class WorkflowSyntaxFixTests {
  constructor() {
    this.tests = [];
    this.passed = 0;
    this.failed = 0;
    this.errorHandler = new EnhancedErrorHandler({ enableSafetyChecks: true });
  }

  /**
   * Add a test case
   * @param {string} name - Test name
   * @param {Function} testFn - Test function
   */
  test(name, testFn) {
    this.tests.push({ name, testFn });
  }

  /**
   * Run all tests
   */
  async runTests() {
    console.log('🧪 Running GitHub Actions Workflow Syntax Fix Tests\n');

    for (const { name, testFn } of this.tests) {
      try {
        console.log(`Testing: ${name}`);
        await testFn();
        console.log(`✅ PASSED: ${name}\n`);
        this.passed++;
      } catch (error) {
        console.error(`❌ FAILED: ${name}`);
        console.error(`   Error: ${error.message}\n`);
        this.failed++;
      }
    }

    this.printSummary();
  }

  /**
   * Print test summary
   */
  printSummary() {
    const total = this.passed + this.failed;
    console.log('📊 Test Summary');
    console.log('================');
    console.log(`Total Tests: ${total}`);
    console.log(`Passed: ${this.passed}`);
    console.log(`Failed: ${this.failed}`);
    console.log(`Success Rate: ${((this.passed / total) * 100).toFixed(1)}%`);

    if (this.failed > 0) {
      process.exit(1);
    } else {
      console.log('\n🎉 All tests passed!');
      process.exit(0);
    }
  }

  /**
   * Assert helper
   * @param {boolean} condition - Condition to check
   * @param {string} message - Error message if condition fails
   */
  assert(condition, message) {
    if (!condition) {
      throw new Error(message);
    }
  }

  /**
   * Assert equals helper
   * @param {*} actual - Actual value
   * @param {*} expected - Expected value
   * @param {string} message - Error message if values don't match
   */
  assertEqual(actual, expected, message) {
    if (actual !== expected) {
      throw new Error(`${message}: expected "${expected}", got "${actual}"`);
    }
  }
}

// Create test instance
const tests = new WorkflowSyntaxFixTests();

// Test 1: Commit hash sanitization
tests.test('Commit hash sanitization in backticks', () => {
  const content = 'Commit SHA: `b224c03` was processed';
  const validation = tests.errorHandler.validateAndSanitizeContent(content);

  tests.assert(!validation.isValid, 'Content with commit hash should be flagged as invalid');
  tests.assert(validation.riskLevel === 'high', 'Commit hash should be high risk');
  tests.assert(validation.sanitizedContent.includes('\\`b224c03`'), 'Commit hash should be properly escaped');
});

// Test 2: Template literal expression removal
tests.test('Template literal expression sanitization', () => {
  const content = 'Value: ${process.env.DANGEROUS_VAR}';
  const validation = tests.errorHandler.validateAndSanitizeContent(content);
  
  tests.assert(!validation.isValid, 'Template literal should be flagged as invalid');
  tests.assert(validation.riskLevel === 'critical', 'Template literal should be critical risk');
  tests.assert(validation.sanitizedContent.includes('[TEMPLATE_EXPRESSION_REMOVED]'), 'Template expression should be removed');
});

// Test 3: Safe content validation
tests.test('Safe content validation', () => {
  const content = 'This is safe content without any dangerous patterns';
  const validation = tests.errorHandler.validateAndSanitizeContent(content);
  
  tests.assert(validation.isValid, 'Safe content should be valid');
  tests.assert(validation.riskLevel === 'low', 'Safe content should be low risk');
  tests.assertEqual(validation.sanitizedContent, content, 'Safe content should not be modified');
});

// Test 4: GitHub Actions output safety
tests.test('GitHub Actions output safety', () => {
  const data = {
    safe_value: 'This is safe',
    dangerous_value: 'Commit: `a1b2c3d` with ${template}',
    multiline_value: 'Line 1\nLine 2\nLine 3'
  };

  const output = tests.errorHandler.createSafeGitHubOutput(data);

  tests.assert(output.includes('safe_value=This is safe'), 'Safe value should be preserved');
  tests.assert(!output.includes('`a1b2c3d`'), 'Dangerous commit hash should be escaped');
  tests.assert(!output.includes('${template}') || output.includes('\\${template}'), 'Template expression should be escaped');
  tests.assert(output.includes('EOF_MULTILINE_VALUE_'), 'Multiline should use EOF format');
});

// Test 5: Claude Review Enhancer output validation
tests.test('Claude Review Enhancer output validation', () => {
  // Mock environment variables
  process.env.PR_NUMBER = '337';
  process.env.GITHUB_REPOSITORY = 'blaze-commerce/test-repo';
  process.env.GITHUB_SHA = 'b224c03a1b2c3d4e5f6789abcdef';
  process.env.GITHUB_TOKEN = 'mock-token';

  const mockClaudeOutput = `## Claude AI Review

  **Commit SHA**: \`b224c03\`

  ### CRITICAL: REQUIRED Issues
  1. Fix the template literal: \${dangerous.code}
  2. Remove eval() call: eval("malicious code")
  `;

  // Test the validation directly with error handler
  const validation = tests.errorHandler.validateAndSanitizeContent(mockClaudeOutput);

  tests.assert(!validation.isValid, 'Claude output with dangerous patterns should be invalid');
  tests.assert(validation.issues.length > 0, 'Issues should be detected');
  tests.assert(validation.sanitizedContent.includes('\\`b224c03`'), 'Commit hash should be properly escaped');
});

// Test 6: JavaScript syntax error handling
tests.test('JavaScript syntax error handling', () => {
  const syntaxError = new Error('SyntaxError: Unexpected identifier \'b224c03\'');
  const result = tests.errorHandler.handleJavaScriptSyntaxError(syntaxError);
  
  tests.assert(result.category === ErrorCategory.JAVASCRIPT_SYNTAX, 'Should be categorized as JavaScript syntax error');
  tests.assert(result.severity === ErrorSeverity.HIGH, 'Should be high severity for commit hash issue');
});

// Test 7: High-risk content sanitization
tests.test('High-risk content sanitization', () => {
  const dangerousContent = `
    const sha = \`b224c03\`;
    const result = \${process.env.SECRET};
    eval("dangerous code");
    Function("return process")();
    require("fs").readFileSync("/etc/passwd");
  `;

  const sanitized = tests.errorHandler.sanitizeHighRiskContent(dangerousContent);

  tests.assert(sanitized.includes('\\`b224c03`'), 'Commit hash should be properly escaped');
  tests.assert(sanitized.includes('[TEMPLATE_EXPRESSION_REMOVED]'), 'Template expression should be removed');
  tests.assert(sanitized.includes('[EVAL_REMOVED]'), 'Eval should be removed');
  tests.assert(sanitized.includes('[FUNCTION_CONSTRUCTOR_REMOVED]'), 'Function constructor should be removed');
  tests.assert(sanitized.includes('[REQUIRE_REMOVED]'), 'Require should be removed');
});

// Test 8: Risk level comparison
tests.test('Risk level comparison', () => {
  tests.assertEqual(tests.errorHandler.compareRiskLevels('low', 'medium'), -1, 'Low should be less than medium');
  tests.assertEqual(tests.errorHandler.compareRiskLevels('high', 'medium'), 1, 'High should be greater than medium');
  tests.assertEqual(tests.errorHandler.compareRiskLevels('critical', 'critical'), 0, 'Critical should equal critical');
});

// Test 9: Empty content handling
tests.test('Empty content handling', () => {
  const validation1 = tests.errorHandler.validateAndSanitizeContent('');
  const validation2 = tests.errorHandler.validateAndSanitizeContent(null);
  const validation3 = tests.errorHandler.validateAndSanitizeContent(undefined);
  
  tests.assert(!validation1.isValid, 'Empty string should be invalid');
  tests.assert(!validation2.isValid, 'Null should be invalid');
  tests.assert(!validation3.isValid, 'Undefined should be invalid');
  
  tests.assertEqual(validation1.sanitizedContent, '', 'Empty content should remain empty');
  tests.assertEqual(validation2.sanitizedContent, '', 'Null should become empty');
  tests.assertEqual(validation3.sanitizedContent, '', 'Undefined should become empty');
});

// Test 10: Multiple risk patterns
tests.test('Multiple risk patterns detection', () => {
  const content = `
    Commit: \`abc123\`
    Template: \${env.VAR}
    Command: \$(whoami)
    Eval: eval("code")
    Process: process.exit(1)
  `;
  
  const validation = tests.errorHandler.validateAndSanitizeContent(content);
  
  tests.assert(!validation.isValid, 'Multiple risks should be invalid');
  tests.assert(validation.riskLevel === 'critical', 'Should be critical due to template literal');
  tests.assert(validation.issues.length >= 4, 'Should detect multiple issues');
});

// Run the tests
if (require.main === module) {
  tests.runTests().catch(error => {
    console.error('Test runner failed:', error);
    process.exit(1);
  });
}

module.exports = { WorkflowSyntaxFixTests };
