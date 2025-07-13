#!/usr/bin/env node

/**
 * Workflow Optimization Validator
 * Validates that all optimization improvements are working correctly
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');

/**
 * Validation Results
 */
class ValidationResults {
  constructor() {
    this.passed = 0;
    this.failed = 0;
    this.warnings = 0;
    this.results = [];
  }

  pass(test, message) {
    this.passed++;
    this.results.push({ test, status: 'PASS', message });
    console.log(`✅ ${test}: ${message}`);
  }

  fail(test, message) {
    this.failed++;
    this.results.push({ test, status: 'FAIL', message });
    console.log(`❌ ${test}: ${message}`);
  }

  warn(test, message) {
    this.warnings++;
    this.results.push({ test, status: 'WARN', message });
    console.log(`⚠️  ${test}: ${message}`);
  }

  summary() {
    console.log('\n📊 Validation Summary:');
    console.log(`✅ Passed: ${this.passed}`);
    console.log(`❌ Failed: ${this.failed}`);
    console.log(`⚠️  Warnings: ${this.warnings}`);
    console.log(`📋 Total: ${this.results.length}`);
    
    return this.failed === 0;
  }
}

/**
 * Workflow Optimization Validator
 */
class WorkflowOptimizationValidator {
  constructor() {
    this.results = new ValidationResults();
    this.rootDir = process.cwd();
  }

  /**
   * Check if file exists
   */
  fileExists(filePath) {
    return fs.existsSync(path.join(this.rootDir, filePath));
  }

  /**
   * Get file line count
   */
  getLineCount(filePath) {
    try {
      const content = fs.readFileSync(path.join(this.rootDir, filePath), 'utf8');
      return content.split('\n').length;
    } catch (error) {
      return 0;
    }
  }

  /**
   * Check if file contains text
   */
  fileContains(filePath, text) {
    try {
      const content = fs.readFileSync(path.join(this.rootDir, filePath), 'utf8');
      return content.includes(text);
    } catch (error) {
      return false;
    }
  }

  /**
   * Validate extracted scripts exist
   */
  validateExtractedScripts() {
    console.log('\n🔍 Validating Extracted Scripts...');

    const requiredScripts = [
      '.github/scripts/file-change-analyzer.js',
      '.github/scripts/version-validator.js',
      '.github/scripts/branch-analyzer.js',
      '.github/scripts/bump-type-analyzer.js',
      '.github/scripts/error-handler.js',
      '.github/scripts/priority-dependency-checker.js',
      '.github/scripts/claude-review-enhancer.js'
    ];

    for (const script of requiredScripts) {
      if (this.fileExists(script)) {
        this.results.pass('Script Extraction', `${script} exists`);
      } else {
        this.results.fail('Script Extraction', `${script} missing`);
      }
    }
  }

  /**
   * Validate workflow complexity reduction
   */
  validateWorkflowComplexity() {
    console.log('\n🔍 Validating Workflow Complexity...');

    const autoVersionPath = '.github/workflows/auto-version.yml';
    if (this.fileExists(autoVersionPath)) {
      const lineCount = this.getLineCount(autoVersionPath);
      
      if (lineCount < 400) {
        this.results.pass('Complexity Reduction', `auto-version.yml reduced to ${lineCount} lines`);
      } else if (lineCount < 600) {
        this.results.warn('Complexity Reduction', `auto-version.yml is ${lineCount} lines (target: <400)`);
      } else {
        this.results.fail('Complexity Reduction', `auto-version.yml is still ${lineCount} lines`);
      }

      // Check for script usage
      if (this.fileContains(autoVersionPath, 'node .github/scripts/')) {
        this.results.pass('Script Integration', 'auto-version.yml uses extracted scripts');
      } else {
        this.results.fail('Script Integration', 'auto-version.yml not using extracted scripts');
      }
    } else {
      this.results.fail('Workflow Files', 'auto-version.yml not found');
    }
  }

  /**
   * Validate priority dependencies
   */
  validatePriorityDependencies() {
    console.log('\n🔍 Validating Priority Dependencies...');

    const workflows = [
      { file: '.github/workflows/claude-pr-review.yml', priority: 1 },
      { file: '.github/workflows/claude-approval-gate.yml', priority: 2 },
      { file: '.github/workflows/auto-version.yml', priority: 3 },
      { file: '.github/workflows/release.yml', priority: 3 }
    ];

    for (const workflow of workflows) {
      if (this.fileExists(workflow.file)) {
        this.results.pass('Workflow Files', `${workflow.file} exists`);

        if (workflow.priority > 1) {
          if (this.fileContains(workflow.file, 'wait-for-priority-') ||
              this.fileContains(workflow.file, 'wait-for-claude-review') ||
              this.fileContains(workflow.file, 'needs:')) {
            this.results.pass('Priority Dependencies', `${workflow.file} has dependency check`);
          } else {
            this.results.fail('Priority Dependencies', `${workflow.file} missing dependency check`);
          }
        }
      } else {
        this.results.fail('Workflow Files', `${workflow.file} not found`);
      }
    }
  }

  /**
   * Validate error handling
   */
  validateErrorHandling() {
    console.log('\n🔍 Validating Error Handling...');

    const errorHandlerPath = '.github/scripts/error-handler.js';
    if (this.fileExists(errorHandlerPath)) {
      this.results.pass('Error Handling', 'error-handler.js exists');

      const requiredClasses = ['ErrorHandler', 'ErrorSeverity', 'ErrorCategory'];
      for (const className of requiredClasses) {
        if (this.fileContains(errorHandlerPath, className)) {
          this.results.pass('Error Handling', `${className} class defined`);
        } else {
          this.results.fail('Error Handling', `${className} class missing`);
        }
      }
    } else {
      this.results.fail('Error Handling', 'error-handler.js not found');
    }

    // Check if scripts use error handling
    const scripts = [
      '.github/scripts/file-change-analyzer.js',
      '.github/scripts/version-validator.js',
      '.github/scripts/branch-analyzer.js'
    ];

    for (const script of scripts) {
      if (this.fileExists(script) && this.fileContains(script, 'Logger')) {
        this.results.pass('Error Integration', `${script} uses standardized logging`);
      } else if (this.fileExists(script)) {
        this.results.warn('Error Integration', `${script} may not use standardized logging`);
      }
    }
  }

  /**
   * Validate Claude review enhancement
   */
  validateClaudeEnhancement() {
    console.log('\n🔍 Validating Claude Review Enhancement...');

    const enhancerPath = '.github/scripts/claude-review-enhancer.js';
    if (this.fileExists(enhancerPath)) {
      this.results.pass('Claude Enhancement', 'claude-review-enhancer.js exists');

      if (this.fileContains(enhancerPath, 'Implementation Status')) {
        this.results.pass('Claude Enhancement', 'Implementation status tracking included');
      } else {
        this.results.fail('Claude Enhancement', 'Implementation status tracking missing');
      }
    } else {
      this.results.fail('Claude Enhancement', 'claude-review-enhancer.js not found');
    }

    // Check if Claude PR review workflow uses enhancement
    const claudeWorkflow = '.github/workflows/claude-pr-review.yml';
    if (this.fileExists(claudeWorkflow) && this.fileContains(claudeWorkflow, 'claude-review-enhancer.js')) {
      this.results.pass('Claude Integration', 'Claude workflow uses enhancement script');
    } else if (this.fileExists(claudeWorkflow)) {
      this.results.warn('Claude Integration', 'Claude workflow may not use enhancement script');
    }
  }

  /**
   * Validate unit tests
   */
  validateUnitTests() {
    console.log('\n🔍 Validating Unit Tests...');

    const testPath = '.github/scripts/tests/workflow-scripts.test.js';
    if (this.fileExists(testPath)) {
      this.results.pass('Unit Tests', 'Test file exists');

      const testContent = fs.readFileSync(path.join(this.rootDir, testPath), 'utf8');
      const testCount = (testContent.match(/suite\.test\(/g) || []).length;
      
      if (testCount >= 10) {
        this.results.pass('Unit Tests', `${testCount} test cases found`);
      } else {
        this.results.warn('Unit Tests', `Only ${testCount} test cases found (target: 10+)`);
      }
    } else {
      this.results.fail('Unit Tests', 'Test file not found');
    }
  }

  /**
   * Validate repository variables usage
   */
  validateRepositoryVariables() {
    console.log('\n🔍 Validating Repository Variables...');

    const workflows = [
      '.github/workflows/auto-version.yml',
      '.github/workflows/claude-pr-review.yml',
      '.github/workflows/claude-approval-gate.yml',
      '.github/workflows/release.yml'
    ];

    for (const workflow of workflows) {
      if (this.fileExists(workflow)) {
        if (this.fileContains(workflow, '${{ vars.')) {
          this.results.pass('Repository Variables', `${workflow} uses repository variables`);
        } else {
          this.results.warn('Repository Variables', `${workflow} may not use repository variables`);
        }
      }
    }
  }

  /**
   * Run all validations
   */
  async validate() {
    console.log('🔍 Starting Workflow Optimization Validation...');
    console.log('================================================');

    this.validateExtractedScripts();
    this.validateWorkflowComplexity();
    this.validatePriorityDependencies();
    this.validateErrorHandling();
    this.validateClaudeEnhancement();
    this.validateUnitTests();
    this.validateRepositoryVariables();

    console.log('\n================================================');
    const success = this.results.summary();

    if (success) {
      console.log('\n🎉 All validations passed! Workflow optimization is complete.');
    } else {
      console.log('\n⚠️  Some validations failed. Please review the issues above.');
    }

    return success;
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  const validator = new WorkflowOptimizationValidator();
  validator.validate().then(success => {
    process.exit(success ? 0 : 1);
  }).catch(error => {
    console.error('❌ Validation failed:', error);
    process.exit(1);
  });
}

module.exports = { WorkflowOptimizationValidator };
