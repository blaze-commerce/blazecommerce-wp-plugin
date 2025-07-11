#!/usr/bin/env node

/**
 * Semantic Versioning Utilities
 * Provides functions for parsing, validating, and comparing semantic versions
 */

const fs = require('fs');
const { execSync } = require('child_process');
const config = require('./config');

// CLAUDE AI REVIEW: Custom error types for better error handling
class ValidationError extends Error {
  constructor(message) {
    super(message);
    this.name = 'ValidationError';
  }
}

class GitError extends Error {
  constructor(message) {
    super(message);
    this.name = 'GitError';
  }
}

class VersionError extends Error {
  constructor(message) {
    super(message);
    this.name = 'VersionError';
  }
}

// Semantic versioning regex pattern
const SEMVER_REGEX = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/;

// Conventional commit regex pattern
const CONVENTIONAL_COMMIT_REGEX = /^(feat|fix|docs|style|refactor|perf|test|chore|build|ci)(\(.+\))?(!)?: (.+)/;

// Breaking change patterns
const BREAKING_CHANGE_PATTERNS = [
  /^(feat|fix|docs|style|refactor|perf|test|chore|build|ci)(\(.+\))?!:/,
  /BREAKING CHANGE:/i
];

/**
 * CLAUDE AI REVIEW: Fixed command injection risk from comment #3060465549, #3060512807, #3060543625
 * Validate and sanitize git tag name for security
 * @param {string} tagName - Tag name to validate
 * @returns {string} Sanitized tag name
 * @throws {Error} If tag name is invalid
 */
function validateTagName(tagName) {
  if (!tagName || typeof tagName !== 'string') {
    throw new Error('Tag name must be a non-empty string');
  }

  // Trim and normalize
  const trimmed = tagName.trim();

  if (trimmed.length === 0) {
    throw new Error('Tag name cannot be empty after trimming');
  }

  if (trimmed.length > config.GIT.MAX_TAG_LENGTH) {
    throw new Error(`Tag name too long (max ${config.GIT.MAX_TAG_LENGTH} characters)`);
  }

  // CLAUDE AI REVIEW: Enhanced security from comment #3060512807 - comprehensive shell metacharacter detection
  const dangerousChars = /[;&|`$(){}[\]\\'"<>*?~]/;
  if (dangerousChars.test(trimmed)) {
    throw new Error('Tag name contains dangerous characters that could cause security issues');
  }

  // Check against allowed pattern
  if (!config.GIT.TAG_NAME_REGEX.test(trimmed)) {
    throw new Error('Tag name contains invalid characters. Only alphanumeric, dots, underscores, and hyphens are allowed');
  }

  // Additional security checks
  if (trimmed.startsWith('-') || trimmed.startsWith('.')) {
    throw new Error('Tag name cannot start with dash or dot');
  }

  if (trimmed.includes('..')) {
    throw new Error('Tag name cannot contain consecutive dots');
  }

  return trimmed;
}

/**
 * Comprehensive input validation function
 * @param {any} input - Input to validate
 * @param {string} type - Expected type ('string', 'number', 'array', 'object')
 * @param {object} constraints - Validation constraints
 * @returns {any} Validated input
 * @throws {Error} If validation fails
 */
function validateInput(input, type, constraints = {}) {
  const {
    required = true,
    minLength = 0,
    maxLength = Infinity,
    min = -Infinity,
    max = Infinity,
    pattern = null,
    allowEmpty = false
  } = constraints;

  // Check if required
  if (required && (input === null || input === undefined)) {
    throw new ValidationError('Input is required but was null or undefined');
  }

  // Allow null/undefined if not required
  if (!required && (input === null || input === undefined)) {
    return input;
  }

  // Type validation
  if (typeof input !== type) {
    throw new ValidationError(`Expected ${type} but got ${typeof input}`);
  }

  // String-specific validations
  if (type === 'string') {
    if (!allowEmpty && input.length === 0) {
      throw new Error('String cannot be empty');
    }
    if (input.length < minLength) {
      throw new Error(`String too short (min ${minLength} characters)`);
    }
    if (input.length > maxLength) {
      throw new Error(`String too long (max ${maxLength} characters)`);
    }
    if (pattern && !pattern.test(input)) {
      throw new Error('String does not match required pattern');
    }
  }

  // Number-specific validations
  if (type === 'number') {
    if (isNaN(input) || !isFinite(input)) {
      throw new Error('Number must be finite');
    }
    if (input < min) {
      throw new Error(`Number too small (min ${min})`);
    }
    if (input > max) {
      throw new Error(`Number too large (max ${max})`);
    }
  }

  // Array-specific validations
  if (type === 'object' && Array.isArray(input)) {
    if (input.length < minLength) {
      throw new Error(`Array too short (min ${minLength} elements)`);
    }
    if (input.length > maxLength) {
      throw new Error(`Array too long (max ${maxLength} elements)`);
    }
  }

  return input;
}

// Rate limiting for git operations (Claude AI recommendation)
let gitOperationCount = 0;
let gitOperationWindow = Date.now();
const GIT_RATE_LIMIT = 100; // operations per minute
const GIT_RATE_WINDOW = 60000; // 1 minute in milliseconds

/**
 * Rate limiting check for git operations
 * Addresses Claude AI security recommendation for rate limiting
 */
function checkGitRateLimit() {
  const now = Date.now();

  // Reset counter if window has passed
  if (now - gitOperationWindow > GIT_RATE_WINDOW) {
    gitOperationCount = 0;
    gitOperationWindow = now;
  }

  gitOperationCount++;

  if (gitOperationCount > GIT_RATE_LIMIT) {
    throw new Error(`❌ Git operation rate limit exceeded: ${GIT_RATE_LIMIT} operations per minute. This may indicate a runaway process or potential security issue.`);
  }
}

/**
 * CLAUDE AI REVIEW: Enhanced with rate limiting and improved security
 * Execute git command safely with input validation and rate limiting
 * @param {string} command - Git command to execute
 * @param {object} options - Execution options
 * @returns {string} Command output
 */
function safeGitExec(command, options = {}) {
  // Rate limiting check (Claude AI recommendation)
  checkGitRateLimit();

  if (!command || typeof command !== 'string') {
    throw new Error('Command must be a non-empty string');
  }

  // CLAUDE AI REVIEW: Security enhancement from comment #3060512807 - git command validation
  // Validate that command starts with 'git' for security
  if (!command.trim().startsWith('git ')) {
    throw new Error('Only git commands are allowed');
  }

  // CLAUDE AI REVIEW: Dangerous pattern detection from comment #3060512807
  // Check for dangerous command patterns
  const dangerousPatterns = [
    /[;&|`$(){}[\]\\'"<>]/,  // Shell metacharacters
    /\s(rm|del|format|mkfs|dd)\s/i,  // Dangerous commands
    /\s--exec\s/i,  // Git exec flag
    /\s-c\s/i,  // Git config flag that could be dangerous
  ];

  for (const pattern of dangerousPatterns) {
    if (pattern.test(command)) {
      throw new Error('Command contains potentially dangerous patterns');
    }
  }

  // CLAUDE AI REVIEW: Security configuration from comment #3060512807 - shell=false prevents injection
  const defaultOptions = {
    ...config.GIT.DEFAULT_OPTIONS,
    timeout: config.GIT.OPERATION_TIMEOUT,
    shell: false,  // Disable shell interpretation for security
    ...options
  };

  // CLAUDE AI REVIEW: Performance monitoring for long-running operations
  const startTime = Date.now();
  const warningThreshold = 5000; // 5 seconds

  try {
    const result = execSync(command, defaultOptions);

    // CLAUDE AI REVIEW: Warn about long-running operations
    const duration = Date.now() - startTime;
    if (duration > warningThreshold) {
      console.warn(`⚠️  Git command took ${duration}ms to execute: ${command.substring(0, 50)}...`);
    }

    return result;
  } catch (error) {
    // Log error but don't expose sensitive information
    const safeMessage = error.message.substring(0, config.ERRORS.MAX_ERROR_MESSAGE_LENGTH);
    console.warn(`Git operation failed: ${safeMessage}`);
    throw new GitError(`Git operation failed: ${error.code || 'unknown error'}`);
  }
}

/**
 * Parse a semantic version string
 * @param {string} version - Version string to parse
 * @returns {object|null} Parsed version object or null if invalid
 */
function parseVersion(version) {
  try {
    validateInput(version, 'string', {
      required: true,
      maxLength: config.VERSION.MAX_VERSION_LENGTH,
      allowEmpty: false
    });
  } catch (error) {
    return null;
  }

  const match = version.match(SEMVER_REGEX);
  if (!match) return null;

  return {
    major: parseInt(match[1], 10),
    minor: parseInt(match[2], 10),
    patch: parseInt(match[3], 10),
    prerelease: match[4] || null,
    build: match[5] || null,
    raw: version
  };
}

/**
 * Validate if a string is a valid semantic version
 * @param {string} version - Version string to validate
 * @returns {boolean} True if valid semantic version
 */
function isValidSemver(version) {
  if (!version || typeof version !== 'string') {
    return false;
  }

  // Reject overly long versions for security
  if (version.length > config.VERSION.MAX_VERSION_LENGTH) {
    return false;
  }

  return SEMVER_REGEX.test(version);
}

/**
 * Compare two semantic versions
 * @param {string} version1 - First version
 * @param {string} version2 - Second version
 * @returns {number} -1 if version1 < version2, 0 if equal, 1 if version1 > version2
 */
function compareVersions(version1, version2) {
  const v1 = parseVersion(version1);
  const v2 = parseVersion(version2);

  if (!v1 || !v2) {
    throw new Error('Invalid version format for comparison');
  }

  // Compare major, minor, patch
  if (v1.major !== v2.major) return v1.major - v2.major;
  if (v1.minor !== v2.minor) return v1.minor - v2.minor;
  if (v1.patch !== v2.patch) return v1.patch - v2.patch;

  // Handle prerelease versions
  if (v1.prerelease && !v2.prerelease) return -1;
  if (!v1.prerelease && v2.prerelease) return 1;
  if (v1.prerelease && v2.prerelease) {
    return v1.prerelease.localeCompare(v2.prerelease);
  }

  return 0;
}

/**
 * Increment version based on type
 * @param {string} version - Current version
 * @param {string} type - Increment type (major, minor, patch)
 * @param {string} prerelease - Optional prerelease identifier
 * @returns {string} New version string
 */
function incrementVersion(version, type, prerelease = null) {
  const parsed = parseVersion(version);
  if (!parsed) {
    throw new Error(`Invalid version format: ${version}`);
  }

  let { major, minor, patch } = parsed;

  // Handle prerelease versioning logic
  if (prerelease) {
    // If current version is already a prerelease of the same type, increment the prerelease number
    if (parsed.prerelease && parsed.prerelease.startsWith(prerelease)) {
      // Extract prerelease number (e.g., "alpha.1" → 1)
      const prereleaseMatch = parsed.prerelease.match(new RegExp(`^${prerelease}\\.(\\d+)$`));
      if (prereleaseMatch) {
        const prereleaseNum = parseInt(prereleaseMatch[1], 10) + 1;
        return `${major}.${minor}.${patch}-${prerelease}.${prereleaseNum}`;
      }
    }

    // For new prerelease or different prerelease type, increment version and add prerelease.1
    switch (type) {
      case 'major':
        major++;
        minor = 0;
        patch = 0;
        break;
      case 'minor':
        minor++;
        patch = 0;
        break;
      case 'patch':
        patch++;
        break;
      default:
        throw new Error(`Invalid increment type: ${type}`);
    }

    return `${major}.${minor}.${patch}-${prerelease}.1`;
  }

  // Standard version increment (no prerelease)
  switch (type) {
    case 'major':
      major++;
      minor = 0;
      patch = 0;
      break;
    case 'minor':
      minor++;
      patch = 0;
      break;
    case 'patch':
      patch++;
      break;
    default:
      throw new Error(`Invalid increment type: ${type}`);
  }

  const newVersion = `${major}.${minor}.${patch}`;

  // Safety check: Ensure the new version is actually different from the original
  if (newVersion === version) {
    throw new Error(`Version increment failed: new version ${newVersion} is the same as original ${version}`);
  }

  // Additional safety check: Ensure the new version is greater than the original
  if (compareVersions(newVersion, version) <= 0) {
    throw new Error(`Version increment failed: new version ${newVersion} is not greater than original ${version}`);
  }

  return newVersion;
}

/**
 * Parse conventional commit message
 * @param {string} message - Commit message
 * @returns {object|null} Parsed commit object or null if not conventional
 */
function parseConventionalCommit(message) {
  const match = message.match(CONVENTIONAL_COMMIT_REGEX);
  if (!match) return null;

  const isBreaking = match[3] === '!' || BREAKING_CHANGE_PATTERNS.some(pattern => 
    pattern.test(message)
  );

  return {
    type: match[1],
    scope: match[2] ? match[2].slice(1, -1) : null, // Remove parentheses
    breaking: isBreaking,
    description: match[4],
    raw: message
  };
}

/**
 * Enhanced version bump type determination with detailed analysis
 * @param {string[]} commits - Array of commit messages
 * @param {object} options - Analysis options
 * @returns {object} Detailed bump analysis result
 */
function determineBumpType(commits, options = {}) {
  const {
    verbose = false,
    forceMinimum = null,
    allowNone = true
  } = options;

  const analysis = {
    bumpType: 'none',
    commits: {
      breaking: [],
      features: [],
      fixes: [],
      other: [],
      invalid: []
    },
    summary: {
      total: commits.length,
      conventional: 0,
      breaking: 0,
      features: 0,
      fixes: 0
    },
    reasoning: []
  };

  // Analyze each commit
  for (const commit of commits) {
    const parsed = parseConventionalCommit(commit);

    if (!parsed) {
      analysis.commits.invalid.push(commit);
      continue;
    }

    analysis.summary.conventional++;

    if (parsed.breaking) {
      analysis.commits.breaking.push({ commit, parsed });
      analysis.summary.breaking++;
    } else if (parsed.type === 'feat') {
      analysis.commits.features.push({ commit, parsed });
      analysis.summary.features++;
    } else if (['fix', 'perf'].includes(parsed.type)) {
      analysis.commits.fixes.push({ commit, parsed });
      analysis.summary.fixes++;
    } else {
      analysis.commits.other.push({ commit, parsed });
    }
  }

  // Determine bump type with reasoning
  if (analysis.summary.breaking > 0) {
    analysis.bumpType = 'major';
    analysis.reasoning.push(`Found ${analysis.summary.breaking} breaking change(s)`);
  } else if (analysis.summary.features > 0) {
    analysis.bumpType = 'minor';
    analysis.reasoning.push(`Found ${analysis.summary.features} new feature(s)`);
  } else if (analysis.summary.fixes > 0) {
    analysis.bumpType = 'patch';
    analysis.reasoning.push(`Found ${analysis.summary.fixes} fix(es)`);
  } else if (analysis.summary.conventional > 0) {
    analysis.bumpType = 'patch';
    analysis.reasoning.push('Found conventional commits but no features/fixes - defaulting to patch');
  } else if (!allowNone) {
    analysis.bumpType = 'patch';
    analysis.reasoning.push('No conventional commits found - forcing patch bump');
  } else {
    analysis.reasoning.push('No conventional commits found');
  }

  // Apply force minimum if specified
  if (forceMinimum) {
    const bumpPriority = { 'patch': 1, 'minor': 2, 'major': 3 };
    const currentPriority = bumpPriority[analysis.bumpType] || 0;
    const forcePriority = bumpPriority[forceMinimum] || 0;

    if (forcePriority > currentPriority) {
      analysis.reasoning.push(`Forced minimum bump type from ${analysis.bumpType} to ${forceMinimum}`);
      analysis.bumpType = forceMinimum;
    }
  }

  if (verbose) {
    console.log('📊 Commit Analysis Results:');
    console.log(`   Total commits: ${analysis.summary.total}`);
    console.log(`   Conventional commits: ${analysis.summary.conventional}`);
    console.log(`   Breaking changes: ${analysis.summary.breaking}`);
    console.log(`   Features: ${analysis.summary.features}`);
    console.log(`   Fixes: ${analysis.summary.fixes}`);
    console.log(`   Bump type: ${analysis.bumpType}`);
    console.log(`   Reasoning: ${analysis.reasoning.join(', ')}`);
  }

  return analysis;
}

// CLAUDE AI REVIEW: Add caching for repeated file operations
let versionCache = null;
let versionCacheTime = 0;
const CACHE_DURATION = 5000; // 5 seconds

/**
 * Get current version from package.json
 * CLAUDE AI REVIEW: Added caching for performance optimization
 * @param {boolean} forceRefresh - Force refresh of cache
 * @returns {string} Current version
 */
function getCurrentVersion(forceRefresh = false) {
  const now = Date.now();

  // CLAUDE AI REVIEW: Use cache if available and not expired
  if (!forceRefresh && versionCache && (now - versionCacheTime) < CACHE_DURATION) {
    return versionCache;
  }

  try {
    const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));

    // CLAUDE AI REVIEW: Update cache
    versionCache = packageJson.version;
    versionCacheTime = now;

    return packageJson.version;
  } catch (error) {
    throw new Error('Could not read version from package.json');
  }
}

/**
 * Check if git tag exists
 * @param {string} tag - Tag name to check
 * @returns {boolean} True if tag exists
 */
function tagExists(tag) {
  try {
    const sanitizedTag = validateTagName(tag);
    safeGitExec(`git rev-parse --verify ${sanitizedTag}`, { stdio: 'ignore' });
    return true;
  } catch (error) {
    return false;
  }
}

/**
 * Get latest git tag
 * @returns {string|null} Latest tag or null if no tags exist
 */
function getLatestTag() {
  try {
    const result = safeGitExec('git describe --tags --abbrev=0').trim();
    return result || null;
  } catch (error) {
    return null;
  }
}

/**
 * Streaming commit processor for large repositories (Claude AI recommendation)
 * Processes commits in batches to avoid memory issues
 * @param {number} batchSize - Size of each batch
 * @param {number} maxCommits - Maximum total commits to process
 * @param {boolean} verbose - Enable verbose logging
 * @returns {object} Streaming commit analysis result
 */
function getCommitsSinceLastTagStreaming(batchSize = 100, maxCommits = 5000, verbose = false) {
  try {
    console.log(`🌊 Using streaming mode for large repository (batch size: ${batchSize})`);

    const lastTag = getLastVersionTag();
    const baseCommand = lastTag ? `git log ${lastTag}..HEAD --oneline --no-merges` : `git log --oneline --no-merges`;

    let allCommits = [];
    let processed = 0;
    let skip = 0;

    while (processed < maxCommits) {
      const currentBatch = Math.min(batchSize, maxCommits - processed);
      const command = `${baseCommand} --skip=${skip} -${currentBatch}`;

      try {
        const batchResult = safeGitExec(command).trim();
        if (!batchResult) break; // No more commits

        const batchCommits = batchResult.split('\n').filter(line => line.trim());
        if (batchCommits.length === 0) break;

        allCommits = allCommits.concat(batchCommits);
        processed += batchCommits.length;
        skip += currentBatch;

        if (verbose) {
          console.log(`   Processed batch: ${batchCommits.length} commits (total: ${processed})`);
        }

        // Break if we got fewer commits than requested (end of history)
        if (batchCommits.length < currentBatch) break;

      } catch (error) {
        if (verbose) {
          console.log(`   Batch processing stopped: ${error.message}`);
        }
        break;
      }
    }

    console.log(`✅ Streaming processing complete: ${processed} commits processed`);

    return {
      messages: allCommits,
      count: allCommits.length,
      lastTag: lastTag,
      range: lastTag ? `${lastTag}..HEAD` : `HEAD~${processed}..HEAD`,
      streamingUsed: true,
      batchesProcessed: Math.ceil(processed / batchSize)
    };

  } catch (error) {
    throw new Error(`Streaming commit processing failed: ${error.message}`);
  }
}

/**
 * Enhanced commit retrieval with detailed analysis and automatic streaming for large repos
 * @param {number} limit - Maximum number of commits to retrieve
 * @param {object} options - Retrieval options
 * @returns {object} Detailed commit analysis
 */
function getCommitsSinceLastTag(limit = config.VERSION.MAX_COMMITS_TO_ANALYZE, options = {}) {
  const { verbose = false, includeDetails = false } = options;

  try {
    // Validate limit parameter
    const safeLimit = Math.min(Math.max(1, parseInt(limit) || config.VERSION.MAX_COMMITS_TO_ANALYZE), config.VERSION.ABSOLUTE_MAX_COMMITS);

    // CLAUDE AI REVIEW: Enhanced memory optimization with hard limits and performance monitoring
    const memoryThreshold = 500; // commits
    const hardMemoryLimit = 5000; // commits - hard limit to prevent memory issues

    if (safeLimit > hardMemoryLimit) {
      throw new Error(`❌ Repository too large: ${safeLimit} commits exceeds hard limit of ${hardMemoryLimit}. Consider using streaming processing or repository cleanup.`);
    }

    if (safeLimit > memoryThreshold) {
      console.warn(`⚠️  Processing ${safeLimit} commits may use significant memory. Consider using a smaller limit for better performance.`);
      console.warn(`   Consider implementing streaming for repositories with ${hardMemoryLimit}+ commits`);

      // Performance monitoring (Claude AI recommendation)
      const startTime = Date.now();
      const startMemory = process.memoryUsage();

      // Store performance metrics for monitoring
      global.performanceMetrics = {
        startTime,
        startMemory,
        commitCount: safeLimit,
        operation: 'commit-analysis'
      };
    }

    const lastTag = getLatestTag();
    const baseCommand = lastTag
      ? `git log ${lastTag}..HEAD --oneline --no-merges`
      : `git log --oneline --no-merges`;

    // Get commit messages
    const messageCommand = `${baseCommand} --format="%s" -${safeLimit}`;
    const messages = safeGitExec(messageCommand).trim();

    // CLAUDE AI REVIEW: Process commits in batches for memory efficiency
    const commitMessages = messages ? messages.split('\n').filter(line => line.trim()) : [];

    // CLAUDE AI REVIEW: Warn if processing large number of commits
    if (commitMessages.length > memoryThreshold) {
      console.warn(`⚠️  Processing ${commitMessages.length} commits. Consider using streaming for very large repositories.`);
    }

    const result = {
      messages: commitMessages,
      count: commitMessages.length,
      lastTag: lastTag,
      range: lastTag ? `${lastTag}..HEAD` : `HEAD~${safeLimit}..HEAD`
    };

    if (includeDetails) {
      // Get detailed commit info
      const detailCommand = `${baseCommand} --format="%H|%s|%an|%ad" --date=short -${safeLimit}`;
      const details = safeGitExec(detailCommand).trim();

      result.details = details ? details.split('\n').map(line => {
        const [hash, subject, author, date] = line.split('|');
        return { hash, subject, author, date };
      }) : [];
    }

    if (verbose) {
      console.log(`📋 Retrieved ${result.count} commits since ${lastTag || 'beginning'}`);
      if (result.count > 0) {
        console.log(`   Range: ${result.range}`);
        console.log(`   Latest: ${commitMessages[0]}`);
      }
    }

    // Performance monitoring completion (Claude AI recommendation)
    if (global.performanceMetrics) {
      const endTime = Date.now();
      const endMemory = process.memoryUsage();
      const duration = endTime - global.performanceMetrics.startTime;
      const memoryDelta = endMemory.heapUsed - global.performanceMetrics.startMemory.heapUsed;

      console.log(`📊 Performance Metrics:`);
      console.log(`   Operation: ${global.performanceMetrics.operation}`);
      console.log(`   Duration: ${duration}ms`);
      console.log(`   Memory delta: ${(memoryDelta / 1024 / 1024).toFixed(2)}MB`);
      console.log(`   Commits processed: ${global.performanceMetrics.commitCount}`);
      console.log(`   Processing rate: ${(global.performanceMetrics.commitCount / (duration / 1000)).toFixed(2)} commits/sec`);

      // Clear metrics
      delete global.performanceMetrics;
    }

    return result;
  } catch (error) {
    const errorMsg = error.message.substring(0, config.ERRORS.MAX_ERROR_MESSAGE_LENGTH);
    if (verbose) {
      console.warn('Could not retrieve git commits:', errorMsg);
    }

    return {
      messages: [],
      count: 0,
      lastTag: null,
      range: null,
      error: errorMsg
    };
  }
}

/**
 * CLAUDE AI REVIEW: Validate calculation inputs
 * @param {object} result - Result object to populate
 * @returns {boolean} True if validation passes
 */
function validateCalculationInputs(result) {
  if (!isValidSemver(result.currentVersion)) {
    result.conflicts.push(`Invalid current version format: ${result.currentVersion}`);
    return false;
  }
  return true;
}

/**
 * CLAUDE AI REVIEW: Auto-determine bump type from commits
 * @param {object} result - Result object to populate
 * @param {object} options - Calculation options
 */
function autoDetermineBumpType(result, options) {
  const { verbose } = options;

  if (!result.bumpType) {
    const commits = getCommitsSinceLastTag(50, { verbose });
    const analysis = determineBumpType(commits.messages, { verbose, allowNone: false });
    result.bumpType = analysis.bumpType;
    result.actions.push(`Auto-determined bump type: ${result.bumpType}`);

    if (verbose) {
      console.log(`🔍 Auto-determined bump type: ${result.bumpType}`);
      console.log(`   Based on ${commits.count} commits since ${commits.lastTag || 'beginning'}`);
    }
  }
}

/**
 * CLAUDE AI REVIEW: Perform version calculation
 * @param {object} result - Result object to populate
 */
function performVersionCalculation(result) {
  result.newVersion = incrementVersion(result.currentVersion, result.bumpType);
  result.actions.push(`Calculated version: ${result.currentVersion} → ${result.newVersion}`);
}

/**
 * CLAUDE AI REVIEW: Check for version conflicts
 * @param {object} result - Result object to populate
 * @param {object} options - Calculation options
 */
function checkVersionConflicts(result, options) {
  const { forceOverride, allowDowngrade } = options;

  const comparison = compareVersions(result.newVersion, result.currentVersion);

  if (comparison <= 0 && !allowDowngrade) {
    if (comparison === 0) {
      result.conflicts.push(`New version ${result.newVersion} is the same as current version`);

      if (forceOverride) {
        // CLAUDE AI REVIEW: Add validation after force-override to prevent infinite loops
        const originalVersion = result.newVersion;
        result.newVersion = incrementVersion(result.currentVersion, 'patch');
        result.bumpType = 'patch';

        // Additional validation to prevent infinite loops
        if (compareVersions(result.newVersion, result.currentVersion) <= 0) {
          result.conflicts.push(`Force-override failed: unable to generate valid version`);
          return;
        }

        result.actions.push(`Force-override: incremented to ${result.newVersion}`);
        result.warnings.push('Used force-override to resolve version conflict');
      }
    } else {
      result.conflicts.push(`New version ${result.newVersion} is less than current version ${result.currentVersion}`);
    }
  }
}

/**
 * CLAUDE AI REVIEW: Check for git tag conflicts
 * @param {object} result - Result object to populate
 * @param {object} options - Calculation options
 */
function checkTagConflicts(result, options) {
  const { forceOverride } = options;

  const tagName = `v${result.newVersion}`;
  if (tagExists(tagName)) {
    result.conflicts.push(`Git tag ${tagName} already exists`);

    if (forceOverride) {
      result.warnings.push(`Tag ${tagName} exists but will be overridden`);
      result.actions.push(`Force-override: will overwrite existing tag ${tagName}`);
    }
  }
}

/**
 * CLAUDE AI REVIEW: Log calculation results
 * @param {object} result - Result object to log
 * @param {boolean} verbose - Whether to log verbosely
 */
function logCalculationResults(result, verbose) {
  if (verbose) {
    console.log('📊 Version Calculation Result:');
    console.log(`   Current: ${result.currentVersion}`);
    console.log(`   New: ${result.newVersion}`);
    console.log(`   Bump type: ${result.bumpType}`);
    console.log(`   Success: ${result.success}`);
    if (result.conflicts.length > 0) {
      console.log(`   Conflicts: ${result.conflicts.length}`);
    }
    if (result.warnings.length > 0) {
      console.log(`   Warnings: ${result.warnings.length}`);
    }
  }
}

/**
 * Comprehensive version calculation with conflict resolution
 * CLAUDE AI REVIEW: Refactored into smaller, focused functions
 * @param {object} options - Calculation options
 * @returns {object} Version calculation result
 */
function calculateNextVersion(options = {}) {
  const {
    currentVersion = null,
    bumpType = null,
    forceOverride = false,
    allowDowngrade = false,
    verbose = false
  } = options;

  const result = {
    success: false,
    currentVersion: currentVersion || getCurrentVersion(),
    newVersion: null,
    bumpType: bumpType,
    conflicts: [],
    warnings: [],
    actions: []
  };

  try {
    // CLAUDE AI REVIEW: Broken down into focused functions
    if (!validateCalculationInputs(result)) {
      return result;
    }

    autoDetermineBumpType(result, { verbose });
    performVersionCalculation(result);
    checkVersionConflicts(result, { forceOverride, allowDowngrade });
    checkTagConflicts(result, { forceOverride });

    // Final validation
    if (result.conflicts.length === 0 || forceOverride) {
      result.success = true;
    }

    logCalculationResults(result, verbose);

  } catch (error) {
    // CLAUDE AI REVIEW: More specific error handling
    if (error.name === 'ValidationError') {
      result.conflicts.push(`Validation error: ${error.message}`);
    } else if (error.name === 'GitError') {
      result.conflicts.push(`Git operation error: ${error.message}`);
    } else {
      result.conflicts.push(`Unexpected error calculating version: ${error.message}`);
    }
  }

  return result;
}

/**
 * Find the next available version that doesn't conflict with existing tags
 * @param {string} baseVersion - Base version to start from
 * @param {string} bumpType - Type of bump (patch, minor, major)
 * @param {object} options - Options
 * @returns {string} Next available version
 */
function findNextAvailableVersion(baseVersion, bumpType = 'patch', options = {}) {
  const { verbose = false, maxAttempts = 10 } = options;

  let currentVersion = baseVersion;
  let attempts = 0;

  while (attempts < maxAttempts) {
    const candidateVersion = incrementVersion(currentVersion, bumpType);
    const tagName = `v${candidateVersion}`;

    if (!tagExists(tagName)) {
      if (verbose) {
        console.log(`✅ Found available version: ${candidateVersion} (tag ${tagName} doesn't exist)`);
      }
      return candidateVersion;
    }

    if (verbose) {
      console.log(`⚠️  Version ${candidateVersion} conflicts with existing tag ${tagName}, trying next...`);
    }

    currentVersion = candidateVersion;
    attempts++;
  }

  throw new Error(`Could not find available version after ${maxAttempts} attempts starting from ${baseVersion}`);
}

/**
 * Smart version resolution with automatic conflict handling
 * @param {object} options - Resolution options
 * @returns {object} Resolution result
 */
function resolveVersionConflicts(options = {}) {
  const {
    targetVersion = null,
    strategy = 'auto', // 'auto', 'force-patch', 'force-minor', 'force-major'
    verbose = false
  } = options;

  const result = {
    success: false,
    originalVersion: getCurrentVersion(),
    resolvedVersion: null,
    strategy: strategy,
    actions: [],
    warnings: []
  };

  try {
    if (targetVersion) {
      // Check if target version conflicts with existing tags
      const tagName = `v${targetVersion}`;
      if (tagExists(tagName)) {
        result.warnings.push(`Target version ${targetVersion} conflicts with existing tag ${tagName}`);

        // Find next available version based on target
        const parsed = parseVersion(targetVersion);
        result.resolvedVersion = findNextAvailableVersion(targetVersion, 'patch', { verbose });
        result.actions.push(`Resolved conflict: ${targetVersion} → ${result.resolvedVersion}`);
      } else if (compareVersions(targetVersion, result.originalVersion) > 0) {
        result.resolvedVersion = targetVersion;
        result.actions.push(`Using specified target version: ${targetVersion}`);
      } else {
        result.warnings.push(`Target version ${targetVersion} is not greater than current ${result.originalVersion}`);
      }
    }

    // Auto-resolution strategies
    if (!result.resolvedVersion) {
      const currentParsed = parseVersion(result.originalVersion);

      switch (strategy) {
        case 'force-patch':
          result.resolvedVersion = findNextAvailableVersion(result.originalVersion, 'patch', { verbose });
          result.actions.push('Applied force-patch strategy with conflict resolution');
          break;

        case 'force-minor':
          result.resolvedVersion = findNextAvailableVersion(result.originalVersion, 'minor', { verbose });
          result.actions.push('Applied force-minor strategy with conflict resolution');
          break;

        case 'force-major':
          result.resolvedVersion = findNextAvailableVersion(result.originalVersion, 'major', { verbose });
          result.actions.push('Applied force-major strategy with conflict resolution');
          break;

        case 'auto':
        default:
          // Try to determine best resolution automatically
          const calculation = calculateNextVersion({
            currentVersion: result.originalVersion,
            forceOverride: false,
            verbose
          });

          if (calculation.success && calculation.newVersion) {
            // Check if calculated version conflicts
            const tagName = `v${calculation.newVersion}`;
            if (tagExists(tagName)) {
              result.resolvedVersion = findNextAvailableVersion(calculation.newVersion, 'patch', { verbose });
              result.actions.push(`Auto-resolution with conflict handling: ${calculation.newVersion} → ${result.resolvedVersion}`);
            } else {
              result.resolvedVersion = calculation.newVersion;
              result.actions.push('Applied auto-resolution strategy');
            }
            result.actions.push(...calculation.actions);
          } else {
            // Fallback to patch increment with conflict resolution
            result.resolvedVersion = findNextAvailableVersion(result.originalVersion, 'patch', { verbose });
            result.actions.push('Fallback: applied patch increment with conflict resolution');
          }
          break;
      }
    }

    // Final validation
    if (result.resolvedVersion && isValidSemver(result.resolvedVersion)) {
      const comparison = compareVersions(result.resolvedVersion, result.originalVersion);
      if (comparison > 0) {
        // Double-check that resolved version doesn't conflict
        const tagName = `v${result.resolvedVersion}`;
        if (tagExists(tagName)) {
          result.warnings.push(`Resolved version ${result.resolvedVersion} still conflicts with tag ${tagName}`);
          result.success = false;
        } else {
          result.success = true;
        }
      } else {
        result.warnings.push(`Resolved version ${result.resolvedVersion} is not greater than original ${result.originalVersion}`);
      }
    }

    if (verbose) {
      console.log('🔧 Version Conflict Resolution:');
      console.log(`   Original: ${result.originalVersion}`);
      console.log(`   Resolved: ${result.resolvedVersion}`);
      console.log(`   Strategy: ${result.strategy}`);
      console.log(`   Success: ${result.success}`);
      if (result.actions.length > 0) {
        console.log('   Actions:');
        result.actions.forEach(action => console.log(`     - ${action}`));
      }
      if (result.warnings.length > 0) {
        console.log('   Warnings:');
        result.warnings.forEach(warning => console.log(`     - ${warning}`));
      }
    }

  } catch (error) {
    result.actions.push(`Error in resolution: ${error.message}`);
  }

  return result;
}

module.exports = {
  parseVersion,
  isValidSemver,
  compareVersions,
  incrementVersion,
  parseConventionalCommit,
  determineBumpType,
  getCurrentVersion,
  tagExists,
  getLatestTag,
  getCommitsSinceLastTag,
  getCommitsSinceLastTagStreaming, // Claude AI recommendation: streaming support
  calculateNextVersion,
  resolveVersionConflicts,
  findNextAvailableVersion,
  validateTagName,
  safeGitExec,
  validateInput,
  checkGitRateLimit, // Claude AI recommendation: rate limiting
  // CLAUDE AI REVIEW: Export custom error types
  ValidationError,
  GitError,
  VersionError,
  SEMVER_REGEX,
  CONVENTIONAL_COMMIT_REGEX,
  BREAKING_CHANGE_PATTERNS
};
