#!/usr/bin/env node

/**
 * Enhanced Version Update Script
 * Updates version across all plugin files with validation and error handling
 */

const fs = require('fs');
const path = require('path');
const { isValidSemver, parseVersion, tagExists, validateTagName } = require('./semver-utils');
const { validateVersionSystem, VERSION_FILES } = require('./validate-version');
const config = require('./config');

// Configuration
const CONFIG = {
  dryRun: process.argv.includes('--dry-run'),
  verbose: process.argv.includes('--verbose') || process.argv.includes('-v'),
  force: process.argv.includes('--force'),
  skipValidation: process.argv.includes('--skip-validation')
};

/**
 * Create backup of a file with retry logic
 * @param {string} filePath - Path to file to backup
 * @returns {string} Backup file path
 */
function createBackup(filePath) {
  if (!filePath || !fs.existsSync(filePath)) {
    throw new Error(`Cannot create backup: file does not exist: ${filePath}`);
  }

  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const backupPath = `${filePath}${config.FILES.BACKUP_EXTENSION}.${timestamp}`;

  let attempts = 0;
  while (attempts < config.ERRORS.MAX_RETRY_ATTEMPTS) {
    try {
      fs.copyFileSync(filePath, backupPath);
      return backupPath;
    } catch (error) {
      attempts++;
      if (attempts >= config.ERRORS.MAX_RETRY_ATTEMPTS) {
        throw new Error(`Failed to create backup after ${attempts} attempts: ${error.message}`);
      }
      // Wait before retry
      const delay = config.ERRORS.RETRY_DELAY * attempts;
      require('child_process').execSync(`sleep ${delay / 1000}`, { stdio: 'ignore' });
    }
  }
}

/**
 * Restore file from backup
 * @param {string} filePath - Original file path
 * @param {string} backupPath - Backup file path
 */
function restoreFromBackup(filePath, backupPath) {
  if (fs.existsSync(backupPath)) {
    fs.copyFileSync(backupPath, filePath);
    fs.unlinkSync(backupPath);
  }
}

/**
 * Update version in a specific file
 * @param {object} fileConfig - File configuration
 * @param {string} newVersion - New version to set
 * @returns {object} Update result
 */
function updateVersionInFile(fileConfig, newVersion) {
  const result = {
    file: fileConfig.path,
    success: false,
    changes: [],
    errors: [],
    backupPath: null
  };

  if (!fs.existsSync(fileConfig.path)) {
    result.errors.push(`File does not exist: ${fileConfig.path}`);
    return result;
  }

  try {
    // Create backup
    result.backupPath = createBackup(fileConfig.path);

    const content = fs.readFileSync(fileConfig.path, 'utf8');
    let updatedContent = content;
    let changesMade = false;

    if (fileConfig.type === 'json') {
      const jsonData = JSON.parse(content);
      const oldVersion = jsonData[fileConfig.field];

      if (oldVersion !== newVersion) {
        jsonData[fileConfig.field] = newVersion;
        updatedContent = JSON.stringify(jsonData, null, '\t') + '\n';
        changesMade = true;
        result.changes.push({
          location: fileConfig.field,
          from: oldVersion,
          to: newVersion
        });
      }
    } else if (fileConfig.type === 'php') {
      for (const pattern of fileConfig.patterns) {
        const match = content.match(pattern.regex);
        if (match) {
          const oldVersion = match[1];
          if (oldVersion !== newVersion) {
            // Create replacement string based on the pattern
            let replacement;
            if (pattern.regex.source.includes('Version:')) {
              replacement = `Version: ${newVersion}`;
            } else if (pattern.regex.source.includes('BLAZE_COMMERCE_VERSION')) {
              replacement = `define( 'BLAZE_COMMERCE_VERSION', '${newVersion}' );`;
            }

            updatedContent = updatedContent.replace(pattern.regex, replacement);
            changesMade = true;
            result.changes.push({
              location: pattern.name,
              from: oldVersion,
              to: newVersion
            });
          }
        } else {
          result.errors.push(`Could not find ${pattern.name} pattern in ${fileConfig.path}`);
        }
      }
    }

    if (changesMade) {
      if (!CONFIG.dryRun) {
        fs.writeFileSync(fileConfig.path, updatedContent);
      }
      result.success = true;
    } else {
      result.success = true; // No changes needed
      // Clean up backup if no changes were made
      if (result.backupPath && fs.existsSync(result.backupPath)) {
        fs.unlinkSync(result.backupPath);
        result.backupPath = null;
      }
    }

  } catch (error) {
    result.errors.push(`Error updating ${fileConfig.path}: ${error.message}`);

    // Restore from backup if update failed
    if (result.backupPath) {
      try {
        restoreFromBackup(fileConfig.path, result.backupPath);
      } catch (restoreError) {
        result.errors.push(`Failed to restore backup: ${restoreError.message}`);
      }
    }
  }

  return result;
}

/**
 * Main version update function
 * @param {string} newVersion - New version to set (optional, reads from package.json if not provided)
 * @returns {boolean} True if update was successful
 */
function updateVersion(newVersion = null) {
  console.log('🔄 Starting version update process...\n');

  // Get version to update to
  if (!newVersion) {
    try {
      const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
      newVersion = packageJson.version;
    } catch (error) {
      console.error('❌ Could not read version from package.json:', error.message);
      return false;
    }
  }

  console.log(`📦 Target version: ${newVersion}`);

  // Validate the new version
  if (!isValidSemver(newVersion)) {
    console.error(`❌ Invalid semantic version format: ${newVersion}`);
    console.error('   Version must follow format: X.Y.Z or X.Y.Z-prerelease');
    return false;
  }

  // Pre-validation (unless skipped)
  if (!CONFIG.skipValidation) {
    console.log('🔍 Running pre-update validation...');

    // Check if tag already exists
    const tagName = `v${newVersion}`;
    if (tagExists(tagName) && !CONFIG.force) {
      console.error(`❌ Git tag ${tagName} already exists`);
      console.error('   Use --force to override or choose a different version');
      return false;
    }
  }

  if (CONFIG.dryRun) {
    console.log('🧪 DRY RUN MODE - No files will be modified\n');
  }

  // Update all version files
  const updateResults = [];
  let hasErrors = false;

  for (const fileConfig of VERSION_FILES) {
    console.log(`📝 Updating ${fileConfig.path}...`);

    const result = updateVersionInFile(fileConfig, newVersion);
    updateResults.push(result);

    if (result.success) {
      if (result.changes.length > 0) {
        console.log(`   ✅ Updated successfully`);
        if (CONFIG.verbose) {
          for (const change of result.changes) {
            console.log(`      ${change.location}: ${change.from} → ${change.to}`);
          }
        }
      } else {
        console.log(`   ℹ️  Already up to date`);
      }
    } else {
      hasErrors = true;
      console.log(`   ❌ Update failed`);
      for (const error of result.errors) {
        console.log(`      ${error}`);
      }
    }
  }

  // Post-validation (unless skipped)
  if (!CONFIG.skipValidation && !hasErrors && !CONFIG.dryRun) {
    console.log('\n🔍 Running post-update validation...');
    const validationPassed = validateVersionSystem({ verbose: false, checkConflicts: false });

    if (!validationPassed) {
      console.error('❌ Post-update validation failed. Rolling back changes...');

      // Rollback all changes
      for (const result of updateResults) {
        if (result.backupPath && fs.existsSync(result.backupPath)) {
          try {
            restoreFromBackup(result.file, result.backupPath);
            console.log(`   🔄 Restored ${result.file}`);
          } catch (error) {
            console.error(`   ❌ Failed to restore ${result.file}: ${error.message}`);
          }
        }
      }
      return false;
    }
  }

  // Clean up backup files on success
  if (!hasErrors && !CONFIG.dryRun) {
    for (const result of updateResults) {
      if (result.backupPath && fs.existsSync(result.backupPath)) {
        fs.unlinkSync(result.backupPath);
      }
    }
  }

  if (hasErrors) {
    console.log('\n❌ Version update failed with errors.');
    return false;
  } else {
    const mode = CONFIG.dryRun ? ' (dry run)' : '';
    console.log(`\n🎉 Version update completed successfully${mode}!`);
    console.log(`   All files now use version: ${newVersion}`);
    return true;
  }
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);

  // Show help
  if (args.includes('--help') || args.includes('-h')) {
    console.log(`
Usage: node scripts/update-version.js [options] [version]

Options:
  --dry-run         Show what would be changed without making changes
  --verbose, -v     Show detailed output
  --force           Override safety checks (like existing git tags)
  --skip-validation Skip pre and post validation
  --help, -h        Show this help message

Examples:
  node scripts/update-version.js                    # Update to version in package.json
  node scripts/update-version.js 1.2.3             # Update to specific version
  node scripts/update-version.js --dry-run         # Preview changes
  node scripts/update-version.js --verbose 1.2.3   # Detailed output
`);
    process.exit(0);
  }

  // Get version from command line argument if provided
  const versionArg = args.find(arg => !arg.startsWith('--') && arg !== '-v');

  const success = updateVersion(versionArg);
  process.exit(success ? 0 : 1);
}

module.exports = {
  updateVersion,
  updateVersionInFile,
  createBackup,
  restoreFromBackup
};
