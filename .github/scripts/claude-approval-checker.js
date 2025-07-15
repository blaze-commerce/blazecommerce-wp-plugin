#!/usr/bin/env node

/**
 * Claude AI Approval Checker
 * 
 * Extracts complex approval logic from the workflow for better maintainability.
 * This script analyzes Claude's review comments and determines approval status.
 */

const { Octokit } = require('@octokit/rest');

class ClaudeApprovalChecker {
  constructor(token, owner, repo) {
    this.github = new Octokit({ auth: token });
    this.owner = owner;
    this.repo = repo;
  }

  /**
   * Approval detection patterns configuration
   */
  static get PATTERNS() {
    return {
      // Primary detection for new bracketed format
      FINAL_VERDICT_BRACKETED: /### FINAL VERDICT[\s\S]*?\*\*Status\*\*:\s*\[([^\]]+)\]/i,
      
      // Fallback to old format
      FINAL_VERDICT_LEGACY: /### FINAL VERDICT[\s\S]*?\*\*Status\*\*:\s*([^*\n\[]+)/i,
      
      // Critical issues detection
      CRITICAL_ISSUES: /\*\*CRITICAL ISSUES\*\*([\s\S]*?)(?=\*\*STRENGTHS\*\*|\*\*AREAS FOR IMPROVEMENT\*\*|\*\*PERFORMANCE CONSIDERATIONS\*\*|\*\*SECURITY ASSESSMENT\*\*|###|$)/i,
      
      // Legacy required issues patterns
      REQUIRED_ISSUES: /CRITICAL:\s*REQUIRED|REQUIRED.*issues?|must\s+be\s+fixed|critical\s+bugs?/i,
      
      // Approval indicators
      APPROVAL_INDICATORS: /no\s+critical\s+issues|ready\s+to\s+merge|looks\s+good/i,
      
      // Implementation verification
      UNIMPLEMENTED_CHANGES: /previous.*changes.*not.*implemented|required.*changes.*missing|still.*need.*to.*address/i
    };
  }

  /**
   * Status types
   */
  static get STATUS() {
    return {
      APPROVED: 'approved',
      CONDITIONAL: 'conditional', 
      BLOCKED: 'blocked',
      UNKNOWN: 'unknown'
    };
  }

  /**
   * Analyze Claude's review comments and determine approval status
   */
  async analyzeClaudeReview(prNumber) {
    try {
      console.log(`INFO: Analyzing Claude review for PR #${prNumber}`);

      // Get all comments for the PR
      const comments = await this.github.rest.issues.listComments({
        owner: this.owner,
        repo: this.repo,
        issue_number: prNumber,
        per_page: 100
      });

      // Filter for Claude App comments
      const claudeAppComments = comments.data.filter(comment =>
        comment.user.login === 'claude[bot]' ||
        comment.user.login === 'claude' ||
        (comment.body && comment.body.includes('Claude AI') && comment.body.includes('review'))
      );

      if (claudeAppComments.length === 0) {
        console.log('INFO: No Claude App review found yet');
        return {
          status: ClaudeApprovalChecker.STATUS.UNKNOWN,
          hasRequiredIssues: false,
          reason: 'No Claude App review found'
        };
      }

      console.log(`Found ${claudeAppComments.length} Claude App comment(s)`);

      // Analyze the most recent Claude comment
      let hasRequiredIssues = false;
      let claudeApprovalStatus = ClaudeApprovalChecker.STATUS.UNKNOWN;
      let claudeReviewContent = '';

      for (const comment of claudeAppComments) {
        claudeReviewContent += comment.body + '\n\n';
        
        const analysis = this.analyzeCommentContent(comment.body);
        claudeApprovalStatus = analysis.status;
        hasRequiredIssues = analysis.hasRequiredIssues || hasRequiredIssues;
      }

      // Additional validation for implementation verification
      if (claudeApprovalStatus === ClaudeApprovalChecker.STATUS.APPROVED) {
        const hasUnimplementedChanges = ClaudeApprovalChecker.PATTERNS.UNIMPLEMENTED_CHANGES.test(claudeReviewContent);
        if (hasUnimplementedChanges) {
          claudeApprovalStatus = ClaudeApprovalChecker.STATUS.BLOCKED;
          hasRequiredIssues = true;
          console.log('Detected unimplemented previous changes - overriding to blocked status');
        }
      }

      return {
        status: claudeApprovalStatus,
        hasRequiredIssues,
        reason: this.getStatusReason(claudeApprovalStatus, hasRequiredIssues),
        reviewContent: claudeReviewContent.substring(0, 1000) + '...'
      };

    } catch (error) {
      console.error(`ERROR: Failed to analyze Claude review: ${error.message}`);
      return {
        status: ClaudeApprovalChecker.STATUS.UNKNOWN,
        hasRequiredIssues: false,
        reason: `Error: ${error.message}`
      };
    }
  }

  /**
   * Analyze individual comment content
   */
  analyzeCommentContent(commentBody) {
    let status = ClaudeApprovalChecker.STATUS.UNKNOWN;
    let hasRequiredIssues = false;

    // Check for standardized Final Verdict section with enhanced bracketed format detection
    let finalVerdictMatch = commentBody.match(ClaudeApprovalChecker.PATTERNS.FINAL_VERDICT_BRACKETED);
    
    // Fallback to old format if new bracketed format not found
    if (!finalVerdictMatch) {
      finalVerdictMatch = commentBody.match(ClaudeApprovalChecker.PATTERNS.FINAL_VERDICT_LEGACY);
    }
    
    if (finalVerdictMatch) {
      const statusText = finalVerdictMatch[1].trim();
      console.log(`Found Final Verdict status: ${statusText}`);

      // Handle bracketed format [APPROVED] or plain text APPROVED
      if (statusText === 'APPROVED' || (statusText.includes('APPROVED') && !statusText.includes('CONDITIONAL'))) {
        status = ClaudeApprovalChecker.STATUS.APPROVED;
        console.log('Claude approved the PR');
      } else if (statusText === 'BLOCKED' || statusText.includes('BLOCKED')) {
        status = ClaudeApprovalChecker.STATUS.BLOCKED;
        hasRequiredIssues = true;
        console.log('Claude blocked the PR');
      } else if (statusText === 'CONDITIONAL APPROVAL' || statusText.includes('CONDITIONAL APPROVAL')) {
        status = ClaudeApprovalChecker.STATUS.CONDITIONAL;
        console.log('Claude conditionally approved the PR');
      } else {
        console.log(`Unknown status format: ${statusText}`);
      }
    }

    // Check for Critical Issues section content
    const criticalIssuesMatch = commentBody.match(ClaudeApprovalChecker.PATTERNS.CRITICAL_ISSUES);
    if (criticalIssuesMatch) {
      const criticalContent = criticalIssuesMatch[1].trim();
      // Check if there's actual content (not just empty lines or dashes)
      if (criticalContent && criticalContent.length > 10 && !criticalContent.match(/^[\s\-_]*$/)) {
        hasRequiredIssues = true;
        console.log('Found content in Critical Issues section');
      }
    }

    // Fallback: Check for legacy patterns if standardized format not found
    if (status === ClaudeApprovalChecker.STATUS.UNKNOWN) {
      if (ClaudeApprovalChecker.PATTERNS.REQUIRED_ISSUES.test(commentBody)) {
        hasRequiredIssues = true;
        status = ClaudeApprovalChecker.STATUS.BLOCKED;
        console.log('Found REQUIRED issues using legacy pattern matching');
      } else if (ClaudeApprovalChecker.PATTERNS.APPROVAL_INDICATORS.test(commentBody)) {
        status = ClaudeApprovalChecker.STATUS.APPROVED;
        console.log('Found approval indicators using legacy pattern matching');
      }
    }

    return { status, hasRequiredIssues };
  }

  /**
   * Get human-readable reason for status
   */
  getStatusReason(status, hasRequiredIssues) {
    switch (status) {
      case ClaudeApprovalChecker.STATUS.APPROVED:
        return hasRequiredIssues ? 
          'Claude approved but has required issues' : 
          'Claude approved with no critical issues';
      case ClaudeApprovalChecker.STATUS.CONDITIONAL:
        return 'Claude conditionally approved';
      case ClaudeApprovalChecker.STATUS.BLOCKED:
        return 'Claude blocked with critical issues';
      default:
        return 'No clear status found in Claude review';
    }
  }

  /**
   * Check if @blazecommerce-claude-ai has already approved
   */
  async checkExistingApproval(prNumber) {
    try {
      const reviews = await this.github.rest.pulls.listReviews({
        owner: this.owner,
        repo: this.repo,
        pull_number: prNumber,
        per_page: 100
      });

      const existingApproval = reviews.data.find(review =>
        review.user.login === 'blazecommerce-claude-ai' && review.state === 'APPROVED'
      );

      return !!existingApproval;
    } catch (error) {
      console.error(`ERROR: Failed to check existing approval: ${error.message}`);
      return false;
    }
  }
}

// Export for use in workflows
module.exports = { ClaudeApprovalChecker };

// CLI usage
if (require.main === module) {
  const token = process.env.GITHUB_TOKEN;
  const repo = process.env.GITHUB_REPOSITORY;
  const prNumber = process.env.PR_NUMBER;

  if (!token || !repo || !prNumber) {
    console.error('ERROR: Missing required environment variables');
    console.error('Required: GITHUB_TOKEN, GITHUB_REPOSITORY, PR_NUMBER');
    process.exit(1);
  }

  const [owner, repoName] = repo.split('/');
  const checker = new ClaudeApprovalChecker(token, owner, repoName);

  checker.analyzeClaudeReview(parseInt(prNumber))
    .then(result => {
      console.log('ANALYSIS RESULT:', JSON.stringify(result, null, 2));
      
      // Set GitHub Actions outputs
      console.log(`::set-output name=status::${result.status}`);
      console.log(`::set-output name=has_required_issues::${result.hasRequiredIssues}`);
      console.log(`::set-output name=reason::${result.reason}`);
    })
    .catch(error => {
      console.error('ERROR:', error.message);
      process.exit(1);
    });
}
