#!/usr/bin/env node

/**
 * Security Utilities for GitHub Actions Workflows
 * Provides reusable security functions for safe workflow execution
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

/**
 * Validate input for security risks
 * @param {string} input - Input to validate
 * @param {Object} options - Validation options
 * @returns {string} Validated and sanitized input
 */
function validateInput(input, options = {}) {
  const {
    maxLength = 10000,
    allowEmpty = true,
    type = 'string'
  } = options;

  // Basic type and existence checks
  if (input === null || input === undefined) {
    if (allowEmpty) return '';
    throw new Error('Input is required but was null or undefined');
  }

  if (typeof input !== 'string') {
    input = String(input);
  }

  // Length validation
  if (input.length > maxLength) {
    throw new Error(`Input exceeds maximum length of ${maxLength} characters`);
  }

  // Security pattern checks
  const dangerousPatterns = [
    { pattern: /`[a-f0-9]{7,40}`/g, description: 'Unescaped commit hashes in backticks' },
    { pattern: /\$\{[^}]*\}/g, description: 'Template literal expressions' },
    { pattern: /\$\([^)]*\)/g, description: 'Command substitution patterns' },
    { pattern: /eval\s*\(/gi, description: 'Eval function calls' },
    { pattern: /Function\s*\(/gi, description: 'Function constructor calls' },
    { pattern: /setTimeout\s*\(\s*["'`]/gi, description: 'setTimeout with string argument' },
    { pattern: /setInterval\s*\(\s*["'`]/gi, description: 'setInterval with string argument' },
    { pattern: /document\./g, description: 'DOM manipulation attempts' },
    { pattern: /window\./g, description: 'Window object access' },
    { pattern: /process\.env\.[A-Z_]+/g, description: 'Direct process.env access (use validation)' },
    { pattern: /require\s*\(\s*["'`][^"'`]*["'`]\s*\)/g, description: 'Dynamic require calls' }
  ];

  const foundIssues = [];
  dangerousPatterns.forEach(({ pattern, description }) => {
    if (pattern.test(input)) {
      foundIssues.push(description);
    }
  });

  if (foundIssues.length > 0) {
    throw new Error(`Security validation failed: ${foundIssues.join(', ')}`);
  }

  return input.trim();
}

/**
 * Sanitize content for safe output
 * @param {string} content - Content to sanitize
 * @returns {string} Sanitized content
 */
function sanitizeContent(content) {
  if (!content || typeof content !== 'string') {
    return '';
  }

  return content
    .replace(/\\/g, '\\\\')    // Escape backslashes
    .replace(/`/g, '\\`')      // Escape backticks
    .replace(/\$/g, '\\$')     // Escape dollar signs
    .replace(/"/g, '\\"')      // Escape double quotes
    .replace(/'/g, "\\'")      // Escape single quotes
    .replace(/\r?\n/g, '\\n')  // Convert newlines
    .trim();
}

/**
 * Create safe GitHub Actions output
 * @param {string} key - Output key
 * @param {string} value - Output value
 * @returns {string} Safe output string
 */
function createSafeOutput(key, value) {
  // Validate key
  if (!key || typeof key !== 'string' || !/^[a-zA-Z_][a-zA-Z0-9_]*$/.test(key)) {
    throw new Error('Invalid output key: must be alphanumeric with underscores');
  }

  // Handle multiline values
  if (typeof value === 'string' && value.includes('\n')) {
    const delimiter = `EOF_${key.toUpperCase()}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    const sanitizedValue = sanitizeContent(value);
    return `${key}<<${delimiter}\n${sanitizedValue}\n${delimiter}`;
  } else {
    // Single line value
    const sanitizedValue = sanitizeContent(String(value));
    return `${key}=${sanitizedValue}`;
  }
}

/**
 * Validate GitHub Actions environment variables
 * @param {Object} envVars - Environment variables to validate
 * @returns {Object} Validated environment variables
 */
function validateEnvironmentVariables(envVars) {
  const validated = {};
  
  Object.entries(envVars).forEach(([key, value]) => {
    try {
      validated[key] = validateInput(value, { allowEmpty: true });
    } catch (error) {
      console.warn(`Warning: Environment variable ${key} failed validation: ${error.message}`);
      validated[key] = sanitizeContent(String(value));
    }
  });

  return validated;
}

/**
 * Safe JSON parsing with validation
 * @param {string} jsonString - JSON string to parse
 * @param {Object} options - Parsing options
 * @returns {Object} Parsed and validated JSON
 */
function safeJsonParse(jsonString, options = {}) {
  const { maxDepth = 10, maxKeys = 100 } = options;

  try {
    // Basic validation
    const validated = validateInput(jsonString, { maxLength: 50000 });
    
    // Parse JSON
    const parsed = JSON.parse(validated);
    
    // Validate structure
    function validateObject(obj, depth = 0) {
      if (depth > maxDepth) {
        throw new Error('JSON structure too deep');
      }
      
      if (typeof obj === 'object' && obj !== null) {
        const keys = Object.keys(obj);
        if (keys.length > maxKeys) {
          throw new Error('Too many keys in JSON object');
        }
        
        keys.forEach(key => {
          validateObject(obj[key], depth + 1);
        });
      }
    }
    
    validateObject(parsed);
    return parsed;
    
  } catch (error) {
    throw new Error(`JSON parsing failed: ${error.message}`);
  }
}

/**
 * Create secure error response
 * @param {Error} error - Error object
 * @param {Object} context - Additional context
 * @returns {Object} Safe error response
 */
function createSecureErrorResponse(error, context = {}) {
  const safeMessage = sanitizeContent(error.message || 'Unknown error');
  const safeStack = error.stack ? sanitizeContent(error.stack.split('\n')[0]) : '';
  
  return {
    error: true,
    message: safeMessage,
    type: error.constructor.name,
    timestamp: new Date().toISOString(),
    context: sanitizeContent(JSON.stringify(context)),
    stack: safeStack
  };
}

/**
 * Validate commit SHA format
 * @param {string} sha - Commit SHA to validate
 * @returns {string} Validated SHA
 */
function validateCommitSha(sha) {
  if (!sha || typeof sha !== 'string') {
    return 'unknown';
  }
  
  // Validate SHA format (40 character hex string)
  if (!/^[a-f0-9]{40}$/i.test(sha)) {
    // If not full SHA, check if it's a short SHA (7+ characters)
    if (!/^[a-f0-9]{7,40}$/i.test(sha)) {
      throw new Error('Invalid commit SHA format');
    }
  }
  
  return sha.toLowerCase();
}

/**
 * Validate PR number
 * @param {string|number} prNumber - PR number to validate
 * @returns {number} Validated PR number
 */
function validatePrNumber(prNumber) {
  const num = parseInt(prNumber);
  if (isNaN(num) || num <= 0 || num > 999999) {
    throw new Error('Invalid PR number');
  }
  return num;
}

/**
 * Create safe GitHub Actions summary
 * @param {Object} data - Summary data
 * @returns {string} Safe summary markdown
 */
function createSafeSummary(data) {
  const {
    title = 'Workflow Summary',
    status = 'unknown',
    details = {},
    errors = []
  } = data;

  let summary = `# ${sanitizeContent(title)}\n\n`;
  summary += `**Status**: ${sanitizeContent(status)}\n`;
  summary += `**Timestamp**: ${new Date().toISOString()}\n\n`;

  if (Object.keys(details).length > 0) {
    summary += `## Details\n\n`;
    Object.entries(details).forEach(([key, value]) => {
      const safeKey = sanitizeContent(key);
      const safeValue = sanitizeContent(String(value));
      summary += `- **${safeKey}**: ${safeValue}\n`;
    });
    summary += `\n`;
  }

  if (errors.length > 0) {
    summary += `## Errors\n\n`;
    errors.forEach((error, index) => {
      const safeError = sanitizeContent(String(error));
      summary += `${index + 1}. ${safeError}\n`;
    });
  }

  return summary;
}

module.exports = {
  validateInput,
  sanitizeContent,
  createSafeOutput,
  validateEnvironmentVariables,
  safeJsonParse,
  createSecureErrorResponse,
  validateCommitSha,
  validatePrNumber,
  createSafeSummary
};
