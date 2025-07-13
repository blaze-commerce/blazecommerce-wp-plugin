#!/usr/bin/env node

/**
 * Version Validator
 * Validates version consistency between package.json and git tags
 * Provides detailed analysis and recommendations
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

/**
 * Import shared logger
 */
const { Logger } = require('./file-change-analyzer');

/**
 * Version Validator Class
 */
class VersionValidator {
  constructor() {
    this.packageJsonPath = path.join(process.cwd(), 'package.json');
  }

  /**
   * Get current version from package.json
   * @returns {string} Current package version
   */
  getCurrentVersion() {
    try {
      if (!fs.existsSync(this.packageJsonPath)) {
        throw new Error('package.json not found');
      }

      const packageJson = JSON.parse(fs.readFileSync(this.packageJsonPath, 'utf8'));
      
      if (!packageJson.version) {
        throw new Error('No version field found in package.json');
      }

      return packageJson.version;
    } catch (error) {
      Logger.error(`Failed to read package.json version: ${error.message}`);
      throw error;
    }
  }

  /**
   * Get latest git tag
   * @returns {string} Latest git tag or 'none' if no tags exist
   */
  getLatestGitTag() {
    try {
      Logger.debug('Retrieving latest git tag...');
      
      // Try optimized version sort first (Claude's recommendation)
      let latestTag;
      try {
        const output = execSync('git tag --list "v*" --sort=-version:refname', { encoding: 'utf8' });
        const tags = output.trim().split('\n').filter(tag => tag.trim());
        latestTag = tags.length > 0 ? tags[0] : '';
      } catch (error) {
        Logger.debug('Version sort failed, trying git describe fallback');
        latestTag = '';
      }

      // Fallback to git describe if no tags found
      if (!latestTag) {
        try {
          latestTag = execSync('git describe --tags --abbrev=0', { encoding: 'utf8' }).trim();
        } catch (error) {
          Logger.debug('No git tags found');
          return 'none';
        }
      }

      Logger.debug(`Latest git tag: ${latestTag}`);
      return latestTag;
    } catch (error) {
      Logger.warning(`Could not retrieve git tags: ${error.message}`);
      return 'none';
    }
  }

  /**
   * Validate semver format
   * @param {string} version - Version string to validate
   * @returns {boolean} True if valid semver format
   */
  isValidSemver(version) {
    // Simplified and tightened regex to prevent edge cases (Claude's recommendation)
    const semverRegex = /^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.-]+)?(\+[a-zA-Z0-9.-]+)?$/;
    return semverRegex.test(version);
  }

  /**
   * Validate version consistency
   * @returns {Object} Validation result with details
   */
  validate() {
    try {
      Logger.info('Validating version consistency between package.json and git tags...');
      
      const packageVersion = this.getCurrentVersion();
      const lastTag = this.getLatestGitTag();
      const expectedTag = `v${packageVersion}`;

      Logger.info(`Package version: ${packageVersion}`);
      Logger.info(`Last git tag: ${lastTag}`);
      Logger.info(`Expected tag: ${expectedTag}`);

      // Validate semver format
      if (!this.isValidSemver(packageVersion)) {
        const error = `Package version '${packageVersion}' is not a valid semver format`;
        Logger.error(error);
        Logger.error('Expected format: MAJOR.MINOR.PATCH[-prerelease][+metadata]');
        Logger.error('Examples: 1.0.0, 1.0.0-alpha.1, 1.0.0+build.123');
        
        return {
          isValid: false,
          error,
          packageVersion,
          lastTag,
          expectedTag,
          hasMismatch: true
        };
      }

      // Check for version mismatch
      const hasMismatch = lastTag === 'none' || lastTag !== expectedTag;
      
      if (hasMismatch) {
        if (lastTag === 'none') {
          Logger.warning('No git tags found - this is the first versioned release');
          Logger.info(`Package.json version: ${packageVersion}`);
          Logger.info('Recommendation: Create initial tag after this release');
        } else {
          Logger.warning('Version mismatch detected!');
          Logger.warning(`Package.json version: ${packageVersion}`);
          Logger.warning(`Last git tag: ${lastTag}`);
          Logger.warning(`Expected tag: ${expectedTag}`);
          Logger.warning('This may cause unexpected version bumps due to analyzing too many commits');
        }
      } else {
        Logger.success('Version consistency validated');
      }

      return {
        isValid: true,
        packageVersion,
        lastTag,
        expectedTag,
        hasMismatch,
        isFirstRelease: lastTag === 'none'
      };

    } catch (error) {
      Logger.error(`Version validation failed: ${error.message}`);
      throw error;
    }
  }

  /**
   * Output results in GitHub Actions format
   * @param {Object} result - Validation result
   */
  outputForGitHubActions(result) {
    if (!result.isValid) {
      console.log('validation_passed=false');
      console.log(`validation_error=${result.error}`);
      return;
    }

    console.log('validation_passed=true');
    console.log(`package_version=${result.packageVersion}`);
    console.log(`last_tag=${result.lastTag}`);
    console.log(`expected_tag=${result.expectedTag}`);
    console.log(`version_mismatch=${result.hasMismatch}`);
    console.log(`is_first_release=${result.isFirstRelease}`);
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  try {
    const validator = new VersionValidator();
    const result = validator.validate();
    validator.outputForGitHubActions(result);
    
    // Exit with error code if validation failed
    process.exit(result.isValid ? 0 : 1);
  } catch (error) {
    Logger.error(`Script execution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = { VersionValidator };
