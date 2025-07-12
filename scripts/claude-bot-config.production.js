/**
 * BlazeCommerce Claude AI Review Bot - Production Configuration
 * 
 * Production-specific configuration optimized for reliability and performance.
 */

const baseConfig = require('./claude-bot-config');

module.exports = {
  ...baseConfig,
  
  // Production API Configuration
  API: {
    ...baseConfig.API,
    ANTHROPIC_TIMEOUT: 90000,       // Longer timeout for production reliability
    GITHUB_TIMEOUT: 45000,          // Longer timeout for production
    MAX_RETRIES: 5,                 // More retries for production reliability
    BASE_DELAY: 2000,               // Longer delays for production stability
  },

  // Production Verification Configuration
  VERIFICATION: {
    ...baseConfig.VERIFICATION,
    CONFIDENCE_THRESHOLD: 0.8,      // Higher threshold for production quality
    MAX_FILE_BATCH_SIZE: 50,        // Larger batches for production efficiency
  },

  // Production Error Handling
  ERROR_HANDLING: {
    ...baseConfig.ERROR_HANDLING,
    CIRCUIT_BREAKER_THRESHOLD: 10,  // Higher threshold for production
    CIRCUIT_BREAKER_TIMEOUT: 600000, // 10 minutes for production
    MAX_RETRY_ATTEMPTS: 5,          // More retries for production
  },

  // Production Timeouts
  TIMEOUTS: {
    ...baseConfig.TIMEOUTS,
    INITIAL_REVIEW: 1200000,        // 20 minutes for production
    VERIFICATION: 900000,           // 15 minutes for production
    AUTO_APPROVAL: 600000,          // 10 minutes for production
  },

  // Production GitHub Configuration
  GITHUB: {
    ...baseConfig.GITHUB,
    PER_PAGE: 50,                   // Larger pages for production efficiency
    MAX_PAGES: 20,                  // More pages for production
    MAX_FILE_SIZE: 2097152,         // 2MB for production
    MAX_TOTAL_FILES: 200,           // More files for production
  },

  // Production Security
  SECURITY: {
    ...baseConfig.SECURITY,
    SANITIZE_LOGS: true,            // Always sanitize logs in production
    VALIDATE_PATHS: true,           // Always validate paths in production
    STRICT_MODE: true,              // Enable strict security mode
  },

  // Production Logging
  LOGGING: {
    ...baseConfig.LOGGING,
    LEVEL: 'info',                  // Less verbose logging for production
    INCLUDE_STACK_TRACES: false,    // No stack traces in production logs
    CONSOLE_OUTPUT: false,          // Disable console output in production
    STRUCTURED_LOGGING: true,       // Enable structured logging
  }
};
