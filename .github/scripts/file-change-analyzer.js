#!/usr/bin/env node

/**
 * File Change Analyzer
 * Analyzes changed files to determine if version bump is needed
 * Supports ignore patterns and provides detailed analysis
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

/**
 * Standardized logging utility
 * Uses stderr for all log messages to prevent GitHub Actions output formatting errors
 * Only actual output data should go to stdout for GitHub Actions
 */
class Logger {
  static info(message) {
    console.error(`INFO: ${message}`);
  }

  static success(message) {
    console.error(`SUCCESS: ${message}`);
  }

  static warning(message) {
    console.error(`WARNING: ${message}`);
  }

  static error(message) {
    console.error(`ERROR: ${message}`);
  }

  static debug(message) {
    if (process.env.DEBUG === 'true') {
      console.error(`DEBUG: ${message}`);
    }
  }
}

/**
 * File Change Analyzer Class
 */
class FileChangeAnalyzer {
  constructor() {
    this.beforeCommit = process.env.GITHUB_EVENT_BEFORE || '';
    this.isFirstPush = this.beforeCommit === '0000000000000000000000000000000000000000';
    this.ignorePatterns = this.loadIgnorePatterns();
  }

  /**
   * Load ignore patterns from external script or default patterns
   * @returns {Array<string>} Array of ignore patterns
   */
  loadIgnorePatterns() {
    try {
      // Try to load from external script first
      const ignoreScript = path.join(process.cwd(), 'scripts', 'get-ignore-patterns.sh');
      if (fs.existsSync(ignoreScript)) {
        Logger.debug('Loading ignore patterns from external script');
        const output = execSync('bash scripts/get-ignore-patterns.sh', { encoding: 'utf8' });

        // Parse patterns and filter out comments and empty lines
        const patterns = output.trim().split('\n')
          .map(line => line.trim())
          .filter(line => line && !line.startsWith('#'))
          .filter(pattern => pattern.length > 0);

        Logger.debug(`Loaded ${patterns.length} ignore patterns from external script`);
        return patterns;
      }
    } catch (error) {
      Logger.warning(`Could not load external ignore patterns: ${error.message}`);
    }

    // Default ignore patterns
    const defaultPatterns = [
      '.github/',
      '.github/workflows/',
      'docs/',
      '*.md',
      '*.txt',
      '.gitignore',
      '.npmignore',
      'LICENSE*',
      'CHANGELOG*',
      'README*',
      'package-lock.json',
      'yarn.lock',
      '.vscode/',
      '.idea/',
      '*.log',
      'test/',
      'tests/',
      '__tests__/',
      '*.test.js',
      '*.spec.js',
      'scripts/',
      'bin/',
      'setup-templates/'
    ];

    Logger.debug(`Using ${defaultPatterns.length} default ignore patterns`);
    return defaultPatterns;
  }

  /**
   * Get list of changed files
   * @returns {Array<string>} Array of changed file paths
   */
  getChangedFiles() {
    try {
      let changedFiles;
      
      if (this.isFirstPush) {
        Logger.info('First push detected, analyzing all files in commit');
        try {
          changedFiles = execSync('git diff --name-only --diff-filter=ACMRT HEAD~1', { encoding: 'utf8' });
        } catch (error) {
          Logger.warning('Could not get diff, falling back to listing all tracked files');
          changedFiles = execSync('git ls-files --cached --exclude-standard', { encoding: 'utf8' });
        }
      } else {
        Logger.info('Regular push detected, analyzing changed files');
        changedFiles = execSync(`git diff --name-only --diff-filter=ACMRT ${this.beforeCommit} HEAD`, { encoding: 'utf8' });
      }

      return changedFiles.trim().split('\n').filter(file => file.trim());
    } catch (error) {
      Logger.error(`Failed to get changed files: ${error.message}`);
      throw error;
    }
  }

  /**
   * Check if a file matches any ignore pattern
   * @param {string} filePath - File path to check
   * @returns {boolean} True if file should be ignored
   */
  shouldIgnoreFile(filePath) {
    // Normalize file path (remove leading ./ if present)
    const normalizedPath = filePath.replace(/^\.\//, '');

    return this.ignorePatterns.some(pattern => {
      const trimmedPattern = pattern.trim();

      // Skip empty patterns and comments (double check)
      if (!trimmedPattern || trimmedPattern.startsWith('#')) {
        return false;
      }

      // Handle directory patterns (ending with /)
      if (trimmedPattern.endsWith('/')) {
        // Directory pattern should match any file within that directory
        const dirPattern = trimmedPattern.slice(0, -1); // Remove trailing slash
        return normalizedPath.startsWith(dirPattern + '/') || normalizedPath === dirPattern;
      }

      // Handle exact file matches
      if (normalizedPath === trimmedPattern) {
        return true;
      }

      // Handle file basename matches (file in any directory)
      const fileName = normalizedPath.split('/').pop();
      if (fileName === trimmedPattern) {
        return true;
      }

      // Handle file extension patterns (starting with . but not a path)
      if (trimmedPattern.startsWith('.') && !trimmedPattern.includes('/')) {
        if (fileName === trimmedPattern || fileName.endsWith(trimmedPattern)) {
          return true;
        }
      }

      // Handle glob patterns with wildcards
      if (trimmedPattern.includes('*')) {
        const regexPattern = trimmedPattern
          .replace(/\./g, '\\.')
          .replace(/\*/g, '.*')
          .replace(/\?/g, '.');

        const regex = new RegExp(`^${regexPattern}$`);
        if (regex.test(normalizedPath)) {
          return true;
        }

        // Also check if the pattern matches the file basename for patterns like *.md
        if (!trimmedPattern.includes('/') && regex.test(fileName)) {
          return true;
        }
      }

      // Handle patterns that should match files in subdirectories
      // e.g., "docs" should match "docs/file.md"
      if (!trimmedPattern.includes('/') && !trimmedPattern.includes('*')) {
        if (normalizedPath.startsWith(trimmedPattern + '/')) {
          return true;
        }
      }

      return false;
    });
  }

  /**
   * Analyze changed files and determine if version bump is needed
   * @returns {Object} Analysis result with shouldBump and details
   */
  analyze() {
    try {
      Logger.info('Starting file change analysis...');
      
      const changedFiles = this.getChangedFiles();
      Logger.info(`Found ${changedFiles.length} changed files`);

      if (changedFiles.length === 0) {
        Logger.warning('No changed files detected');
        return {
          shouldBump: false,
          reason: 'No changed files detected',
          changedFiles: [],
          ignoredFiles: [],
          significantFiles: []
        };
      }

      const ignoredFiles = [];
      const significantFiles = [];

      // Categorize files with detailed logging
      changedFiles.forEach(file => {
        const shouldIgnore = this.shouldIgnoreFile(file);
        if (shouldIgnore) {
          ignoredFiles.push(file);
          Logger.debug(`IGNORED: ${file}`);
        } else {
          significantFiles.push(file);
          Logger.debug(`SIGNIFICANT: ${file}`);
        }
      });

      // Log analysis results
      Logger.info(`Analysis complete:`);
      Logger.info(`  Total files: ${changedFiles.length}`);
      Logger.info(`  Ignored files: ${ignoredFiles.length}`);
      Logger.info(`  Significant files: ${significantFiles.length}`);

      // Always show file categorization for transparency
      if (ignoredFiles.length > 0) {
        Logger.info('Ignored files:');
        ignoredFiles.forEach(file => Logger.info(`  - ${file} (ignored)`));
      }

      if (significantFiles.length > 0) {
        Logger.info('Significant files (will trigger version bump):');
        significantFiles.forEach(file => Logger.info(`  - ${file} (significant)`));
      }

      // Show loaded ignore patterns for debugging
      if (process.env.DEBUG === 'true') {
        Logger.debug('Loaded ignore patterns:');
        this.ignorePatterns.forEach(pattern => Logger.debug(`  - "${pattern}"`));
      }

      const shouldBump = significantFiles.length > 0;
      
      if (shouldBump) {
        Logger.success('Version bump needed - significant files were changed');
      } else {
        Logger.info('Version bump skipped - only ignored files were changed');
      }

      return {
        shouldBump,
        reason: shouldBump ? 'Significant files changed' : 'Only ignored files changed',
        changedFiles,
        ignoredFiles,
        significantFiles,
        analysis: {
          totalFiles: changedFiles.length,
          ignoredCount: ignoredFiles.length,
          significantCount: significantFiles.length
        }
      };

    } catch (error) {
      Logger.error(`File analysis failed: ${error.message}`);
      throw error;
    }
  }

  /**
   * Output results in GitHub Actions format
   * @param {Object} result - Analysis result
   */
  outputForGitHubActions(result) {
    const fs = require('fs');

    // Prepare output data
    const outputs = [
      `should_bump_version=${result.shouldBump}`,
      `analysis_reason=${result.reason}`,
      `changed_files_count=${result.analysis.totalFiles}`,
      `significant_files_count=${result.analysis.significantCount}`,
      `ignored_files_count=${result.analysis.ignoredCount}`
    ];

    // Write to GitHub Actions output file if available
    if (process.env.GITHUB_OUTPUT) {
      try {
        outputs.forEach(output => {
          fs.appendFileSync(process.env.GITHUB_OUTPUT, `${output}\n`);
        });
        Logger.debug('Successfully wrote outputs to GITHUB_OUTPUT file');
      } catch (error) {
        Logger.error(`Failed to write to GITHUB_OUTPUT file: ${error.message}`);
        // Fallback to stdout for backward compatibility
        outputs.forEach(output => console.log(output));
      }
    } else {
      // Fallback to stdout when GITHUB_OUTPUT is not available
      Logger.debug('GITHUB_OUTPUT not available, using stdout');
      outputs.forEach(output => console.log(output));
    }
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  try {
    const analyzer = new FileChangeAnalyzer();
    const result = analyzer.analyze();
    analyzer.outputForGitHubActions(result);
    
    // Exit with appropriate code
    process.exit(0);
  } catch (error) {
    Logger.error(`Script execution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = { FileChangeAnalyzer, Logger };
