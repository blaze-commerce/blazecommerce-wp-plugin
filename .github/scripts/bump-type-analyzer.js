#!/usr/bin/env node

/**
 * Bump Type Analyzer
 * Analyzes commits to determine version bump type (major, minor, patch)
 * Supports conventional commits and handles version mismatches
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { execSync } = require('child_process');
const { Logger } = require('./file-change-analyzer');

/**
 * Bump Type Analyzer Class
 */
class BumpTypeAnalyzer {
  constructor() {
    this.limitedCommitLimit = parseInt(process.env.LIMITED_COMMIT_LIMIT) || 25;
    this.fallbackCommitLimit = parseInt(process.env.FALLBACK_COMMIT_LIMIT) || 50;
  }

  /**
   * Get commits for analysis based on version mismatch status
   * @param {boolean} hasMismatch - Whether there's a version mismatch
   * @param {string} lastTag - Last git tag
   * @returns {Array<string>} Array of commit messages
   */
  getCommitsForAnalysis(hasMismatch, lastTag) {
    try {
      let commits;
      
      if (hasMismatch || lastTag === 'none') {
        Logger.info(`Using limited commit analysis (${this.limitedCommitLimit} commits) due to version mismatch`);
        
        try {
          const output = execSync(`git log --oneline -n ${this.limitedCommitLimit} --pretty=format:"%s"`, { encoding: 'utf8' });
          commits = output.trim().split('\n').filter(msg => msg.trim());
        } catch (error) {
          Logger.warning('Limited analysis failed, using fallback');
          const output = execSync(`git log --oneline -n ${this.fallbackCommitLimit} --pretty=format:"%s"`, { encoding: 'utf8' });
          commits = output.trim().split('\n').filter(msg => msg.trim());
        }
      } else {
        Logger.info(`Analyzing commits since last tag: ${lastTag}`);
        
        try {
          const output = execSync(`git log ${lastTag}..HEAD --oneline --pretty=format:"%s"`, { encoding: 'utf8' });
          commits = output.trim().split('\n').filter(msg => msg.trim());
        } catch (error) {
          Logger.warning('Tag-based analysis failed, falling back to limited analysis');
          const output = execSync(`git log --oneline -n ${this.limitedCommitLimit} --pretty=format:"%s"`, { encoding: 'utf8' });
          commits = output.trim().split('\n').filter(msg => msg.trim());
        }
      }

      Logger.info(`Found ${commits.length} commits to analyze`);
      return commits;
    } catch (error) {
      Logger.error(`Failed to get commits: ${error.message}`);
      throw error;
    }
  }

  /**
   * Parse conventional commit message
   * @param {string} message - Commit message
   * @returns {Object} Parsed commit info
   */
  parseConventionalCommit(message) {
    // Enhanced conventional commit pattern
    const patterns = [
      /^(\w+)(\([^)]+\))?\s*!:\s*(.+)$/,  // Breaking change with !
      /^(\w+)(\([^)]+\))?\s*:\s*(.+)$/,   // Standard conventional commit
      /^(BREAKING CHANGE|breaking change):\s*(.+)$/i,  // Explicit breaking change
      /^(feat|feature)[\s:]+(.+)$/i,      // Feature variations
      /^(fix|bugfix)[\s:]+(.+)$/i,        // Fix variations
      /^(docs?)[\s:]+(.+)$/i,             // Documentation
      /^(chore)[\s:]+(.+)$/i              // Chore
    ];

    for (const pattern of patterns) {
      const match = message.match(pattern);
      if (match) {
        const isBreaking = message.includes('!:') || 
                          message.toLowerCase().includes('breaking change') ||
                          message.toLowerCase().includes('breaking:');
        
        return {
          type: match[1].toLowerCase(),
          scope: match[2] ? match[2].slice(1, -1) : null,
          description: match[3] || match[2] || '',
          isBreaking,
          isConventional: true
        };
      }
    }

    // Fallback: analyze message content for keywords
    const lowerMessage = message.toLowerCase();
    
    if (lowerMessage.includes('breaking') || lowerMessage.includes('major')) {
      return { type: 'breaking', isBreaking: true, isConventional: false, description: message };
    }
    
    if (lowerMessage.includes('feat') || lowerMessage.includes('feature') || lowerMessage.includes('add')) {
      return { type: 'feat', isBreaking: false, isConventional: false, description: message };
    }
    
    if (lowerMessage.includes('fix') || lowerMessage.includes('bug')) {
      return { type: 'fix', isBreaking: false, isConventional: false, description: message };
    }

    return { type: 'other', isBreaking: false, isConventional: false, description: message };
  }

  /**
   * Analyze commits and determine bump type
   * @param {boolean} hasMismatch - Whether there's a version mismatch
   * @param {string} lastTag - Last git tag
   * @returns {Object} Analysis result with bump type and details
   */
  analyze(hasMismatch = false, lastTag = 'none') {
    try {
      Logger.info('Analyzing commits for version bump type...');
      Logger.info('Commit Analysis Strategy:');
      Logger.info('  • Normal operation: Analyze commits since last matching git tag');
      Logger.info('  • Version mismatch: Use limited analysis to avoid historical features');
      Logger.info('  • No tags found: Analyze recent commits with fallback limits');

      const commits = this.getCommitsForAnalysis(hasMismatch, lastTag);
      
      if (commits.length === 0) {
        Logger.warning('No commits found for analysis');
        return {
          bumpType: 'patch',
          reason: 'No commits found - defaulting to patch',
          analysis: {
            totalCommits: 0,
            breakingChanges: 0,
            features: 0,
            fixes: 0,
            other: 0
          }
        };
      }

      const analysis = {
        totalCommits: commits.length,
        breakingChanges: 0,
        features: 0,
        fixes: 0,
        other: 0,
        conventionalCommits: 0
      };

      const parsedCommits = commits.map(message => {
        const parsed = this.parseConventionalCommit(message);
        
        if (parsed.isConventional) analysis.conventionalCommits++;
        if (parsed.isBreaking) analysis.breakingChanges++;
        else if (parsed.type === 'feat' || parsed.type === 'feature') analysis.features++;
        else if (parsed.type === 'fix' || parsed.type === 'bugfix') analysis.fixes++;
        else analysis.other++;

        return { message, parsed };
      });

      // Determine bump type based on analysis
      let bumpType, reason;
      
      if (analysis.breakingChanges > 0) {
        bumpType = 'major';
        reason = `Found ${analysis.breakingChanges} breaking change(s)`;
      } else if (analysis.features > 0) {
        bumpType = 'minor';
        reason = `Found ${analysis.features} feature(s)`;
      } else {
        bumpType = 'patch';
        reason = analysis.fixes > 0 ? 
          `Found ${analysis.fixes} fix(es)` : 
          'No features or breaking changes - defaulting to patch';
      }

      Logger.info(`Bump type determined: ${bumpType.toUpperCase()}`);
      Logger.info(`Reasoning: ${reason}`);
      Logger.info(`Analysis summary:`);
      Logger.info(`  • Total commits: ${analysis.totalCommits}`);
      Logger.info(`  • Conventional commits: ${analysis.conventionalCommits}`);
      Logger.info(`  • Breaking changes: ${analysis.breakingChanges}`);
      Logger.info(`  • Features: ${analysis.features}`);
      Logger.info(`  • Fixes: ${analysis.fixes}`);
      Logger.info(`  • Other: ${analysis.other}`);

      return {
        bumpType,
        reason,
        analysis,
        commits: parsedCommits,
        strategy: hasMismatch ? 'limited' : 'tag-based'
      };

    } catch (error) {
      Logger.error(`Commit analysis failed: ${error.message}`);
      throw error;
    }
  }

  /**
   * Output results in GitHub Actions format
   * @param {Object} result - Analysis result
   */
  outputForGitHubActions(result) {
    console.log(`bump_type=${result.bumpType}`);
    console.log(`bump_reason=${result.reason}`);
    console.log(`total_commits=${result.analysis.totalCommits}`);
    console.log(`breaking_changes=${result.analysis.breakingChanges}`);
    console.log(`features=${result.analysis.features}`);
    console.log(`fixes=${result.analysis.fixes}`);
    console.log(`analysis_strategy=${result.strategy}`);
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  try {
    const analyzer = new BumpTypeAnalyzer();
    
    // Get parameters from command line or environment
    const hasMismatch = process.argv[2] === 'true' || process.env.VERSION_MISMATCH === 'true';
    const lastTag = process.argv[3] || process.env.LAST_TAG || 'none';
    
    const result = analyzer.analyze(hasMismatch, lastTag);
    analyzer.outputForGitHubActions(result);
    
    process.exit(0);
  } catch (error) {
    Logger.error(`Script execution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = { BumpTypeAnalyzer };
