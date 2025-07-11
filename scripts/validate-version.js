#!/usr/bin/env node

/**
 * Version Validation System
 * Validates version consistency across all plugin files and checks for conflicts
 */

const fs = require('fs');
const path = require('path');
const { isValidSemver, parseVersion, compareVersions, tagExists, getCurrentVersion } = require('./semver-utils');

// Files that contain version information
const VERSION_FILES = [
  {
    path: 'package.json',
    type: 'json',
    field: 'version'
  },
  {
    path: 'blaze-wooless.php',
    type: 'php',
    patterns: [
      { regex: /^Version:\s*([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)$/m, name: 'Plugin Header' },
      { regex: /define\(\s*'BLAZE_COMMERCE_VERSION',\s*'([\d.]+(?:-[\w.]+)?(?:\+[\w.]+)?)'\s*\);/, name: 'Version Constant' }
    ]
  },
  {
    path: 'blocks/package.json',
    type: 'json',
    field: 'version'
  }
];

/**
 * Extract version from a file
 * @param {object} fileConfig - File configuration object
 * @returns {object} Version information
 */
function extractVersionFromFile(fileConfig) {
  const filePath = fileConfig.path;
  
  if (!fs.existsSync(filePath)) {
    return {
      file: filePath,
      exists: false,
      versions: [],
      errors: [`File does not exist: ${filePath}`]
    };
  }

  const content = fs.readFileSync(filePath, 'utf8');
  const result = {
    file: filePath,
    exists: true,
    versions: [],
    errors: []
  };

  try {
    if (fileConfig.type === 'json') {
      const jsonData = JSON.parse(content);
      const version = jsonData[fileConfig.field];
      if (version) {
        result.versions.push({
          location: fileConfig.field,
          version: version,
          valid: isValidSemver(version)
        });
      } else {
        result.errors.push(`Missing ${fileConfig.field} field in JSON`);
      }
    } else if (fileConfig.type === 'php') {
      for (const pattern of fileConfig.patterns) {
        const match = content.match(pattern.regex);
        if (match) {
          result.versions.push({
            location: pattern.name,
            version: match[1],
            valid: isValidSemver(match[1])
          });
        } else {
          result.errors.push(`Could not find ${pattern.name} in ${filePath}`);
        }
      }
    }
  } catch (error) {
    result.errors.push(`Error parsing ${filePath}: ${error.message}`);
  }

  return result;
}

/**
 * Validate all version files
 * @returns {object} Validation results
 */
function validateAllVersions() {
  const results = {
    files: [],
    allVersions: [],
    isConsistent: true,
    hasErrors: false,
    summary: {
      totalFiles: 0,
      validFiles: 0,
      totalVersions: 0,
      validVersions: 0,
      uniqueVersions: new Set()
    }
  };

  // Extract versions from all files
  for (const fileConfig of VERSION_FILES) {
    const fileResult = extractVersionFromFile(fileConfig);
    results.files.push(fileResult);
    results.summary.totalFiles++;

    if (fileResult.exists && fileResult.errors.length === 0) {
      results.summary.validFiles++;
    }

    if (fileResult.errors.length > 0) {
      results.hasErrors = true;
    }

    // Collect all versions
    for (const versionInfo of fileResult.versions) {
      results.allVersions.push({
        file: fileResult.file,
        location: versionInfo.location,
        version: versionInfo.version,
        valid: versionInfo.valid
      });
      results.summary.totalVersions++;
      
      if (versionInfo.valid) {
        results.summary.validVersions++;
        results.summary.uniqueVersions.add(versionInfo.version);
      }
    }
  }

  // Check consistency
  if (results.summary.uniqueVersions.size > 1) {
    results.isConsistent = false;
    results.hasErrors = true;
  }

  return results;
}

/**
 * Validate a specific version string
 * @param {string} version - Version to validate
 * @returns {object} Validation result
 */
function validateVersion(version) {
  const result = {
    version: version,
    valid: false,
    errors: [],
    warnings: [],
    parsed: null
  };

  // Check if version is a valid semantic version
  if (!isValidSemver(version)) {
    result.errors.push('Version does not follow semantic versioning format (X.Y.Z)');
    return result;
  }

  result.valid = true;
  result.parsed = parseVersion(version);

  // Check for potential issues
  if (result.parsed.major === 0) {
    result.warnings.push('Major version 0 indicates initial development phase');
  }

  if (result.parsed.prerelease) {
    result.warnings.push('Pre-release version detected');
  }

  return result;
}

/**
 * Check for version conflicts with git tags
 * @param {string} version - Version to check
 * @returns {object} Conflict check result
 */
function checkVersionConflicts(version) {
  const result = {
    version: version,
    hasConflicts: false,
    conflicts: [],
    suggestions: []
  };

  // Check if tag already exists
  const tagName = `v${version}`;
  if (tagExists(tagName)) {
    result.hasConflicts = true;
    result.conflicts.push(`Git tag ${tagName} already exists`);
  }

  // Compare with current version
  try {
    const currentVersion = getCurrentVersion();
    const comparison = compareVersions(version, currentVersion);
    
    if (comparison <= 0) {
      result.hasConflicts = true;
      result.conflicts.push(`New version ${version} is not greater than current version ${currentVersion}`);
      
      const parsed = parseVersion(currentVersion);
      if (parsed) {
        result.suggestions.push(`Consider using ${parsed.major}.${parsed.minor}.${parsed.patch + 1} for patch`);
        result.suggestions.push(`Consider using ${parsed.major}.${parsed.minor + 1}.0 for minor`);
        result.suggestions.push(`Consider using ${parsed.major + 1}.0.0 for major`);
      }
    }
  } catch (error) {
    result.conflicts.push(`Could not compare with current version: ${error.message}`);
  }

  return result;
}

/**
 * Generate validation report
 * @param {object} validationResults - Results from validateAllVersions
 * @returns {string} Formatted report
 */
function generateValidationReport(validationResults) {
  let report = '\n=== VERSION VALIDATION REPORT ===\n\n';

  // Summary
  report += `📊 SUMMARY:\n`;
  report += `   Files checked: ${validationResults.summary.totalFiles}\n`;
  report += `   Valid files: ${validationResults.summary.validFiles}\n`;
  report += `   Total versions found: ${validationResults.summary.totalVersions}\n`;
  report += `   Valid versions: ${validationResults.summary.validVersions}\n`;
  report += `   Unique versions: ${validationResults.summary.uniqueVersions.size}\n`;
  report += `   Consistent: ${validationResults.isConsistent ? '✅ Yes' : '❌ No'}\n\n`;

  // File details
  report += `📁 FILE DETAILS:\n`;
  for (const fileResult of validationResults.files) {
    report += `   ${fileResult.file}:\n`;
    if (!fileResult.exists) {
      report += `     ❌ File does not exist\n`;
      continue;
    }

    if (fileResult.versions.length === 0) {
      report += `     ⚠️  No versions found\n`;
    } else {
      for (const versionInfo of fileResult.versions) {
        const status = versionInfo.valid ? '✅' : '❌';
        report += `     ${status} ${versionInfo.location}: ${versionInfo.version}\n`;
      }
    }

    if (fileResult.errors.length > 0) {
      for (const error of fileResult.errors) {
        report += `     ❌ ${error}\n`;
      }
    }
  }

  // Unique versions
  if (validationResults.summary.uniqueVersions.size > 0) {
    report += `\n🔢 UNIQUE VERSIONS FOUND:\n`;
    for (const version of validationResults.summary.uniqueVersions) {
      report += `   ${version}\n`;
    }
  }

  // Consistency issues
  if (!validationResults.isConsistent) {
    report += `\n⚠️  CONSISTENCY ISSUES:\n`;
    report += `   Multiple different versions found across files.\n`;
    report += `   All version locations should have the same version number.\n`;
  }

  return report;
}

/**
 * Main validation function
 * @param {object} options - Validation options
 * @returns {boolean} True if validation passes
 */
function validateVersionSystem(options = {}) {
  const { verbose = false, checkConflicts = true } = options;

  console.log('🔍 Validating version system...\n');

  const results = validateAllVersions();
  
  if (verbose) {
    console.log(generateValidationReport(results));
  }

  let hasIssues = results.hasErrors || !results.isConsistent;

  // Check for conflicts if requested
  if (checkConflicts && results.summary.uniqueVersions.size === 1) {
    const version = Array.from(results.summary.uniqueVersions)[0];
    const conflictCheck = checkVersionConflicts(version);
    
    if (conflictCheck.hasConflicts) {
      hasIssues = true;
      console.log('⚠️  VERSION CONFLICTS DETECTED:');
      for (const conflict of conflictCheck.conflicts) {
        console.log(`   ❌ ${conflict}`);
      }
      
      if (conflictCheck.suggestions.length > 0) {
        console.log('\n💡 SUGGESTIONS:');
        for (const suggestion of conflictCheck.suggestions) {
          console.log(`   ${suggestion}`);
        }
      }
    }
  }

  if (hasIssues) {
    console.log('\n❌ Version validation failed. Please fix the issues above.');
    return false;
  } else {
    console.log('✅ Version validation passed. All versions are consistent and valid.');
    return true;
  }
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const verbose = args.includes('--verbose') || args.includes('-v');
  const checkConflicts = !args.includes('--no-conflicts');

  const success = validateVersionSystem({ verbose, checkConflicts });
  process.exit(success ? 0 : 1);
}

module.exports = {
  extractVersionFromFile,
  validateAllVersions,
  validateVersion,
  checkVersionConflicts,
  generateValidationReport,
  validateVersionSystem,
  VERSION_FILES
};
