#!/usr/bin/env node

/**
 * Version Analyzer Script
 * Extracts complex version calculation logic from GitHub workflows
 * Provides semantic version analysis and bump recommendations
 */

const fs = require('fs');
const path = require('path');

class VersionAnalyzer {
  constructor() {
    this.currentVersion = process.env.CURRENT_VERSION || '1.0.0';
    this.commitMessages = process.env.COMMIT_MESSAGES || '';
    this.changedFiles = process.env.CHANGED_FILES || '';
    this.isMainBranch = process.env.GITHUB_REF === 'refs/heads/main';
  }

  /**
   * Parse semantic version string
   * @param {string} version - Version string (e.g., "1.2.3")
   * @returns {object} Parsed version components
   */
  parseVersion(version) {
    const cleanVersion = version.replace(/^v/, '');
    const parts = cleanVersion.split('.');
    
    if (parts.length !== 3) {
      throw new Error(`Invalid version format: ${version}`);
    }

    return {
      major: parseInt(parts[0], 10),
      minor: parseInt(parts[1], 10),
      patch: parseInt(parts[2], 10),
      original: version
    };
  }

  /**
   * Analyze commit messages to determine version bump type
   * @returns {string} Version bump type: 'major', 'minor', 'patch', or 'none'
   */
  analyzeCommitMessages() {
    const messages = this.commitMessages.toLowerCase();
    
    // Check for breaking changes (major version bump)
    if (messages.includes('breaking change') || 
        messages.includes('breaking:') ||
        messages.includes('!:')) {
      return 'major';
    }
    
    // Check for new features (minor version bump)
    if (messages.includes('feat:') || 
        messages.includes('feature:') ||
        messages.includes('add:') ||
        messages.includes('new:')) {
      return 'minor';
    }
    
    // Check for fixes and improvements (patch version bump)
    if (messages.includes('fix:') || 
        messages.includes('bug:') ||
        messages.includes('patch:') ||
        messages.includes('hotfix:') ||
        messages.includes('security:')) {
      return 'patch';
    }
    
    // Check for documentation or non-functional changes
    if (messages.includes('docs:') || 
        messages.includes('style:') ||
        messages.includes('refactor:') ||
        messages.includes('test:') ||
        messages.includes('chore:')) {
      return 'none';
    }
    
    // Default to patch for any other changes
    return 'patch';
  }

  /**
   * Analyze changed files to determine impact
   * @returns {object} File analysis results
   */
  analyzeChangedFiles() {
    const files = this.changedFiles.split('\n').filter(f => f.trim());
    
    const analysis = {
      hasSourceChanges: false,
      hasConfigChanges: false,
      hasDocumentationOnly: false,
      hasTestChanges: false,
      criticalFiles: []
    };

    const sourcePatterns = ['.php', '.js', '.css', '.scss', '.ts'];
    const configPatterns = ['package.json', 'composer.json', '.yml', '.yaml', '.json'];
    const docPatterns = ['.md', '.txt', 'README', 'CHANGELOG'];
    const testPatterns = ['test/', 'tests/', '.test.', '.spec.'];
    const criticalPatterns = ['blaze-wooless.php', 'package.json', 'composer.json'];

    for (const file of files) {
      // Check for source code changes
      if (sourcePatterns.some(pattern => file.endsWith(pattern))) {
        analysis.hasSourceChanges = true;
      }
      
      // Check for configuration changes
      if (configPatterns.some(pattern => file.includes(pattern))) {
        analysis.hasConfigChanges = true;
      }
      
      // Check for documentation changes
      if (docPatterns.some(pattern => file.includes(pattern))) {
        analysis.hasDocumentationOnly = true;
      }
      
      // Check for test changes
      if (testPatterns.some(pattern => file.includes(pattern))) {
        analysis.hasTestChanges = true;
      }
      
      // Check for critical files
      if (criticalPatterns.some(pattern => file.includes(pattern))) {
        analysis.criticalFiles.push(file);
      }
    }

    return analysis;
  }

  /**
   * Calculate the new version based on analysis
   * @returns {string} New version string
   */
  calculateNewVersion() {
    try {
      const currentVersionParts = this.parseVersion(this.currentVersion);
      const bumpType = this.analyzeCommitMessages();
      const fileAnalysis = this.analyzeChangedFiles();

      // If only documentation changed, don't bump version
      if (fileAnalysis.hasDocumentationOnly &&
          !fileAnalysis.hasSourceChanges &&
          !fileAnalysis.hasConfigChanges) {
        console.error('DOCS: Only documentation changes detected - no version bump needed');
        return this.currentVersion;
      }

      let newVersion = { ...currentVersionParts };

      switch (bumpType) {
        case 'major':
          newVersion.major += 1;
          newVersion.minor = 0;
          newVersion.patch = 0;
          break;
        case 'minor':
          newVersion.minor += 1;
          newVersion.patch = 0;
          break;
        case 'patch':
          newVersion.patch += 1;
          break;
        case 'none':
          console.error('RETRY: No functional changes detected - no version bump needed');
          return this.currentVersion;
      }

      const newVersionString = `${newVersion.major}.${newVersion.minor}.${newVersion.patch}`;

      console.error(`ANALYSIS: Version Analysis Results:`);
      console.error(`  Current Version: ${this.currentVersion}`);
      console.error(`  Bump Type: ${bumpType}`);
      console.error(`  New Version: ${newVersionString}`);
      console.error(`  Critical Files Changed: ${fileAnalysis.criticalFiles.length}`);

      return newVersionString;
      
    } catch (error) {
      console.error(`ERROR: Version calculation failed: ${error.message}`);
      process.exit(1);
    }
  }

  /**
   * Validate the calculated version
   * @param {string} version - Version to validate
   * @returns {boolean} True if valid
   */
  validateVersion(version) {
    const semverRegex = /^\d+\.\d+\.\d+$/;
    return semverRegex.test(version);
  }

  /**
   * Main execution function
   */
  run() {
    console.error('EXECUTING: Starting version analysis...');

    const newVersion = this.calculateNewVersion();

    if (!this.validateVersion(newVersion)) {
      console.error(`ERROR: Invalid version format: ${newVersion}`);
      process.exit(1);
    }

    // Write to GitHub Actions output file if available
    if (process.env.GITHUB_OUTPUT) {
      try {
        fs.appendFileSync(process.env.GITHUB_OUTPUT, `NEW_VERSION=${newVersion}\n`);
        console.error('Successfully wrote NEW_VERSION to GITHUB_OUTPUT file');
      } catch (error) {
        console.error(`Failed to write to GITHUB_OUTPUT file: ${error.message}`);
        // Fallback to stdout for backward compatibility
        console.log(`NEW_VERSION=${newVersion}`);
      }
    } else {
      // Fallback to stdout when GITHUB_OUTPUT is not available
      console.error('GITHUB_OUTPUT not available, using stdout');
      console.log(`NEW_VERSION=${newVersion}`);
    }

    console.error('SUCCESS: Version analysis completed successfully');
  }
}

// Execute if run directly
if (require.main === module) {
  const analyzer = new VersionAnalyzer();
  analyzer.run();
}

module.exports = VersionAnalyzer;
