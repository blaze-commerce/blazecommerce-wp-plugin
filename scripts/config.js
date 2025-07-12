#!/usr/bin/env node

/**
 * CLAUDE AI REVIEW: Fixed magic numbers from comment #3060465549, #3060512807, #3060543625
 * Configuration Constants for Version Management System
 * Centralizes all configuration values to avoid magic numbers and improve maintainability
 */

module.exports = {
  // Version validation settings
  VERSION: {
    // Maximum number of commits to analyze for version determination
    MAX_COMMITS_TO_ANALYZE: 50,

    // Maximum number of commits to process for changelog generation
    MAX_CHANGELOG_COMMITS: 100,

    // Hard limit for memory safety
    ABSOLUTE_MAX_COMMITS: 1000,

    // Backup file timestamp format
    BACKUP_TIMESTAMP_FORMAT: 'YYYY-MM-DD-HH-mm-ss',

    // Maximum backup files to keep
    MAX_BACKUP_FILES: 10,

    // Maximum version string length
    MAX_VERSION_LENGTH: 100,

    // Maximum issue number value
    MAX_ISSUE_NUMBER: 999999
  },

  // Git operation settings
  GIT: {
    // Timeout for git operations (in milliseconds)
    OPERATION_TIMEOUT: 30000,
    
    // Maximum tag name length
    MAX_TAG_LENGTH: 100,
    
    // Valid characters for tag names (security) - CLAUDE AI REVIEW: Updated to support semantic versioning with prerelease and build metadata
    TAG_NAME_REGEX: /^[a-zA-Z0-9._+-]+$/,
    
    // Git command options
    DEFAULT_OPTIONS: {
      encoding: 'utf8',
      maxBuffer: 1024 * 1024 // 1MB
    }
  },

  // Changelog generation settings
  CHANGELOG: {
    // Maximum commit message length to process
    MAX_COMMIT_MESSAGE_LENGTH: 500,
    
    // Maximum number of references to extract per commit
    MAX_REFERENCES_PER_COMMIT: 10,
    
    // Batch size for processing commits
    COMMIT_BATCH_SIZE: 20,
    
    // Maximum abbreviation expansion length
    MAX_EXPANSION_LENGTH: 100
  },

  // File operation settings
  FILES: {
    // Maximum file size to process (in bytes)
    MAX_FILE_SIZE: 10 * 1024 * 1024, // 10MB
    
    // File encoding
    DEFAULT_ENCODING: 'utf8',
    
    // Backup file extension
    BACKUP_EXTENSION: '.backup',
    
    // Temporary file prefix
    TEMP_PREFIX: '.tmp-version-'
  },

  // Error handling settings
  ERRORS: {
    // Maximum retry attempts for operations
    MAX_RETRY_ATTEMPTS: 3,
    
    // Retry delay in milliseconds
    RETRY_DELAY: 1000,
    
    // Maximum error message length
    MAX_ERROR_MESSAGE_LENGTH: 1000
  },

  // Performance settings
  PERFORMANCE: {
    // Enable caching for file operations
    ENABLE_FILE_CACHE: true,
    
    // Cache TTL in milliseconds
    CACHE_TTL: 60000, // 1 minute
    
    // Maximum cache size (number of entries)
    MAX_CACHE_SIZE: 100
  },

  // Validation settings
  VALIDATION: {
    // Enable strict validation mode
    STRICT_MODE: false,
    
    // Validation timeout in milliseconds
    TIMEOUT: 10000,
    
    // Maximum validation errors to report
    MAX_ERRORS_TO_REPORT: 20
  }
};
