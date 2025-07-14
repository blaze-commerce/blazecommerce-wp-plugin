#!/usr/bin/env node

/**
 * Version Mismatch Fix Script
 * Automatically fixes version mismatches between git tags and files
 * Provides safe rollback capabilities and comprehensive validation
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Import utilities
const { validateVersionSync, generateSyncReport } = require('./version-sync-validator');
const { isValidSemver } = require('./semver-utils');

/**
 * Create backup of files before modification
 * @param {Array} filePaths - Array of file paths to backup
 * @returns {string} Backup directory path
 */
function createBackup(filePaths) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  const backupDir = path.join('.backup', `version-fix-${timestamp}`);
  
  // Create backup directory
  if (!fs.existsSync('.backup')) {
    fs.mkdirSync('.backup');
  }
  fs.mkdirSync(backupDir, { recursive: true });

  // Copy files to backup
  for (const filePath of filePaths) {
    if (fs.existsSync(filePath)) {
      const backupPath = path.join(backupDir, filePath);
      const backupFileDir = path.dirname(backupPath);
      
      if (!fs.existsSync(backupFileDir)) {
        fs.mkdirSync(backupFileDir, { recursive: true });
      }
      
      fs.copyFileSync(filePath, backupPath);
      console.log(`📋 Backed up: ${filePath} → ${backupPath}`);
    }
  }

  return backupDir;
}

/**
 * Restore files from backup
 * @param {string} backupDir - Backup directory path
 * @param {Array} filePaths - Array of file paths to restore
 */
function restoreFromBackup(backupDir, filePaths) {
  console.log(`🔄 Restoring files from backup: ${backupDir}`);
  
  for (const filePath of filePaths) {
    const backupPath = path.join(backupDir, filePath);
    if (fs.existsSync(backupPath)) {
      fs.copyFileSync(backupPath, filePath);
      console.log(`✅ Restored: ${backupPath} → ${filePath}`);
    }
  }
}

/**
 * Update version in package.json
 * @param {string} filePath - Path to package.json
 * @param {string} version - New version
 */
function updatePackageJson(filePath, version) {
  if (!fs.existsSync(filePath)) {
    throw new Error(`File not found: ${filePath}`);
  }

  const content = fs.readFileSync(filePath, 'utf8');
  const packageData = JSON.parse(content);
  
  packageData.version = version;
  
  fs.writeFileSync(filePath, JSON.stringify(packageData, null, 2) + '\n');
  console.log(`✅ Updated ${filePath}: version → ${version}`);
}

/**
 * Update version in PHP file
 * @param {string} filePath - Path to PHP file
 * @param {string} version - New version
 */
function updatePhpFile(filePath, version) {
  if (!fs.existsSync(filePath)) {
    throw new Error(`File not found: ${filePath}`);
  }

  let content = fs.readFileSync(filePath, 'utf8');
  let updated = false;

  // Update plugin header version
  const headerRegex = /^Version:\s*([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)$/m;
  if (headerRegex.test(content)) {
    content = content.replace(headerRegex, `Version: ${version}`);
    updated = true;
    console.log(`✅ Updated ${filePath}: Plugin Header → ${version}`);
  }

  // Update PHP constant
  const constantRegex = /define\(\s*'BLAZE_COMMERCE_VERSION',\s*'([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)'\s*\);/;
  if (constantRegex.test(content)) {
    content = content.replace(constantRegex, `define( 'BLAZE_COMMERCE_VERSION', '${version}' );`);
    updated = true;
    console.log(`✅ Updated ${filePath}: Version Constant → ${version}`);
  }

  if (updated) {
    fs.writeFileSync(filePath, content);
  } else {
    console.log(`⚠️  No version patterns found in ${filePath}`);
  }
}

/**
 * Update version in README.md
 * @param {string} filePath - Path to README.md
 * @param {string} version - New version
 */
function updateReadmeFile(filePath, version) {
  if (!fs.existsSync(filePath)) {
    throw new Error(`File not found: ${filePath}`);
  }

  let content = fs.readFileSync(filePath, 'utf8');
  
  // Update version badge
  const badgeRegex = /^\*\*Version:\*\*\s*([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)$/m;
  if (badgeRegex.test(content)) {
    content = content.replace(badgeRegex, `**Version:** ${version}`);
    fs.writeFileSync(filePath, content);
    console.log(`✅ Updated ${filePath}: Version Badge → ${version}`);
  } else {
    console.log(`⚠️  No version badge found in ${filePath}`);
  }
}

/**
 * Fix version mismatches by updating all files to match target version
 * @param {string} targetVersion - Target version to sync to
 * @param {object} options - Fix options
 * @returns {object} Fix result
 */
function fixVersionMismatch(targetVersion, options = {}) {
  const { 
    verbose = false, 
    dryRun = false, 
    createBackups = true,
    validateAfter = true 
  } = options;

  const result = {
    success: false,
    targetVersion: targetVersion,
    filesUpdated: [],
    errors: [],
    backupDir: null,
    validationResult: null
  };

  try {
    // Validate target version
    if (!isValidSemver(targetVersion)) {
      result.errors.push(`Invalid semantic version: ${targetVersion}`);
      return result;
    }

    console.log(`🔧 Fixing version mismatches to: ${targetVersion}`);
    if (dryRun) {
      console.log('🔍 DRY RUN MODE - No files will be modified');
    }

    // Get list of files to update
    const filesToUpdate = [
      'package.json',
      'blaze-wooless.php', 
      'blocks/package.json',
      'README.md'
    ].filter(file => fs.existsSync(file));

    if (verbose) {
      console.log(`📁 Files to update: ${filesToUpdate.join(', ')}`);
    }

    // Create backup if not dry run
    if (!dryRun && createBackups) {
      result.backupDir = createBackup(filesToUpdate);
    }

    // Update each file
    if (!dryRun) {
      try {
        // Update package.json files
        if (fs.existsSync('package.json')) {
          updatePackageJson('package.json', targetVersion);
          result.filesUpdated.push('package.json');
        }

        if (fs.existsSync('blocks/package.json')) {
          updatePackageJson('blocks/package.json', targetVersion);
          result.filesUpdated.push('blocks/package.json');
        }

        // Update PHP file
        if (fs.existsSync('blaze-wooless.php')) {
          updatePhpFile('blaze-wooless.php', targetVersion);
          result.filesUpdated.push('blaze-wooless.php');
        }

        // Update README
        if (fs.existsSync('README.md')) {
          updateReadmeFile('README.md', targetVersion);
          result.filesUpdated.push('README.md');
        }

      } catch (error) {
        result.errors.push(`Error updating files: ${error.message}`);
        
        // Restore from backup if error occurred
        if (result.backupDir) {
          console.log('❌ Error occurred, restoring from backup...');
          restoreFromBackup(result.backupDir, filesToUpdate);
        }
        
        return result;
      }
    }

    // Validate after update
    if (validateAfter && !dryRun) {
      console.log('\n🔍 Validating version synchronization after fix...');
      result.validationResult = validateVersionSync(null, { verbose: false });
      
      if (!result.validationResult.success) {
        result.errors.push('Validation failed after fix');
        
        // Restore from backup if validation failed
        if (result.backupDir) {
          console.log('❌ Validation failed, restoring from backup...');
          restoreFromBackup(result.backupDir, filesToUpdate);
        }
        
        return result;
      }
    }

    result.success = true;
    console.log(`✅ Successfully fixed version mismatches to ${targetVersion}`);
    
    if (result.filesUpdated.length > 0) {
      console.log(`📁 Updated files: ${result.filesUpdated.join(', ')}`);
    }

  } catch (error) {
    result.errors.push(`Fix operation failed: ${error.message}`);
  }

  return result;
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);

  if (args.length === 0 || args.includes('--help') || args.includes('-h')) {
    console.log(`
Usage: node scripts/fix-version-mismatch.js <version> [options]

Arguments:
  <version>          Target version to sync all files to (e.g., 1.14.1)

Options:
  --dry-run         Show what would be changed without making changes
  --verbose, -v     Show detailed output
  --no-backup       Skip creating backup files
  --no-validate     Skip validation after fix
  --help, -h        Show this help message

Examples:
  node scripts/fix-version-mismatch.js 1.14.1
  node scripts/fix-version-mismatch.js 1.14.1 --dry-run --verbose
  node scripts/fix-version-mismatch.js 1.14.1 --no-backup
`);
    process.exit(0);
  }

  const targetVersion = args[0];
  const verbose = args.includes('--verbose') || args.includes('-v');
  const dryRun = args.includes('--dry-run');
  const createBackups = !args.includes('--no-backup');
  const validateAfter = !args.includes('--no-validate');

  if (!targetVersion) {
    console.error('❌ Error: Target version is required');
    console.error('Usage: node scripts/fix-version-mismatch.js <version>');
    process.exit(1);
  }

  console.log('🔧 Starting version mismatch fix...\n');

  const result = fixVersionMismatch(targetVersion, {
    verbose,
    dryRun,
    createBackups,
    validateAfter
  });

  if (result.success) {
    console.log('\n✅ Version mismatch fix completed successfully!');

    if (result.validationResult) {
      console.log('✅ Post-fix validation passed');
    }

    if (result.backupDir) {
      console.log(`📋 Backup created at: ${result.backupDir}`);
    }

    process.exit(0);
  } else {
    console.log('\n❌ Version mismatch fix failed!');

    for (const error of result.errors) {
      console.error(`   ❌ ${error}`);
    }

    if (result.validationResult && !result.validationResult.success) {
      console.log('\n📊 Post-fix validation report:');
      console.log(generateSyncReport(result.validationResult));
    }

    process.exit(1);
  }
}

module.exports = {
  fixVersionMismatch,
  createBackup,
  restoreFromBackup,
  updatePackageJson,
  updatePhpFile,
  updateReadmeFile
};
