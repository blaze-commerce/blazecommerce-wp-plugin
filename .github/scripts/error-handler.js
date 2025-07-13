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
class WorkflowErrorHandler {
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
  getContext() {
    return {
      workflow: process.env.GITHUB_WORKFLOW || 'unknown',
      job: process.env.GITHUB_JOB || 'unknown',
      step: process.env.GITHUB_STEP || 'unknown',
      repository: process.env.GITHUB_REPOSITORY || 'unknown',
      runId: process.env.GITHUB_RUN_ID || 'unknown',
      sha: process.env.GITHUB_SHA || 'unknown',
      timestamp: new Date().toISOString()
    };
  }

  /**
   * Create a standardized error object
   * @param {string} message - Error message
   * @param {string} category - Error category
   * @param {string} severity - Error severity
   * @param {Error} originalError - Original error object
   * @param {Object} metadata - Additional metadata
   * @returns {Object} Standardized error object
   */
  createError(message, category = ErrorCategory.UNKNOWN, severity = ErrorSeverity.MEDIUM, originalError = null, metadata = {}) {
    return {
      id: this.generateErrorId(),
      message,
      category,
      severity,
      timestamp: new Date().toISOString(),
      context: this.context,
      originalError: originalError ? {
        name: originalError.name,
        message: originalError.message,
        stack: originalError.stack
      } : null,
      metadata
    };
  }

  /**
   * Generate unique error ID
   * @returns {string} Unique error ID
   */
  generateErrorId() {
    return `err_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
  }

  /**
   * Handle and log an error
   * @param {string} message - Error message
   * @param {string} category - Error category
   * @param {string} severity - Error severity
   * @param {Error} originalError - Original error object
   * @param {Object} metadata - Additional metadata
   * @returns {Object} Created error object
   */
  handleError(message, category = ErrorCategory.UNKNOWN, severity = ErrorSeverity.MEDIUM, originalError = null, metadata = {}) {
    const error = this.createError(message, category, severity, originalError, metadata);
    this.errors.push(error);
    
    this.logError(error);
    
    // Set GitHub Actions output if in CI environment
    if (process.env.GITHUB_ACTIONS) {
      this.setGitHubOutput(error);
    }

    return error;
  }

  /**
   * Handle and log a warning
   * @param {string} message - Warning message
   * @param {Object} metadata - Additional metadata
   * @returns {Object} Created warning object
   */
  handleWarning(message, metadata = {}) {
    const warning = {
      id: this.generateErrorId(),
      message,
      timestamp: new Date().toISOString(),
      context: this.context,
      metadata
    };
    
    this.warnings.push(warning);
    console.log(`WARNING:  WARNING: ${message}`);
    
    return warning;
  }

  /**
   * Log error with appropriate formatting
   * @param {Object} error - Error object to log
   */
  logError(error) {
    const severityEmoji = {
      [ErrorSeverity.LOW]: 'INFO:',
      [ErrorSeverity.MEDIUM]: 'WARNING:',
      [ErrorSeverity.HIGH]: '',
      [ErrorSeverity.CRITICAL]: 'CRITICAL:'
    };

    console.error(`${severityEmoji[error.severity]} ERROR [${error.category.toUpperCase()}]: ${error.message}`);
    console.error(`   Error ID: ${error.id}`);
    console.error(`   Severity: ${error.severity}`);
    console.error(`   Context: ${error.context.workflow}/${error.context.job}`);
    
    if (error.originalError) {
      console.error(`   Original Error: ${error.originalError.name}: ${error.originalError.message}`);
      if (process.env.DEBUG === 'true') {
        console.error(`   Stack Trace: ${error.originalError.stack}`);
      }
    }

    if (Object.keys(error.metadata).length > 0) {
      console.error(`   Metadata: ${JSON.stringify(error.metadata, null, 2)}`);
    }
  }

  /**
   * Set GitHub Actions output for error
   * @param {Object} error - Error object
   */
  setGitHubOutput(error) {
    console.log(`error_occurred=true`);
    console.log(`error_id=${error.id}`);
    console.log(`error_message=${error.message}`);
    console.log(`error_category=${error.category}`);
    console.log(`error_severity=${error.severity}`);
  }

  /**
   * Check if any critical errors occurred
   * @returns {boolean} True if critical errors exist
   */
  hasCriticalErrors() {
    return this.errors.some(error => error.severity === ErrorSeverity.CRITICAL);
  }

  /**
   * Get error summary
   * @returns {Object} Error summary
   */
  getSummary() {
    const summary = {
      totalErrors: this.errors.length,
      totalWarnings: this.warnings.length,
      bySeverity: {},
      byCategory: {},
      hasCritical: this.hasCriticalErrors()
    };

    // Count by severity
    Object.values(ErrorSeverity).forEach(severity => {
      summary.bySeverity[severity] = this.errors.filter(e => e.severity === severity).length;
    });

    // Count by category
    Object.values(ErrorCategory).forEach(category => {
      summary.byCategory[category] = this.errors.filter(e => e.category === category).length;
    });

    return summary;
  }

  /**
   * Generate error report
   * @returns {string} Formatted error report
   */
  generateReport() {
    const summary = this.getSummary();
    
    let report = `\nANALYSIS: ERROR REPORT\n`;
    report += `================\n`;
    report += `Total Errors: ${summary.totalErrors}\n`;
    report += `Total Warnings: ${summary.totalWarnings}\n`;
    report += `Critical Errors: ${summary.hasCritical ? 'YES' : 'NO'}\n\n`;

    if (summary.totalErrors > 0) {
      report += `Errors by Severity:\n`;
      Object.entries(summary.bySeverity).forEach(([severity, count]) => {
        if (count > 0) {
          report += `  ${severity}: ${count}\n`;
        }
      });

      report += `\nErrors by Category:\n`;
      Object.entries(summary.byCategory).forEach(([category, count]) => {
        if (count > 0) {
          report += `  ${category}: ${count}\n`;
        }
      });
    }

    return report;
  }

  /**
   * Save error report to file
   * @param {string} filePath - Path to save report
   */
  saveReport(filePath) {
    try {
      const report = this.generateReport();
      const fullReport = {
        summary: this.getSummary(),
        errors: this.errors,
        warnings: this.warnings,
        context: this.context,
        generatedAt: new Date().toISOString()
      };

      fs.writeFileSync(filePath, JSON.stringify(fullReport, null, 2));
      console.log(`REPORT: Error report saved to: ${filePath}`);
    } catch (error) {
      console.error(`Failed to save error report: ${error.message}`);
    }
  }

  /**
   * Exit with appropriate code based on error severity
   */
  exitWithCode() {
    const summary = this.getSummary();
    
    if (summary.hasCritical) {
      console.error('\nCRITICAL: CRITICAL ERRORS DETECTED - EXITING WITH CODE 2');
      process.exit(2);
    } else if (summary.totalErrors > 0) {
      console.error('\nWARNING: ERRORS DETECTED - EXITING WITH CODE 1');
      process.exit(1);
    } else if (summary.totalWarnings > 0) {
      console.log('\nWARNING:  WARNINGS DETECTED - EXITING WITH CODE 0');
      process.exit(0);
    } else {
      console.log('\nSUCCESS: NO ERRORS OR WARNINGS - EXITING WITH CODE 0');
      process.exit(0);
    }
  }
}

/**
 * Global error handler instance
 */
const globalErrorHandler = new ErrorHandler();

/**
 * Convenience functions
 */
const handleError = (message, category, severity, originalError, metadata) => 
  globalErrorHandler.handleError(message, category, severity, originalError, metadata);

const handleWarning = (message, metadata) => 
  globalErrorHandler.handleWarning(message, metadata);

const handleCriticalError = (message, category, originalError, metadata) => 
  globalErrorHandler.handleError(message, category, ErrorSeverity.CRITICAL, originalError, metadata);

/**
 * Process uncaught exceptions
 */
process.on('uncaughtException', (error) => {
  handleCriticalError('Uncaught Exception', ErrorCategory.UNKNOWN, error);
  globalErrorHandler.exitWithCode();
});

process.on('unhandledRejection', (reason, promise) => {
  handleCriticalError('Unhandled Promise Rejection', ErrorCategory.UNKNOWN, reason);
  globalErrorHandler.exitWithCode();
});

module.exports = {
  ErrorHandler,
  ErrorSeverity,
  ErrorCategory,
  globalErrorHandler,
  handleError,
  handleWarning,
  handleCriticalError
};
