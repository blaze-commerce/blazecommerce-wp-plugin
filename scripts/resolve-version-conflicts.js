#!/usr/bin/env node

/**
 * Version Conflict Resolution Module
 * Extracted from GitHub Actions workflow for better maintainability and testability
 * Addresses Claude AI recommendation to extract complex workflow logic
 */

const { 
  parseVersion, 
  incrementVersion, 
  findNextAvailableVersion, 
  tagExists,
  isValidSemver 
} = require('./semver-utils');

/**
 * Validate version format with comprehensive error reporting
 * @param {string} version - Version to validate
 * @param {string} context - Context for error reporting
 * @returns {boolean} - True if valid
 */
function validateVersionFormat(version, context = 'version') {
  if (!isValidSemver(version)) {
    console.error(`❌ Invalid ${context} format: ${version}`);
    console.error('   Expected format: X.Y.Z or X.Y.Z-prerelease+build');
    return false;
  }
  return true;
}

/**
 * Enhanced conflict resolution with prerelease support
 * Addresses Claude AI recommendation for better error handling and prerelease logic
 * @param {object} options - Resolution options
 * @returns {object} - Resolution result
 */
function resolveVersionConflicts(options = {}) {
  const {
    newVersion,
    prereleaseType = null,
    maxAttempts = 50,
    verbose = true
  } = options;

  console.log('🔍 Starting enhanced conflict resolution process...');
  console.log(`📊 Conflict Resolution Details:`);
  console.log(`   Base version: ${newVersion}`);
  console.log(`   Prerelease type: ${prereleaseType || 'none (stable release)'}`);
  console.log(`   Max attempts: ${maxAttempts}`);

  // Validate input version format
  if (!validateVersionFormat(newVersion, 'calculated version')) {
    throw new Error(`Invalid calculated version format: ${newVersion}`);
  }

  const tagName = `v${newVersion}`;
  console.log(`🏷️  Checking tag: ${tagName}`);

  // Check if tag already exists
  if (!tagExists(tagName)) {
    console.log(`✅ No conflicts detected for ${tagName}`);
    console.log('   Tag is available for use');
    return {
      success: true,
      originalVersion: newVersion,
      resolvedVersion: newVersion,
      conflictDetected: false,
      attemptsUsed: 0
    };
  }

  // Conflict detected - start resolution process
  console.log('⚠️  Git tag conflict detected:');
  console.log(`   Calculated version: ${newVersion}`);
  console.log(`   Git tag ${tagName} already exists`);
  
  // Get tag creation date for additional context
  try {
    const { execSync } = require('child_process');
    const tagDate = execSync(`git log -1 --format=%ci "${tagName}" 2>/dev/null`, { 
      encoding: 'utf8',
      timeout: 5000 
    }).trim();
    console.log(`   Tag creation date: ${tagDate || 'unknown'}`);
  } catch (error) {
    console.log('   Tag creation date: unknown');
  }

  console.log('🔄 Auto-resolving by finding next available version...');

  // Enhanced prerelease-aware conflict resolution
  let resolvedVersion;
  try {
    if (prereleaseType) {
      resolvedVersion = resolvePrereleasConflict(newVersion, prereleaseType, verbose);
    } else {
      resolvedVersion = findNextAvailableVersion(newVersion, 'patch', { 
        verbose,
        maxAttempts 
      });
    }
  } catch (error) {
    console.error('❌ Error in conflict resolution:');
    console.error(`   Message: ${error.message}`);
    console.error('   Possible causes:');
    console.error('   - Git repository access issues');
    console.error('   - Network connectivity problems');
    console.error('   - Corrupted git tags');
    console.error('   - Insufficient permissions');
    throw error;
  }

  // Validate resolved version
  if (!resolvedVersion) {
    throw new Error('No available version found within reasonable range');
  }

  if (!validateVersionFormat(resolvedVersion, 'resolved version')) {
    throw new Error(`Resolved version has invalid format: ${resolvedVersion}`);
  }

  // Final verification that resolved version is actually available
  const resolvedTagName = `v${resolvedVersion}`;
  if (tagExists(resolvedTagName)) {
    throw new Error(`Resolved version tag already exists: ${resolvedTagName}. This indicates a race condition or logic error.`);
  }

  // Calculate attempts used
  const parsed = parseVersion(newVersion);
  const resolvedParsed = parseVersion(resolvedVersion);
  const attemptsUsed = resolvedParsed.patch - parsed.patch;

  console.log('✅ Git tag conflict resolved successfully:');
  console.log(`   Original calculated version: ${newVersion}`);
  console.log(`   Final resolved version: ${resolvedVersion}`);
  console.log(`   Resolution method: Auto-incremented patch version`);
  console.log(`   Attempts made: ${attemptsUsed}`);
  console.log(`   Verified tag availability: ✅`);

  return {
    success: true,
    originalVersion: newVersion,
    resolvedVersion,
    conflictDetected: true,
    attemptsUsed,
    resolutionMethod: prereleaseType ? 'prerelease-increment' : 'patch-increment'
  };
}

/**
 * Resolve prerelease conflicts with intelligent increment strategy
 * @param {string} version - Base version
 * @param {string} prereleaseType - Type of prerelease (alpha, beta, rc)
 * @param {boolean} verbose - Verbose logging
 * @returns {string} - Resolved version
 */
function resolvePrereleasConflict(version, prereleaseType, verbose = true) {
  const parsed = parseVersion(version);
  
  if (verbose) {
    console.log('🔍 Prerelease conflict resolution:');
    console.log(`   Strategy: prerelease increment or patch increment`);
  }

  // Try incrementing prerelease number first if same type
  if (parsed && parsed.prerelease && parsed.prerelease.startsWith(prereleaseType)) {
    try {
      const prereleaseIncrement = incrementVersion(version, 'patch', prereleaseType);
      const tagName = `v${prereleaseIncrement}`;
      
      if (!tagExists(tagName)) {
        if (verbose) {
          console.log(`✅ Prerelease increment successful: ${prereleaseIncrement}`);
        }
        return prereleaseIncrement;
      }
    } catch (error) {
      if (verbose) {
        console.log('⚠️  Prerelease increment failed, falling back to findNextAvailableVersion');
      }
    }
  }

  // Fallback to standard conflict resolution
  return findNextAvailableVersion(version, 'patch', { 
    verbose,
    maxAttempts: 50
  });
}

/**
 * Main CLI interface
 */
if (require.main === module) {
  const args = process.argv.slice(2);
  const newVersion = args[0];
  const prereleaseType = args[1] || null;

  if (!newVersion) {
    console.error('Usage: node resolve-version-conflicts.js <version> [prerelease-type]');
    process.exit(1);
  }

  try {
    const result = resolveVersionConflicts({
      newVersion,
      prereleaseType,
      verbose: true
    });

    console.log(`\n📦 Final resolved version: ${result.resolvedVersion}`);
    
    // Output for GitHub Actions
    if (process.env.GITHUB_OUTPUT) {
      const fs = require('fs');
      fs.appendFileSync(process.env.GITHUB_OUTPUT, `RESOLVED_VERSION=${result.resolvedVersion}\n`);
      fs.appendFileSync(process.env.GITHUB_OUTPUT, `CONFLICT_DETECTED=${result.conflictDetected}\n`);
      fs.appendFileSync(process.env.GITHUB_OUTPUT, `ATTEMPTS_USED=${result.attemptsUsed}\n`);
    }

    process.exit(0);
  } catch (error) {
    console.error(`❌ Version conflict resolution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = {
  resolveVersionConflicts,
  resolvePrereleasConflict,
  validateVersionFormat
};
