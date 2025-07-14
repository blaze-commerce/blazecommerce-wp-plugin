#!/usr/bin/env node

/**
 * Version Synchronization Validator
 * Validates that git tags match version references in all files
 * Provides detailed mismatch reports and resolution suggestions
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Import existing utilities
const {
  isValidSemver,
  parseVersion,
  compareVersions,
  tagExists,
  getCurrentVersion
} = require('./semver-utils');

// Version file configurations (same as validate-version.js)
const VERSION_FILES = [
  {
    path: 'package.json',
    type: 'json',
    field: 'version',
    description: 'NPM package version'
  },
  {
    path: 'blaze-wooless.php',
    type: 'php',
    patterns: [
      { 
        regex: /^Version:\s*([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)$/m, 
        name: 'Plugin Header',
        description: 'WordPress plugin header version'
      },
      { 
        regex: /define\(\s*'BLAZE_COMMERCE_VERSION',\s*'([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)'\s*\);/, 
        name: 'Version Constant',
        description: 'PHP version constant'
      }
    ]
  },
  {
    path: 'blocks/package.json',
    type: 'json',
    field: 'version',
    description: 'Blocks package version'
  },
  {
    path: 'README.md',
    type: 'markdown',
    patterns: [
      { 
        regex: /^\*\*Version:\*\*\s*([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)$/m, 
        name: 'Version Badge',
        description: 'README version badge'
      }
    ]
  }
];

/**
 * Extract version from git tag
 * @param {string} tag - Git tag (e.g., 'v1.14.1')
 * @returns {string|null} Version string (e.g., '1.14.1') or null if invalid
 */
function extractVersionFromTag(tag) {
  if (!tag || typeof tag !== 'string') {
    return null;
  }

  // Remove 'v' prefix if present
  const version = tag.startsWith('v') ? tag.slice(1) : tag;
  
  // Validate semantic version format
  if (!isValidSemver(version)) {
    return null;
  }

  return version;
}

/**
 * Get current git tag
 * @returns {string|null} Current git tag or null if not found
 */
function getCurrentGitTag() {
  try {
    // Try to get tag from environment (GitHub Actions)
    if (process.env.GITHUB_REF && process.env.GITHUB_REF.startsWith('refs/tags/')) {
      return process.env.GITHUB_REF.replace('refs/tags/', '');
    }

    // Try to get current tag from git
    const tag = execSync('git describe --exact-match --tags HEAD', { 
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore']
    }).trim();
    
    return tag;
  } catch (error) {
    // No tag found or not in git repo
    return null;
  }
}

/**
 * Get latest git tag
 * @returns {string|null} Latest git tag or null if not found
 */
function getLatestGitTag() {
  try {
    const tag = execSync('git describe --tags --abbrev=0', { 
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore']
    }).trim();
    
    return tag;
  } catch (error) {
    return null;
  }
}

/**
 * Extract version from a single file
 * @param {object} fileConfig - File configuration
 * @returns {object} Extraction result
 */
function extractVersionFromFile(fileConfig) {
  const result = {
    file: fileConfig.path,
    description: fileConfig.description,
    exists: false,
    versions: [],
    errors: []
  };

  if (!fs.existsSync(fileConfig.path)) {
    result.errors.push(`File does not exist: ${fileConfig.path}`);
    return result;
  }

  result.exists = true;

  try {
    const content = fs.readFileSync(fileConfig.path, 'utf8');

    if (fileConfig.type === 'json') {
      const jsonData = JSON.parse(content);
      const version = jsonData[fileConfig.field];
      
      if (version) {
        result.versions.push({
          location: fileConfig.field,
          version: version,
          valid: isValidSemver(version),
          description: fileConfig.description
        });
      } else {
        result.errors.push(`Missing ${fileConfig.field} field in JSON`);
      }
    } else if (fileConfig.patterns) {
      for (const pattern of fileConfig.patterns) {
        const match = content.match(pattern.regex);
        if (match) {
          result.versions.push({
            location: pattern.name,
            version: match[1],
            valid: isValidSemver(match[1]),
            description: pattern.description || pattern.name
          });
        } else {
          result.errors.push(`Could not find ${pattern.name} in ${fileConfig.path}`);
        }
      }
    }
  } catch (error) {
    result.errors.push(`Error reading/parsing ${fileConfig.path}: ${error.message}`);
  }

  return result;
}

/**
 * Validate version synchronization between git tag and files
 * @param {string} targetTag - Git tag to validate against (optional)
 * @param {object} options - Validation options
 * @returns {object} Validation result
 */
function validateVersionSync(targetTag = null, options = {}) {
  const { verbose = false, strict = true } = options;

  const result = {
    success: false,
    gitTag: null,
    gitVersion: null,
    fileVersions: [],
    mismatches: [],
    errors: [],
    summary: {
      totalFiles: 0,
      validFiles: 0,
      consistentFiles: 0,
      uniqueVersions: new Set()
    }
  };

  try {
    // Get git tag to validate against
    result.gitTag = targetTag || getCurrentGitTag() || getLatestGitTag();

    if (!result.gitTag) {
      result.errors.push('No git tag found to validate against');
      return result;
    }

    // Extract version from git tag
    result.gitVersion = extractVersionFromTag(result.gitTag);

    if (!result.gitVersion) {
      result.errors.push(`Invalid git tag format: ${result.gitTag}`);
      return result;
    }

    if (verbose) {
      console.log(`🏷️  Validating against git tag: ${result.gitTag} (version: ${result.gitVersion})`);
    }

    // Extract versions from all files
    for (const fileConfig of VERSION_FILES) {
      const fileResult = extractVersionFromFile(fileConfig);
      result.fileVersions.push(fileResult);
      result.summary.totalFiles++;

      if (fileResult.exists && fileResult.errors.length === 0) {
        result.summary.validFiles++;
      }

      // Check each version in the file
      for (const versionInfo of fileResult.versions) {
        result.summary.uniqueVersions.add(versionInfo.version);

        // Compare with git tag version
        if (versionInfo.version === result.gitVersion) {
          result.summary.consistentFiles++;
        } else {
          result.mismatches.push({
            file: fileResult.file,
            location: versionInfo.location,
            description: versionInfo.description,
            expected: result.gitVersion,
            actual: versionInfo.version,
            valid: versionInfo.valid
          });
        }
      }

      // Add file-level errors to global errors
      result.errors.push(...fileResult.errors);
    }

    // Determine success
    result.success = result.mismatches.length === 0 && result.errors.length === 0;

    if (verbose) {
      console.log(`📊 Validation Summary:`);
      console.log(`   Files checked: ${result.summary.totalFiles}`);
      console.log(`   Valid files: ${result.summary.validFiles}`);
      console.log(`   Consistent files: ${result.summary.consistentFiles}`);
      console.log(`   Unique versions: ${result.summary.uniqueVersions.size}`);
      console.log(`   Mismatches: ${result.mismatches.length}`);
      console.log(`   Errors: ${result.errors.length}`);
    }

  } catch (error) {
    result.errors.push(`Validation failed: ${error.message}`);
  }

  return result;
}

/**
 * Generate detailed validation report
 * @param {object} validationResult - Result from validateVersionSync
 * @returns {string} Formatted report
 */
function generateSyncReport(validationResult) {
  let report = '\n=== VERSION SYNCHRONIZATION REPORT ===\n\n';

  // Header
  report += `🏷️  Git Tag: ${validationResult.gitTag || 'Not found'}\n`;
  report += `📦 Expected Version: ${validationResult.gitVersion || 'Invalid'}\n`;
  report += `✅ Status: ${validationResult.success ? 'SYNCHRONIZED' : 'MISMATCHED'}\n\n`;

  // Summary
  report += `📊 SUMMARY:\n`;
  report += `   Files checked: ${validationResult.summary.totalFiles}\n`;
  report += `   Valid files: ${validationResult.summary.validFiles}\n`;
  report += `   Consistent files: ${validationResult.summary.consistentFiles}\n`;
  report += `   Unique versions found: ${validationResult.summary.uniqueVersions.size}\n`;
  report += `   Mismatches: ${validationResult.mismatches.length}\n`;
  report += `   Errors: ${validationResult.errors.length}\n\n`;

  // Mismatches
  if (validationResult.mismatches.length > 0) {
    report += `❌ VERSION MISMATCHES:\n`;
    for (const mismatch of validationResult.mismatches) {
      report += `   ${mismatch.file} (${mismatch.location}):\n`;
      report += `     Expected: ${mismatch.expected}\n`;
      report += `     Actual: ${mismatch.actual}\n`;
      report += `     Description: ${mismatch.description}\n\n`;
    }
  }

  // Errors
  if (validationResult.errors.length > 0) {
    report += `⚠️  ERRORS:\n`;
    for (const error of validationResult.errors) {
      report += `   ❌ ${error}\n`;
    }
    report += '\n';
  }

  // Resolution suggestions
  if (validationResult.mismatches.length > 0) {
    report += `🔧 RESOLUTION SUGGESTIONS:\n`;
    report += `   1. Run automatic fix: node scripts/fix-version-mismatch.js ${validationResult.gitVersion}\n`;
    report += `   2. Manual update: node scripts/update-version.js ${validationResult.gitVersion} --force\n`;
    report += `   3. Validate after fix: node scripts/version-sync-validator.js --verbose\n\n`;
  }

  return report;
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const verbose = args.includes('--verbose') || args.includes('-v');
  const strict = args.includes('--strict');
  const tagIndex = args.findIndex(arg => arg === '--tag');
  const targetTag = tagIndex !== -1 && args[tagIndex + 1] ? args[tagIndex + 1] : null;

  console.log('🔍 Running version synchronization validation...\n');

  const result = validateVersionSync(targetTag, { verbose, strict });

  if (verbose || !result.success) {
    console.log(generateSyncReport(result));
  }

  if (result.success) {
    console.log('✅ Version synchronization validation passed!');
    console.log(`   Git tag ${result.gitTag} matches all file versions (${result.gitVersion})`);
  } else {
    console.log('❌ Version synchronization validation failed!');

    if (result.mismatches.length > 0) {
      console.log(`   Found ${result.mismatches.length} version mismatch(es)`);
      console.log('   Run with --verbose for detailed report');

      console.log('\n🔧 QUICK FIXES:');
      console.log('   • Fix automatically: npm run fix-version-mismatch:auto');
      console.log('   • Fix to specific version: node scripts/fix-version-mismatch.js [VERSION]');
      console.log('   • Detailed analysis: npm run validate-version-sync:verbose');

      console.log('\n📚 HELP:');
      console.log('   • Documentation: docs/version-synchronization.md');
      console.log('   • Examples: docs/version-synchronization.md#usage');
    }

    if (result.errors.length > 0) {
      console.log(`   Found ${result.errors.length} error(s)`);
    }

    console.log('\n💡 Quick fix commands:');
    if (result.gitVersion) {
      console.log(`   node scripts/fix-version-mismatch.js ${result.gitVersion}`);
      console.log(`   node scripts/update-version.js ${result.gitVersion} --force`);
    }
  }

  process.exit(result.success ? 0 : 1);
}

module.exports = {
  extractVersionFromTag,
  getCurrentGitTag,
  getLatestGitTag,
  extractVersionFromFile,
  validateVersionSync,
  generateSyncReport,
  VERSION_FILES
};
