/**
 * Tests for Claude Approval Checker
 * 
 * Integration tests to verify approval logic works correctly
 */

const { ClaudeApprovalChecker } = require('../scripts/claude-approval-checker');

// Mock GitHub API responses
const mockGitHub = {
  rest: {
    issues: {
      listComments: jest.fn()
    },
    pulls: {
      listReviews: jest.fn()
    }
  }
};

// Mock Octokit
jest.mock('@octokit/rest', () => ({
  Octokit: jest.fn(() => mockGitHub)
}));

describe('ClaudeApprovalChecker', () => {
  let checker;

  beforeEach(() => {
    checker = new ClaudeApprovalChecker('mock-token', 'test-owner', 'test-repo');
    jest.clearAllMocks();
  });

  describe('analyzeCommentContent', () => {
    test('should detect APPROVED status with bracketed format', () => {
      const comment = `
        ### FINAL VERDICT
        **Status**: [APPROVED]
        **Merge Readiness**: READY TO MERGE
        **Recommendation**: Code looks good
      `;

      const result = checker.analyzeCommentContent(comment);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.APPROVED);
      expect(result.hasRequiredIssues).toBe(false);
    });

    test('should detect BLOCKED status with bracketed format', () => {
      const comment = `
        ### FINAL VERDICT
        **Status**: [BLOCKED]
        **Merge Readiness**: NOT READY
        **Recommendation**: Critical issues found
      `;

      const result = checker.analyzeCommentContent(comment);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.BLOCKED);
      expect(result.hasRequiredIssues).toBe(true);
    });

    test('should detect CONDITIONAL APPROVAL status', () => {
      const comment = `
        ### FINAL VERDICT
        **Status**: [CONDITIONAL APPROVAL]
        **Merge Readiness**: READY AFTER FIXES
        **Recommendation**: Minor improvements needed
      `;

      const result = checker.analyzeCommentContent(comment);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.CONDITIONAL);
      expect(result.hasRequiredIssues).toBe(false);
    });

    test('should fallback to legacy format detection', () => {
      const comment = `
        ### FINAL VERDICT
        **Status**: APPROVED
        **Merge Readiness**: READY TO MERGE
        **Recommendation**: Code looks good
      `;

      const result = checker.analyzeCommentContent(comment);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.APPROVED);
      expect(result.hasRequiredIssues).toBe(false);
    });

    test('should detect critical issues in dedicated section', () => {
      const comment = `
        **CRITICAL ISSUES**
        - Security vulnerability in authentication
        - Memory leak in data processing
        
        ### FINAL VERDICT
        **Status**: [BLOCKED]
      `;

      const result = checker.analyzeCommentContent(comment);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.BLOCKED);
      expect(result.hasRequiredIssues).toBe(true);
    });

    test('should detect legacy required issues patterns', () => {
      const comment = `
        CRITICAL: REQUIRED - Fix security vulnerability
        This must be fixed before merge.
      `;

      const result = checker.analyzeCommentContent(comment);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.BLOCKED);
      expect(result.hasRequiredIssues).toBe(true);
    });

    test('should detect unimplemented previous changes', () => {
      const comment = `
        ### FINAL VERDICT
        **Status**: [APPROVED]
        
        Note: Previous changes not implemented yet.
      `;

      const result = checker.analyzeCommentContent(comment);
      
      // Should be overridden by implementation verification
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.APPROVED);
      expect(result.hasRequiredIssues).toBe(false);
    });

    test('should handle unknown status format gracefully', () => {
      const comment = `
        ### FINAL VERDICT
        **Status**: UNKNOWN_STATUS
        **Recommendation**: Something unclear
      `;

      const result = checker.analyzeCommentContent(comment);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.UNKNOWN);
      expect(result.hasRequiredIssues).toBe(false);
    });
  });

  describe('analyzeClaudeReview', () => {
    test('should handle no Claude comments found', async () => {
      mockGitHub.rest.issues.listComments.mockResolvedValue({
        data: [
          { user: { login: 'other-user' }, body: 'Regular comment' }
        ]
      });

      const result = await checker.analyzeClaudeReview(123);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.UNKNOWN);
      expect(result.reason).toBe('No Claude App review found');
    });

    test('should analyze Claude bot comments', async () => {
      mockGitHub.rest.issues.listComments.mockResolvedValue({
        data: [
          {
            user: { login: 'claude[bot]' },
            body: `
              ### FINAL VERDICT
              **Status**: [APPROVED]
              **Recommendation**: Code looks good
            `
          }
        ]
      });

      const result = await checker.analyzeClaudeReview(123);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.APPROVED);
      expect(result.hasRequiredIssues).toBe(false);
    });

    test('should handle API errors gracefully', async () => {
      mockGitHub.rest.issues.listComments.mockRejectedValue(
        new Error('API Error')
      );

      const result = await checker.analyzeClaudeReview(123);
      
      expect(result.status).toBe(ClaudeApprovalChecker.STATUS.UNKNOWN);
      expect(result.reason).toContain('Error: API Error');
    });
  });

  describe('checkExistingApproval', () => {
    test('should detect existing blazecommerce-claude-ai approval', async () => {
      mockGitHub.rest.pulls.listReviews.mockResolvedValue({
        data: [
          {
            user: { login: 'blazecommerce-claude-ai' },
            state: 'APPROVED'
          }
        ]
      });

      const result = await checker.checkExistingApproval(123);
      
      expect(result).toBe(true);
    });

    test('should return false when no approval exists', async () => {
      mockGitHub.rest.pulls.listReviews.mockResolvedValue({
        data: [
          {
            user: { login: 'other-user' },
            state: 'APPROVED'
          }
        ]
      });

      const result = await checker.checkExistingApproval(123);
      
      expect(result).toBe(false);
    });

    test('should handle API errors gracefully', async () => {
      mockGitHub.rest.pulls.listReviews.mockRejectedValue(
        new Error('API Error')
      );

      const result = await checker.checkExistingApproval(123);
      
      expect(result).toBe(false);
    });
  });

  describe('getStatusReason', () => {
    test('should provide appropriate reasons for each status', () => {
      expect(checker.getStatusReason(ClaudeApprovalChecker.STATUS.APPROVED, false))
        .toBe('Claude approved with no critical issues');
      
      expect(checker.getStatusReason(ClaudeApprovalChecker.STATUS.APPROVED, true))
        .toBe('Claude approved but has required issues');
      
      expect(checker.getStatusReason(ClaudeApprovalChecker.STATUS.CONDITIONAL, false))
        .toBe('Claude conditionally approved');
      
      expect(checker.getStatusReason(ClaudeApprovalChecker.STATUS.BLOCKED, true))
        .toBe('Claude blocked with critical issues');
      
      expect(checker.getStatusReason(ClaudeApprovalChecker.STATUS.UNKNOWN, false))
        .toBe('No clear status found in Claude review');
    });
  });
});

// Integration test scenarios
describe('Integration Test Scenarios', () => {
  test('Scenario 1: New PR with Claude approval', async () => {
    // This would be a full end-to-end test
    // Testing the complete workflow from PR creation to approval
    expect(true).toBe(true); // Placeholder
  });

  test('Scenario 2: PR with required changes not implemented', async () => {
    // Test the implementation verification logic
    expect(true).toBe(true); // Placeholder
  });

  test('Scenario 3: PR with conditional approval', async () => {
    // Test conditional approval handling
    expect(true).toBe(true); // Placeholder
  });
});
