/**
 * BlazeCommerce Claude AI Review Bot - Test Suite
 * 
 * Comprehensive test suite for Claude AI bot components including
 * unit tests, integration tests, and error scenario validation.
 */

const fs = require('fs');
const path = require('path');
const assert = require('assert');

// Import modules to test
const VerificationEngine = require('./verification-engine');
const RecommendationTracker = require('./recommendation-tracker');
const ErrorHandler = require('./error-handling-utils');
const config = require('./claude-bot-config');

class ClaudeBotTestSuite {
  constructor() {
    this.testResults = {
      passed: 0,
      failed: 0,
      total: 0,
      failures: []
    };
  }

  /**
   * Run a single test with error handling
   */
  async runTest(testName, testFunction) {
    this.testResults.total++;
    
    try {
      console.log(`🧪 Running: ${testName}`);
      await testFunction();
      this.testResults.passed++;
      console.log(`✅ PASSED: ${testName}`);
    } catch (error) {
      this.testResults.failed++;
      this.testResults.failures.push({ testName, error: error.message });
      console.log(`❌ FAILED: ${testName} - ${error.message}`);
    }
  }

  /**
   * Test VerificationEngine input validation
   */
  async testVerificationEngineValidation() {
    // Test missing GitHub token
    try {
      new VerificationEngine({});
      throw new Error('Should have thrown error for missing token');
    } catch (error) {
      assert(error.message.includes('GitHub token'), 'Should validate GitHub token');
    }

    // Use environment variable or placeholder for test token
    const testToken = process.env.TEST_GITHUB_TOKEN || '[REPLACE_WITH_ACTUAL_VALUE_FROM_USER_CREDENTIALS]';

    // Test missing owner
    try {
      new VerificationEngine({ githubToken: testToken });
      throw new Error('Should have thrown error for missing owner');
    } catch (error) {
      assert(error.message.includes('repository owner'), 'Should validate repository owner');
    }

    // Test missing repo
    try {
      new VerificationEngine({
        githubToken: testToken,
        owner: 'test-owner'
      });
      throw new Error('Should have thrown error for missing repo');
    } catch (error) {
      assert(error.message.includes('repository name'), 'Should validate repository name');
    }

    // Test missing PR number
    try {
      new VerificationEngine({
        githubToken: testToken,
        owner: 'test-owner',
        repo: 'test-repo'
      });
      throw new Error('Should have thrown error for missing PR number');
    } catch (error) {
      assert(error.message.includes('PR number'), 'Should validate PR number');
    }

    // Test valid configuration
    const engine = new VerificationEngine({
      githubToken: testToken,
      owner: 'test-owner',
      repo: 'test-repo',
      prNumber: '123'
    });
    
    assert(engine.owner === 'test-owner', 'Should set owner correctly');
    assert(engine.repo === 'test-repo', 'Should set repo correctly');
    assert(engine.prNumber === 123, 'Should parse PR number as integer');
  }

  /**
   * Test RecommendationTracker path validation
   */
  async testRecommendationTrackerPathValidation() {
    // Test path traversal protection
    try {
      new RecommendationTracker({
        trackingFile: '../../../etc/passwd'
      });
      throw new Error('Should have thrown error for path traversal');
    } catch (error) {
      assert(error.message.includes('Invalid file path'), 'Should prevent path traversal');
    }

    // Test valid path
    const tracker = new RecommendationTracker({
      trackingFile: 'CLAUDE_REVIEW_TRACKING.md'
    });
    
    assert(tracker.trackingFile.includes('.github'), 'Should resolve to .github directory');
  }

  /**
   * Test ErrorHandler retry mechanisms
   */
  async testErrorHandlerRetryLogic() {
    const errorHandler = new ErrorHandler({
      maxRetries: 2,
      baseDelay: 100 // Short delay for testing
    });

    let attempts = 0;
    const failingOperation = async () => {
      attempts++;
      if (attempts < 3) {
        throw new Error('Simulated failure');
      }
      return 'success';
    };

    const result = await errorHandler.executeWithRetry(
      failingOperation,
      'test-operation'
    );

    assert(result === 'success', 'Should succeed after retries');
    assert(attempts === 3, 'Should retry correct number of times');
  }

  /**
   * Test ErrorHandler circuit breaker
   */
  async testErrorHandlerCircuitBreaker() {
    const errorHandler = new ErrorHandler({
      maxRetries: 1,
      circuitBreakerThreshold: 2,
      baseDelay: 10
    });

    const alwaysFailingOperation = async () => {
      throw new Error('Always fails');
    };

    // Trigger circuit breaker
    try {
      await errorHandler.executeWithRetry(alwaysFailingOperation, 'test-cb-1');
    } catch (error) {
      // Expected to fail
    }

    try {
      await errorHandler.executeWithRetry(alwaysFailingOperation, 'test-cb-2');
    } catch (error) {
      // Expected to fail, should open circuit breaker
    }

    // Circuit breaker should now be open
    try {
      await errorHandler.executeWithRetry(alwaysFailingOperation, 'test-cb-3');
      throw new Error('Should have been blocked by circuit breaker');
    } catch (error) {
      assert(error.message.includes('Circuit breaker'), 'Should be blocked by circuit breaker');
    }
  }

  /**
   * Test configuration constants
   */
  async testConfigurationConstants() {
    // Test required configuration sections exist
    assert(config.API, 'Should have API configuration');
    assert(config.VERIFICATION, 'Should have verification configuration');
    assert(config.ERROR_HANDLING, 'Should have error handling configuration');
    assert(config.TIMEOUTS, 'Should have timeout configuration');
    assert(config.PATHS, 'Should have paths configuration');

    // Test specific values
    assert(typeof config.VERIFICATION.CONFIDENCE_THRESHOLD === 'number', 'Confidence threshold should be number');
    assert(config.VERIFICATION.CONFIDENCE_THRESHOLD >= 0 && config.VERIFICATION.CONFIDENCE_THRESHOLD <= 1, 'Confidence threshold should be between 0 and 1');
    
    assert(typeof config.API.MAX_RETRIES === 'number', 'Max retries should be number');
    assert(config.API.MAX_RETRIES > 0, 'Max retries should be positive');

    assert(typeof config.PATHS.TRACKING_FILE === 'string', 'Tracking file path should be string');
    assert(config.PATHS.TRACKING_FILE.includes('.github'), 'Tracking file should be in .github directory');
  }

  /**
   * Test file operations safety
   */
  async testFileOperationsSafety() {
    const tracker = new RecommendationTracker({
      prNumber: '999',
      trackingFile: 'test-tracking.md',
      stateFile: 'test-state.json'
    });

    // Test that file paths are properly validated
    assert(tracker.trackingFile.includes('.github'), 'Tracking file should be in .github directory');
    assert(tracker.stateFile.includes('.github'), 'State file should be in .github directory');
    
    // Test that paths don't contain traversal attempts
    assert(!tracker.trackingFile.includes('..'), 'Tracking file path should not contain traversal');
    assert(!tracker.stateFile.includes('..'), 'State file path should not contain traversal');
  }

  /**
   * Test error classification
   */
  async testErrorClassification() {
    const errorHandler = new ErrorHandler();

    // Test different error types
    const anthropicError = new Error('anthropic api error');
    const githubError = new Error('github rate limit exceeded');
    const timeoutError = new Error('operation timed out');
    const networkError = new Error('network connection failed');
    const validationError = new Error('invalid input provided');

    assert(errorHandler.classifyError(anthropicError) === 'anthropic_api', 'Should classify Anthropic errors');
    assert(errorHandler.classifyError(githubError) === 'github_api', 'Should classify GitHub errors');
    assert(errorHandler.classifyError(timeoutError) === 'timeout', 'Should classify timeout errors');
    assert(errorHandler.classifyError(networkError) === 'network', 'Should classify network errors');
    assert(errorHandler.classifyError(validationError) === 'validation', 'Should classify validation errors');
  }

  /**
   * Test delay calculation
   */
  async testDelayCalculation() {
    const errorHandler = new ErrorHandler({
      baseDelay: 1000,
      maxDelay: 10000
    });

    // Test exponential backoff
    const delay1 = errorHandler.calculateDelay(1);
    const delay2 = errorHandler.calculateDelay(2);
    const delay3 = errorHandler.calculateDelay(3);

    assert(delay1 >= 750 && delay1 <= 1250, 'First delay should be around base delay with jitter');
    assert(delay2 >= 1500 && delay2 <= 2500, 'Second delay should be around 2x base delay with jitter');
    assert(delay3 >= 3000 && delay3 <= 5000, 'Third delay should be around 4x base delay with jitter');

    // Test max delay limit
    const largeDelay = errorHandler.calculateDelay(10);
    assert(largeDelay <= 10000, 'Delay should not exceed max delay');
  }

  /**
   * Run all tests
   */
  async runAllTests() {
    console.log('🚀 Starting Claude AI Bot Test Suite\n');

    await this.runTest('VerificationEngine Input Validation', () => this.testVerificationEngineValidation());
    await this.runTest('RecommendationTracker Path Validation', () => this.testRecommendationTrackerPathValidation());
    await this.runTest('ErrorHandler Retry Logic', () => this.testErrorHandlerRetryLogic());
    await this.runTest('ErrorHandler Circuit Breaker', () => this.testErrorHandlerCircuitBreaker());
    await this.runTest('Configuration Constants', () => this.testConfigurationConstants());
    await this.runTest('File Operations Safety', () => this.testFileOperationsSafety());
    await this.runTest('Error Classification', () => this.testErrorClassification());
    await this.runTest('Delay Calculation', () => this.testDelayCalculation());

    this.printResults();
  }

  /**
   * Print test results
   */
  printResults() {
    console.log('\n📊 Test Results:');
    console.log(`✅ Passed: ${this.testResults.passed}`);
    console.log(`❌ Failed: ${this.testResults.failed}`);
    console.log(`📈 Total: ${this.testResults.total}`);
    console.log(`🎯 Success Rate: ${Math.round((this.testResults.passed / this.testResults.total) * 100)}%`);

    if (this.testResults.failures.length > 0) {
      console.log('\n💥 Failures:');
      this.testResults.failures.forEach(failure => {
        console.log(`  - ${failure.testName}: ${failure.error}`);
      });
    }

    if (this.testResults.failed === 0) {
      console.log('\n🎉 All tests passed! Claude AI Bot is ready for production.');
    } else {
      console.log('\n⚠️ Some tests failed. Please review and fix issues before deployment.');
      process.exit(1);
    }
  }
}

// CLI usage
if (require.main === module) {
  const testSuite = new ClaudeBotTestSuite();
  testSuite.runAllTests().catch(error => {
    console.error('💥 Test suite failed:', error);
    process.exit(1);
  });
}

module.exports = ClaudeBotTestSuite;
