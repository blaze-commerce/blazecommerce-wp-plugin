#!/usr/bin/env node

/**
 * Semantic Versioning Utilities
 * Provides functions for parsing, validating, and comparing semantic versions
 */

const fs = require('fs');
const { execSync } = require('child_process');
const config = require('./config');

// Semantic versioning regex pattern
const SEMVER_REGEX = /^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/;

// Conventional commit regex pattern
const CONVENTIONAL_COMMIT_REGEX = /^(feat|fix|docs|style|refactor|perf|test|chore|build|ci)(\(.+\))?(!)?: (.+)$/;

// Breaking change patterns
const BREAKING_CHANGE_PATTERNS = [
  /^(feat|fix|docs|style|refactor|perf|test|chore|build|ci)(\(.+\))?!:/,
  /BREAKING CHANGE:/i
];

/**
 * Validate and sanitize git tag name for security
 * @param {string} tagName - Tag name to validate
 * @returns {string} Sanitized tag name
 * @throws {Error} If tag name is invalid
 */
function validateTagName(tagName) {
  if (!tagName || typeof tagName !== 'string') {
    throw new Error('Tag name must be a non-empty string');
  }

  if (tagName.length > config.GIT.MAX_TAG_LENGTH) {
    throw new Error(`Tag name too long (max ${config.GIT.MAX_TAG_LENGTH} characters)`);
  }

  if (!config.GIT.TAG_NAME_REGEX.test(tagName)) {
    throw new Error('Tag name contains invalid characters. Only alphanumeric, dots, underscores, and hyphens are allowed');
  }

  return tagName.trim();
}

/**
 * Execute git command safely with input validation
 * @param {string} command - Git command to execute
 * @param {object} options - Execution options
 * @returns {string} Command output
 */
function safeGitExec(command, options = {}) {
  const defaultOptions = {
    ...config.GIT.DEFAULT_OPTIONS,
    timeout: config.GIT.OPERATION_TIMEOUT,
    ...options
  };

  try {
    return execSync(command, defaultOptions);
  } catch (error) {
    // Log error but don't expose sensitive information
    console.warn(`Git operation failed: ${error.message.substring(0, 100)}`);
    throw error;
  }
}

/**
 * Parse a semantic version string
 * @param {string} version - Version string to parse
 * @returns {object|null} Parsed version object or null if invalid
 */
function parseVersion(version) {
  if (!version || typeof version !== 'string') {
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
  if (version.length > 100) {
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

  let newVersion = `${major}.${minor}.${patch}`;
  if (prerelease) {
    newVersion += `-${prerelease}`;
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
 * Determine version bump type from commit messages
 * @param {string[]} commits - Array of commit messages
 * @returns {string} Version bump type (major, minor, patch, none)
 */
function determineBumpType(commits) {
  let hasBreaking = false;
  let hasFeature = false;
  let hasFix = false;

  for (const commit of commits) {
    const parsed = parseConventionalCommit(commit);
    if (!parsed) continue;

    if (parsed.breaking) {
      hasBreaking = true;
    } else if (parsed.type === 'feat') {
      hasFeature = true;
    } else if (['fix', 'perf'].includes(parsed.type)) {
      hasFix = true;
    }
  }

  if (hasBreaking) return 'major';
  if (hasFeature) return 'minor';
  if (hasFix) return 'patch';
  return 'none';
}

/**
 * Get current version from package.json
 * @returns {string} Current version
 */
function getCurrentVersion() {
  try {
    const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
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
 * Get commits since last tag or all commits if no tags
 * @param {number} limit - Maximum number of commits to retrieve
 * @returns {string[]} Array of commit messages
 */
function getCommitsSinceLastTag(limit = config.VERSION.MAX_COMMITS_TO_ANALYZE) {
  try {
    // Validate limit parameter
    const safeLimit = Math.min(Math.max(1, parseInt(limit) || config.VERSION.MAX_COMMITS_TO_ANALYZE), 1000);

    const lastTag = getLatestTag();
    const gitLogCommand = lastTag
      ? `git log ${lastTag}..HEAD --oneline --no-merges --format="%s" -${safeLimit}`
      : `git log --oneline --no-merges --format="%s" -${safeLimit}`;

    const output = safeGitExec(gitLogCommand).trim();
    return output ? output.split('\n').filter(line => line.trim()) : [];
  } catch (error) {
    console.warn('Could not retrieve git commits:', error.message.substring(0, config.ERRORS.MAX_ERROR_MESSAGE_LENGTH));
    return [];
  }
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
  validateTagName,
  safeGitExec,
  SEMVER_REGEX,
  CONVENTIONAL_COMMIT_REGEX,
  BREAKING_CHANGE_PATTERNS
};
