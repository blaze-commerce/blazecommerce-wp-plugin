#!/usr/bin/env node

/**
 * Branch Analyzer
 * Determines prerelease type based on branch name
 * Supports feature, develop, release, and main branches
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { Logger } = require('./file-change-analyzer');

/**
 * Branch Analyzer Class
 */
class BranchAnalyzer {
  constructor() {
    this.branchName = this.getCurrentBranch();
  }

  /**
   * Get current branch name from environment or git
   * @returns {string} Current branch name
   */
  getCurrentBranch() {
    // Try GitHub environment first
    if (process.env.GITHUB_REF) {
      return process.env.GITHUB_REF.replace('refs/heads/', '');
    }

    // Try command line argument
    if (process.argv[2]) {
      return process.argv[2];
    }

    // Fallback to git command
    try {
      const { execSync } = require('child_process');
      return execSync('git rev-parse --abbrev-ref HEAD', { encoding: 'utf8' }).trim();
    } catch (error) {
      Logger.warning(`Could not determine branch name: ${error.message}`);
      return 'unknown';
    }
  }

  /**
   * Determine prerelease type based on branch name
   * @returns {Object} Analysis result with prerelease type and reasoning
   */
  analyze() {
    Logger.info(`Determining prerelease type based on branch: ${this.branchName}`);

    let prereleaseType = '';
    let reasoning = '';
    let isStableRelease = false;

    // Branch-based prerelease strategy
    if (this.branchName.startsWith('feature/')) {
      prereleaseType = 'alpha';
      reasoning = 'Feature branch detected → alpha prerelease';
      Logger.info('🔬 Feature branch detected → alpha prerelease');
    } else if (this.branchName === 'develop') {
      prereleaseType = 'beta';
      reasoning = 'Develop branch detected → beta prerelease';
      Logger.info('🧪 Develop branch detected → beta prerelease');
    } else if (this.branchName.startsWith('release/')) {
      prereleaseType = 'rc';
      reasoning = 'Release branch detected → release candidate';
      Logger.info('🚀 Release branch detected → release candidate');
    } else if (this.branchName === 'main' || this.branchName === 'master') {
      prereleaseType = '';
      reasoning = 'Main branch detected → stable release';
      isStableRelease = true;
      Logger.info('📦 Main branch detected → stable release');
    } else {
      prereleaseType = '';
      reasoning = 'Other branch detected → stable release';
      isStableRelease = true;
      Logger.info('🔧 Other branch detected → stable release');
    }

    return {
      branchName: this.branchName,
      prereleaseType,
      reasoning,
      isStableRelease,
      branchType: this.getBranchType()
    };
  }

  /**
   * Get branch type category
   * @returns {string} Branch type category
   */
  getBranchType() {
    if (this.branchName.startsWith('feature/')) return 'feature';
    if (this.branchName === 'develop') return 'develop';
    if (this.branchName.startsWith('release/')) return 'release';
    if (this.branchName === 'main' || this.branchName === 'master') return 'main';
    if (this.branchName.startsWith('hotfix/')) return 'hotfix';
    if (this.branchName.startsWith('bugfix/')) return 'bugfix';
    return 'other';
  }

  /**
   * Output results in GitHub Actions format
   * @param {Object} result - Analysis result
   */
  outputForGitHubActions(result) {
    console.log(`prerelease_type=${result.prereleaseType}`);
    console.log(`branch_name=${result.branchName}`);
    console.log(`branch_type=${result.branchType}`);
    console.log(`is_stable_release=${result.isStableRelease}`);
    console.log(`reasoning=${result.reasoning}`);
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  try {
    const analyzer = new BranchAnalyzer();
    const result = analyzer.analyze();
    analyzer.outputForGitHubActions(result);
    
    process.exit(0);
  } catch (error) {
    Logger.error(`Script execution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = { BranchAnalyzer };
