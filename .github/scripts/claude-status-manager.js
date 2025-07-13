#!/usr/bin/env node

/**
 * Claude Status Manager
 * Centralized status management for Claude AI workflows
 * Handles status check creation, updates, and state transitions
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { Logger } = require('./file-change-analyzer');

/**
 * Claude AI Status States
 */
const CLAUDE_STATES = {
  PENDING: 'pending',
  REVIEWING: 'pending', 
  SUCCESS: 'success',
  FAILURE: 'failure',
  ERROR: 'error'
};

/**
 * Status Check Contexts
 */
const STATUS_CONTEXTS = {
  REVIEW: 'claude-ai/review',
  APPROVAL: 'claude-ai/approval-required'
};

/**
 * Claude Status Manager Class
 */
class ClaudeStatusManager {
  constructor(githubToken, repoOwner, repoName) {
    this.githubToken = githubToken;
    this.repoOwner = repoOwner;
    this.repoName = repoName;
    this.baseUrl = 'https://api.github.com';
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

    const url = `${this.baseUrl}${endpoint}`;
    const response = await fetch(url, {
      method: options.method || 'GET',
      headers: {
        'Authorization': `token ${this.githubToken}`,
        'Accept': 'application/vnd.github.v3+json',
        'User-Agent': 'BlazeCommerce-Claude-Status-Manager',
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
   * Create or update a status check
   * @param {string} sha - Commit SHA
   * @param {string} state - Status state
   * @param {string} context - Status context
   * @param {string} description - Status description
   * @param {string} targetUrl - Target URL (optional)
   * @returns {Promise<Object>} Status response
   */
  async setStatus(sha, state, context, description, targetUrl = null) {
    try {
      const statusData = {
        state,
        description,
        context
      };

      if (targetUrl) {
        statusData.target_url = targetUrl;
      }

      Logger.info(`Setting status: ${context} = ${state} (${description})`);

      const response = await this.makeGitHubRequest(
        `/repos/${this.repoOwner}/${this.repoName}/statuses/${sha}`,
        {
          method: 'POST',
          body: statusData
        }
      );

      Logger.success(`Status set successfully: ${context} = ${state}`);
      return response;
    } catch (error) {
      Logger.error(`Failed to set status: ${error.message}`);
      throw error;
    }
  }

  /**
   * Set Claude review status
   * @param {string} sha - Commit SHA
   * @param {string} state - Status state
   * @param {string} description - Status description
   * @param {number} prNumber - PR number for target URL
   * @returns {Promise<Object>} Status response
   */
  async setReviewStatus(sha, state, description, prNumber) {
    const targetUrl = `https://github.com/${this.repoOwner}/${this.repoName}/pull/${prNumber}`;
    return await this.setStatus(sha, state, STATUS_CONTEXTS.REVIEW, description, targetUrl);
  }

  /**
   * Set Claude approval status
   * @param {string} sha - Commit SHA
   * @param {string} state - Status state
   * @param {string} description - Status description
   * @param {number} prNumber - PR number for target URL
   * @returns {Promise<Object>} Status response
   */
  async setApprovalStatus(sha, state, description, prNumber) {
    const targetUrl = `https://github.com/${this.repoOwner}/${this.repoName}/pull/${prNumber}`;
    return await this.setStatus(sha, state, STATUS_CONTEXTS.APPROVAL, description, targetUrl);
  }

  /**
   * Get current status checks for a commit
   * @param {string} sha - Commit SHA
   * @returns {Promise<Array>} Status checks
   */
  async getStatusChecks(sha) {
    try {
      const response = await this.makeGitHubRequest(
        `/repos/${this.repoOwner}/${this.repoName}/commits/${sha}/status`
      );
      return response.statuses || [];
    } catch (error) {
      Logger.warning(`Failed to get status checks: ${error.message}`);
      return [];
    }
  }

  /**
   * Get Claude-specific status checks
   * @param {string} sha - Commit SHA
   * @returns {Promise<Object>} Claude status checks
   */
  async getClaudeStatusChecks(sha) {
    const statuses = await this.getStatusChecks(sha);
    
    const claudeStatuses = {
      review: null,
      approval: null
    };

    statuses.forEach(status => {
      if (status.context === STATUS_CONTEXTS.REVIEW) {
        claudeStatuses.review = status;
      } else if (status.context === STATUS_CONTEXTS.APPROVAL) {
        claudeStatuses.approval = status;
      }
    });

    return claudeStatuses;
  }

  /**
   * Handle Claude review start
   * @param {string} sha - Commit SHA
   * @param {number} prNumber - PR number
   * @returns {Promise<void>}
   */
  async handleReviewStart(sha, prNumber) {
    await this.setReviewStatus(sha, CLAUDE_STATES.REVIEWING, 'Claude AI review in progress...', prNumber);
    await this.setApprovalStatus(sha, CLAUDE_STATES.PENDING, 'Waiting for Claude AI review to complete', prNumber);
  }

  /**
   * Handle Claude review success
   * @param {string} sha - Commit SHA
   * @param {number} prNumber - PR number
   * @param {boolean} hasBlockingIssues - Whether there are blocking issues
   * @param {Object} recommendations - Recommendation counts
   * @returns {Promise<void>}
   */
  async handleReviewSuccess(sha, prNumber, hasBlockingIssues, recommendations = {}) {
    const requiredCount = recommendations.required || 0;
    const importantCount = recommendations.important || 0;
    
    let reviewDescription = 'Claude AI review completed';
    if (requiredCount > 0) {
      reviewDescription += ` - ${requiredCount} required issue(s) found`;
    } else if (importantCount > 0) {
      reviewDescription += ` - ${importantCount} improvement(s) recommended`;
    } else {
      reviewDescription += ' - No issues found';
    }

    await this.setReviewStatus(sha, CLAUDE_STATES.SUCCESS, reviewDescription, prNumber);

    // Set approval status based on blocking issues
    if (hasBlockingIssues) {
      await this.setApprovalStatus(sha, CLAUDE_STATES.PENDING, 
        `Blocked: ${requiredCount} required issue(s) must be resolved`, prNumber);
    } else {
      await this.setApprovalStatus(sha, CLAUDE_STATES.SUCCESS, 
        'Approved: No blocking issues found', prNumber);
    }
  }

  /**
   * Handle Claude review failure
   * @param {string} sha - Commit SHA
   * @param {number} prNumber - PR number
   * @param {string} errorMessage - Error message
   * @returns {Promise<void>}
   */
  async handleReviewFailure(sha, prNumber, errorMessage = 'Claude AI review service unavailable') {
    await this.setReviewStatus(sha, CLAUDE_STATES.FAILURE, errorMessage, prNumber);
    await this.setApprovalStatus(sha, CLAUDE_STATES.FAILURE,
      'Manual review required - Claude AI service unavailable', prNumber);
  }

  /**
   * Handle manual approval override
   * @param {string} sha - Commit SHA
   * @param {number} prNumber - PR number
   * @param {string} approver - Who approved manually
   * @returns {Promise<void>}
   */
  async handleManualApproval(sha, prNumber, approver) {
    await this.setApprovalStatus(sha, CLAUDE_STATES.SUCCESS,
      `Manually approved by ${approver}`, prNumber);
  }

  /**
   * Check if Claude review is required for this commit
   * @param {string} sha - Commit SHA
   * @returns {Promise<boolean>} Whether review is required
   */
  async isReviewRequired(sha) {
    const claudeStatuses = await this.getClaudeStatusChecks(sha);

    // Review is required if:
    // 1. No review status exists, OR
    // 2. Review status is pending/failure, OR
    // 3. Approval status is pending/failure
    return !claudeStatuses.review ||
           claudeStatuses.review.state === CLAUDE_STATES.PENDING ||
           claudeStatuses.review.state === CLAUDE_STATES.FAILURE ||
           !claudeStatuses.approval ||
           claudeStatuses.approval.state === CLAUDE_STATES.PENDING ||
           claudeStatuses.approval.state === CLAUDE_STATES.FAILURE;
  }

  /**
   * Get workflow state summary
   * @param {string} sha - Commit SHA
   * @returns {Promise<Object>} State summary
   */
  async getWorkflowState(sha) {
    const claudeStatuses = await this.getClaudeStatusChecks(sha);

    return {
      review: {
        exists: !!claudeStatuses.review,
        state: claudeStatuses.review?.state || 'none',
        description: claudeStatuses.review?.description || 'No review status'
      },
      approval: {
        exists: !!claudeStatuses.approval,
        state: claudeStatuses.approval?.state || 'none',
        description: claudeStatuses.approval?.description || 'No approval status'
      },
      needsReview: await this.isReviewRequired(sha),
      canMerge: claudeStatuses.approval?.state === CLAUDE_STATES.SUCCESS
    };
  }

  /**
   * Reset all Claude statuses (for re-evaluation)
   * @param {string} sha - Commit SHA
   * @param {number} prNumber - PR number
   * @returns {Promise<void>}
   */
  async resetStatuses(sha, prNumber) {
    await this.setReviewStatus(sha, CLAUDE_STATES.PENDING, 'Waiting for Claude AI review...', prNumber);
    await this.setApprovalStatus(sha, CLAUDE_STATES.PENDING, 'Waiting for Claude AI review to complete', prNumber);
  }
}

/**
 * Utility functions for GitHub Actions integration
 */
class ClaudeStatusUtils {
  /**
   * Create status manager from environment variables
   * @returns {ClaudeStatusManager} Status manager instance
   */
  static fromEnvironment() {
    const githubToken = process.env.GITHUB_TOKEN;
    const repository = process.env.GITHUB_REPOSITORY;

    if (!repository) {
      throw new Error('GITHUB_REPOSITORY environment variable is required');
    }

    const [repoOwner, repoName] = repository.split('/');
    return new ClaudeStatusManager(githubToken, repoOwner, repoName);
  }

  /**
   * Get PR context from environment
   * @returns {Object} PR context
   */
  static getPRContext() {
    return {
      prNumber: process.env.PR_NUMBER || process.env.GITHUB_EVENT_NUMBER,
      sha: process.env.GITHUB_SHA,
      repository: process.env.GITHUB_REPOSITORY
    };
  }

  /**
   * Output status for GitHub Actions
   * @param {Object} state - Workflow state
   */
  static outputForGitHubActions(state) {
    console.log(`claude_review_state=${state.review.state}`);
    console.log(`claude_approval_state=${state.approval.state}`);
    console.log(`claude_needs_review=${state.needsReview}`);
    console.log(`claude_can_merge=${state.canMerge}`);
    console.log(`claude_review_exists=${state.review.exists}`);
    console.log(`claude_approval_exists=${state.approval.exists}`);
  }
}

module.exports = {
  ClaudeStatusManager,
  ClaudeStatusUtils,
  CLAUDE_STATES,
  STATUS_CONTEXTS
};

// CLI usage
if (require.main === module) {
  (async () => {
    try {
      const action = process.argv[2];
      const statusManager = ClaudeStatusUtils.fromEnvironment();
      const context = ClaudeStatusUtils.getPRContext();

      if (!context.prNumber || !context.sha) {
        throw new Error('PR number and SHA are required');
      }

      switch (action) {
        case 'start-review':
          await statusManager.handleReviewStart(context.sha, context.prNumber);
          break;
        case 'review-success':
          const hasBlocking = process.argv[3] === 'true';
          const recommendations = JSON.parse(process.argv[4] || '{}');
          await statusManager.handleReviewSuccess(context.sha, context.prNumber, hasBlocking, recommendations);
          break;
        case 'review-failure':
          const errorMsg = process.argv[3] || 'Claude AI review service unavailable';
          await statusManager.handleReviewFailure(context.sha, context.prNumber, errorMsg);
          break;
        case 'get-state':
          const state = await statusManager.getWorkflowState(context.sha);
          ClaudeStatusUtils.outputForGitHubActions(state);
          break;
        case 'reset':
          await statusManager.resetStatuses(context.sha, context.prNumber);
          break;
        default:
          console.error('Usage: node claude-status-manager.js <action> [args...]');
          console.error('Actions: start-review, review-success, review-failure, get-state, reset');
          process.exit(1);
      }

      Logger.success(`Status action '${action}' completed successfully`);
    } catch (error) {
      Logger.error(`Status action failed: ${error.message}`);
      process.exit(1);
    }
  })();
}
