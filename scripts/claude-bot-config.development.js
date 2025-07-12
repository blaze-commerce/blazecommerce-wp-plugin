/**
 * BlazeCommerce Claude AI Review Bot - Development Configuration
 * 
 * Development-specific configuration overrides for testing and debugging.
 */

const baseConfig = require('./claude-bot-config');

module.exports = {
  ...baseConfig,
  
  // Development API Configuration
  API: {
    ...baseConfig.API,
    ANTHROPIC_TIMEOUT: 30000,       // Shorter timeout for development
    GITHUB_TIMEOUT: 15000,          // Shorter timeout for development
    MAX_RETRIES: 2,                 // Fewer retries for faster feedback
    BASE_DELAY: 500,                // Shorter delays for development
  },

  // Development Verification Configuration
  VERIFICATION: {
    ...baseConfig.VERIFICATION,
    CONFIDENCE_THRESHOLD: 0.6,      // Lower threshold for testing
    MAX_FILE_BATCH_SIZE: 10,        // Smaller batches for development
  },

  // Development Error Handling
  ERROR_HANDLING: {
    ...baseConfig.ERROR_HANDLING,
    CIRCUIT_BREAKER_THRESHOLD: 3,   // Lower threshold for testing
    CIRCUIT_BREAKER_TIMEOUT: 60000, // 1 minute for development
    MAX_RETRY_ATTEMPTS: 2,          // Fewer retries
  },

  // Development Timeouts
  TIMEOUTS: {
    ...baseConfig.TIMEOUTS,
    INITIAL_REVIEW: 300000,         // 5 minutes for development
    VERIFICATION: 180000,           // 3 minutes for development
    AUTO_APPROVAL: 120000,          // 2 minutes for development
  },

  // Development GitHub Configuration
  GITHUB: {
    ...baseConfig.GITHUB,
    PER_PAGE: 10,                   // Smaller pages for development
    MAX_PAGES: 5,                   // Fewer pages for development
    MAX_FILE_SIZE: 524288,          // 512KB for development
    MAX_TOTAL_FILES: 50,            // Fewer files for development
  },

  // Development Logging
  LOGGING: {
    ...baseConfig.LOGGING,
    LEVEL: 'debug',                 // More verbose logging
    INCLUDE_STACK_TRACES: true,     // Include stack traces
    CONSOLE_OUTPUT: true,           // Enable console output
  }
};
