# Claude AI Workflows Troubleshooting Guide

## Overview

This guide helps diagnose and resolve issues with the Claude AI review and approval gate workflows. Use this when the Claude AI system is not behaving as expected.

## Quick Diagnosis

### Check Current Status
1. Go to your PR page
2. Look at the status checks section
3. Check for these status contexts:
   - `claude-ai/review` - Claude review progress
   - `claude-ai/approval-required` - Approval gate status

### Status Check States
- **🟡 Pending**: Workflow is running or waiting
- **✅ Success**: Workflow completed successfully
- **❌ Failure**: Workflow failed or manual review required
- **⚪ No Status**: Workflow hasn't run yet

## Common Issues and Solutions

### 1. Claude Review Stuck in "Pending"

**Symptoms:**
- `claude-ai/review` shows "Pending" for more than 15 minutes
- No Claude AI comment appears on the PR

**Possible Causes:**
- Claude AI API service is down
- ANTHROPIC_API_KEY is invalid or missing
- Workflow is queued behind other jobs

**Solutions:**
1. **Check workflow logs:**
   ```
   Go to Actions tab → Find "Priority 1: Claude AI PR Review" → Check logs
   ```

2. **Manually trigger re-evaluation:**
   ```
   Comment "@claude" on the PR to trigger re-evaluation
   ```

3. **Check API key:**
   ```
   Verify ANTHROPIC_API_KEY secret is set and valid
   ```

### 2. Approval Gate Not Updating After New Push

**Symptoms:**
- Made changes to address Claude recommendations
- Pushed new commits
- Approval gate still shows "blocked" status

**Possible Causes:**
- Approval gate workflow didn't trigger
- Claude review workflow failed on new commit
- Status checks not properly updated

**Solutions:**
1. **Trigger manual re-evaluation:**
   ```
   Comment "@claude" on the PR
   ```

2. **Check recent workflow runs:**
   ```
   Actions tab → Look for recent "Priority 2: Claude AI Approval Gate" runs
   ```

3. **Verify triggers:**
   ```
   Ensure the push triggered both Claude review and approval gate workflows
   ```

### 3. Manual Review Required Message

**Symptoms:**
- Status shows "Manual review required - Claude AI service unavailable"
- Claude AI review failed multiple times

**Possible Causes:**
- Claude AI API is temporarily unavailable
- Network connectivity issues
- API rate limits exceeded

**Solutions:**
1. **Wait and retry:**
   ```
   Wait 10-15 minutes, then comment "@claude" to retry
   ```

2. **Check service status:**
   ```
   Verify Anthropic API service status
   ```

3. **Manual approval:**
   ```
   A maintainer can manually approve the PR if Claude AI is unavailable
   ```

### 4. @claude Mention Not Working

**Symptoms:**
- Commented "@claude" but no response
- Approval gate didn't re-evaluate

**Possible Causes:**
- Comment trigger not configured
- Insufficient permissions
- PR is not in open state

**Solutions:**
1. **Check PR state:**
   ```
   Ensure PR is open (not draft or closed)
   ```

2. **Use exact mention:**
   ```
   Comment exactly "@claude" (case-sensitive)
   ```

3. **Check workflow permissions:**
   ```
   Verify BOT_GITHUB_TOKEN has necessary permissions
   ```

### 5. Status Checks Not Appearing

**Symptoms:**
- No Claude AI status checks visible on PR
- Workflows run but don't create status checks

**Possible Causes:**
- Missing BOT_GITHUB_TOKEN
- Insufficient token permissions
- Branch protection rules not configured

**Solutions:**
1. **Check token configuration:**
   ```
   Verify BOT_GITHUB_TOKEN secret exists and has 'statuses:write' permission
   ```

2. **Update branch protection:**
   ```
   Add required status checks:
   - claude-ai/approval-required
   - Priority 1: Claude AI PR Review / claude-review
   ```

## Debugging Steps

### 1. Check Workflow Logs

1. Go to **Actions** tab in GitHub
2. Find the relevant workflow run
3. Click on the failed job
4. Look for error messages in the logs

**Key log messages to look for:**
- "🔄 Initializing Claude AI review status..."
- "✅ Claude AI Review succeeded on attempt X"
- "❌ All Claude AI Review attempts failed"
- "📊 Final result: approved=X, state=X, reason=X"

### 2. Verify Environment Variables

Check that these are properly configured:
- `ANTHROPIC_API_KEY`: Claude AI API key
- `BOT_GITHUB_TOKEN`: Enhanced GitHub token
- `GITHUB_REPOSITORY`: Repository name (auto-set)

### 3. Test Status Manager

Run the test script to verify status management:
```bash
node .github/scripts/test-claude-workflows.js
```

### 4. Manual Status Check

Use the status manager CLI to check current state:
```bash
# Set environment variables
export GITHUB_TOKEN="your-token"
export GITHUB_REPOSITORY="owner/repo"
export PR_NUMBER="123"
export GITHUB_SHA="commit-sha"

# Check current state
node .github/scripts/claude-status-manager.js get-state
```

## Advanced Troubleshooting

### Workflow Dependency Issues

If approval gate runs before Claude review completes:

1. **Check concurrency groups:**
   ```yaml
   concurrency:
     group: priority-1-claude-review-pr-${{ github.event.pull_request.number }}
   ```

2. **Verify workflow triggers:**
   ```yaml
   on:
     workflow_run:
       workflows: ["Priority 1: Claude AI PR Review"]
       types: [completed]
   ```

### API Rate Limiting

If hitting GitHub API limits:

1. **Check rate limit status:**
   ```bash
   curl -H "Authorization: token $GITHUB_TOKEN" \
        https://api.github.com/rate_limit
   ```

2. **Implement backoff:**
   - Workflows include automatic retry logic
   - Wait periods between API calls

### Permission Issues

If workflows fail with permission errors:

1. **Verify token scopes:**
   - `contents:read`
   - `pull-requests:write`
   - `statuses:write`
   - `issues:write`

2. **Check repository settings:**
   - Actions permissions enabled
   - Workflow permissions configured

## Emergency Procedures

### Bypass Claude AI Temporarily

If Claude AI is completely unavailable:

1. **Disable branch protection temporarily:**
   ```
   Repository Settings → Branches → Edit protection rule
   Uncheck "claude-ai/approval-required"
   ```

2. **Manual approval process:**
   ```
   Have maintainers manually review and approve PRs
   Re-enable protection when Claude AI is restored
   ```

### Reset Workflow State

If workflows are in an inconsistent state:

1. **Reset status checks:**
   ```bash
   node .github/scripts/claude-status-manager.js reset
   ```

2. **Re-trigger workflows:**
   ```
   Close and reopen the PR, or
   Comment "@claude" to trigger re-evaluation
   ```

## Getting Help

### Log Collection

When reporting issues, include:
1. PR number and repository
2. Workflow run URLs
3. Error messages from logs
4. Current status check states
5. Timeline of actions taken

### Contact Information

- **GitHub Issues**: Create issue in the repository
- **Internal Support**: Contact the BlazeCommerce development team
- **Documentation**: Refer to `docs/claude-ai-approval-gate-fixes.md`

---

*This troubleshooting guide covers common issues and solutions for the Claude AI workflow system. Keep this document updated as new issues are discovered and resolved.*
