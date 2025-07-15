#!/usr/bin/env node

/**
 * Claude AI Error Handler
 * 
 * Provides specific error types and handling for Claude AI workflows
 */

class ClaudeError extends Error {
  constructor(message, type, code, details = {}) {
    super(message);
    this.name = 'ClaudeError';
    this.type = type;
    this.code = code;
    this.details = details;
    this.timestamp = new Date().toISOString();
  }

  toJSON() {
    return {
      name: this.name,
      message: this.message,
      type: this.type,
      code: this.code,
      details: this.details,
      timestamp: this.timestamp,
      stack: this.stack
    };
  }
}

class ClaudeErrorHandler {
  static get ERROR_TYPES() {
    return {
      API_ERROR: 'API_ERROR',
      AUTHENTICATION_ERROR: 'AUTHENTICATION_ERROR',
      PERMISSION_ERROR: 'PERMISSION_ERROR',
      VALIDATION_ERROR: 'VALIDATION_ERROR',
      TIMEOUT_ERROR: 'TIMEOUT_ERROR',
      PARSING_ERROR: 'PARSING_ERROR',
      CONFIGURATION_ERROR: 'CONFIGURATION_ERROR',
      NETWORK_ERROR: 'NETWORK_ERROR'
    };
  }

  static get ERROR_CODES() {
    return {
      // API Errors
      GITHUB_API_FAILURE: 'GITHUB_API_FAILURE',
      ANTHROPIC_API_FAILURE: 'ANTHROPIC_API_FAILURE',
      RATE_LIMIT_EXCEEDED: 'RATE_LIMIT_EXCEEDED',
      
      // Authentication Errors
      MISSING_TOKEN: 'MISSING_TOKEN',
      INVALID_TOKEN: 'INVALID_TOKEN',
      TOKEN_EXPIRED: 'TOKEN_EXPIRED',
      
      // Permission Errors
      INSUFFICIENT_PERMISSIONS: 'INSUFFICIENT_PERMISSIONS',
      REPOSITORY_ACCESS_DENIED: 'REPOSITORY_ACCESS_DENIED',
      
      // Validation Errors
      INVALID_PR_NUMBER: 'INVALID_PR_NUMBER',
      MISSING_REQUIRED_FIELD: 'MISSING_REQUIRED_FIELD',
      INVALID_STATUS_FORMAT: 'INVALID_STATUS_FORMAT',
      
      // Timeout Errors
      WORKFLOW_TIMEOUT: 'WORKFLOW_TIMEOUT',
      API_REQUEST_TIMEOUT: 'API_REQUEST_TIMEOUT',
      
      // Parsing Errors
      COMMENT_PARSING_FAILED: 'COMMENT_PARSING_FAILED',
      STATUS_DETECTION_FAILED: 'STATUS_DETECTION_FAILED',
      
      // Configuration Errors
      MISSING_CONFIGURATION: 'MISSING_CONFIGURATION',
      INVALID_CONFIGURATION: 'INVALID_CONFIGURATION'
    };
  }

  /**
   * Create a specific error based on the error type and context
   */
  static createError(type, code, message, details = {}) {
    return new ClaudeError(message, type, code, details);
  }

  /**
   * Handle GitHub API errors
   */
  static handleGitHubAPIError(error, context = {}) {
    const { status, message } = error;
    
    switch (status) {
      case 401:
        return this.createError(
          this.ERROR_TYPES.AUTHENTICATION_ERROR,
          this.ERROR_CODES.INVALID_TOKEN,
          'GitHub API authentication failed. Check BOT_GITHUB_TOKEN secret.',
          { originalError: error, context }
        );
      
      case 403:
        if (message.includes('rate limit')) {
          return this.createError(
            this.ERROR_TYPES.API_ERROR,
            this.ERROR_CODES.RATE_LIMIT_EXCEEDED,
            'GitHub API rate limit exceeded. Please wait before retrying.',
            { originalError: error, context }
          );
        }
        return this.createError(
          this.ERROR_TYPES.PERMISSION_ERROR,
          this.ERROR_CODES.INSUFFICIENT_PERMISSIONS,
          'Insufficient permissions for GitHub API operation.',
          { originalError: error, context }
        );
      
      case 404:
        return this.createError(
          this.ERROR_TYPES.VALIDATION_ERROR,
          this.ERROR_CODES.REPOSITORY_ACCESS_DENIED,
          'Repository or resource not found. Check repository access.',
          { originalError: error, context }
        );
      
      case 422:
        return this.createError(
          this.ERROR_TYPES.VALIDATION_ERROR,
          this.ERROR_CODES.INVALID_PR_NUMBER,
          'Invalid request data. Check PR number and parameters.',
          { originalError: error, context }
        );
      
      default:
        return this.createError(
          this.ERROR_TYPES.API_ERROR,
          this.ERROR_CODES.GITHUB_API_FAILURE,
          `GitHub API error: ${message}`,
          { originalError: error, context, status }
        );
    }
  }

  /**
   * Handle validation errors
   */
  static handleValidationError(field, value, expectedType) {
    return this.createError(
      this.ERROR_TYPES.VALIDATION_ERROR,
      this.ERROR_CODES.MISSING_REQUIRED_FIELD,
      `Validation failed for field '${field}'. Expected ${expectedType}, got ${typeof value}.`,
      { field, value, expectedType }
    );
  }

  /**
   * Handle parsing errors
   */
  static handleParsingError(content, pattern, context = {}) {
    return this.createError(
      this.ERROR_TYPES.PARSING_ERROR,
      this.ERROR_CODES.COMMENT_PARSING_FAILED,
      'Failed to parse Claude review comment. Pattern not found.',
      { content: content.substring(0, 200) + '...', pattern, context }
    );
  }

  /**
   * Handle timeout errors
   */
  static handleTimeoutError(operation, timeoutMs) {
    return this.createError(
      this.ERROR_TYPES.TIMEOUT_ERROR,
      this.ERROR_CODES.WORKFLOW_TIMEOUT,
      `Operation '${operation}' timed out after ${timeoutMs}ms.`,
      { operation, timeoutMs }
    );
  }

  /**
   * Handle configuration errors
   */
  static handleConfigurationError(configKey, expectedValue) {
    return this.createError(
      this.ERROR_TYPES.CONFIGURATION_ERROR,
      this.ERROR_CODES.MISSING_CONFIGURATION,
      `Missing or invalid configuration for '${configKey}'. Expected: ${expectedValue}`,
      { configKey, expectedValue }
    );
  }

  /**
   * Log error with appropriate level and format
   */
  static logError(error, context = {}) {
    const errorInfo = {
      timestamp: new Date().toISOString(),
      type: error.type || 'UNKNOWN',
      code: error.code || 'UNKNOWN',
      message: error.message,
      context,
      stack: error.stack
    };

    // Determine log level based on error type
    const logLevel = this.getLogLevel(error.type);
    
    console.error(`${logLevel}: Claude AI Error - ${error.type}:${error.code}`);
    console.error(`Message: ${error.message}`);
    console.error(`Details:`, JSON.stringify(errorInfo, null, 2));
    
    return errorInfo;
  }

  /**
   * Get appropriate log level for error type
   */
  static getLogLevel(errorType) {
    switch (errorType) {
      case this.ERROR_TYPES.AUTHENTICATION_ERROR:
      case this.ERROR_TYPES.PERMISSION_ERROR:
        return 'CRITICAL';
      case this.ERROR_TYPES.API_ERROR:
      case this.ERROR_TYPES.TIMEOUT_ERROR:
        return 'ERROR';
      case this.ERROR_TYPES.VALIDATION_ERROR:
      case this.ERROR_TYPES.PARSING_ERROR:
        return 'WARNING';
      default:
        return 'ERROR';
    }
  }

  /**
   * Get user-friendly error message for display
   */
  static getUserFriendlyMessage(error) {
    switch (error.code) {
      case this.ERROR_CODES.MISSING_TOKEN:
        return 'Authentication required. Please configure BOT_GITHUB_TOKEN secret.';
      case this.ERROR_CODES.INSUFFICIENT_PERMISSIONS:
        return 'Insufficient permissions. Please check repository access settings.';
      case this.ERROR_CODES.RATE_LIMIT_EXCEEDED:
        return 'API rate limit exceeded. Please wait a few minutes before retrying.';
      case this.ERROR_CODES.INVALID_PR_NUMBER:
        return 'Invalid pull request number. Please check the PR exists.';
      case this.ERROR_CODES.COMMENT_PARSING_FAILED:
        return 'Unable to parse Claude review. Please check review format.';
      case this.ERROR_CODES.WORKFLOW_TIMEOUT:
        return 'Operation timed out. Please try again or contact support.';
      default:
        return error.message || 'An unexpected error occurred.';
    }
  }

  /**
   * Create recovery suggestions based on error type
   */
  static getRecoverySuggestions(error) {
    switch (error.code) {
      case this.ERROR_CODES.MISSING_TOKEN:
        return [
          'Configure BOT_GITHUB_TOKEN in repository secrets',
          'Ensure token has required permissions',
          'Verify token is not expired'
        ];
      case this.ERROR_CODES.RATE_LIMIT_EXCEEDED:
        return [
          'Wait for rate limit to reset',
          'Reduce API call frequency',
          'Consider using GitHub App authentication'
        ];
      case this.ERROR_CODES.COMMENT_PARSING_FAILED:
        return [
          'Check Claude review format matches expected pattern',
          'Verify FINAL VERDICT section exists',
          'Update pattern configuration if needed'
        ];
      case this.ERROR_CODES.WORKFLOW_TIMEOUT:
        return [
          'Increase timeout configuration',
          'Check for workflow dependencies',
          'Verify GitHub Actions service status'
        ];
      default:
        return [
          'Check workflow logs for details',
          'Verify configuration settings',
          'Contact support if issue persists'
        ];
    }
  }
}

module.exports = { ClaudeError, ClaudeErrorHandler };

// CLI usage for testing
if (require.main === module) {
  // Example usage
  try {
    throw ClaudeErrorHandler.createError(
      ClaudeErrorHandler.ERROR_TYPES.VALIDATION_ERROR,
      ClaudeErrorHandler.ERROR_CODES.INVALID_PR_NUMBER,
      'Test error message',
      { prNumber: 'invalid' }
    );
  } catch (error) {
    ClaudeErrorHandler.logError(error, { test: true });
    console.log('User-friendly message:', ClaudeErrorHandler.getUserFriendlyMessage(error));
    console.log('Recovery suggestions:', ClaudeErrorHandler.getRecoverySuggestions(error));
  }
}
