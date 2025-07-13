#!/usr/bin/env node

/**
 * Claude Review Enhancer
 * Enhances Claude AI review comments with implementation status tracking
 * Shows which recommendations have been applied vs. pending
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');
const { Logger } = require('./file-change-analyzer');

/**
 * Claude Review Enhancer Class
 */
class ClaudeReviewEnhancer {
  constructor() {
    this.prNumber = process.env.PR_NUMBER || process.argv[2];
    this.trackingDir = '.github/claude-tracking';
    this.trackingFile = path.join(this.trackingDir, `pr-${this.prNumber}-recommendations.json`);
  }

  /**
   * Load tracking data for the PR
   * @returns {Object} Tracking data or null if not found
   */
  loadTrackingData() {
    try {
      if (!fs.existsSync(this.trackingFile)) {
        Logger.warning(`Tracking file not found: ${this.trackingFile}`);
        return null;
      }

      const data = JSON.parse(fs.readFileSync(this.trackingFile, 'utf8'));
      Logger.info(`Loaded tracking data for PR #${this.prNumber}`);
      return data;
    } catch (error) {
      Logger.error(`Failed to load tracking data: ${error.message}`);
      return null;
    }
  }

  /**
   * Parse Claude review output to extract recommendations
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
      
      if (trimmedLine.includes('🔴 REQUIRED')) {
        if (currentItem && currentSection) {
          recommendations[currentSection].push(currentItem.trim());
        }
        currentSection = 'required';
        currentItem = trimmedLine;
      } else if (trimmedLine.includes('🟡 IMPORTANT')) {
        if (currentItem && currentSection) {
          recommendations[currentSection].push(currentItem.trim());
        }
        currentSection = 'important';
        currentItem = trimmedLine;
      } else if (trimmedLine.includes('🔵 SUGGESTIONS')) {
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
   * Generate enhanced review comment with implementation status
   * @param {string} claudeOutput - Raw Claude review output
   * @param {Object} trackingData - Existing tracking data
   * @returns {string} Enhanced review comment
   */
  generateEnhancedComment(claudeOutput, trackingData) {
    const recommendations = this.parseClaudeReview(claudeOutput);
    const timestamp = new Date().toISOString();
    
    let comment = `## 🤖 BlazeCommerce Claude AI Review\n\n`;
    comment += `**Review Timestamp**: ${new Date().toLocaleString()}\n`;
    comment += `**Repository Type**: ${trackingData?.repo_type || 'general'}\n\n`;

    // Add implementation status summary
    if (trackingData) {
      const totalRequired = trackingData.total_recommendations.required;
      const resolvedRequired = trackingData.resolved_recommendations.required.length;
      const totalImportant = trackingData.total_recommendations.important;
      const resolvedImportant = trackingData.resolved_recommendations.important.length;

      comment += `### 📊 Implementation Status\n\n`;
      comment += `| Category | Total | Applied | Pending | Status |\n`;
      comment += `|----------|-------|---------|---------|--------|\n`;
      comment += `| 🔴 **REQUIRED** | ${totalRequired} | ${resolvedRequired} | ${totalRequired - resolvedRequired} | ${resolvedRequired === totalRequired ? '✅ Complete' : '⏳ Pending'} |\n`;
      comment += `| 🟡 **IMPORTANT** | ${totalImportant} | ${resolvedImportant} | ${totalImportant - resolvedImportant} | ${resolvedImportant === totalImportant ? '✅ Complete' : '⏳ Pending'} |\n\n`;
    }

    // Add detailed recommendations with status
    if (recommendations.required.length > 0) {
      comment += `### 🔴 REQUIRED Issues (Must Fix Before Merge)\n\n`;
      recommendations.required.forEach((item, index) => {
        const isResolved = trackingData?.resolved_recommendations.required.includes(index);
        const status = isResolved ? '✅ **APPLIED**' : '⚠️ **PENDING**';
        const timestamp = isResolved ? 
          `\n*Applied: ${trackingData.resolved_recommendations.required_timestamps?.[index] || 'Unknown'}*` : '';
        
        comment += `${index + 1}. ${status}\n`;
        comment += `   ${item}${timestamp}\n\n`;
      });
    }

    if (recommendations.important.length > 0) {
      comment += `### 🟡 IMPORTANT Improvements (Recommended)\n\n`;
      recommendations.important.forEach((item, index) => {
        const isResolved = trackingData?.resolved_recommendations.important.includes(index);
        const status = isResolved ? '✅ **APPLIED**' : '⏳ **PENDING**';
        const timestamp = isResolved ? 
          `\n*Applied: ${trackingData.resolved_recommendations.important_timestamps?.[index] || 'Unknown'}*` : '';
        
        comment += `${index + 1}. ${status}\n`;
        comment += `   ${item}${timestamp}\n\n`;
      });
    }

    if (recommendations.suggestions.length > 0) {
      comment += `### 🔵 SUGGESTIONS (Optional)\n\n`;
      recommendations.suggestions.forEach((item, index) => {
        comment += `${index + 1}. ${item}\n\n`;
      });
    }

    // Add footer with instructions
    comment += `---\n\n`;
    comment += `### 📋 Next Steps\n\n`;
    
    if (recommendations.required.length > 0) {
      const pendingRequired = trackingData ? 
        recommendations.required.length - trackingData.resolved_recommendations.required.length :
        recommendations.required.length;
      
      if (pendingRequired > 0) {
        comment += `❌ **${pendingRequired} REQUIRED issue(s) must be addressed before this PR can be approved.**\n\n`;
      } else {
        comment += `✅ **All REQUIRED issues have been addressed!**\n\n`;
      }
    }

    if (recommendations.important.length > 0) {
      const pendingImportant = trackingData ? 
        recommendations.important.length - trackingData.resolved_recommendations.important.length :
        recommendations.important.length;
      
      if (pendingImportant > 0) {
        comment += `⏳ **${pendingImportant} IMPORTANT improvement(s) are recommended but not required for approval.**\n\n`;
      }
    }

    comment += `### 🔄 Auto-Approval Status\n\n`;
    const hasBlockingIssues = recommendations.required.length > 0 && 
      (!trackingData || trackingData.resolved_recommendations.required.length < recommendations.required.length);
    
    if (hasBlockingIssues) {
      comment += `🚫 **Auto-approval blocked** - REQUIRED issues must be resolved first.\n\n`;
    } else {
      comment += `✅ **Ready for auto-approval** - No blocking issues found.\n\n`;
    }

    comment += `*This review will automatically update when changes are pushed. The PR will be auto-approved once all REQUIRED issues are resolved.*\n\n`;
    comment += `---\n`;
    comment += `*🤖 Generated by BlazeCommerce Claude AI Review Bot v2.0*`;

    return comment;
  }

  /**
   * Update tracking data with new recommendations
   * @param {Object} recommendations - Parsed recommendations
   * @param {Object} existingData - Existing tracking data
   * @returns {Object} Updated tracking data
   */
  updateTrackingData(recommendations, existingData = null) {
    const timestamp = new Date().toISOString();
    
    const trackingData = existingData || {
      pr_number: parseInt(this.prNumber),
      created_at: timestamp,
      repo_type: process.env.REPO_TYPE || 'general',
      total_recommendations: {
        required: 0,
        important: 0,
        suggestions: 0
      },
      resolved_recommendations: {
        required: [],
        important: [],
        required_timestamps: {},
        important_timestamps: {}
      },
      recommendations: {
        required: [],
        important: [],
        suggestions: []
      }
    };

    // Update with new recommendations
    trackingData.updated_at = timestamp;
    trackingData.total_recommendations = {
      required: recommendations.required.length,
      important: recommendations.important.length,
      suggestions: recommendations.suggestions.length
    };
    trackingData.recommendations = recommendations;

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
   * Process Claude review and generate enhanced comment
   * @param {string} claudeOutput - Raw Claude review output
   * @returns {Object} Processing result
   */
  processReview(claudeOutput) {
    try {
      Logger.info(`Processing Claude review for PR #${this.prNumber}`);

      // Load existing tracking data
      const existingData = this.loadTrackingData();

      // Parse recommendations
      const recommendations = this.parseClaudeReview(claudeOutput);

      // Update tracking data
      const trackingData = this.updateTrackingData(recommendations, existingData);

      // Generate enhanced comment
      const enhancedComment = this.generateEnhancedComment(claudeOutput, trackingData);

      // Save updated tracking data
      this.saveTrackingData(trackingData);

      return {
        success: true,
        enhancedComment,
        trackingData,
        recommendations,
        hasBlockingIssues: recommendations.required.length > 0
      };

    } catch (error) {
      Logger.error(`Failed to process review: ${error.message}`);
      return {
        success: false,
        error: error.message,
        enhancedComment: claudeOutput, // Fallback to original
        hasBlockingIssues: claudeOutput.includes('🔴 REQUIRED')
      };
    }
  }

  /**
   * Output results in GitHub Actions format
   * @param {Object} result - Processing result
   */
  outputForGitHubActions(result) {
    console.log(`enhanced_comment=${result.enhancedComment.replace(/\n/g, '\\n')}`);
    console.log(`has_blocking_issues=${result.hasBlockingIssues}`);
    console.log(`processing_success=${result.success}`);
    
    if (result.recommendations) {
      console.log(`required_count=${result.recommendations.required.length}`);
      console.log(`important_count=${result.recommendations.important.length}`);
      console.log(`suggestions_count=${result.recommendations.suggestions.length}`);
    }
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  try {
    const enhancer = new ClaudeReviewEnhancer();
    const claudeOutput = process.env.CLAUDE_OUTPUT || process.argv[3] || '';
    
    if (!enhancer.prNumber) {
      Logger.error('PR number is required as environment variable PR_NUMBER or first argument');
      process.exit(1);
    }

    const result = enhancer.processReview(claudeOutput);
    enhancer.outputForGitHubActions(result);
    
    process.exit(result.success ? 0 : 1);
  } catch (error) {
    Logger.error(`Script execution failed: ${error.message}`);
    process.exit(1);
  }
}

module.exports = { ClaudeReviewEnhancer };
