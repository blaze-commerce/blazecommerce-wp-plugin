/**
 * BlazeCommerce Claude AI Review Bot - Centralized Configuration
 *
 * This file contains all configuration constants and settings used across
 * the Claude AI review bot system to avoid scattered hardcoded values.
 */

// Load environment-specific configuration if available
function loadConfig() {
  const env = process.env.NODE_ENV || 'production';
  const baseConfig = getBaseConfig();

  try {
    const envConfig = require(`./claude-bot-config.${env}.js`);
    console.log(`📋 Loaded ${env} configuration`);
    return envConfig;
  } catch (error) {
    console.log(`📋 Using base configuration (${env} config not found)`);
    return baseConfig;
  }
}

function getBaseConfig() {
  return {
  // API Configuration
  API: {
    ANTHROPIC_TIMEOUT: 60000, // 60 seconds
    GITHUB_TIMEOUT: 30000,    // 30 seconds
    MAX_RETRIES: 3,
    BASE_DELAY: 1000,         // 1 second
    MAX_DELAY: 30000,         // 30 seconds
    RATE_LIMIT_THRESHOLD: 10, // Minimum remaining requests before waiting
  },

  // Verification Engine Configuration
  VERIFICATION: {
    CONFIDENCE_THRESHOLD: 0.7,        // Minimum confidence for "addressed" status
    PARTIAL_CONFIDENCE_THRESHOLD: 0.4, // Minimum confidence for "partial" status
    MAX_FILE_BATCH_SIZE: 30,          // Files per batch for pagination
    RELEVANCE_THRESHOLD: 0.3,         // Minimum relevance score for file matching
  },

  // Error Handling Configuration
  ERROR_HANDLING: {
    CIRCUIT_BREAKER_THRESHOLD: 5,     // Failures before circuit breaker opens
    CIRCUIT_BREAKER_TIMEOUT: 300000,  // 5 minutes
    MAX_RETRY_ATTEMPTS: 3,
    EXPONENTIAL_BACKOFF_BASE: 1000,   // 1 second base delay
  },

  // Workflow Timeouts
  TIMEOUTS: {
    INITIAL_REVIEW: 900000,    // 15 minutes
    VERIFICATION: 600000,      // 10 minutes
    AUTO_APPROVAL: 300000,     // 5 minutes
    HEALTH_CHECK: 30000,       // 30 seconds
  },

  // File Paths
  PATHS: {
    TRACKING_FILE: '.github/CLAUDE_REVIEW_TRACKING.md',
    STATE_FILE: '.github/claude-review-state.json',
    ERROR_LOG: '.github/claude-bot-errors.log',
    GITHUB_DIR: '.github',
  },

  // Recommendation Categories
  CATEGORIES: {
    REQUIRED: { 
      icon: '🔴', 
      priority: 1,
      description: 'Critical issues that must be fixed'
    },
    IMPORTANT: { 
      icon: '🟡', 
      priority: 2,
      description: 'Significant improvements recommended'
    },
    SUGGESTION: { 
      icon: '🔵', 
      priority: 3,
      description: 'Optional enhancements'
    }
  },

  // Status Types
  STATUSES: {
    PENDING: { 
      icon: '⏳', 
      description: 'Not yet addressed' 
    },
    PARTIAL: { 
      icon: '🔄', 
      description: 'Partially implemented' 
    },
    ADDRESSED: { 
      icon: '✅', 
      description: 'Fully addressed' 
    },
    VERIFIED: { 
      icon: '✅', 
      description: 'Verified and confirmed' 
    }
  },

  // Claude AI Model Configuration
  CLAUDE: {
    MODEL: 'claude-3-5-sonnet-20241022',
    MAX_TOKENS: 4000,
    API_VERSION: '2023-06-01',
    TEMPERATURE: 0.1, // Low temperature for consistent, focused responses
  },

  // Performance Targets
  PERFORMANCE: {
    INITIAL_REVIEW_TARGET: 180000,    // 3 minutes target
    VERIFICATION_TARGET: 120000,      // 2 minutes target
    AUTO_APPROVAL_TARGET: 60000,      // 1 minute target
    SUCCESS_RATE_TARGET: 0.95,        // 95% success rate
    AVAILABILITY_TARGET: 0.995,       // 99.5% availability
  },

  // GitHub API Configuration
  GITHUB: {
    PER_PAGE: 30,                    // Items per page for pagination
    MAX_PAGES: 10,                   // Maximum pages to fetch
    RATE_LIMIT_BUFFER: 50,           // Buffer for rate limit checks
    RETRY_AFTER_HEADER: 'retry-after',
    MAX_FILE_SIZE: 1048576,          // 1MB maximum file size for analysis
    MAX_TOTAL_FILES: 100,            // Maximum total files to process
  },

  // Security Settings
  SECURITY: {
    ALLOWED_ORGANIZATIONS: ['blaze-commerce'],
    MAX_SECRET_LENGTH: 1000,         // Maximum length for secrets
    SANITIZE_LOGS: true,             // Remove sensitive data from logs
    VALIDATE_PATHS: true,            // Enable path traversal protection
  }
  };
}

// Export the configuration with environment-specific overrides
module.exports = loadConfig();
