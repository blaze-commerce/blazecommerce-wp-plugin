#!/usr/bin/env node

/**
 * Workflow Naming Validation Script
 * Validates that all workflow naming conventions are consistent and accurate
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');

class WorkflowNamingValidator {
  constructor() {
    this.errors = [];
    this.warnings = [];
    this.successes = [];
    this.workflowsDir = '.github/workflows';
  }

  /**
   * Main validation function
   */
  async validate() {
    console.log('🔍 Validating GitHub Workflow Naming Conventions...\n');

    // Check if workflows directory exists
    if (!fs.existsSync(this.workflowsDir)) {
      this.errors.push('Workflows directory not found: ' + this.workflowsDir);
      return this.generateReport();
    }

    // Get all workflow files
    const workflowFiles = fs.readdirSync(this.workflowsDir)
      .filter(file => file.endsWith('.yml') || file.endsWith('.yaml'))
      .map(file => path.join(this.workflowsDir, file));

    // Validate each workflow
    for (const filePath of workflowFiles) {
      await this.validateWorkflowFile(filePath);
    }

    // Validate expected workflows exist
    this.validateExpectedWorkflows();

    // Validate naming consistency
    this.validateNamingConsistency();

    return this.generateReport();
  }

  /**
   * Validate individual workflow file
   */
  async validateWorkflowFile(filePath) {
    const fileName = path.basename(filePath);
    console.log(`📋 Validating: ${fileName}`);

    try {
      // Read file content
      const content = fs.readFileSync(filePath, 'utf8');

      // Simple validation - check for required fields
      if (!content.includes('name:')) {
        this.errors.push(`${fileName}: Missing 'name:' field`);
        return;
      }

      if (!content.includes('on:')) {
        this.errors.push(`${fileName}: Missing 'on:' field`);
        return;
      }

      if (!content.includes('jobs:')) {
        this.errors.push(`${fileName}: Missing 'jobs:' field`);
        return;
      }

      // Extract workflow name
      const nameMatch = content.match(/^name:\s*["']?([^"'\n]+)["']?/m);
      if (nameMatch) {
        const workflowName = nameMatch[1].trim();
        this.validateWorkflowNaming(fileName, { name: workflowName });
      }

      this.successes.push(`${fileName}: Valid YAML structure and naming`);

    } catch (error) {
      this.errors.push(`${fileName}: File reading error - ${error.message}`);
    }
  }

  /**
   * Validate workflow naming patterns
   */
  validateWorkflowNaming(fileName, workflow) {
    const workflowName = workflow.name;

    // Expected workflow mappings
    const expectedMappings = {
      'workflow-preflight-check.yml': 'Priority 1: Workflow Pre-flight Check',
      'claude-code-review.yml': 'Priority 2: Claude AI Code Review',
      'claude-approval-gate.yml': 'Priority 3: Claude AI Approval Gate',
      'auto-version.yml': 'Priority 4: Auto Version Bump',
      'release.yml': 'Priority 5: Create Release',
      'tests.yml': 'Priority 6: Tests',
      'claude.yml': 'Priority 7: Claude Code',
      'test-claude-output-fix.yml': 'Priority 8: Test Claude Output Fix',
      'test-claude-approval.yml': 'Priority 9: Test Claude Approval'
    };

    // Check if filename matches expected naming
    if (expectedMappings[fileName]) {
      if (workflowName === expectedMappings[fileName]) {
        this.successes.push(`${fileName}: Correct workflow name mapping`);
      } else {
        this.errors.push(`${fileName}: Expected name "${expectedMappings[fileName]}", got "${workflowName}"`);
      }
    } else {
      this.warnings.push(`${fileName}: No expected naming pattern defined`);
    }

    // Validate priority numbering
    if (workflowName.includes('Priority')) {
      const priorityMatch = workflowName.match(/Priority (\d+):/);
      if (priorityMatch) {
        const priority = parseInt(priorityMatch[1]);
        if (priority >= 1 && priority <= 9) {
          this.successes.push(`${fileName}: Valid priority number (${priority})`);
        } else {
          this.warnings.push(`${fileName}: Unusual priority number (${priority})`);
        }
      }
    }
  }

  /**
   * Validate that expected workflows exist
   */
  validateExpectedWorkflows() {
    const expectedFiles = [
      'workflow-preflight-check.yml',
      'claude-code-review.yml',
      'claude-approval-gate.yml',
      'auto-version.yml',
      'release.yml',
      'tests.yml',
      'claude.yml'
    ];

    for (const expectedFile of expectedFiles) {
      const filePath = path.join(this.workflowsDir, expectedFile);
      if (fs.existsSync(filePath)) {
        this.successes.push(`Required workflow exists: ${expectedFile}`);
      } else {
        this.errors.push(`Missing required workflow: ${expectedFile}`);
      }
    }

    // Check that old filename doesn't exist
    const oldFile = path.join(this.workflowsDir, 'claude-direct-approval.yml');
    if (fs.existsSync(oldFile)) {
      this.errors.push('Old workflow file still exists: claude-direct-approval.yml');
    } else {
      this.successes.push('Old workflow file successfully removed: claude-direct-approval.yml');
    }
  }

  /**
   * Validate naming consistency across documentation
   */
  validateNamingConsistency() {
    // Check for any remaining old references
    const checkFiles = [
      'docs/workflow-priority-restructuring-guide.md',
      'docs/development/claude-workflow-sequence.md',
      'docs/workflow-sequence-configuration.md'
    ];

    for (const filePath of checkFiles) {
      if (fs.existsSync(filePath)) {
        const content = fs.readFileSync(filePath, 'utf8');
        
        // Check for old naming patterns
        if (content.includes('Claude Direct Approval') && !filePath.includes('refactor-summary')) {
          this.errors.push(`${filePath}: Contains old naming "Claude Direct Approval"`);
        } else {
          this.successes.push(`${filePath}: No old naming references found`);
        }

        if (content.includes('claude-direct-approval') && !filePath.includes('refactor-summary')) {
          this.errors.push(`${filePath}: Contains old filename "claude-direct-approval"`);
        } else {
          this.successes.push(`${filePath}: No old filename references found`);
        }
      }
    }
  }

  /**
   * Generate validation report
   */
  generateReport() {
    console.log('\n' + '='.repeat(60));
    console.log('📊 WORKFLOW NAMING VALIDATION REPORT');
    console.log('='.repeat(60));

    if (this.successes.length > 0) {
      console.log('\n✅ SUCCESSES:');
      this.successes.forEach(success => console.log(`   ${success}`));
    }

    if (this.warnings.length > 0) {
      console.log('\n⚠️  WARNINGS:');
      this.warnings.forEach(warning => console.log(`   ${warning}`));
    }

    if (this.errors.length > 0) {
      console.log('\n❌ ERRORS:');
      this.errors.forEach(error => console.log(`   ${error}`));
    }

    console.log('\n📈 SUMMARY:');
    console.log(`   ✅ Successes: ${this.successes.length}`);
    console.log(`   ⚠️  Warnings: ${this.warnings.length}`);
    console.log(`   ❌ Errors: ${this.errors.length}`);

    const isValid = this.errors.length === 0;
    console.log(`\n🎯 OVERALL STATUS: ${isValid ? '✅ VALID' : '❌ INVALID'}`);

    return isValid;
  }
}

// Run validation if called directly
if (require.main === module) {
  const validator = new WorkflowNamingValidator();
  validator.validate().then(isValid => {
    process.exit(isValid ? 0 : 1);
  }).catch(error => {
    console.error('❌ Validation failed:', error.message);
    process.exit(1);
  });
}

module.exports = { WorkflowNamingValidator };
