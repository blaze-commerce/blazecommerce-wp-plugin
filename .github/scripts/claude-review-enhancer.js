#!/usr/bin/env node

/**
 * Claude Review Enhancer
 * Enhances Claude AI review comments with progressive tracking and state management
 * Tracks recommendations across PR updates, detects resolved issues, and prevents duplicates
 *
 * Features:
 * - Progressive review tracking across PR updates
 * - GitHub API integration for comment history analysis
 * - Automatic detection of resolved recommendations
 * - Version tracking and timestamp management
 * - Duplicate recommendation prevention
 * - Progress reporting and status summaries
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 2.0.0
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { Logger } = require('./file-change-analyzer');

// Polyfill for fetch in Node.js environments that don't have it
if (typeof fetch === 'undefined') {
  try {
    global.fetch = require('node-fetch');
  } catch (error) {
    // Try to load fallback implementation
    try {
      require('./fetch-fallback.js');
    } catch (fallbackError) {
      console.warn('WARNING: No fetch implementation available - GitHub API features will be limited');
    }
  }
}

/**
 * Claude Review Enhancer Class
 * Enhanced with progressive tracking and GitHub API integration
 */
class ClaudeReviewEnhancer {
  constructor() {
    this.prNumber = process.env.PR_NUMBER || process.argv[2];
    this.githubToken = process.env.GITHUB_TOKEN;
    this.repoOwner = process.env.GITHUB_REPOSITORY?.split('/')[0];
    this.repoName = process.env.GITHUB_REPOSITORY?.split('/')[1];
    this.currentSha = process.env.GITHUB_SHA;
    this.trackingDir = '.github/claude-tracking';
    this.trackingFile = path.join(this.trackingDir, `pr-${this.prNumber}-recommendations.json`);
    this.reviewVersion = 1;
    this.claudeBotUsers = ['claude[bot]', 'blazecommerce-claude-ai', 'github-actions[bot]'];
  }

  /**
   * Make GitHub API request
   * @param {string} endpoint - API endpoint
   * @param {Object} options - Request options
   * @returns {Promise<Object>} API response
   */
  async makeGitHubRequest(endpoint, options = {}) {
    if (!this.githubToken) {
      throw new Error('GitHub token not available');
    }

    const url = `https://api.github.com${endpoint}`;
    const response = await fetch(url, {
      method: options.method || 'GET',
      headers: {
        'Authorization': `token ${this.githubToken}`,
        'Accept': 'application/vnd.github.v3+json',
        'User-Agent': 'BlazeCommerce-Claude-Review-Enhancer',
        ...options.headers
      },
      body: options.body ? JSON.stringify(options.body) : undefined
    });

    if (!response.ok) {
      throw new Error(`GitHub API error: ${response.status} ${response.statusText}`);
    }

    return await response.json();
  }

  /**
   * Retrieve previous Claude AI comments from the PR
   * @returns {Promise<Array>} Array of previous Claude comments
   */
  async getPreviousClaudeComments() {
    try {
      Logger.info(`Retrieving previous Claude comments for PR #${this.prNumber}`);

      const comments = await this.makeGitHubRequest(
        `/repos/${this.repoOwner}/${this.repoName}/issues/${this.prNumber}/comments`
      );

      const claudeComments = comments.filter(comment =>
        this.claudeBotUsers.includes(comment.user.login) &&
        comment.body.includes('BlazeCommerce Claude AI Review')
      );

      Logger.info(`Found ${claudeComments.length} previous Claude comments`);
      return claudeComments.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
    } catch (error) {
      Logger.warning(`Failed to retrieve previous comments: ${error.message}`);
      return [];
    }
  }

  /**
   * Load tracking data for the PR (enhanced with GitHub API integration)
   * @returns {Object} Tracking data or null if not found
   */
  async loadTrackingData() {
    try {
      // Try to load from file first
      let fileData = null;
      if (fs.existsSync(this.trackingFile)) {
        fileData = JSON.parse(fs.readFileSync(this.trackingFile, 'utf8'));
        Logger.info(`Loaded tracking data from file for PR #${this.prNumber}`);
      }

      // Get previous comments from GitHub API
      const previousComments = await this.getPreviousClaudeComments();

      // If we have previous comments but no file data, reconstruct from comments
      if (previousComments.length > 0 && !fileData) {
        Logger.info('Reconstructing tracking data from previous comments');
        fileData = this.reconstructTrackingFromComments(previousComments);
      }

      // Update version number based on previous comments
      if (previousComments.length > 0) {
        this.reviewVersion = previousComments.length + 1;
      }

      return fileData;
    } catch (error) {
      Logger.error(`Failed to load tracking data: ${error.message}`);
      return null;
    }
  }

  /**
   * Reconstruct tracking data from previous comments
   * @param {Array} comments - Previous Claude comments
   * @returns {Object} Reconstructed tracking data
   */
  reconstructTrackingFromComments(comments) {
    const latestComment = comments[comments.length - 1];
    const trackingData = {
      pr_number: parseInt(this.prNumber),
      created_at: comments[0].created_at,
      updated_at: latestComment.created_at,
      repo_type: process.env.REPO_TYPE || 'general',
      review_history: [],
      total_recommendations: { required: 0, important: 0, suggestions: 0 },
      resolved_recommendations: { required: [], important: [], required_timestamps: {}, important_timestamps: {} },
      recommendations: { required: [], important: [], suggestions: [] }
    };

    // Parse each comment to build history
    comments.forEach((comment, index) => {
      const recommendations = this.parseClaudeReview(comment.body);
      trackingData.review_history.push({
        version: index + 1,
        timestamp: comment.created_at,
        comment_id: comment.id,
        recommendations: recommendations
      });
    });

    // Use latest recommendations as current
    if (trackingData.review_history.length > 0) {
      const latest = trackingData.review_history[trackingData.review_history.length - 1];
      trackingData.recommendations = latest.recommendations;
      trackingData.total_recommendations = {
        required: latest.recommendations.required.length,
        important: latest.recommendations.important.length,
        suggestions: latest.recommendations.suggestions.length
      };
    }

    Logger.info(`Reconstructed tracking data from ${comments.length} previous comments`);
    return trackingData;
  }

  /**
   * Generate unique hash for a recommendation to track it across reviews
   * @param {string} recommendation - Recommendation text
   * @returns {string} Unique hash
   */
  generateRecommendationHash(recommendation) {
    // Clean the recommendation text for consistent hashing
    const cleanText = recommendation
      .replace(/^\d+\.\s*/, '') // Remove numbering
      .replace(/SUCCESS:.*?\n/g, '') // Remove status indicators
      .replace(/WARNING:.*?\n/g, '')
      .replace(/PENDING:.*?\n/g, '')
      .replace(/\*Applied:.*?\*/g, '') // Remove timestamps
      .trim();

    return crypto.createHash('md5').update(cleanText).digest('hex').substring(0, 8);
  }

  /**
   * Compare current recommendations with previous ones to detect resolved issues
   * @param {Object} currentRecommendations - Current recommendations
   * @param {Object} trackingData - Previous tracking data
   * @returns {Object} Analysis of resolved and new recommendations
   */
  analyzeRecommendationChanges(currentRecommendations, trackingData) {
    const analysis = {
      resolved: { required: [], important: [] },
      new: { required: [], important: [] },
      persistent: { required: [], important: [] },
      resolvedHashes: { required: [], important: [] }
    };

    if (!trackingData || !trackingData.recommendations) {
      // First review - all recommendations are new
      analysis.new.required = currentRecommendations.required.map((rec, index) => ({ index, text: rec, hash: this.generateRecommendationHash(rec) }));
      analysis.new.important = currentRecommendations.important.map((rec, index) => ({ index, text: rec, hash: this.generateRecommendationHash(rec) }));
      return analysis;
    }

    // Create hash maps for comparison
    const previousHashes = {
      required: trackingData.recommendations.required.map(rec => this.generateRecommendationHash(rec)),
      important: trackingData.recommendations.important.map(rec => this.generateRecommendationHash(rec))
    };

    const currentHashes = {
      required: currentRecommendations.required.map(rec => this.generateRecommendationHash(rec)),
      important: currentRecommendations.important.map(rec => this.generateRecommendationHash(rec))
    };

    // Analyze each category
    ['required', 'important'].forEach(category => {
      // Find resolved recommendations (in previous but not in current)
      previousHashes[category].forEach((hash, index) => {
        if (!currentHashes[category].includes(hash)) {
          analysis.resolved[category].push({
            index,
            text: trackingData.recommendations[category][index],
            hash,
            resolvedAt: new Date().toISOString()
          });
          analysis.resolvedHashes[category].push(hash);
        }
      });

      // Find new and persistent recommendations
      currentRecommendations[category].forEach((rec, index) => {
        const hash = this.generateRecommendationHash(rec);
        if (previousHashes[category].includes(hash)) {
          analysis.persistent[category].push({ index, text: rec, hash });
        } else {
          analysis.new[category].push({ index, text: rec, hash });
        }
      });
    });

    Logger.info(`Analysis: ${analysis.resolved.required.length + analysis.resolved.important.length} resolved, ${analysis.new.required.length + analysis.new.important.length} new recommendations`);
    return analysis;
  }

  /**
   * Parse Claude review output to extract recommendations (enhanced)
   * @param {string} claudeOutput - Raw Claude review output
   * @returns {Object} Parsed recommendations
   */
  parseClaudeReview(claudeOutput) {
    const recommendations = {
      required: [],
      important: [],
      suggestions: []
    };

    if (!claudeOutput) {
      Logger.warning('No Claude output provided');
      return recommendations;
    }

    const lines = claudeOutput.split('\n');
    let currentSection = null;
    let currentItem = '';

    for (const line of lines) {
      const trimmedLine = line.trim();
      
      if (trimmedLine.includes('CRITICAL: REQUIRED')) {
        if (currentItem && currentSection) {
          recommendations[currentSection].push(currentItem.trim());
        }
        currentSection = 'required';
        currentItem = trimmedLine;
      } else if (trimmedLine.includes('WARNING: IMPORTANT')) {
        if (currentItem && currentSection) {
          recommendations[currentSection].push(currentItem.trim());
        }
        currentSection = 'important';
        currentItem = trimmedLine;
      } else if (trimmedLine.includes('INFO: SUGGESTIONS')) {
        if (currentItem && currentSection) {
          recommendations[currentSection].push(currentItem.trim());
        }
        currentSection = 'suggestions';
        currentItem = trimmedLine;
      } else if (currentSection && trimmedLine) {
        // Continue building the current item
        currentItem += '\n' + trimmedLine;
      } else if (currentSection && !trimmedLine) {
        // Empty line indicates end of current item
        if (currentItem) {
          recommendations[currentSection].push(currentItem.trim());
          currentItem = '';
        }
      }
    }

    // Add the last item if exists
    if (currentItem && currentSection) {
      recommendations[currentSection].push(currentItem.trim());
    }

    Logger.info(`Parsed ${recommendations.required.length} required, ${recommendations.important.length} important, ${recommendations.suggestions.length} suggestions`);
    return recommendations;
  }

  /**
   * Generate enhanced review comment with progressive tracking
   * @param {string} claudeOutput - Raw Claude review output
   * @param {Object} trackingData - Existing tracking data
   * @param {Object} analysis - Recommendation change analysis
   * @returns {string} Enhanced review comment
   */
  generateEnhancedComment(claudeOutput, trackingData, analysis) {
    const recommendations = this.parseClaudeReview(claudeOutput);
    const timestamp = new Date().toISOString();
    const reviewDate = new Date().toLocaleString();

    let comment = `## BOT: BlazeCommerce Claude AI Review v${this.reviewVersion}\n\n`;
    comment += `**Review Timestamp**: ${reviewDate}\n`;
    comment += `**Repository Type**: ${trackingData?.repo_type || 'general'}\n`;
    comment += `**Commit SHA**: \`${this.currentSha?.substring(0, 7) || 'unknown'}\`\n`;
    comment += `**Review Version**: ${this.reviewVersion}/${(trackingData?.review_history?.length || 0) + 1}\n\n`;

    // Add progress summary if this is not the first review
    if (analysis && (analysis.resolved.required.length > 0 || analysis.resolved.important.length > 0)) {
      const totalResolved = analysis.resolved.required.length + analysis.resolved.important.length;
      const totalNew = analysis.new.required.length + analysis.new.important.length;
      const totalPersistent = analysis.persistent.required.length + analysis.persistent.important.length;

      comment += `### TARGET: Progress Summary\n\n`;
      comment += `| Status | Count | Description |\n`;
      comment += `|--------|-------|-------------|\n`;
      comment += `| SUCCESS: **Resolved** | ${totalResolved} | Issues addressed since last review |\n`;
      comment += `| NEW: **New** | ${totalNew} | New issues identified in this review |\n`;
      comment += `| PENDING: **Persistent** | ${totalPersistent} | Issues still requiring attention |\n\n`;

      if (totalResolved > 0) {
        comment += `COMPLETED: **Great progress!** ${totalResolved} recommendation(s) have been successfully addressed.\n\n`;
      }
    }

    // Add implementation status summary
    if (trackingData && trackingData.review_history && trackingData.review_history.length > 0) {
      const currentRequired = recommendations.required.length;
      const currentImportant = recommendations.important.length;
      const resolvedRequired = analysis?.resolved.required.length || 0;
      const resolvedImportant = analysis?.resolved.important.length || 0;

      comment += `### ANALYSIS: Overall Implementation Status\n\n`;
      comment += `| Category | Current | Resolved This Update | Status |\n`;
      comment += `|----------|---------|---------------------|--------|\n`;
      comment += `| CRITICAL: **REQUIRED** | ${currentRequired} | ${resolvedRequired} | ${currentRequired === 0 ? 'SUCCESS: All Clear' : 'WARNING: Needs Attention'} |\n`;
      comment += `| WARNING: **IMPORTANT** | ${currentImportant} | ${resolvedImportant} | ${currentImportant === 0 ? 'SUCCESS: All Clear' : 'PENDING: Recommended'} |\n\n`;
    }

    // Show resolved issues first (if any)
    if (analysis && (analysis.resolved.required.length > 0 || analysis.resolved.important.length > 0)) {
      comment += `### SUCCESS: Recently Resolved Issues\n\n`;

      if (analysis.resolved.required.length > 0) {
        comment += `#### CRITICAL: REQUIRED Issues Resolved:\n`;
        analysis.resolved.required.forEach((item, index) => {
          comment += `${index + 1}. SUCCESS: **RESOLVED** - ${item.text}\n`;
          comment += `   *Resolved at: ${new Date(item.resolvedAt).toLocaleString()}*\n\n`;
        });
      }

      if (analysis.resolved.important.length > 0) {
        comment += `#### WARNING: IMPORTANT Issues Resolved:\n`;
        analysis.resolved.important.forEach((item, index) => {
          comment += `${index + 1}. SUCCESS: **RESOLVED** - ${item.text}\n`;
          comment += `   *Resolved at: ${new Date(item.resolvedAt).toLocaleString()}*\n\n`;
        });
      }
    }

    // Add current REQUIRED issues
    if (recommendations.required.length > 0) {
      comment += `### CRITICAL: REQUIRED Issues (Must Fix Before Merge)\n\n`;
      recommendations.required.forEach((item, index) => {
        const isNew = analysis?.new.required.some(newItem => newItem.index === index);
        const isPersistent = analysis?.persistent.required.some(persistentItem => persistentItem.index === index);

        let status = 'WARNING: **PENDING**';
        let badge = '';

        if (isNew) {
          badge = ' NEW: **NEW**';
        } else if (isPersistent) {
          badge = ' RETRY: **PERSISTENT**';
        }

        comment += `${index + 1}. ${status}${badge}\n`;
        comment += `   ${item}\n\n`;
      });
    }

    // Add current IMPORTANT issues
    if (recommendations.important.length > 0) {
      comment += `### WARNING: IMPORTANT Improvements (Recommended)\n\n`;
      recommendations.important.forEach((item, index) => {
        const isNew = analysis?.new.important.some(newItem => newItem.index === index);
        const isPersistent = analysis?.persistent.important.some(persistentItem => persistentItem.index === index);

        let status = 'PENDING: **PENDING**';
        let badge = '';

        if (isNew) {
          badge = ' NEW: **NEW**';
        } else if (isPersistent) {
          badge = ' RETRY: **PERSISTENT**';
        }

        comment += `${index + 1}. ${status}${badge}\n`;
        comment += `   ${item}\n\n`;
      });
    }

    if (recommendations.suggestions.length > 0) {
      comment += `### INFO: SUGGESTIONS (Optional)\n\n`;
      recommendations.suggestions.forEach((item, index) => {
        comment += `${index + 1}. ${item}\n\n`;
      });
    }

    // Add footer with instructions
    comment += `---\n\n`;
    comment += `### SUMMARY: Next Steps\n\n`;
    
    if (recommendations.required.length > 0) {
      const pendingRequired = trackingData ? 
        recommendations.required.length - trackingData.resolved_recommendations.required.length :
        recommendations.required.length;
      
      if (pendingRequired > 0) {
        comment += `ERROR: **${pendingRequired} REQUIRED issue(s) must be addressed before this PR can be approved.**\n\n`;
      } else {
        comment += `SUCCESS: **All REQUIRED issues have been addressed!**\n\n`;
      }
    }

    if (recommendations.important.length > 0) {
      const pendingImportant = trackingData ? 
        recommendations.important.length - trackingData.resolved_recommendations.important.length :
        recommendations.important.length;
      
      if (pendingImportant > 0) {
        comment += `PENDING: **${pendingImportant} IMPORTANT improvement(s) are recommended but not required for approval.**\n\n`;
      }
    }

    comment += `### RETRY: Auto-Approval Status\n\n`;
    const hasBlockingIssues = recommendations.required.length > 0 && 
      (!trackingData || trackingData.resolved_recommendations.required.length < recommendations.required.length);
    
    if (hasBlockingIssues) {
      comment += ` **Auto-approval blocked** - REQUIRED issues must be resolved first.\n\n`;
    } else {
      comment += `SUCCESS: **Ready for auto-approval** - No blocking issues found.\n\n`;
    }

    comment += `*This review will automatically update when changes are pushed. The PR will be auto-approved once all REQUIRED issues are resolved.*\n\n`;
    comment += `---\n`;
    comment += `*BOT: Generated by BlazeCommerce Claude AI Review Bot v2.0*`;

    return comment;
  }

  /**
   * Update tracking data with new recommendations and analysis
   * @param {Object} recommendations - Parsed recommendations
   * @param {Object} analysis - Recommendation change analysis
   * @param {Object} existingData - Existing tracking data
   * @returns {Object} Updated tracking data
   */
  updateTrackingData(recommendations, analysis, existingData = null) {
    const timestamp = new Date().toISOString();

    const trackingData = existingData || {
      pr_number: parseInt(this.prNumber),
      created_at: timestamp,
      repo_type: process.env.REPO_TYPE || 'general',
      review_history: [],
      total_recommendations: { required: 0, important: 0, suggestions: 0 },
      resolved_recommendations: { required: [], important: [], required_timestamps: {}, important_timestamps: {} },
      recommendations: { required: [], important: [], suggestions: [] },
      recommendation_hashes: { required: [], important: [] }
    };

    // Update basic info
    trackingData.updated_at = timestamp;
    trackingData.current_sha = this.currentSha;
    trackingData.review_version = this.reviewVersion;

    // Add current review to history
    const currentReview = {
      version: this.reviewVersion,
      timestamp: timestamp,
      sha: this.currentSha,
      recommendations: recommendations,
      analysis: analysis,
      resolved_count: {
        required: analysis?.resolved.required.length || 0,
        important: analysis?.resolved.important.length || 0
      }
    };

    if (!trackingData.review_history) {
      trackingData.review_history = [];
    }
    trackingData.review_history.push(currentReview);

    // Update current recommendations
    trackingData.recommendations = recommendations;
    trackingData.total_recommendations = {
      required: recommendations.required.length,
      important: recommendations.important.length,
      suggestions: recommendations.suggestions.length
    };

    // Update recommendation hashes for tracking
    trackingData.recommendation_hashes = {
      required: recommendations.required.map(rec => this.generateRecommendationHash(rec)),
      important: recommendations.important.map(rec => this.generateRecommendationHash(rec))
    };

    // Update resolved recommendations tracking
    if (analysis) {
      // Add newly resolved recommendations to the resolved list
      analysis.resolved.required.forEach(resolved => {
        if (!trackingData.resolved_recommendations.required_timestamps[resolved.hash]) {
          trackingData.resolved_recommendations.required_timestamps[resolved.hash] = resolved.resolvedAt;
        }
      });

      analysis.resolved.important.forEach(resolved => {
        if (!trackingData.resolved_recommendations.important_timestamps[resolved.hash]) {
          trackingData.resolved_recommendations.important_timestamps[resolved.hash] = resolved.resolvedAt;
        }
      });

      // Update resolved arrays with unique hashes
      trackingData.resolved_recommendations.required = [
        ...new Set([
          ...(trackingData.resolved_recommendations.required || []),
          ...analysis.resolved.required.map(r => r.hash)
        ])
      ];

      trackingData.resolved_recommendations.important = [
        ...new Set([
          ...(trackingData.resolved_recommendations.important || []),
          ...analysis.resolved.important.map(r => r.hash)
        ])
      ];
    }

    // Calculate cumulative statistics
    trackingData.cumulative_stats = {
      total_reviews: trackingData.review_history.length,
      total_resolved: {
        required: trackingData.resolved_recommendations.required.length,
        important: trackingData.resolved_recommendations.important.length
      },
      current_pending: {
        required: recommendations.required.length,
        important: recommendations.important.length
      }
    };

    return trackingData;
  }

  /**
   * Save tracking data to file
   * @param {Object} trackingData - Data to save
   */
  saveTrackingData(trackingData) {
    try {
      // Ensure directory exists
      if (!fs.existsSync(this.trackingDir)) {
        fs.mkdirSync(this.trackingDir, { recursive: true });
      }

      fs.writeFileSync(this.trackingFile, JSON.stringify(trackingData, null, 2));
      Logger.success(`Tracking data saved to ${this.trackingFile}`);
    } catch (error) {
      Logger.error(`Failed to save tracking data: ${error.message}`);
      throw error;
    }
  }

  /**
   * Process Claude review and generate enhanced comment with progressive tracking
   * @param {string} claudeOutput - Raw Claude review output
   * @returns {Promise<Object>} Processing result
   */
  async processReview(claudeOutput) {
    try {
      Logger.info(`Processing Claude review v${this.reviewVersion} for PR #${this.prNumber}`);

      // Load existing tracking data (with GitHub API integration)
      const existingData = await this.loadTrackingData();

      // Parse current recommendations
      const recommendations = this.parseClaudeReview(claudeOutput);

      // Analyze changes compared to previous recommendations
      const analysis = this.analyzeRecommendationChanges(recommendations, existingData);

      // Update tracking data with analysis
      const trackingData = this.updateTrackingData(recommendations, analysis, existingData);

      // Generate enhanced comment with progressive tracking
      const enhancedComment = this.generateEnhancedComment(claudeOutput, trackingData, analysis);

      // Save updated tracking data
      this.saveTrackingData(trackingData);

      // Calculate blocking status (only current required issues block)
      const hasBlockingIssues = recommendations.required.length > 0;
      const progressMade = analysis.resolved.required.length > 0 || analysis.resolved.important.length > 0;

      return {
        success: true,
        enhancedComment,
        trackingData,
        recommendations,
        analysis,
        hasBlockingIssues,
        progressMade,
        reviewVersion: this.reviewVersion,
        resolvedCount: {
          required: analysis.resolved.required.length,
          important: analysis.resolved.important.length
        }
      };

    } catch (error) {
      Logger.error(`Failed to process review: ${error.message}`);
      return {
        success: false,
        error: error.message,
        enhancedComment: claudeOutput, // Fallback to original
        hasBlockingIssues: claudeOutput.includes('CRITICAL: REQUIRED'),
        progressMade: false,
        reviewVersion: this.reviewVersion
      };
    }
  }

  /**
   * Output results in GitHub Actions format (enhanced)
   * @param {Object} result - Processing result
   */
  outputForGitHubActions(result) {
    // Escape the comment for GitHub Actions output
    const escapedComment = result.enhancedComment.replace(/\n/g, '\\n').replace(/"/g, '\\"');

    console.log(`enhanced_comment=${escapedComment}`);
    console.log(`has_blocking_issues=${result.hasBlockingIssues}`);
    console.log(`processing_success=${result.success}`);
    console.log(`progress_made=${result.progressMade || false}`);
    console.log(`review_version=${result.reviewVersion || 1}`);

    if (result.recommendations) {
      console.log(`required_count=${result.recommendations.required.length}`);
      console.log(`important_count=${result.recommendations.important.length}`);
      console.log(`suggestions_count=${result.recommendations.suggestions.length}`);
    }

    if (result.resolvedCount) {
      console.log(`resolved_required=${result.resolvedCount.required}`);
      console.log(`resolved_important=${result.resolvedCount.important}`);
      console.log(`total_resolved=${result.resolvedCount.required + result.resolvedCount.important}`);
    }

    if (result.analysis) {
      const newCount = (result.analysis.new.required.length || 0) + (result.analysis.new.important.length || 0);
      const persistentCount = (result.analysis.persistent.required.length || 0) + (result.analysis.persistent.important.length || 0);
      console.log(`new_issues_count=${newCount}`);
      console.log(`persistent_issues_count=${persistentCount}`);
    }
  }
}

/**
 * Main execution (async)
 */
if (require.main === module) {
  (async () => {
    try {
      const enhancer = new ClaudeReviewEnhancer();
      const claudeOutput = process.env.CLAUDE_OUTPUT || process.argv[3] || '';

      if (!enhancer.prNumber) {
        Logger.error('PR number is required as environment variable PR_NUMBER or first argument');
        process.exit(1);
      }

      if (!enhancer.githubToken) {
        Logger.warning('GitHub token not available - progressive tracking features will be limited');
      }

      Logger.info(`Starting Claude review processing v${enhancer.reviewVersion} for PR #${enhancer.prNumber}`);

      const result = await enhancer.processReview(claudeOutput);
      enhancer.outputForGitHubActions(result);

      if (result.progressMade) {
        Logger.success(`Progress made: ${result.resolvedCount.required + result.resolvedCount.important} issues resolved`);
      }

      process.exit(result.success ? 0 : 1);
    } catch (error) {
      Logger.error(`Script execution failed: ${error.message}`);
      process.exit(1);
    }
  })();
}

module.exports = { ClaudeReviewEnhancer };
