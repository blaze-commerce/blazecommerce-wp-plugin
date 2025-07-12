/**
 * BlazeCommerce Claude AI Review Bot - Recommendation Tracker
 * 
 * Tracking and state management system for Claude AI recommendations
 * with comprehensive audit trail and progress monitoring.
 */

const fs = require('fs');
const path = require('path');
const config = require('./claude-bot-config');

class RecommendationTracker {
  constructor(options = {}) {
    this.prNumber = options.prNumber || process.env.PR_NUMBER;

    // Validate and sanitize file paths to prevent path traversal
    const trackingFile = options.trackingFile || config.PATHS.TRACKING_FILE;
    const stateFile = options.stateFile || config.PATHS.STATE_FILE;

    this.trackingFile = this.validateFilePath(trackingFile);
    this.stateFile = this.validateFilePath(stateFile);

    this.categories = config.CATEGORIES;
    this.statuses = config.STATUSES;
  }

  /**
   * Validate and sanitize file paths to prevent path traversal attacks
   */
  validateFilePath(filePath) {
    // Ensure the path is within the .github directory
    const safePath = path.resolve(config.PATHS.GITHUB_DIR, path.basename(filePath));

    // Additional validation to ensure it's within the expected directory
    if (!safePath.startsWith(path.resolve(config.PATHS.GITHUB_DIR))) {
      throw new Error(`Invalid file path: ${filePath}. Must be within ${config.PATHS.GITHUB_DIR} directory.`);
    }

    return safePath;
  }

  /**
   * Initialize tracking for a new PR
   */
  async initializeTracking(prData, recommendations) {
    console.log(`📋 Initializing tracking for PR #${this.prNumber}`);
    
    const trackingData = {
      prNumber: this.prNumber,
      title: prData.title,
      author: prData.user.login,
      createdAt: new Date().toISOString(),
      lastUpdated: new Date().toISOString(),
      recommendations: recommendations.map(rec => ({
        ...rec,
        status: 'pending',
        confidence: 0,
        history: [{
          timestamp: new Date().toISOString(),
          status: 'pending',
          confidence: 0,
          note: 'Initial recommendation'
        }]
      })),
      summary: {
        total: recommendations.length,
        byCategory: this.summarizeByCategory(recommendations),
        byStatus: this.summarizeByStatus(recommendations)
      }
    };
    
    await this.saveTrackingFile(trackingData);
    await this.saveStateFile(trackingData);
    
    console.log('✅ Tracking initialized');
    return trackingData;
  }

  /**
   * Update recommendation status
   */
  async updateRecommendationStatus(recommendationId, newStatus, confidence, evidence = []) {
    console.log(`🔄 Updating recommendation ${recommendationId}: ${newStatus} (${confidence}% confidence)`);
    
    const trackingData = await this.loadTrackingData();
    
    if (!trackingData) {
      throw new Error('No tracking data found. Initialize tracking first.');
    }
    
    const recommendation = trackingData.recommendations.find(r => r.id === recommendationId);
    
    if (!recommendation) {
      throw new Error(`Recommendation ${recommendationId} not found`);
    }
    
    // Update recommendation
    const oldStatus = recommendation.status;
    recommendation.status = newStatus;
    recommendation.confidence = confidence;
    recommendation.evidence = evidence;
    recommendation.lastUpdated = new Date().toISOString();
    
    // Add to history
    recommendation.history.push({
      timestamp: new Date().toISOString(),
      status: newStatus,
      confidence,
      evidence: evidence.length,
      note: `Status changed from ${oldStatus} to ${newStatus}`
    });
    
    // Update summary
    trackingData.summary = {
      total: trackingData.recommendations.length,
      byCategory: this.summarizeByCategory(trackingData.recommendations),
      byStatus: this.summarizeByStatus(trackingData.recommendations)
    };
    
    trackingData.lastUpdated = new Date().toISOString();
    
    await this.saveTrackingFile(trackingData);
    await this.saveStateFile(trackingData);
    
    console.log('✅ Recommendation status updated');
    return trackingData;
  }

  /**
   * Bulk update multiple recommendations
   */
  async bulkUpdateRecommendations(updates) {
    console.log(`🔄 Bulk updating ${updates.length} recommendations`);
    
    const trackingData = await this.loadTrackingData();
    
    if (!trackingData) {
      throw new Error('No tracking data found. Initialize tracking first.');
    }
    
    for (const update of updates) {
      const recommendation = trackingData.recommendations.find(r => r.id === update.id);
      
      if (recommendation) {
        const oldStatus = recommendation.status;
        recommendation.status = update.status;
        recommendation.confidence = update.confidence;
        recommendation.evidence = update.evidence || [];
        recommendation.lastUpdated = new Date().toISOString();
        
        // Add to history
        recommendation.history.push({
          timestamp: new Date().toISOString(),
          status: update.status,
          confidence: update.confidence,
          evidence: (update.evidence || []).length,
          note: `Bulk update: ${oldStatus} → ${update.status}`
        });
      }
    }
    
    // Update summary
    trackingData.summary = {
      total: trackingData.recommendations.length,
      byCategory: this.summarizeByCategory(trackingData.recommendations),
      byStatus: this.summarizeByStatus(trackingData.recommendations)
    };
    
    trackingData.lastUpdated = new Date().toISOString();
    
    await this.saveTrackingFile(trackingData);
    await this.saveStateFile(trackingData);
    
    console.log('✅ Bulk update completed');
    return trackingData;
  }

  /**
   * Get current tracking status
   */
  async getTrackingStatus() {
    const trackingData = await this.loadTrackingData();
    
    if (!trackingData) {
      return null;
    }
    
    return {
      prNumber: trackingData.prNumber,
      lastUpdated: trackingData.lastUpdated,
      summary: trackingData.summary,
      autoApprovalReady: this.isAutoApprovalReady(trackingData),
      recommendations: trackingData.recommendations.map(rec => ({
        id: rec.id,
        category: rec.category,
        status: rec.status,
        confidence: rec.confidence,
        lastUpdated: rec.lastUpdated
      }))
    };
  }

  /**
   * Check if auto-approval criteria are met
   */
  isAutoApprovalReady(trackingData) {
    const requiredRecommendations = trackingData.recommendations.filter(r => r.category === 'REQUIRED');
    const importantRecommendations = trackingData.recommendations.filter(r => r.category === 'IMPORTANT');
    
    const requiredAddressed = requiredRecommendations.every(r => 
      r.status === 'addressed' || r.status === 'verified'
    );
    
    const importantAddressed = importantRecommendations.every(r => 
      r.status === 'addressed' || r.status === 'verified'
    );
    
    return {
      ready: requiredAddressed && importantAddressed,
      requiredAddressed,
      importantAddressed,
      pendingRequired: requiredRecommendations.filter(r => 
        r.status !== 'addressed' && r.status !== 'verified'
      ).length,
      pendingImportant: importantRecommendations.filter(r => 
        r.status !== 'addressed' && r.status !== 'verified'
      ).length
    };
  }

  /**
   * Generate tracking report
   */
  async generateTrackingReport() {
    const trackingData = await this.loadTrackingData();
    
    if (!trackingData) {
      return 'No tracking data available.';
    }
    
    const autoApproval = this.isAutoApprovalReady(trackingData);
    
    let report = `# 📊 Claude AI Review Tracking Report\n\n`;
    report += `**PR #${trackingData.prNumber}**: ${trackingData.title}\n`;
    report += `**Author**: ${trackingData.author}\n`;
    report += `**Last Updated**: ${new Date(trackingData.lastUpdated).toLocaleString()}\n\n`;
    
    // Summary section
    report += `## 📋 Summary\n\n`;
    report += `- **Total Recommendations**: ${trackingData.summary.total}\n`;
    report += `- **Addressed**: ${trackingData.summary.byStatus.addressed || 0}\n`;
    report += `- **Pending**: ${trackingData.summary.byStatus.pending || 0}\n`;
    report += `- **Partial**: ${trackingData.summary.byStatus.partial || 0}\n\n`;
    
    // Auto-approval status
    report += `## 🚀 Auto-Approval Status\n\n`;
    if (autoApproval.ready) {
      report += `✅ **Ready for auto-approval** - All REQUIRED and IMPORTANT recommendations addressed\n\n`;
    } else {
      report += `⏳ **Pending auto-approval**:\n`;
      if (autoApproval.pendingRequired > 0) {
        report += `- ${autoApproval.pendingRequired} REQUIRED recommendation(s) pending\n`;
      }
      if (autoApproval.pendingImportant > 0) {
        report += `- ${autoApproval.pendingImportant} IMPORTANT recommendation(s) pending\n`;
      }
      report += '\n';
    }
    
    // Recommendations by category
    for (const category of ['REQUIRED', 'IMPORTANT', 'SUGGESTION']) {
      const categoryRecs = trackingData.recommendations.filter(r => r.category === category);
      
      if (categoryRecs.length > 0) {
        report += `## ${this.categories[category].icon} ${category} Recommendations\n\n`;
        
        categoryRecs.forEach((rec, index) => {
          const statusInfo = this.statuses[rec.status];
          report += `### ${index + 1}. ${statusInfo.icon} ${rec.id}\n`;
          report += `**Status**: ${rec.status} (${rec.confidence}% confidence)\n`;
          report += `**Content**: ${rec.content.substring(0, 150)}...\n`;
          report += `**Last Updated**: ${new Date(rec.lastUpdated).toLocaleString()}\n\n`;
        });
      }
    }
    
    // Verification commands
    report += `## 🧪 Verification Commands\n\n`;
    report += `\`\`\`bash\n`;
    report += `# Check tracking status\n`;
    report += `node scripts/recommendation-tracker.js status\n\n`;
    report += `# Generate full report\n`;
    report += `node scripts/recommendation-tracker.js report\n`;
    report += `\`\`\`\n\n`;
    
    return report;
  }

  /**
   * Load tracking data from file
   */
  async loadTrackingData() {
    try {
      try {
        await fs.promises.access(this.stateFile);
        const content = await fs.promises.readFile(this.stateFile, 'utf8');
        return JSON.parse(content);
      } catch (accessError) {
        // File doesn't exist, return null
        return null;
      }
    } catch (error) {
      console.error('❌ Failed to load tracking data:', error.message);
      return null;
    }
  }

  /**
   * Save tracking data to markdown file
   */
  async saveTrackingFile(trackingData) {
    try {
      const report = await this.generateTrackingReport();
      await fs.promises.writeFile(this.trackingFile, report);
      console.log('✅ Tracking file saved');
    } catch (error) {
      console.error('❌ Failed to save tracking file:', error.message);
    }
  }

  /**
   * Save state data to JSON file
   */
  async saveStateFile(trackingData) {
    try {
      await fs.promises.writeFile(this.stateFile, JSON.stringify(trackingData, null, 2));
      console.log('✅ State file saved');
    } catch (error) {
      console.error('❌ Failed to save state file:', error.message);
    }
  }

  /**
   * Summarize recommendations by category
   */
  summarizeByCategory(recommendations) {
    const summary = {};
    
    for (const category of Object.keys(this.categories)) {
      summary[category.toLowerCase()] = recommendations.filter(r => r.category === category).length;
    }
    
    return summary;
  }

  /**
   * Summarize recommendations by status
   */
  summarizeByStatus(recommendations) {
    const summary = {};
    
    for (const status of Object.keys(this.statuses)) {
      summary[status] = recommendations.filter(r => r.status === status).length;
    }
    
    return summary;
  }
}

// Export for use in workflows
module.exports = RecommendationTracker;

// CLI usage
if (require.main === module) {
  const command = process.argv[2];
  const prNumber = process.env.PR_NUMBER || process.argv[3];
  
  const tracker = new RecommendationTracker({ prNumber });
  
  switch (command) {
    case 'status':
      tracker.getTrackingStatus()
        .then(status => {
          if (status) {
            console.log('📊 Tracking Status:');
            console.log(JSON.stringify(status, null, 2));
          } else {
            console.log('No tracking data found');
          }
        })
        .catch(console.error);
      break;
      
    case 'report':
      tracker.generateTrackingReport()
        .then(report => {
          console.log(report);
        })
        .catch(console.error);
      break;
      
    default:
      console.log('Usage: node recommendation-tracker.js <command> [pr_number]');
      console.log('Commands: status, report');
      process.exit(1);
  }
}
