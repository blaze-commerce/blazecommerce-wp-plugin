#!/usr/bin/env node

/**
 * Enhanced Error Handler for GitHub Actions Workflows
 * Provides comprehensive error handling, logging, recovery mechanisms,
 * and JavaScript syntax error prevention for BlazeCommerce workflow scripts
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 2.0.0
 */

const fs = require('fs');
const path = require('path');

/**
 * Error severity levels
 */
const ErrorSeverity = {
  LOW: 'low',
  MEDIUM: 'medium',
  HIGH: 'high',
  CRITICAL: 'critical'
};

/**
 * Error categories for better classification
 */
const ErrorCategory = {
  GITHUB_API: 'github_api',
  FILE_SYSTEM: 'file_system',
  VALIDATION: 'validation',
  NETWORK: 'network',
  PARSING: 'parsing',
  AUTHENTICATION: 'authentication',
  WORKFLOW: 'workflow',
  JAVASCRIPT_SYNTAX: 'javascript_syntax',
  TEMPLATE_INJECTION: 'template_injection'
};

/**
 * Enhanced Error Handler Class with JavaScript Safety Features
 */
class EnhancedErrorHandler {
  constructor(options = {}) {
    this.logLevel = options.logLevel || 'info';
    this.enableFileLogging = options.enableFileLogging !== false;
    this.logDirectory = options.logDirectory || '.github/logs';
    this.maxLogSize = options.maxLogSize || 1024 * 1024; // 1MB
    this.enableStackTrace = options.enableStackTrace !== false;
    this.enableSafetyChecks = options.enableSafetyChecks !== false;
    
    // Ensure log directory exists
    if (this.enableFileLogging) {
      this.ensureLogDirectory();
    }
  }

  /**
   * Ensure log directory exists
   */
  ensureLogDirectory() {
    try {
      if (!fs.existsSync(this.logDirectory)) {
        fs.mkdirSync(this.logDirectory, { recursive: true });
      }
    } catch (error) {
      console.error(`Failed to create log directory: ${error.message}`);
      this.enableFileLogging = false;
    }
  }

  /**
   * Validate and sanitize content for GitHub Actions safety
   * @param {string} content - Content to validate
   * @returns {Object} Validation result with sanitized content
   */
  validateAndSanitizeContent(content) {
    const result = {
      isValid: true,
      issues: [],
      sanitizedContent: content,
      riskLevel: 'low'
    };

    if (!content || typeof content !== 'string') {
      result.isValid = false;
      result.issues.push('Content is empty or not a string');
      result.sanitizedContent = '';
      return result;
    }

    // Check for JavaScript syntax risks
    const syntaxRisks = [
      { pattern: /`[a-f0-9]{7,40}`/g, description: 'Unescaped commit hashes in backticks', risk: 'high' },
      { pattern: /\$\{[^}]*\}/g, description: 'Template literal expressions', risk: 'critical' },
      { pattern: /\$\([^)]*\)/g, description: 'Command substitution patterns', risk: 'high' },
      { pattern: /eval\s*\(/g, description: 'Eval function calls', risk: 'critical' },
      { pattern: /Function\s*\(/g, description: 'Function constructor calls', risk: 'critical' },
      { pattern: /setTimeout\s*\(/g, description: 'setTimeout with string argument', risk: 'medium' },
      { pattern: /setInterval\s*\(/g, description: 'setInterval with string argument', risk: 'medium' },
      { pattern: /document\./g, description: 'DOM manipulation attempts', risk: 'medium' },
      { pattern: /window\./g, description: 'Window object access', risk: 'medium' },
      { pattern: /process\./g, description: 'Process object access', risk: 'high' },
      { pattern: /require\s*\(/g, description: 'Require function calls', risk: 'high' },
      { pattern: /import\s+/g, description: 'Import statements', risk: 'medium' }
    ];

    let maxRiskLevel = 'low';
    syntaxRisks.forEach(({ pattern, description, risk }) => {
      if (pattern.test(content)) {
        result.issues.push({ description, risk, pattern: pattern.toString() });
        if (this.compareRiskLevels(risk, maxRiskLevel) > 0) {
          maxRiskLevel = risk;
        }
      }
    });

    result.riskLevel = maxRiskLevel;

    // If high or critical risk, sanitize the content
    if (maxRiskLevel === 'high' || maxRiskLevel === 'critical') {
      result.sanitizedContent = this.sanitizeHighRiskContent(content);
      result.isValid = false;
    } else if (result.issues.length > 0) {
      result.sanitizedContent = this.sanitizeContent(content);
    }

    return result;
  }

  /**
   * Compare risk levels
   * @param {string} risk1 - First risk level
   * @param {string} risk2 - Second risk level
   * @returns {number} Comparison result (-1, 0, 1)
   */
  compareRiskLevels(risk1, risk2) {
    const levels = { low: 0, medium: 1, high: 2, critical: 3 };
    return levels[risk1] - levels[risk2];
  }

  /**
   * Sanitize high-risk content
   * @param {string} content - Content to sanitize
   * @returns {string} Sanitized content
   */
  sanitizeHighRiskContent(content) {
    return content
      .replace(/`[a-f0-9]{7,40}`/g, (match) => `\\${match}`) // Escape commit hashes
      .replace(/\$\{[^}]*\}/g, '[TEMPLATE_EXPRESSION_REMOVED]') // Remove template expressions
      .replace(/\$\([^)]*\)/g, '[COMMAND_SUBSTITUTION_REMOVED]') // Remove command substitution
      .replace(/eval\s*\([^)]*\)/g, '[EVAL_REMOVED]') // Remove eval calls
      .replace(/Function\s*\([^)]*\)/g, '[FUNCTION_CONSTRUCTOR_REMOVED]') // Remove Function constructor
      .replace(/require\s*\([^)]*\)/g, '[REQUIRE_REMOVED]') // Remove require calls
      .replace(/process\.[a-zA-Z_][a-zA-Z0-9_]*/g, '[PROCESS_ACCESS_REMOVED]') // Remove process access
      .trim();
  }

  /**
   * Handle error (basic implementation for compatibility)
   * @param {Error|string} error - Error to handle
   * @param {Object} context - Error context
   * @returns {Object} Error handling result
   */
  handleError(error, context = {}) {
    const errorObj = {
      message: error.message || error.toString(),
      category: context.category || ErrorCategory.WORKFLOW,
      severity: context.severity || ErrorSeverity.MEDIUM,
      context: context
    };

    console.error(`ERROR [${errorObj.category}]: ${errorObj.message}`);

    return {
      handled: true,
      category: errorObj.category,
      severity: errorObj.severity,
      shouldExit: errorObj.severity === ErrorSeverity.CRITICAL
    };
  }

  /**
   * Sanitize content for safe GitHub Actions output
   * @param {string} content - Content to sanitize
   * @returns {string} Sanitized content
   */
  sanitizeContent(content) {
    if (!content || typeof content !== 'string') {
      return '';
    }

    return content
      .replace(/\\/g, '\\\\')  // Escape backslashes first
      .replace(/`/g, '\\`')    // Escape backticks to prevent template literal issues
      .replace(/\$/g, '\\$')   // Escape dollar signs to prevent variable interpolation
      .replace(/"/g, '\\"')    // Escape double quotes
      .replace(/'/g, "\\'")    // Escape single quotes
      .replace(/\r?\n/g, '\\n') // Convert newlines to escaped newlines
      .trim();
  }

  /**
   * Handle JavaScript syntax errors specifically
   * @param {Error} error - JavaScript syntax error
   * @param {Object} context - Error context
   * @returns {Object} Handled error result
   */
  handleJavaScriptSyntaxError(error, context = {}) {
    const errorMessage = error.message || error.toString();
    
    // Check if this is the specific "Unexpected identifier" error we're fixing
    const isUnexpectedIdentifier = /Unexpected identifier/.test(errorMessage);
    const hasCommitHash = /[a-f0-9]{7,40}/.test(errorMessage);
    
    if (isUnexpectedIdentifier && hasCommitHash) {
      return this.handleError(error, {
        ...context,
        category: ErrorCategory.JAVASCRIPT_SYNTAX,
        severity: ErrorSeverity.HIGH,
        recovery: {
          action: 'sanitize_template_literals',
          message: 'Commit hash causing JavaScript syntax error - applying template literal sanitization'
        }
      });
    }

    return this.handleError(error, {
      ...context,
      category: ErrorCategory.JAVASCRIPT_SYNTAX,
      severity: ErrorSeverity.MEDIUM
    });
  }

  /**
   * Create safe GitHub Actions output
   * @param {Object} data - Data to output
   * @returns {string} Safe output string
   */
  createSafeGitHubOutput(data) {
    const outputLines = [];
    
    Object.entries(data).forEach(([key, value]) => {
      if (typeof value === 'string' && value.includes('\n')) {
        // Use multiline format for multiline strings
        const delimiter = `EOF_${key.toUpperCase()}_${Date.now()}`;
        outputLines.push(`${key}<<${delimiter}`);
        
        // Validate and sanitize multiline content
        const validation = this.validateAndSanitizeContent(value);
        outputLines.push(validation.sanitizedContent);
        outputLines.push(delimiter);
        
        if (!validation.isValid) {
          console.error(`WARNING: ${key} content was sanitized due to: ${validation.issues.map(i => i.description).join(', ')}`);
        }
      } else {
        // Simple key=value format
        const safeValue = this.sanitizeContent(String(value));
        outputLines.push(`${key}=${safeValue}`);
      }
    });

    return outputLines.join('\n');
  }

  /**
   * Generate GitHub Actions error annotation
   * @param {Object} errorObj - Error object
   * @param {Object} options - Annotation options
   */
  createGitHubAnnotation(errorObj, options = {}) {
    const file = options.file || '';
    const line = options.line || '';
    const col = options.col || '';
    const title = options.title || `${errorObj.category} Error`;

    let annotation = `::error`;

    if (file) annotation += ` file=${file}`;
    if (line) annotation += `,line=${line}`;
    if (col) annotation += `,col=${col}`;
    if (title) annotation += `,title=${title}`;

    annotation += `::${errorObj.message}`;

    console.log(annotation);
  }

  /**
   * Generate error summary for GitHub Actions
   * @param {Array} errors - Array of error objects
   * @returns {string} Error summary
   */
  generateErrorSummary(errors) {
    if (!errors || errors.length === 0) {
      return 'No errors to report';
    }

    const summary = {
      total: errors.length,
      critical: errors.filter(e => e.severity === ErrorSeverity.CRITICAL).length,
      high: errors.filter(e => e.severity === ErrorSeverity.HIGH).length,
      medium: errors.filter(e => e.severity === ErrorSeverity.MEDIUM).length,
      low: errors.filter(e => e.severity === ErrorSeverity.LOW).length
    };

    let report = `## Error Summary\n\n`;
    report += `**Total Errors**: ${summary.total}\n\n`;
    report += `| Severity | Count |\n`;
    report += `|----------|-------|\n`;
    report += `| Critical | ${summary.critical} |\n`;
    report += `| High | ${summary.high} |\n`;
    report += `| Medium | ${summary.medium} |\n`;
    report += `| Low | ${summary.low} |\n\n`;

    if (summary.critical > 0) {
      report += `### Critical Errors\n\n`;
      errors.filter(e => e.severity === ErrorSeverity.CRITICAL).forEach((error, index) => {
        report += `${index + 1}. **${error.category}**: ${error.message}\n`;
      });
      report += `\n`;
    }

    return report;
  }

  /**
   * Process uncaught exceptions safely
   * @param {Error} error - Uncaught exception
   */
  handleUncaughtException(error) {
    console.error('🚨 CRITICAL: Uncaught Exception detected');

    const result = this.handleJavaScriptSyntaxError(error, {
      severity: ErrorSeverity.CRITICAL,
      category: ErrorCategory.WORKFLOW
    });

    // Create safe fallback output
    const fallbackData = {
      processing_success: 'false',
      has_blocking_issues: 'true',
      error_occurred: 'true',
      error_message: this.sanitizeContent(error.message || 'Uncaught exception'),
      review_version: '1',
      required_count: '0',
      important_count: '0',
      total_resolved: '0'
    };

    const safeOutput = this.createSafeGitHubOutput(fallbackData);

    // Write to GitHub Actions output if available
    const githubOutput = process.env.GITHUB_OUTPUT;
    if (githubOutput) {
      try {
        require('fs').appendFileSync(githubOutput, safeOutput + '\n');
        console.error('SUCCESS: Fallback output written to GitHub Actions');
      } catch (writeError) {
        console.error('CRITICAL: Failed to write fallback output:', writeError.message);
      }
    }

    return result;
  }
}

// CLI interface for GitHub Actions usage
if (require.main === module) {
  const handler = new EnhancedErrorHandler({ enableSafetyChecks: true });
  const command = process.argv[2];

  switch (command) {
    case 'handle-uncaught-exception':
      const error = new Error(process.argv[3] || 'Uncaught exception in workflow');
      handler.handleUncaughtException(error);
      break;

    case 'validate-output':
      const githubOutput = process.env.GITHUB_OUTPUT;
      if (githubOutput && require('fs').existsSync(githubOutput)) {
        const content = require('fs').readFileSync(githubOutput, 'utf8');
        const validation = handler.validateAndSanitizeContent(content);
        if (!validation.isValid) {
          console.error('WARNING: GitHub Actions output contains unsafe content');
          validation.issues.forEach(issue => {
            console.error(`  - ${issue.description} (${issue.risk} risk)`);
          });
          process.exit(1);
        } else {
          console.log('SUCCESS: GitHub Actions output validation passed');
          process.exit(0);
        }
      } else {
        console.error('ERROR: GitHub Actions output file not found');
        process.exit(1);
      }
      break;

    case 'create-fallback-output':
      const fallbackData = {
        processing_success: 'false',
        has_blocking_issues: 'true',
        error_occurred: 'true',
        error_message: 'Claude AI review processing failed',
        review_version: '1',
        required_count: '0',
        important_count: '0',
        total_resolved: '0',
        enhanced_comment: 'Claude AI review service encountered an issue. Manual review recommended.'
      };

      const safeOutput = handler.createSafeGitHubOutput(fallbackData);
      const outputFile = process.env.GITHUB_OUTPUT;

      if (outputFile) {
        require('fs').appendFileSync(outputFile, safeOutput + '\n');
        console.log('SUCCESS: Fallback output created');
      } else {
        console.log(safeOutput);
      }
      break;

    default:
      console.log('Usage: enhanced-error-handler.js <command>');
      console.log('Commands:');
      console.log('  handle-uncaught-exception [message] - Handle uncaught exceptions');
      console.log('  validate-output                     - Validate GitHub Actions output');
      console.log('  create-fallback-output              - Create safe fallback output');
      process.exit(1);
  }
}

module.exports = {
  EnhancedErrorHandler,
  ErrorSeverity,
  ErrorCategory
};
