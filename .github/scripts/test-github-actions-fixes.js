#!/usr/bin/env node

/**
 * Test Script for GitHub Actions Output Fixes
 * Validates that all scripts properly handle GitHub Actions output formatting
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const os = require('os');

/**
 * Test Logger - uses stderr to avoid interfering with output tests
 */
class TestLogger {
  static info(message) {
    console.error(`INFO: ${message}`);
  }

  static success(message) {
    console.error(`SUCCESS: ${message}`);
  }

  static error(message) {
    console.error(`ERROR: ${message}`);
  }

  static warning(message) {
    console.error(`WARNING: ${message}`);
  }
}

/**
 * GitHub Actions Output Fixes Tester
 */
class GitHubActionsFixesTester {
  constructor() {
    this.scriptsDir = path.join(process.cwd(), '.github', 'scripts');
    this.workflowsDir = path.join(process.cwd(), '.github', 'workflows');
    this.testResults = [];
    this.errors = [];
    this.warnings = [];
  }

  /**
   * Test a script's output format
   * @param {string} scriptPath - Path to the script
   * @param {Array} args - Arguments to pass to the script
   * @returns {Promise<Object>} Test result
   */
  async testScriptOutput(scriptPath, args = []) {
    const scriptName = path.basename(scriptPath);
    TestLogger.info(`Testing ${scriptName}...`);

    // Create temporary output file
    const tempOutputFile = path.join(os.tmpdir(), `github-output-${Date.now()}.txt`);
    
    try {
      // Set up environment
      const env = {
        ...process.env,
        GITHUB_OUTPUT: tempOutputFile,
        DEBUG: 'false' // Disable debug output for cleaner testing
      };

      // Run the script
      const command = `node "${scriptPath}" ${args.join(' ')}`;
      const result = execSync(command, { 
        env,
        encoding: 'utf8',
        stdio: ['pipe', 'pipe', 'pipe']
      });

      // Check if output file was created and has valid content
      let outputContent = '';
      let outputValid = true;
      let outputErrors = [];

      if (fs.existsSync(tempOutputFile)) {
        outputContent = fs.readFileSync(tempOutputFile, 'utf8');
        
        // Validate output format
        const lines = outputContent.trim().split('\n').filter(line => line.trim());
        
        for (const line of lines) {
          if (!line.includes('=')) {
            outputValid = false;
            outputErrors.push(`Invalid format: "${line}" (missing =)`);
          }
          
          // Check for non-ASCII characters
          if (/[^\x00-\x7F]/.test(line)) {
            outputValid = false;
            outputErrors.push(`Non-ASCII characters in: "${line}"`);
          }
        }
      } else {
        // Check if script outputs to stdout as fallback
        if (result.trim()) {
          outputContent = result.trim();
          TestLogger.warning(`${scriptName} used stdout fallback`);
        } else {
          outputValid = false;
          outputErrors.push('No output file created and no stdout output');
        }
      }

      return {
        script: scriptName,
        success: outputValid,
        outputContent,
        errors: outputErrors,
        hasOutputFile: fs.existsSync(tempOutputFile)
      };

    } catch (error) {
      return {
        script: scriptName,
        success: false,
        outputContent: '',
        errors: [`Script execution failed: ${error.message}`],
        hasOutputFile: false
      };
    } finally {
      // Clean up temp file
      if (fs.existsSync(tempOutputFile)) {
        fs.unlinkSync(tempOutputFile);
      }
    }
  }

  /**
   * Test all critical scripts
   */
  async testAllScripts() {
    TestLogger.info('Testing all critical GitHub Actions scripts...');

    const scriptsToTest = [
      {
        path: path.join(this.scriptsDir, 'file-change-analyzer.js'),
        args: [],
        env: { GITHUB_EVENT_BEFORE: '0000000000000000000000000000000000000000' }
      },
      {
        path: path.join(this.scriptsDir, 'branch-analyzer.js'),
        args: ['main'],
        env: {}
      },
      {
        path: path.join(this.scriptsDir, 'bump-type-analyzer.js'),
        args: ['false', 'none'],
        env: {}
      },
      {
        path: path.join(this.scriptsDir, 'version-analyzer.js'),
        args: [],
        env: {}
      },
      {
        path: path.join(this.scriptsDir, 'claude-status-manager.js'),
        args: ['get-state'],
        env: { PR_NUMBER: '337', GITHUB_SHA: 'test123', GITHUB_REPOSITORY: 'test/repo' }
      },
      {
        path: path.join(this.scriptsDir, 'commit-parser.js'),
        args: [],
        env: { COMMIT_MESSAGES: 'feat: test feature\nfix: test fix' }
      },
      {
        path: path.join(this.scriptsDir, 'version-validator.js'),
        args: [],
        env: {}
      }
    ];

    for (const { path: scriptPath, args, env } of scriptsToTest) {
      if (!fs.existsSync(scriptPath)) {
        TestLogger.warning(`Script not found: ${scriptPath}`);
        continue;
      }

      // Set up environment variables
      Object.assign(process.env, env);

      const result = await this.testScriptOutput(scriptPath, args);
      this.testResults.push(result);

      if (result.success) {
        TestLogger.success(`${result.script} passed output format test`);
      } else {
        TestLogger.error(`${result.script} failed output format test`);
        result.errors.forEach(error => TestLogger.error(`  - ${error}`));
      }
    }
  }

  /**
   * Check workflow files for problematic patterns
   */
  checkWorkflowFiles() {
    TestLogger.info('Checking workflow files for problematic patterns...');

    const workflowFiles = fs.readdirSync(this.workflowsDir)
      .filter(file => file.endsWith('.yml') || file.endsWith('.yaml'))
      .map(file => path.join(this.workflowsDir, file));

    for (const filePath of workflowFiles) {
      const content = fs.readFileSync(filePath, 'utf8');
      const fileName = path.basename(filePath);

      // Check for node script redirection to $GITHUB_OUTPUT
      const redirectionMatches = content.match(/node\s+[^\s]+\s+>>\s+\$GITHUB_OUTPUT/g);
      if (redirectionMatches) {
        this.warnings.push({
          file: fileName,
          issue: `Found ${redirectionMatches.length} instance(s) of node script output redirection`,
          lines: redirectionMatches
        });
      }

      // Check for emoji characters
      const emojiMatches = content.match(/[^\x00-\x7F]/g);
      if (emojiMatches) {
        this.errors.push({
          file: fileName,
          issue: `Found ${emojiMatches.length} non-ASCII character(s)`,
          characters: [...new Set(emojiMatches)]
        });
      }
    }

    TestLogger.success(`Checked ${workflowFiles.length} workflow files`);
  }

  /**
   * Generate comprehensive test report
   */
  generateReport() {
    TestLogger.info('Generating test report...');

    const passedTests = this.testResults.filter(r => r.success).length;
    const failedTests = this.testResults.filter(r => !r.success).length;

    console.log('\n' + '='.repeat(60));
    console.log('GITHUB ACTIONS OUTPUT FIXES TEST REPORT');
    console.log('='.repeat(60));

    console.log(`\nScript Tests: ${passedTests} passed, ${failedTests} failed`);
    
    if (failedTests > 0) {
      console.log('\nFailed Tests:');
      this.testResults.filter(r => !r.success).forEach(result => {
        console.log(`\n❌ ${result.script}:`);
        result.errors.forEach(error => console.log(`   - ${error}`));
      });
    }

    if (this.warnings.length > 0) {
      console.log(`\nWarnings (${this.warnings.length}):`);
      this.warnings.forEach(warning => {
        console.log(`\n⚠️  ${warning.file}: ${warning.issue}`);
        if (warning.lines) {
          warning.lines.forEach(line => console.log(`   - ${line}`));
        }
      });
    }

    if (this.errors.length > 0) {
      console.log(`\nErrors (${this.errors.length}):`);
      this.errors.forEach(error => {
        console.log(`\n❌ ${error.file}: ${error.issue}`);
        if (error.characters) {
          console.log(`   Characters: ${error.characters.join(', ')}`);
        }
      });
    }

    const overallSuccess = failedTests === 0 && this.errors.length === 0;
    
    console.log('\n' + '='.repeat(60));
    console.log(`OVERALL RESULT: ${overallSuccess ? 'PASS' : 'FAIL'}`);
    console.log('='.repeat(60));

    return overallSuccess;
  }

  /**
   * Run all tests
   */
  async runAllTests() {
    TestLogger.info('Starting GitHub Actions output fixes validation...');

    try {
      await this.testAllScripts();
      this.checkWorkflowFiles();
      
      const success = this.generateReport();
      return success;
    } catch (error) {
      TestLogger.error(`Test execution failed: ${error.message}`);
      return false;
    }
  }
}

// Run tests if this file is executed directly
if (require.main === module) {
  const tester = new GitHubActionsFixesTester();
  
  tester.runAllTests().then(success => {
    process.exit(success ? 0 : 1);
  }).catch(error => {
    TestLogger.error(`Test suite failed: ${error.message}`);
    process.exit(1);
  });
}

module.exports = { GitHubActionsFixesTester };
