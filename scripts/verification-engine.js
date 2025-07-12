/**
 * BlazeCommerce Claude AI Review Bot - Verification Engine
 * 
 * Advanced verification system that tracks and validates recommendation implementation
 * with intelligent file matching and confidence scoring.
 */

const { Octokit } = require('@octokit/rest');
const fs = require('fs');
const path = require('path');

class VerificationEngine {
  constructor(options = {}) {
    this.github = new Octokit({
      auth: options.githubToken || process.env.BOT_GITHUB_TOKEN
    });
    
    this.owner = options.owner || process.env.GITHUB_REPOSITORY_OWNER;
    this.repo = options.repo || process.env.GITHUB_REPOSITORY_NAME;
    this.prNumber = options.prNumber || process.env.PR_NUMBER;
    
    this.trackingFile = '.github/CLAUDE_REVIEW_TRACKING.md';
    this.confidenceThreshold = 0.7; // Minimum confidence for "addressed" status
  }

  /**
   * Main verification process
   */
  async runVerification() {
    try {
      console.log('🔍 Starting recommendation verification...');
      
      // Get PR details and changed files
      const prData = await this.getPRData();
      const changedFiles = await this.getChangedFiles();
      
      // Get existing Claude reviews
      const claudeReviews = await this.getClaudeReviews();
      
      if (claudeReviews.length === 0) {
        console.log('ℹ️ No Claude reviews found, skipping verification');
        return { success: true, message: 'No reviews to verify' };
      }
      
      // Parse recommendations from reviews
      const recommendations = this.parseRecommendations(claudeReviews);
      
      // Analyze file changes against recommendations
      const verificationResults = await this.analyzeImplementation(
        recommendations, 
        changedFiles
      );
      
      // Update tracking file
      await this.updateTrackingFile(verificationResults);
      
      // Post verification comment
      await this.postVerificationComment(verificationResults);
      
      console.log('✅ Verification completed successfully');
      return { success: true, results: verificationResults };
      
    } catch (error) {
      console.error('❌ Verification failed:', error.message);
      await this.handleVerificationError(error);
      return { success: false, error: error.message };
    }
  }

  /**
   * Get PR data from GitHub API
   */
  async getPRData() {
    const response = await this.github.rest.pulls.get({
      owner: this.owner,
      repo: this.repo,
      pull_number: this.prNumber
    });
    
    return response.data;
  }

  /**
   * Get changed files in the PR
   */
  async getChangedFiles() {
    const response = await this.github.rest.pulls.listFiles({
      owner: this.owner,
      repo: this.repo,
      pull_number: this.prNumber
    });
    
    return response.data.map(file => ({
      filename: file.filename,
      status: file.status,
      additions: file.additions,
      deletions: file.deletions,
      patch: file.patch || '',
      changes: file.changes
    }));
  }

  /**
   * Get Claude AI review comments
   */
  async getClaudeReviews() {
    const response = await this.github.rest.issues.listComments({
      owner: this.owner,
      repo: this.repo,
      issue_number: this.prNumber
    });
    
    return response.data.filter(comment => 
      comment.user.login === 'blazecommerce-claude-ai' ||
      comment.user.login === 'github-actions[bot]' &&
      comment.body.includes('🤖 BlazeCommerce Claude AI Review')
    );
  }

  /**
   * Parse recommendations from Claude review comments
   */
  parseRecommendations(reviews) {
    const recommendations = [];
    
    reviews.forEach((review, reviewIndex) => {
      const content = review.body;
      
      // Extract categorized recommendations
      const requiredMatches = content.match(/🔴\s*REQUIRED[:\s-]*(.*?)(?=🟡|🔵|$)/gs) || [];
      const importantMatches = content.match(/🟡\s*IMPORTANT[:\s-]*(.*?)(?=🔴|🔵|$)/gs) || [];
      const suggestionMatches = content.match(/🔵\s*SUGGESTION[:\s-]*(.*?)(?=🔴|🟡|$)/gs) || [];
      
      // Process each category
      requiredMatches.forEach((match, index) => {
        recommendations.push({
          id: `required-${reviewIndex}-${index}`,
          category: 'REQUIRED',
          content: match.replace(/🔴\s*REQUIRED[:\s-]*/, '').trim(),
          reviewId: review.id,
          status: 'pending',
          confidence: 0
        });
      });
      
      importantMatches.forEach((match, index) => {
        recommendations.push({
          id: `important-${reviewIndex}-${index}`,
          category: 'IMPORTANT',
          content: match.replace(/🟡\s*IMPORTANT[:\s-]*/, '').trim(),
          reviewId: review.id,
          status: 'pending',
          confidence: 0
        });
      });
      
      suggestionMatches.forEach((match, index) => {
        recommendations.push({
          id: `suggestion-${reviewIndex}-${index}`,
          category: 'SUGGESTION',
          content: match.replace(/🔵\s*SUGGESTION[:\s-]*/, '').trim(),
          reviewId: review.id,
          status: 'pending',
          confidence: 0
        });
      });
    });
    
    return recommendations;
  }

  /**
   * Analyze file changes to determine recommendation implementation
   */
  async analyzeImplementation(recommendations, changedFiles) {
    const results = {
      recommendations: [],
      summary: {
        total: recommendations.length,
        addressed: 0,
        pending: 0,
        confidence: 0
      }
    };
    
    for (const recommendation of recommendations) {
      const analysis = await this.analyzeRecommendation(recommendation, changedFiles);
      results.recommendations.push(analysis);
      
      if (analysis.status === 'addressed') {
        results.summary.addressed++;
      } else {
        results.summary.pending++;
      }
    }
    
    // Calculate overall confidence
    if (results.recommendations.length > 0) {
      const totalConfidence = results.recommendations.reduce(
        (sum, rec) => sum + rec.confidence, 0
      );
      results.summary.confidence = totalConfidence / results.recommendations.length;
    }
    
    return results;
  }

  /**
   * Analyze individual recommendation against file changes
   */
  async analyzeRecommendation(recommendation, changedFiles) {
    let confidence = 0;
    let relevantFiles = [];
    let evidence = [];
    
    // Extract file references from recommendation content
    const fileReferences = this.extractFileReferences(recommendation.content);
    
    // Calculate relevance score for each changed file
    for (const file of changedFiles) {
      const relevanceScore = this.calculateRelevanceScore(
        recommendation.content,
        file,
        fileReferences
      );
      
      if (relevanceScore > 0.3) { // Threshold for relevance
        relevantFiles.push({
          filename: file.filename,
          relevance: relevanceScore,
          changes: file.changes
        });
        
        // Analyze file content for implementation evidence
        const fileEvidence = this.analyzeFileEvidence(recommendation, file);
        evidence.push(...fileEvidence);
        
        confidence = Math.max(confidence, relevanceScore);
      }
    }
    
    // Determine status based on confidence
    let status = 'pending';
    if (confidence >= this.confidenceThreshold) {
      status = 'addressed';
    } else if (confidence >= 0.4) {
      status = 'partial';
    }
    
    return {
      ...recommendation,
      status,
      confidence: Math.round(confidence * 100) / 100,
      relevantFiles,
      evidence,
      lastUpdated: new Date().toISOString()
    };
  }

  /**
   * Extract file references from recommendation content
   */
  extractFileReferences(content) {
    const filePatterns = [
      /`([^`]+\.(js|ts|jsx|tsx|php|css|scss|md|json|yml|yaml))`/g,
      /([a-zA-Z0-9_-]+\/[a-zA-Z0-9_.-]+\.(js|ts|jsx|tsx|php|css|scss|md|json|yml|yaml))/g,
      /([a-zA-Z0-9_.-]+\.(js|ts|jsx|tsx|php|css|scss|md|json|yml|yaml))/g
    ];
    
    const references = [];
    
    filePatterns.forEach(pattern => {
      let match;
      while ((match = pattern.exec(content)) !== null) {
        references.push(match[1] || match[0]);
      }
    });
    
    return [...new Set(references)]; // Remove duplicates
  }

  /**
   * Calculate relevance score between recommendation and file
   */
  calculateRelevanceScore(recommendationContent, file, fileReferences) {
    let score = 0;
    
    // Direct file reference match
    if (fileReferences.some(ref => file.filename.includes(ref) || ref.includes(file.filename))) {
      score += 0.8;
    }
    
    // Content-based matching
    const contentKeywords = this.extractKeywords(recommendationContent);
    const fileKeywords = this.extractKeywords(file.patch);
    
    const commonKeywords = contentKeywords.filter(keyword => 
      fileKeywords.includes(keyword)
    );
    
    if (commonKeywords.length > 0) {
      score += Math.min(0.6, commonKeywords.length * 0.1);
    }
    
    // File type relevance
    const fileExtension = path.extname(file.filename);
    if (recommendationContent.includes(fileExtension)) {
      score += 0.2;
    }
    
    return Math.min(1.0, score);
  }

  /**
   * Extract keywords from text content
   */
  extractKeywords(content) {
    if (!content) return [];
    
    return content
      .toLowerCase()
      .replace(/[^\w\s]/g, ' ')
      .split(/\s+/)
      .filter(word => word.length > 3)
      .filter(word => !['this', 'that', 'with', 'from', 'they', 'have', 'been', 'will', 'would', 'could', 'should'].includes(word));
  }

  /**
   * Analyze file for implementation evidence
   */
  analyzeFileEvidence(recommendation, file) {
    const evidence = [];
    
    if (!file.patch) return evidence;
    
    // Look for added lines that might address the recommendation
    const addedLines = file.patch
      .split('\n')
      .filter(line => line.startsWith('+') && !line.startsWith('+++'))
      .map(line => line.substring(1));
    
    // Simple keyword matching for evidence
    const recommendationKeywords = this.extractKeywords(recommendation.content);
    
    addedLines.forEach((line, index) => {
      const lineKeywords = this.extractKeywords(line);
      const commonKeywords = recommendationKeywords.filter(keyword => 
        lineKeywords.includes(keyword)
      );
      
      if (commonKeywords.length > 0) {
        evidence.push({
          type: 'code_addition',
          line: line.trim(),
          keywords: commonKeywords,
          confidence: Math.min(0.8, commonKeywords.length * 0.2)
        });
      }
    });
    
    return evidence;
  }

  /**
   * Update tracking file with verification results
   */
  async updateTrackingFile(results) {
    try {
      let trackingContent = '';
      
      // Try to read existing tracking file
      if (fs.existsSync(this.trackingFile)) {
        trackingContent = fs.readFileSync(this.trackingFile, 'utf8');
      } else {
        // Create new tracking file
        trackingContent = `# Claude AI Review Tracking for PR #${this.prNumber}\n\n`;
        trackingContent += 'This file tracks all Claude AI recommendations and their implementation status.\n\n';
      }
      
      // Add verification results section
      const timestamp = new Date().toISOString();
      trackingContent += `\n## 🔍 Verification Results (${timestamp})\n\n`;
      trackingContent += `### Summary\n`;
      trackingContent += `- **Total Recommendations**: ${results.summary.total}\n`;
      trackingContent += `- **Addressed**: ${results.summary.addressed}\n`;
      trackingContent += `- **Pending**: ${results.summary.pending}\n`;
      trackingContent += `- **Overall Confidence**: ${Math.round(results.summary.confidence * 100)}%\n\n`;
      
      // Add detailed results
      trackingContent += `### Detailed Status\n\n`;
      results.recommendations.forEach(rec => {
        const statusIcon = rec.status === 'addressed' ? '✅' : 
                          rec.status === 'partial' ? '🔄' : '⏳';
        
        trackingContent += `#### ${statusIcon} ${rec.category} - ${rec.id}\n`;
        trackingContent += `**Status**: ${rec.status} (${Math.round(rec.confidence * 100)}% confidence)\n`;
        trackingContent += `**Content**: ${rec.content.substring(0, 100)}...\n`;
        
        if (rec.relevantFiles.length > 0) {
          trackingContent += `**Relevant Files**: ${rec.relevantFiles.map(f => f.filename).join(', ')}\n`;
        }
        
        trackingContent += '\n';
      });
      
      // Write updated tracking file
      fs.writeFileSync(this.trackingFile, trackingContent);
      console.log('✅ Tracking file updated');
      
    } catch (error) {
      console.error('❌ Failed to update tracking file:', error.message);
    }
  }

  /**
   * Post verification comment to PR
   */
  async postVerificationComment(results) {
    const timestamp = new Date().toISOString();
    
    const comment = `## 🔍 Recommendation Verification Update
    
**Verification Timestamp**: ${timestamp}
**Overall Progress**: ${results.summary.addressed}/${results.summary.total} recommendations addressed

### 📊 Status Summary
- ✅ **Addressed**: ${results.summary.addressed}
- ⏳ **Pending**: ${results.summary.pending}
- 🎯 **Confidence**: ${Math.round(results.summary.confidence * 100)}%

### 🔄 Auto-Approval Status
${this.getAutoApprovalStatus(results)}

See \`.github/CLAUDE_REVIEW_TRACKING.md\` for detailed verification results.

---
*Automated verification by BlazeCommerce Claude AI Review Bot*`;

    await this.github.rest.issues.createComment({
      owner: this.owner,
      repo: this.repo,
      issue_number: this.prNumber,
      body: comment
    });
    
    console.log('✅ Verification comment posted');
  }

  /**
   * Get auto-approval status message
   */
  getAutoApprovalStatus(results) {
    const requiredAddressed = results.recommendations
      .filter(r => r.category === 'REQUIRED')
      .every(r => r.status === 'addressed');
    
    const importantAddressed = results.recommendations
      .filter(r => r.category === 'IMPORTANT')
      .every(r => r.status === 'addressed');
    
    if (requiredAddressed && importantAddressed) {
      return '🚀 **Ready for auto-approval** - All REQUIRED and IMPORTANT recommendations addressed';
    } else if (!requiredAddressed) {
      const pendingRequired = results.recommendations
        .filter(r => r.category === 'REQUIRED' && r.status !== 'addressed').length;
      return `⏳ **Pending auto-approval** - ${pendingRequired} REQUIRED recommendation(s) still need attention`;
    } else {
      const pendingImportant = results.recommendations
        .filter(r => r.category === 'IMPORTANT' && r.status !== 'addressed').length;
      return `⏳ **Pending auto-approval** - ${pendingImportant} IMPORTANT recommendation(s) still need attention`;
    }
  }

  /**
   * Handle verification errors
   */
  async handleVerificationError(error) {
    try {
      const errorComment = `## ⚠️ Verification Error
      
The recommendation verification process encountered an error:

**Error**: ${error.message}

The verification will be retried automatically on the next commit. If this error persists, please check the workflow logs for more details.

---
*BlazeCommerce Claude AI Review Bot*`;

      await this.github.rest.issues.createComment({
        owner: this.owner,
        repo: this.repo,
        issue_number: this.prNumber,
        body: errorComment
      });
      
    } catch (commentError) {
      console.error('❌ Failed to post error comment:', commentError.message);
    }
  }
}

// Export for use in workflows
module.exports = VerificationEngine;

// CLI usage
if (require.main === module) {
  const engine = new VerificationEngine({
    githubToken: process.env.BOT_GITHUB_TOKEN,
    owner: process.env.GITHUB_REPOSITORY_OWNER,
    repo: process.env.GITHUB_REPOSITORY_NAME?.split('/')[1],
    prNumber: parseInt(process.env.PR_NUMBER)
  });
  
  engine.runVerification()
    .then(result => {
      console.log('Verification result:', result);
      process.exit(result.success ? 0 : 1);
    })
    .catch(error => {
      console.error('Verification failed:', error);
      process.exit(1);
    });
}
