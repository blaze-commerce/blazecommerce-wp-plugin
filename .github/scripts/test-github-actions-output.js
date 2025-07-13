#!/usr/bin/env node

/**
 * GitHub Actions Output Test Script
 * Tests the claude-review-enhancer.js script to ensure it properly formats output
 * for GitHub Actions without causing "Invalid format" errors.
 * 
 * This test specifically addresses the issue:
 * "Invalid format 'INFO: Starting Claude review processing v1 for PR #337'"
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const os = require('os');

/**
 * Test Logger - Uses only ASCII characters for GitHub Actions compatibility
 */
class TestLogger {
  static info(message) {
    console.log(`INFO: ${message}`);
  }
  
  static success(message) {
    console.log(`SUCCESS: ${message}`);
  }
  
  static warning(message) {
    console.log(`WARNING: ${message}`);
  }
  
  static error(message) {
    console.error(`ERROR: ${message}`);
  }
}

/**
 * GitHub Actions Output Tester
 */
class GitHubActionsOutputTester {
  constructor() {
    this.tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'github-actions-test-'));
    this.outputFile = path.join(this.tempDir, 'github_output');
    this.errors = [];
    this.warnings = [];
  }

  /**
   * Clean up temporary files
   */
  cleanup() {
    try {
      if (fs.existsSync(this.outputFile)) {
        fs.unlinkSync(this.outputFile);
      }
      fs.rmdirSync(this.tempDir);
    } catch (error) {
      TestLogger.warning(`Cleanup failed: ${error.message}`);
    }
  }

  /**
   * Test the claude-review-enhancer.js script output formatting
   */
  async testClaudeReviewEnhancer() {
    TestLogger.info('Testing claude-review-enhancer.js output formatting...');
    
    return new Promise((resolve) => {
      // Create a mock Claude output for testing
      const mockClaudeOutput = `## Code Review Summary

### Required Changes
- Fix security vulnerability in authentication
- Update deprecated API calls

### Important Improvements  
- Add error handling for network requests
- Improve code documentation

### Suggestions
- Consider using TypeScript for better type safety

Overall, the code needs some important fixes before it can be merged.`;

      // Set up environment for testing
      const env = {
        ...process.env,
        GITHUB_OUTPUT: this.outputFile,
        CLAUDE_OUTPUT: mockClaudeOutput,
        PR_NUMBER: '337',
        GITHUB_TOKEN: 'test-token'
      };

      // Run the script
      const scriptPath = path.join(__dirname, 'claude-review-enhancer.js');
      const child = spawn('node', [scriptPath], {
        env,
        stdio: ['pipe', 'pipe', 'pipe']
      });

      let stdout = '';
      let stderr = '';

      child.stdout.on('data', (data) => {
        stdout += data.toString();
      });

      child.stderr.on('data', (data) => {
        stderr += data.toString();
      });

      child.on('close', (code) => {
        this.analyzeOutput(code, stdout, stderr);
        resolve(code === 0);
      });

      // Send mock input to the script
      child.stdin.write(mockClaudeOutput);
      child.stdin.end();
    });
  }

  /**
   * Analyze the script output and GitHub Actions output file
   */
  analyzeOutput(exitCode, stdout, stderr) {
    TestLogger.info(`Script exit code: ${exitCode}`);
    
    // Check if output file was created
    if (!fs.existsSync(this.outputFile)) {
      this.errors.push({
        type: 'missing-output-file',
        message: 'GitHub Actions output file was not created'
      });
      return;
    }

    // Read and analyze the output file
    const outputContent = fs.readFileSync(this.outputFile, 'utf8');
    TestLogger.info('GitHub Actions output file content:');
    console.log('--- OUTPUT FILE START ---');
    console.log(outputContent);
    console.log('--- OUTPUT FILE END ---');

    // Validate output format
    this.validateOutputFormat(outputContent);

    // Check stderr for any issues
    if (stderr) {
      TestLogger.info('Script stderr output:');
      console.log(stderr);
    }

    // Check stdout (should be minimal or empty)
    if (stdout.trim()) {
      TestLogger.warning('Script produced stdout output (should be minimal):');
      console.log(stdout);
    }
  }

  /**
   * Validate that the output follows GitHub Actions format requirements
   */
  validateOutputFormat(content) {
    const lines = content.split('\n').filter(line => line.trim());
    
    for (let i = 0; i < lines.length; i++) {
      const line = lines[i];
      
      // Check for multiline format (key<<EOF)
      if (line.includes('<<')) {
        const [key, delimiter] = line.split('<<');
        if (!key || !delimiter) {
          this.errors.push({
            type: 'invalid-multiline-format',
            line: i + 1,
            content: line,
            message: 'Invalid multiline format'
          });
          continue;
        }
        
        // Find the closing delimiter
        let found = false;
        for (let j = i + 1; j < lines.length; j++) {
          if (lines[j] === delimiter) {
            found = true;
            i = j; // Skip to after the delimiter
            break;
          }
        }
        
        if (!found) {
          this.errors.push({
            type: 'missing-delimiter',
            line: i + 1,
            content: line,
            message: `Missing closing delimiter: ${delimiter}`
          });
        }
        
        continue;
      }
      
      // Check for key=value format
      if (!line.includes('=')) {
        this.errors.push({
          type: 'invalid-format',
          line: i + 1,
          content: line,
          message: 'Line does not follow key=value format'
        });
        continue;
      }
      
      // Check for problematic characters that could cause GitHub Actions issues
      if (/[^\x00-\x7F]/.test(line)) {
        this.warnings.push({
          type: 'non-ascii-characters',
          line: i + 1,
          content: line,
          message: 'Line contains non-ASCII characters'
        });
      }
    }
  }

  /**
   * Run all tests and generate report
   */
  async runAllTests() {
    TestLogger.info('Starting GitHub Actions Output Tests...');
    
    try {
      const success = await this.testClaudeReviewEnhancer();
      this.generateReport();
      return success && this.errors.length === 0;
    } finally {
      this.cleanup();
    }
  }

  /**
   * Generate test report
   */
  generateReport() {
    TestLogger.info('Generating test report...');
    
    if (this.errors.length === 0) {
      TestLogger.success('All tests passed! GitHub Actions output formatting is correct.');
    } else {
      TestLogger.error(`Found ${this.errors.length} error(s):`);
      
      this.errors.forEach((error, index) => {
        TestLogger.error(`${index + 1}. ${error.type} (line ${error.line || 'N/A'})`);
        TestLogger.error(`   Message: ${error.message}`);
        if (error.content) {
          TestLogger.error(`   Content: ${error.content}`);
        }
        TestLogger.error('');
      });
    }

    if (this.warnings.length > 0) {
      TestLogger.warning(`Found ${this.warnings.length} warning(s):`);
      
      this.warnings.forEach((warning, index) => {
        TestLogger.warning(`${index + 1}. ${warning.type} (line ${warning.line || 'N/A'})`);
        TestLogger.warning(`   Message: ${warning.message}`);
        if (warning.content) {
          TestLogger.warning(`   Content: ${warning.content}`);
        }
        TestLogger.warning('');
      });
    }

    // Output results for GitHub Actions
    if (process.env.GITHUB_ACTIONS) {
      console.log(`test_passed=${this.errors.length === 0}`);
      console.log(`error_count=${this.errors.length}`);
      console.log(`warning_count=${this.warnings.length}`);
    }
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  (async () => {
    const tester = new GitHubActionsOutputTester();
    const success = await tester.runAllTests();
    
    process.exit(success ? 0 : 1);
  })();
}

module.exports = { GitHubActionsOutputTester, TestLogger };
