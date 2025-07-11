#!/usr/bin/env node

/**
 * Semantic Versioning Utilities
 * Provides functions for parsing, validating, and comparing semantic versions
 */

const fs = require('fs');
const { execSync } = require('child_process');

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
 * Parse a semantic version string
 * @param {string} version - Version string to parse
 * @returns {object|null} Parsed version object or null if invalid
 */
function parseVersion(version) {
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
    execSync(`git rev-parse --verify ${tag}`, { stdio: 'ignore' });
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
    return execSync('git describe --tags --abbrev=0', { encoding: 'utf8' }).trim();
  } catch (error) {
    return null;
  }
}

/**
 * Get commits since last tag or all commits if no tags
 * @param {number} limit - Maximum number of commits to retrieve
 * @returns {string[]} Array of commit messages
 */
function getCommitsSinceLastTag(limit = 50) {
  try {
    const lastTag = getLatestTag();
    const gitLogCommand = lastTag 
      ? `git log ${lastTag}..HEAD --oneline --no-merges --format="%s" -${limit}`
      : `git log --oneline --no-merges --format="%s" -${limit}`;
    
    const output = execSync(gitLogCommand, { encoding: 'utf8' }).trim();
    return output ? output.split('\n') : [];
  } catch (error) {
    console.warn('Could not retrieve git commits:', error.message);
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
  SEMVER_REGEX,
  CONVENTIONAL_COMMIT_REGEX,
  BREAKING_CHANGE_PATTERNS
};
