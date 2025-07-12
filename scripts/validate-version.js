#!/usr/bin/env node

/**
 * Version Validation System
 * Validates version consistency across all plugin files and checks for conflicts
 */

const fs = require('fs');
const path = require('path');
const {
  isValidSemver,
  parseVersion,
  compareVersions,
  tagExists,
  getCurrentVersion,
  validateTagName,
  calculateNextVersion,
  resolveVersionConflicts,
  determineBumpType,
  getCommitsSinceLastTag
} = require('./semver-utils');
const config = require('./config');

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
  if (!fileConfig || !fileConfig.path) {
    return {
      file: 'unknown',
      exists: false,
      versions: [],
      errors: ['Invalid file configuration']
    };
  }

  const filePath = fileConfig.path;

  if (!fs.existsSync(filePath)) {
    return {
      file: filePath,
      exists: false,
      versions: [],
      errors: [`File does not exist: ${filePath}`]
    };
  }

  let content;
  try {
    const stats = fs.statSync(filePath);
    if (stats.size > config.FILES.MAX_FILE_SIZE) {
      return {
        file: filePath,
        exists: true,
        versions: [],
        errors: [`File too large: ${filePath} (${stats.size} bytes, max ${config.FILES.MAX_FILE_SIZE})`]
      };
    }

    content = fs.readFileSync(filePath, config.FILES.DEFAULT_ENCODING);
  } catch (error) {
    return {
      file: filePath,
      exists: true,
      versions: [],
      errors: [`Cannot read file: ${filePath} - ${error.message}`]
    };
  }

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
 * Enhanced version conflict checking with resolution capabilities
 * @param {string} version - Version to check for conflicts
 * @param {object} options - Conflict checking options
 * @returns {object} Enhanced conflict check result
 */
function checkVersionConflicts(version, options = {}) {
  const {
    enableResolution = false,
    resolutionStrategy = 'auto',
    verbose = false
  } = options;

  if (!version || typeof version !== 'string') {
    return {
      version: version,
      hasConflicts: true,
      conflicts: ['Invalid version provided'],
      suggestions: [],
      resolution: null,
      analysis: null
    };
  }

  const result = {
    version: version,
    hasConflicts: false,
    conflicts: [],
    suggestions: [],
    resolution: null,
    analysis: null
  };

  try {
    // Validate version format first
    if (!isValidSemver(version)) {
      result.hasConflicts = true;
      result.conflicts.push(`Invalid semantic version format: ${version}`);
      return result;
    }

    // Check if tag already exists
    const tagName = `v${version}`;
    validateTagName(tagName); // Validate tag name for security

    if (tagExists(tagName)) {
      result.hasConflicts = true;
      result.conflicts.push(`Git tag ${tagName} already exists`);
    }
  } catch (error) {
    result.hasConflicts = true;
    result.conflicts.push(`Error checking git tags: ${error.message}`);
    return result;
  }

  // Compare with current version
  try {
    const currentVersion = getCurrentVersion();
    const comparison = compareVersions(version, currentVersion);

    if (comparison <= 0) {
      result.hasConflicts = true;

      if (comparison === 0) {
        // CLAUDE AI REVIEW: Combine related error messages for consistency
        result.conflicts.push(`Version ${version} already exists (same as current version). This usually indicates the validation is running after version bump. Consider using --no-conflicts flag for post-bump validation.`);
      } else {
        result.conflicts.push(`New version ${version} is not greater than current version ${currentVersion}`);
      }

      // Enhanced suggestions with commit analysis
      const commits = getCommitsSinceLastTag(50);
      const bumpAnalysis = determineBumpType(commits.messages, { verbose });

      result.analysis = {
        currentVersion,
        targetVersion: version,
        commitCount: commits.count,
        recommendedBump: bumpAnalysis.bumpType,
        reasoning: bumpAnalysis.reasoning
      };

      const parsed = parseVersion(currentVersion);
      if (parsed) {
        result.suggestions.push(`Recommended (${bumpAnalysis.bumpType}): ${
          bumpAnalysis.bumpType === 'major' ? `${parsed.major + 1}.0.0` :
          bumpAnalysis.bumpType === 'minor' ? `${parsed.major}.${parsed.minor + 1}.0` :
          `${parsed.major}.${parsed.minor}.${parsed.patch + 1}`
        }`);
        result.suggestions.push(`Alternative patch: ${parsed.major}.${parsed.minor}.${parsed.patch + 1}`);
        result.suggestions.push(`Alternative minor: ${parsed.major}.${parsed.minor + 1}.0`);
        result.suggestions.push(`Alternative major: ${parsed.major + 1}.0.0`);
      }

      // Attempt automatic resolution if enabled
      if (enableResolution) {
        result.resolution = resolveVersionConflicts({
          targetVersion: version,
          strategy: resolutionStrategy,
          verbose
        });
      }
    }
  } catch (error) {
    result.conflicts.push(`Could not compare with current version: ${error.message}`);
  }

  return result;
}

/**
 * Comprehensive version system analysis
 * @param {object} options - Analysis options
 * @returns {object} Detailed analysis result
 */
function analyzeVersionSystem(options = {}) {
  const { verbose = false } = options;

  const analysis = {
    timestamp: new Date().toISOString(),
    currentVersion: null,
    versionConsistency: null,
    gitStatus: {
      hasRepo: false,
      latestTag: null,
      commitsSinceTag: 0,
      uncommittedChanges: false
    },
    recommendations: [],
    issues: [],
    nextVersions: {
      patch: null,
      minor: null,
      major: null,
      recommended: null
    }
  };

  try {
    // Get current version and consistency check
    analysis.currentVersion = getCurrentVersion();
    analysis.versionConsistency = validateAllVersions();

    // Git repository analysis
    try {
      const { execSync } = require('child_process');

      // Check if we're in a git repo
      execSync('git rev-parse --git-dir', { stdio: 'ignore' });
      analysis.gitStatus.hasRepo = true;

      // Get latest tag
      try {
        analysis.gitStatus.latestTag = execSync('git describe --tags --abbrev=0', { encoding: 'utf8' }).trim();
      } catch (e) {
        // No tags exist
      }

      // Get commits since last tag
      const commits = getCommitsSinceLastTag(100, { verbose });
      analysis.gitStatus.commitsSinceTag = commits.count;

      // Check for uncommitted changes
      try {
        const status = execSync('git status --porcelain', { encoding: 'utf8' });
        analysis.gitStatus.uncommittedChanges = status.trim().length > 0;
      } catch (e) {
        // Ignore status check errors
      }

      // Calculate next versions
      const currentParsed = parseVersion(analysis.currentVersion);
      if (currentParsed) {
        analysis.nextVersions.patch = `${currentParsed.major}.${currentParsed.minor}.${currentParsed.patch + 1}`;
        analysis.nextVersions.minor = `${currentParsed.major}.${currentParsed.minor + 1}.0`;
        analysis.nextVersions.major = `${currentParsed.major + 1}.0.0`;

        // Determine recommended version based on commits
        if (commits.count > 0) {
          const bumpAnalysis = determineBumpType(commits.messages, { verbose });
          analysis.nextVersions.recommended = analysis.nextVersions[bumpAnalysis.bumpType];
          analysis.recommendations.push(`Based on ${commits.count} commits, recommend ${bumpAnalysis.bumpType} bump to ${analysis.nextVersions.recommended}`);
        } else {
          analysis.recommendations.push('No commits since last tag - no version bump needed');
        }
      }

    } catch (error) {
      analysis.gitStatus.hasRepo = false;
      analysis.issues.push(`Git repository analysis failed: ${error.message}`);
    }

    // Add consistency issues
    if (!analysis.versionConsistency.isConsistent) {
      analysis.issues.push('Version inconsistency detected across files');
    }

    if (analysis.versionConsistency.hasErrors) {
      analysis.issues.push('Version validation errors found');
    }

    if (verbose) {
      console.log('🔍 Version System Analysis:');
      console.log(`   Current version: ${analysis.currentVersion}`);
      console.log(`   Consistent: ${analysis.versionConsistency.isConsistent}`);
      console.log(`   Git repo: ${analysis.gitStatus.hasRepo}`);
      console.log(`   Commits since tag: ${analysis.gitStatus.commitsSinceTag}`);
      console.log(`   Recommended next: ${analysis.nextVersions.recommended || 'none'}`);
    }

  } catch (error) {
    // CLAUDE AI REVIEW: More specific error handling
    if (error.code === 'ENOENT') {
      analysis.issues.push(`File not found during analysis: ${error.path}`);
    } else if (error.code === 'EACCES') {
      analysis.issues.push(`Permission denied during analysis: ${error.path}`);
    } else if (error.name === 'SyntaxError') {
      analysis.issues.push(`JSON parsing error during analysis: ${error.message}`);
    } else if (error.message.includes('git')) {
      analysis.issues.push(`Git operation failed during analysis: ${error.message}`);
    } else {
      analysis.issues.push(`Analysis failed: ${error.message}`);
    }
  }

  return analysis;
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
 * Enhanced main validation function with comprehensive analysis
 * @param {object} options - Validation options
 * @returns {object} Detailed validation result
 */
function validateVersionSystem(options = {}) {
  const {
    verbose = false,
    checkConflicts = true,
    enableResolution = false,
    resolutionStrategy = 'auto',
    returnDetails = false
  } = options;

  console.log('🔍 Validating version system...\n');

  const results = validateAllVersions();
  const analysis = analyzeVersionSystem({ verbose });

  const validationResult = {
    success: false,
    hasErrors: results.hasErrors,
    isConsistent: results.isConsistent,
    conflicts: [],
    resolutions: [],
    analysis: analysis,
    timestamp: new Date().toISOString()
  };

  if (verbose) {
    console.log(generateValidationReport(results));

    // Enhanced analysis output
    if (analysis.recommendations.length > 0) {
      console.log('\n🎯 RECOMMENDATIONS:');
      for (const rec of analysis.recommendations) {
        console.log(`   ${rec}`);
      }
    }

    if (analysis.issues.length > 0) {
      console.log('\n⚠️  ANALYSIS ISSUES:');
      for (const issue of analysis.issues) {
        console.log(`   ❌ ${issue}`);
      }
    }
  }

  let hasIssues = results.hasErrors || !results.isConsistent;

  // Enhanced conflict checking with resolution
  if (checkConflicts && results.summary.uniqueVersions.size === 1) {
    const version = Array.from(results.summary.uniqueVersions)[0];
    const conflictCheck = checkVersionConflicts(version, {
      enableResolution,
      resolutionStrategy,
      verbose
    });

    validationResult.conflicts = conflictCheck.conflicts;

    if (conflictCheck.hasConflicts) {
      hasIssues = true;
      console.log('⚠️  VERSION CONFLICTS DETECTED:');
      for (const conflict of conflictCheck.conflicts) {
        console.log(`   ❌ ${conflict}`);
      }

      // Enhanced suggestions with commit analysis
      if (conflictCheck.analysis) {
        console.log('\n📊 COMMIT ANALYSIS:');
        console.log(`   Commits since last tag: ${conflictCheck.analysis.commitCount}`);
        console.log(`   Recommended bump type: ${conflictCheck.analysis.recommendedBump}`);
        if (conflictCheck.analysis.reasoning.length > 0) {
          console.log(`   Reasoning: ${conflictCheck.analysis.reasoning.join(', ')}`);
        }
      }

      if (conflictCheck.suggestions.length > 0) {
        console.log('\n💡 ENHANCED SUGGESTIONS:');
        for (const suggestion of conflictCheck.suggestions) {
          console.log(`   ${suggestion}`);
        }
      }

      // Show resolution if available
      if (conflictCheck.resolution && conflictCheck.resolution.success) {
        console.log('\n🔧 AUTOMATIC RESOLUTION AVAILABLE:');
        console.log(`   Resolved version: ${conflictCheck.resolution.resolvedVersion}`);
        console.log(`   Strategy: ${conflictCheck.resolution.strategy}`);
        for (const action of conflictCheck.resolution.actions) {
          console.log(`   • ${action}`);
        }
        validationResult.resolutions.push(conflictCheck.resolution);
      }
    }
  }

  validationResult.success = !hasIssues;

  if (hasIssues) {
    console.log('\n❌ Version validation failed. Please fix the issues above.');
    if (enableResolution && validationResult.resolutions.length > 0) {
      console.log('\n💡 TIP: Automatic resolutions are available. Use --apply-resolution to apply them.');
    }
  } else {
    console.log('✅ Version validation passed. All versions are consistent and valid.');
  }

  return returnDetails ? validationResult : validationResult.success;
}

// Enhanced CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const verbose = args.includes('--verbose') || args.includes('-v');
  const checkConflicts = !args.includes('--no-conflicts');
  const enableResolution = args.includes('--enable-resolution');
  const applyResolution = args.includes('--apply-resolution');
  const analyze = args.includes('--analyze');

  // Get resolution strategy
  const strategyIndex = args.findIndex(arg => arg === '--strategy');
  const resolutionStrategy = strategyIndex !== -1 && args[strategyIndex + 1]
    ? args[strategyIndex + 1]
    : 'auto';

  if (analyze) {
    // Run comprehensive analysis
    console.log('🔍 Running comprehensive version system analysis...\n');
    const analysis = analyzeVersionSystem({ verbose: true });

    console.log('\n📊 ANALYSIS SUMMARY:');
    console.log(`   Current version: ${analysis.currentVersion}`);
    console.log(`   Git repository: ${analysis.gitStatus.hasRepo ? 'Yes' : 'No'}`);
    console.log(`   Latest tag: ${analysis.gitStatus.latestTag || 'None'}`);
    console.log(`   Commits since tag: ${analysis.gitStatus.commitsSinceTag}`);
    console.log(`   Recommended next version: ${analysis.nextVersions.recommended || 'None'}`);

    if (analysis.issues.length > 0) {
      console.log('\n⚠️  ISSUES FOUND:');
      for (const issue of analysis.issues) {
        console.log(`   ❌ ${issue}`);
      }
    }

    process.exit(analysis.issues.length > 0 ? 1 : 0);
  }

  const result = validateVersionSystem({
    verbose,
    checkConflicts,
    enableResolution: enableResolution || applyResolution,
    resolutionStrategy,
    returnDetails: applyResolution
  });

  if (applyResolution && typeof result === 'object' && result.resolutions.length > 0) {
    console.log('\n🔧 Applying automatic resolution...');

    const resolution = result.resolutions[0]; // Use first available resolution
    try {
      // Apply the resolution by updating version files
      const { updateVersionInAllFiles } = require('./update-version');
      const updateResult = updateVersionInAllFiles(resolution.resolvedVersion, {
        verbose: true,
        skipValidation: true // Skip validation since we're resolving conflicts
      });

      if (updateResult) {
        console.log(`✅ Successfully applied resolution: version updated to ${resolution.resolvedVersion}`);
        process.exit(0);
      } else {
        console.log('❌ Failed to apply resolution');
        process.exit(1);
      }
    } catch (error) {
      console.log(`❌ Error applying resolution: ${error.message}`);
      process.exit(1);
    }
  }

  const success = typeof result === 'object' ? result.success : result;
  process.exit(success ? 0 : 1);
}

module.exports = {
  extractVersionFromFile,
  validateAllVersions,
  validateVersion,
  checkVersionConflicts,
  generateValidationReport,
  validateVersionSystem,
  analyzeVersionSystem,
  VERSION_FILES
};
