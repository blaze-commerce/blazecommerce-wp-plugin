#!/usr/bin/env node

/**
 * GitHub Actions Output Formatting Test
 * Tests all workflow scripts to ensure they don't contain emojis or special characters
 * that could cause GitHub Actions output formatting failures.
 * 
 * This test prevents issues like:
 * - "Unable to process file command 'output' successfully"
 * - "Invalid format 'INFO: Starting Claude review processing v1 for PR #337'"
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');

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
 * GitHub Actions Output Formatting Tester
 */
class OutputFormattingTester {
  constructor() {
    this.workflowsDir = '.github/workflows';
    this.scriptsDir = '.github/scripts';
    this.errors = [];
    this.warnings = [];
  }

  /**
   * Check if text contains non-ASCII characters (emojis, special symbols)
   * @param {string} text - Text to check
   * @returns {boolean} True if contains non-ASCII characters
   */
  containsNonASCII(text) {
    return /[^\x00-\x7F]/.test(text);
  }

  /**
   * Extract problematic characters from text
   * @param {string} text - Text to analyze
   * @returns {Array} Array of problematic characters
   */
  extractNonASCII(text) {
    const matches = text.match(/[^\x00-\x7F]/g);
    return matches ? [...new Set(matches)] : [];
  }

  /**
   * Test YAML workflow files for emoji usage in echo statements
   */
  testWorkflowFiles() {
    TestLogger.info('Testing YAML workflow files for output formatting issues...');
    
    if (!fs.existsSync(this.workflowsDir)) {
      TestLogger.warning(`Workflows directory not found: ${this.workflowsDir}`);
      return;
    }

    const workflowFiles = fs.readdirSync(this.workflowsDir)
      .filter(file => file.endsWith('.yml') || file.endsWith('.yaml'));

    for (const file of workflowFiles) {
      const filePath = path.join(this.workflowsDir, file);
      const content = fs.readFileSync(filePath, 'utf8');
      const lines = content.split('\n');

      lines.forEach((line, index) => {
        const lineNumber = index + 1;
        
        // Check for echo statements with non-ASCII characters
        if (line.includes('echo') && this.containsNonASCII(line)) {
          const problematicChars = this.extractNonASCII(line);
          this.errors.push({
            file: filePath,
            line: lineNumber,
            content: line.trim(),
            issue: `Echo statement contains non-ASCII characters: ${problematicChars.join(', ')}`,
            type: 'workflow-echo'
          });
        }

        // Check for GitHub output assignments with non-ASCII characters
        if (line.includes('>> $GITHUB_OUTPUT') && this.containsNonASCII(line)) {
          const problematicChars = this.extractNonASCII(line);
          this.errors.push({
            file: filePath,
            line: lineNumber,
            content: line.trim(),
            issue: `GitHub output assignment contains non-ASCII characters: ${problematicChars.join(', ')}`,
            type: 'github-output'
          });
        }
      });
    }

    TestLogger.success(`Tested ${workflowFiles.length} workflow files`);
  }

  /**
   * Test JavaScript files for console.log statements with emojis
   */
  testJavaScriptFiles() {
    TestLogger.info('Testing JavaScript files for console output formatting issues...');
    
    if (!fs.existsSync(this.scriptsDir)) {
      TestLogger.warning(`Scripts directory not found: ${this.scriptsDir}`);
      return;
    }

    const jsFiles = this.getAllJSFiles(this.scriptsDir);

    for (const filePath of jsFiles) {
      const content = fs.readFileSync(filePath, 'utf8');
      const lines = content.split('\n');

      lines.forEach((line, index) => {
        const lineNumber = index + 1;
        
        // Check for console.log statements with non-ASCII characters
        if (line.includes('console.log') && this.containsNonASCII(line)) {
          const problematicChars = this.extractNonASCII(line);
          this.errors.push({
            file: filePath,
            line: lineNumber,
            content: line.trim(),
            issue: `Console.log statement contains non-ASCII characters: ${problematicChars.join(', ')}`,
            type: 'console-log'
          });
        }

        // Check for console.error statements with non-ASCII characters
        if (line.includes('console.error') && this.containsNonASCII(line)) {
          const problematicChars = this.extractNonASCII(line);
          this.errors.push({
            file: filePath,
            line: lineNumber,
            content: line.trim(),
            issue: `Console.error statement contains non-ASCII characters: ${problematicChars.join(', ')}`,
            type: 'console-error'
          });
        }
      });
    }

    TestLogger.success(`Tested ${jsFiles.length} JavaScript files`);
  }

  /**
   * Recursively get all JavaScript files in a directory
   * @param {string} dir - Directory to search
   * @returns {Array} Array of JavaScript file paths
   */
  getAllJSFiles(dir) {
    const jsFiles = [];
    
    const items = fs.readdirSync(dir);
    for (const item of items) {
      const itemPath = path.join(dir, item);
      const stat = fs.statSync(itemPath);
      
      if (stat.isDirectory()) {
        jsFiles.push(...this.getAllJSFiles(itemPath));
      } else if (item.endsWith('.js')) {
        jsFiles.push(itemPath);
      }
    }
    
    return jsFiles;
  }

  /**
   * Test specific patterns that are known to cause GitHub Actions failures
   */
  testKnownProblematicPatterns() {
    TestLogger.info('Testing for known problematic patterns...');
    
    const problematicPatterns = [
      { pattern: /INFO:/g, description: 'Information emoji (INFO:)' },
      { pattern: /SUCCESS:/g, description: 'Check mark emoji (SUCCESS:)' },
      { pattern: /ERROR:/g, description: 'Cross mark emoji (ERROR:)' },
      { pattern: /WARNING:/g, description: 'Warning emoji (WARNING:)' },
      { pattern: /DEBUG:/g, description: 'Magnifying glass emoji (DEBUG:)' },
      { pattern: /BOT:/g, description: 'Robot emoji (BOT:)' },
      { pattern: /TARGET:/g, description: 'Target emoji (TARGET:)' },
      { pattern: /RETRY:/g, description: 'Refresh emoji (RETRY:)' },
      { pattern: /NOTE:/g, description: 'Memo emoji (NOTE:)' },
      { pattern: /COMPLETED:/g, description: 'Party emoji (COMPLETED:)' }
    ];

    // Test all workflow and script files
    const allFiles = [
      ...fs.readdirSync(this.workflowsDir).map(f => path.join(this.workflowsDir, f)),
      ...this.getAllJSFiles(this.scriptsDir)
    ];

    for (const filePath of allFiles) {
      if (!fs.existsSync(filePath)) continue;
      
      const content = fs.readFileSync(filePath, 'utf8');
      
      for (const { pattern, description } of problematicPatterns) {
        const matches = content.match(pattern);
        if (matches) {
          this.warnings.push({
            file: filePath,
            issue: `Found ${matches.length} occurrence(s) of ${description}`,
            type: 'known-pattern'
          });
        }
      }
    }

    TestLogger.success('Completed known pattern analysis');
  }

  /**
   * Run all tests and generate report
   */
  runAllTests() {
    TestLogger.info('Starting GitHub Actions Output Formatting Tests...');
    
    this.testWorkflowFiles();
    this.testJavaScriptFiles();
    this.testKnownProblematicPatterns();
    
    this.generateReport();
    
    return this.errors.length === 0;
  }

  /**
   * Generate test report
   */
  generateReport() {
    TestLogger.info('Generating test report...');
    
    if (this.errors.length === 0) {
      TestLogger.success('All tests passed! No GitHub Actions output formatting issues found.');
    } else {
      TestLogger.error(`Found ${this.errors.length} critical formatting issue(s):`);
      
      this.errors.forEach((error, index) => {
        TestLogger.error(`${index + 1}. ${error.file}:${error.line || 'N/A'}`);
        TestLogger.error(`   Issue: ${error.issue}`);
        TestLogger.error(`   Content: ${error.content || 'N/A'}`);
        TestLogger.error('');
      });
    }

    if (this.warnings.length > 0) {
      TestLogger.warning(`Found ${this.warnings.length} warning(s):`);
      
      this.warnings.forEach((warning, index) => {
        TestLogger.warning(`${index + 1}. ${warning.file}`);
        TestLogger.warning(`   Issue: ${warning.issue}`);
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
  const tester = new OutputFormattingTester();
  const success = tester.runAllTests();
  
  process.exit(success ? 0 : 1);
}

module.exports = { OutputFormattingTester, TestLogger };
