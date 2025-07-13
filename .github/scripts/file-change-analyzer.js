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
 */
class Logger {
  static info(message) {
    console.log(`ℹ️  ${message}`);
  }
  
  static success(message) {
    console.log(`✅ ${message}`);
  }
  
  static warning(message) {
    console.log(`⚠️  ${message}`);
  }
  
  static error(message) {
    console.error(`❌ ${message}`);
  }
  
  static debug(message) {
    if (process.env.DEBUG === 'true') {
      console.log(`🔍 DEBUG: ${message}`);
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
        return output.trim().split('\n').filter(pattern => pattern.trim());
      }
    } catch (error) {
      Logger.warning(`Could not load external ignore patterns: ${error.message}`);
    }

    // Default ignore patterns
    return [
      '.github/workflows/*',
      'docs/*',
      '*.md',
      '*.txt',
      '.gitignore',
      '.npmignore',
      'LICENSE*',
      'CHANGELOG*',
      'README*',
      'package-lock.json',
      'yarn.lock',
      '.vscode/*',
      '.idea/*',
      '*.log',
      'test/*',
      'tests/*',
      '__tests__/*',
      '*.test.js',
      '*.spec.js'
    ];
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
    return this.ignorePatterns.some(pattern => {
      // Convert glob pattern to regex
      const regexPattern = pattern
        .replace(/\./g, '\\.')
        .replace(/\*/g, '.*')
        .replace(/\?/g, '.');
      
      const regex = new RegExp(`^${regexPattern}$`);
      return regex.test(filePath);
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

      // Categorize files
      changedFiles.forEach(file => {
        if (this.shouldIgnoreFile(file)) {
          ignoredFiles.push(file);
        } else {
          significantFiles.push(file);
        }
      });

      // Log analysis results
      Logger.info(`Analysis complete:`);
      Logger.info(`  Total files: ${changedFiles.length}`);
      Logger.info(`  Ignored files: ${ignoredFiles.length}`);
      Logger.info(`  Significant files: ${significantFiles.length}`);

      if (process.env.DEBUG === 'true') {
        Logger.debug('Ignored files:');
        ignoredFiles.forEach(file => Logger.debug(`  - ${file}`));
        Logger.debug('Significant files:');
        significantFiles.forEach(file => Logger.debug(`  - ${file}`));
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
    // Set output for GitHub Actions
    console.log(`should_bump_version=${result.shouldBump}`);
    console.log(`analysis_reason=${result.reason}`);
    console.log(`changed_files_count=${result.analysis.totalFiles}`);
    console.log(`significant_files_count=${result.analysis.significantCount}`);
    console.log(`ignored_files_count=${result.analysis.ignoredCount}`);
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
