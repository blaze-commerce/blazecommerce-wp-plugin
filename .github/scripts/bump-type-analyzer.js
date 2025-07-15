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

// Import intelligent commit scanning functions
const {
  analyzeCommitHistoryWithGapDetection,
  detectVersionGaps,
  getVersionHistory
} = require('../../scripts/semver-utils');

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

      // PRIORITY FIX: Always prioritize the most recent commit for triggering events
      // Check if this is a merge commit and get the actual triggering commit
      let triggeringCommit = null;
      try {
        const headCommit = execSync('git log -1 --pretty=format:"%s"', { encoding: 'utf8' }).trim();
        Logger.info(`Head commit: ${headCommit}`);

        // If it's a merge commit, get the second-to-last commit (the actual feature/fix commit)
        if (headCommit.startsWith('Merge pull request') || headCommit.startsWith('Merge branch')) {
          const secondCommit = execSync('git log -2 --skip=1 --pretty=format:"%s"', { encoding: 'utf8' }).trim();
          if (secondCommit) {
            triggeringCommit = secondCommit;
            Logger.info(`Detected merge commit, using triggering commit: ${triggeringCommit}`);
          }
        } else {
          triggeringCommit = headCommit;
        }
      } catch (error) {
        Logger.warning(`Could not determine triggering commit: ${error.message}`);
      }

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

      // PRIORITY FIX: If we have a triggering commit, prioritize it for bump type determination
      if (triggeringCommit && !commits.includes(triggeringCommit)) {
        Logger.info('Adding triggering commit to analysis');
        commits.unshift(triggeringCommit);
      }

      Logger.info(`Found ${commits.length} commits to analyze`);
      return { commits, triggeringCommit };
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
      Logger.info('  - PRIORITY: Triggering commit takes precedence for bump type');
      Logger.info('  - Normal operation: Analyze commits since last matching git tag');
      Logger.info('  - Version mismatch: Use limited analysis to avoid historical features');
      Logger.info('  - No tags found: Analyze recent commits with fallback limits');

      const { commits, triggeringCommit } = this.getCommitsForAnalysis(hasMismatch, lastTag);

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
        conventionalCommits: 0,
        triggeringCommitType: null
      };

      // PRIORITY FIX: Analyze triggering commit first to determine primary bump type
      let triggeringCommitAnalysis = null;
      if (triggeringCommit) {
        triggeringCommitAnalysis = this.parseConventionalCommit(triggeringCommit);
        analysis.triggeringCommitType = triggeringCommitAnalysis.type;
        Logger.info(`Triggering commit analysis: ${triggeringCommit} -> ${triggeringCommitAnalysis.type || 'unknown'}`);
      }

      const parsedCommits = commits.map(message => {
        const parsed = this.parseConventionalCommit(message);

        if (parsed.isConventional) analysis.conventionalCommits++;
        if (parsed.isBreaking) analysis.breakingChanges++;
        else if (parsed.type === 'feat' || parsed.type === 'feature') analysis.features++;
        else if (parsed.type === 'fix' || parsed.type === 'bugfix') analysis.fixes++;
        else analysis.other++;

        return { message, parsed };
      });

      // PRIORITY FIX: Determine bump type with triggering commit priority
      let bumpType, reason;

      // If we have a triggering commit, prioritize its type unless there are breaking changes
      if (triggeringCommitAnalysis && analysis.breakingChanges === 0) {
        if (triggeringCommitAnalysis.isBreaking) {
          bumpType = 'major';
          reason = 'Triggering commit contains breaking changes';
        } else if (triggeringCommitAnalysis.type === 'feat' || triggeringCommitAnalysis.type === 'feature') {
          bumpType = 'minor';
          reason = 'Triggering commit is a feature';
        } else if (triggeringCommitAnalysis.type === 'fix' || triggeringCommitAnalysis.type === 'bugfix') {
          bumpType = 'patch';
          reason = 'Triggering commit is a fix';
        } else {
          // Fall back to historical analysis
          bumpType = this.determineBumpTypeFromHistory(analysis);
          reason = `Triggering commit type unclear, using historical analysis: ${bumpType}`;
        }
      } else {
        // Use historical analysis (original logic)
        bumpType = this.determineBumpTypeFromHistory(analysis);
        reason = this.getBumpReasonFromHistory(analysis, bumpType);
      }

      Logger.info(`Bump type determined: ${bumpType.toUpperCase()}`);
      Logger.info(`Reasoning: ${reason}`);
      Logger.info(`Analysis summary:`);
      Logger.info(`  - Total commits: ${analysis.totalCommits}`);
      Logger.info(`  - Conventional commits: ${analysis.conventionalCommits}`);
      Logger.info(`  - Breaking changes: ${analysis.breakingChanges}`);
      Logger.info(`  - Features: ${analysis.features}`);
      Logger.info(`  - Fixes: ${analysis.fixes}`);
      Logger.info(`  - Other: ${analysis.other}`);

      return {
        bumpType,
        reason,
        analysis,
        commits: parsedCommits,
        strategy: hasMismatch ? 'limited' : 'tag-based',
        triggeringCommit: triggeringCommit || null
      };

    } catch (error) {
      Logger.error(`Commit analysis failed: ${error.message}`);
      throw error;
    }
  }

  /**
   * Determine bump type from historical analysis (original logic)
   * @param {Object} analysis - Analysis object with commit counts
   * @returns {string} Bump type
   */
  determineBumpTypeFromHistory(analysis) {
    if (analysis.breakingChanges > 0) return 'major';
    if (analysis.features > 0) return 'minor';
    return 'patch';
  }

  /**
   * Get bump reason from historical analysis
   * @param {Object} analysis - Analysis object with commit counts
   * @param {string} bumpType - Determined bump type
   * @returns {string} Reason string
   */
  getBumpReasonFromHistory(analysis, bumpType) {
    if (bumpType === 'major') return `Found ${analysis.breakingChanges} breaking change(s)`;
    if (bumpType === 'minor') return `Found ${analysis.features} feature(s)`;
    return analysis.fixes > 0 ?
      `Found ${analysis.fixes} fix(es)` :
      'No features or breaking changes - defaulting to patch';
  }

  /**
   * Perform intelligent commit analysis with gap detection
   * @param {boolean} hasMismatch - Whether there's a version mismatch
   * @param {string} lastTag - Last git tag
   * @param {object} options - Analysis options
   * @returns {Object} Enhanced analysis result with gap detection
   */
  analyzeIntelligent(hasMismatch = false, lastTag = 'none', options = {}) {
    try {
      Logger.info('🔍 Starting intelligent commit analysis with gap detection...');

      const {
        enableGapDetection = true,
        enableCumulativeAnalysis = true,
        verbose = true
      } = options;

      // Perform intelligent analysis
      const intelligentResult = analyzeCommitHistoryWithGapDetection({
        verbose,
        includeGapDetection: enableGapDetection,
        maxCommitsToAnalyze: hasMismatch ? this.limitedCommitLimit : 1000,
        maxTagsToAnalyze: 10,
        enableCumulativeAnalysis
      });

      // Get traditional analysis for comparison
      const traditionalResult = this.analyze(hasMismatch, lastTag);

      // Combine results with intelligent enhancements
      const enhancedResult = {
        // Core analysis
        bumpType: intelligentResult.finalBumpType,
        reason: this.buildEnhancedReason(intelligentResult, traditionalResult),
        analysis: {
          ...traditionalResult.analysis,
          intelligent: true,
          confidence: intelligentResult.confidence,
          gapDetection: intelligentResult.gapDetection,
          cumulativeAnalysis: intelligentResult.cumulativeAnalysis
        },

        // Enhanced information
        intelligentAnalysis: intelligentResult,
        traditionalAnalysis: traditionalResult,
        recommendations: intelligentResult.recommendations,
        strategy: hasMismatch ? 'limited-intelligent' : 'full-intelligent',

        // Compatibility
        commits: traditionalResult.commits,
        triggeringCommit: traditionalResult.triggeringCommit
      };

      // Log intelligent analysis results
      Logger.info(`✅ Intelligent analysis complete:`);
      Logger.info(`   Final bump type: ${enhancedResult.bumpType}`);
      Logger.info(`   Confidence: ${intelligentResult.confidence}`);
      Logger.info(`   Gap detection: ${intelligentResult.gapDetection ?
        (intelligentResult.gapDetection.hasGaps ?
          `${intelligentResult.gapDetection.gapCount} gaps found` : 'No gaps') : 'Disabled'}`);
      Logger.info(`   Recommendations: ${intelligentResult.recommendations.length}`);

      if (intelligentResult.recommendations.length > 0) {
        Logger.info('💡 Recommendations:');
        intelligentResult.recommendations.forEach((rec, i) => {
          Logger.info(`   ${i + 1}. ${rec}`);
        });
      }

      return enhancedResult;

    } catch (error) {
      Logger.error(`❌ Intelligent analysis failed: ${error.message}`);
      Logger.info('🔄 Falling back to traditional analysis...');

      // Fallback to traditional analysis
      const fallbackResult = this.analyze(hasMismatch, lastTag);
      fallbackResult.analysis.intelligent = false;
      fallbackResult.analysis.fallback = true;
      fallbackResult.analysis.error = error.message;

      return fallbackResult;
    }
  }

  /**
   * Build enhanced reason combining intelligent and traditional analysis
   * @param {Object} intelligentResult - Intelligent analysis result
   * @param {Object} traditionalResult - Traditional analysis result
   * @returns {string} Enhanced reason string
   */
  buildEnhancedReason(intelligentResult, traditionalResult) {
    const reasons = [];

    // Primary reason from intelligent analysis
    if (intelligentResult.currentAnalysis.reasoning.length > 0) {
      reasons.push(intelligentResult.currentAnalysis.reasoning[0]);
    }

    // Add gap detection insights
    if (intelligentResult.gapDetection && intelligentResult.gapDetection.hasGaps) {
      reasons.push(`Gap detection: ${intelligentResult.gapDetection.gapCount} version gaps found`);
    }

    // Add cumulative analysis insights
    if (intelligentResult.cumulativeAnalysis &&
        intelligentResult.cumulativeAnalysis.cumulativeBumpType !== intelligentResult.currentAnalysis.bumpType) {
      reasons.push(`Cumulative analysis suggests ${intelligentResult.cumulativeAnalysis.cumulativeBumpType} bump`);
    }

    // Add confidence indicator
    reasons.push(`Confidence: ${intelligentResult.confidence}`);

    return reasons.join(' | ');
  }

  /**
   * Output results in GitHub Actions format
   * @param {Object} result - Analysis result
   */
  outputForGitHubActions(result) {
    const fs = require('fs');

    // Prepare output data
    const outputs = [
      `bump_type=${result.bumpType}`,
      `bump_reason=${result.reason}`,
      `total_commits=${result.analysis.totalCommits}`,
      `breaking_changes=${result.analysis.breakingChanges}`,
      `features=${result.analysis.features}`,
      `fixes=${result.analysis.fixes}`,
      `analysis_strategy=${result.strategy}`
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
    const analyzer = new BumpTypeAnalyzer();

    // Get parameters from command line or environment
    const hasMismatch = process.argv[2] === 'true' || process.env.VERSION_MISMATCH === 'true';
    const lastTag = process.argv[3] || process.env.LAST_TAG || 'none';
    const useIntelligentAnalysis = process.argv[4] === 'true' ||
                                   process.env.USE_INTELLIGENT_ANALYSIS === 'true' ||
                                   process.env.ENABLE_GAP_DETECTION === 'true';

    Logger.info(`🔧 Analysis Configuration:`);
    Logger.info(`   Version mismatch: ${hasMismatch}`);
    Logger.info(`   Last tag: ${lastTag}`);
    Logger.info(`   Intelligent analysis: ${useIntelligentAnalysis}`);

    let result;

    if (useIntelligentAnalysis) {
      Logger.info('🔍 Using intelligent commit analysis with gap detection...');
      result = analyzer.analyzeIntelligent(hasMismatch, lastTag, {
        enableGapDetection: true,
        enableCumulativeAnalysis: true,
        verbose: true
      });
    } else {
      Logger.info('📊 Using traditional commit analysis...');
      result = analyzer.analyze(hasMismatch, lastTag);
    }

    analyzer.outputForGitHubActions(result);

    process.exit(0);
  } catch (error) {
    Logger.error(`Script execution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = { BumpTypeAnalyzer };
