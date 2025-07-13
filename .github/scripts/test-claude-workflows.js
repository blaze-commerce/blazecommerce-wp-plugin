#!/usr/bin/env node

/**
 * Claude Workflows Test Script
 * Tests the Claude AI review and approval gate workflows
 * Validates status management and workflow communication
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { ClaudeStatusManager, ClaudeStatusUtils, CLAUDE_STATES } = require('./claude-status-manager');
const { Logger } = require('./file-change-analyzer');

/**
 * Test Suite for Claude Workflows
 */
class ClaudeWorkflowTests {
  constructor() {
    this.testResults = [];
    this.statusManager = null;
  }

  /**
   * Initialize test environment
   */
  async initialize() {
    try {
      // Mock environment for testing
      process.env.GITHUB_REPOSITORY = process.env.GITHUB_REPOSITORY || 'blaze-commerce/test-repo';
      process.env.GITHUB_TOKEN = process.env.GITHUB_TOKEN || 'mock-token';
      
      this.statusManager = ClaudeStatusUtils.fromEnvironment();
      Logger.info('Test environment initialized');
      return true;
    } catch (error) {
      Logger.error(`Failed to initialize test environment: ${error.message}`);
      return false;
    }
  }

  /**
   * Run a test case
   * @param {string} testName - Name of the test
   * @param {Function} testFunction - Test function to run
   */
  async runTest(testName, testFunction) {
    try {
      Logger.info(`Running test: ${testName}`);
      const startTime = Date.now();
      
      await testFunction();
      
      const duration = Date.now() - startTime;
      this.testResults.push({
        name: testName,
        status: 'PASS',
        duration,
        error: null
      });
      
      Logger.success(`✅ ${testName} - PASSED (${duration}ms)`);
    } catch (error) {
      this.testResults.push({
        name: testName,
        status: 'FAIL',
        duration: 0,
        error: error.message
      });
      
      Logger.error(`❌ ${testName} - FAILED: ${error.message}`);
    }
  }

  /**
   * Test status manager initialization
   */
  async testStatusManagerInitialization() {
    if (!this.statusManager) {
      throw new Error('Status manager not initialized');
    }
    
    if (!this.statusManager.githubToken) {
      throw new Error('GitHub token not available');
    }
    
    if (!this.statusManager.repoOwner || !this.statusManager.repoName) {
      throw new Error('Repository information not available');
    }
  }

  /**
   * Test status state definitions
   */
  async testStatusStates() {
    const requiredStates = ['PENDING', 'SUCCESS', 'FAILURE', 'ERROR'];
    
    for (const state of requiredStates) {
      if (!CLAUDE_STATES[state]) {
        throw new Error(`Missing required state: ${state}`);
      }
    }
    
    // Verify state values are valid GitHub status states
    const validGitHubStates = ['pending', 'success', 'failure', 'error'];
    for (const [key, value] of Object.entries(CLAUDE_STATES)) {
      if (!validGitHubStates.includes(value)) {
        throw new Error(`Invalid GitHub status state: ${key} = ${value}`);
      }
    }
  }

  /**
   * Test workflow state analysis
   */
  async testWorkflowStateAnalysis() {
    const mockSha = 'abc123def456';
    
    // Mock the getStatusChecks method for testing
    const originalMethod = this.statusManager.getStatusChecks;
    this.statusManager.getStatusChecks = async () => [
      {
        context: 'claude-ai/review',
        state: 'success',
        description: 'Claude AI review completed - No issues found'
      },
      {
        context: 'claude-ai/approval-required',
        state: 'success',
        description: 'Approved: No blocking issues found'
      }
    ];
    
    try {
      const state = await this.statusManager.getWorkflowState(mockSha);
      
      if (!state.review || !state.approval) {
        throw new Error('Missing review or approval state');
      }
      
      if (state.review.state !== 'success' || state.approval.state !== 'success') {
        throw new Error('Incorrect state parsing');
      }
      
      if (!state.canMerge) {
        throw new Error('Should be able to merge with success states');
      }
      
    } finally {
      // Restore original method
      this.statusManager.getStatusChecks = originalMethod;
    }
  }

  /**
   * Test PR context extraction
   */
  async testPRContextExtraction() {
    // Test with different environment setups
    const testCases = [
      {
        env: { PR_NUMBER: '123', GITHUB_SHA: 'abc123' },
        expected: { prNumber: '123', sha: 'abc123' }
      },
      {
        env: { GITHUB_EVENT_NUMBER: '456', GITHUB_SHA: 'def456' },
        expected: { prNumber: '456', sha: 'def456' }
      }
    ];
    
    for (const testCase of testCases) {
      // Backup original env
      const originalEnv = { ...process.env };
      
      try {
        // Set test environment
        Object.assign(process.env, testCase.env);
        
        const context = ClaudeStatusUtils.getPRContext();
        
        if (context.prNumber !== testCase.expected.prNumber) {
          throw new Error(`Expected PR number ${testCase.expected.prNumber}, got ${context.prNumber}`);
        }
        
        if (context.sha !== testCase.expected.sha) {
          throw new Error(`Expected SHA ${testCase.expected.sha}, got ${context.sha}`);
        }
        
      } finally {
        // Restore original environment
        process.env = originalEnv;
      }
    }
  }

  /**
   * Test GitHub Actions output format
   */
  async testGitHubActionsOutput() {
    const mockState = {
      review: { state: 'success', exists: true },
      approval: { state: 'success', exists: true },
      needsReview: false,
      canMerge: true
    };
    
    // Capture console output
    const originalLog = console.log;
    const outputs = [];
    console.log = (message) => outputs.push(message);
    
    try {
      ClaudeStatusUtils.outputForGitHubActions(mockState);
      
      const expectedOutputs = [
        'claude_review_state=success',
        'claude_approval_state=success',
        'claude_needs_review=false',
        'claude_can_merge=true',
        'claude_review_exists=true',
        'claude_approval_exists=true'
      ];
      
      for (const expected of expectedOutputs) {
        if (!outputs.includes(expected)) {
          throw new Error(`Missing expected output: ${expected}`);
        }
      }
      
    } finally {
      console.log = originalLog;
    }
  }

  /**
   * Test error handling
   */
  async testErrorHandling() {
    // Test with invalid repository - use setStatus which should throw errors
    const invalidStatusManager = new ClaudeStatusManager('invalid-token', 'invalid', 'repo');

    try {
      await invalidStatusManager.setStatus('invalid-sha', 'success', 'test-context', 'test description');
      // Should not reach here if error handling works
      throw new Error('Expected error was not thrown');
    } catch (error) {
      // Accept various error types that indicate proper error handling
      const validErrorTypes = [
        'GitHub API error',
        'GitHub token not available',
        'Failed to set status',
        'Unauthorized',
        'fetch is not defined'
      ];

      const hasValidError = validErrorTypes.some(errorType =>
        error.message.includes(errorType)
      );

      if (!hasValidError) {
        throw new Error(`Unexpected error type: ${error.message}`);
      }

      // If we got here, error handling is working correctly
    }
  }

  /**
   * Run all tests
   */
  async runAllTests() {
    Logger.info('🧪 Starting Claude Workflows Test Suite');
    
    if (!(await this.initialize())) {
      Logger.error('Failed to initialize test environment');
      return false;
    }
    
    const tests = [
      ['Status Manager Initialization', () => this.testStatusManagerInitialization()],
      ['Status States Definition', () => this.testStatusStates()],
      ['Workflow State Analysis', () => this.testWorkflowStateAnalysis()],
      ['PR Context Extraction', () => this.testPRContextExtraction()],
      ['GitHub Actions Output', () => this.testGitHubActionsOutput()],
      ['Error Handling', () => this.testErrorHandling()]
    ];
    
    for (const [testName, testFunction] of tests) {
      await this.runTest(testName, testFunction);
    }
    
    return this.generateReport();
  }

  /**
   * Generate test report
   */
  generateReport() {
    const totalTests = this.testResults.length;
    const passedTests = this.testResults.filter(r => r.status === 'PASS').length;
    const failedTests = this.testResults.filter(r => r.status === 'FAIL').length;
    
    Logger.info('\n📊 Test Results Summary:');
    Logger.info(`   Total Tests: ${totalTests}`);
    Logger.info(`   Passed: ${passedTests}`);
    Logger.info(`   Failed: ${failedTests}`);
    Logger.info(`   Success Rate: ${((passedTests / totalTests) * 100).toFixed(1)}%`);
    
    if (failedTests > 0) {
      Logger.error('\n❌ Failed Tests:');
      this.testResults
        .filter(r => r.status === 'FAIL')
        .forEach(result => {
          Logger.error(`   - ${result.name}: ${result.error}`);
        });
    }
    
    const success = failedTests === 0;
    if (success) {
      Logger.success('\n🎉 All tests passed!');
    } else {
      Logger.error('\n💥 Some tests failed!');
    }
    
    return success;
  }
}

// CLI execution
if (require.main === module) {
  (async () => {
    const testSuite = new ClaudeWorkflowTests();
    const success = await testSuite.runAllTests();
    process.exit(success ? 0 : 1);
  })();
}

module.exports = { ClaudeWorkflowTests };
