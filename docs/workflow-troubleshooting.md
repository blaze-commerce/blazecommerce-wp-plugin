# GitHub Workflow Troubleshooting Guide

## 🚨 Quick Diagnosis

### Workflow Status Check

Run this quick diagnostic to identify common issues:

```bash
# Check workflow status
gh run list --limit 5

# Check specific workflow
gh run view [RUN_ID] --log

# Check repository variables
gh variable list

# Check secrets (names only)
gh secret list
```

## 🔍 Common Failure Patterns

### 1. Claude AI Service Failures

#### Pattern: All Claude AI attempts fail
```
❌ Claude AI Review (Attempt 1): failure
❌ Claude AI Review (Attempt 2): failure  
❌ Claude AI Review (Attempt 3): failure
```

**Root Causes**:
- Anthropic API service outage
- Invalid or expired `ANTHROPIC_API_KEY`
- Rate limiting or quota exceeded
- Network connectivity issues

**Diagnostic Steps**:
1. Check Anthropic service status: https://status.anthropic.com
2. Verify API key in repository secrets
3. Check workflow logs for specific error messages
4. Review API usage and rate limits

**Solutions**:
```yaml
# Immediate fixes:
1. Wait 15-30 minutes for service recovery
2. Retry by pushing a small commit
3. Check API key validity

# Long-term fixes:
1. Increase timeout values if needed
2. Implement additional retry logic
3. Set up service status monitoring
```

#### Pattern: Intermittent Claude AI failures
```
✅ Claude AI Review (Attempt 1): success (sometimes)
❌ Claude AI Review (Attempt 1): failure (sometimes)
```

**Root Causes**:
- API rate limiting
- Large PR size causing timeouts
- Temporary service degradation

**Solutions**:
```yaml
# Adjust configuration variables:
CLAUDE_REVIEW_TIMEOUT: 20        # Increase from default 15
CLAUDE_RETRY_TIMEOUT: 12         # Increase from default 8
CLAUDE_BACKOFF_DELAY: 6          # Increase from default 4

# PR optimization:
- Break large PRs into smaller chunks
- Use conventional commit messages
- Exclude unnecessary files from analysis
```

### 2. Token Permission Failures

#### Pattern: "Insufficient permissions" errors
```
❌ Error: Resource not accessible by integration
❌ Error: Must have admin rights to Repository
```

**Root Causes**:
- Missing required permissions on token
- Using wrong token type
- Repository branch protection conflicts

**Diagnostic Steps**:
```bash
# Check current token permissions
gh auth status

# Verify repository access
gh repo view [OWNER/REPO]

# Check branch protection rules
gh api repos/[OWNER]/[REPO]/branches/main/protection
```

**Solutions**:
```yaml
# Use BOT_GITHUB_TOKEN with these permissions:
- pull_requests: write
- contents: read  
- metadata: read
- statuses: write
- actions: read

# Or update github.token permissions in workflow:
permissions:
  contents: read
  pull-requests: write
  statuses: write
  checks: write
```

### 3. Priority Enforcement Issues

#### Pattern: Workflows running out of order
```
⚠️ Priority 2 started before Priority 1 completed
⚠️ Approval gate running without review completion
```

**Root Causes**:
- Dependency check job failing
- Concurrency group conflicts
- Workflow trigger timing issues

**Diagnostic Steps**:
1. Check `wait-for-claude-review` job logs
2. Verify workflow run timestamps
3. Review concurrency group naming

**Solutions**:
```yaml
# Ensure proper job dependencies:
jobs:
  claude-approval-gate:
    needs: [wait-for-claude-review]
    
# Check concurrency groups:
concurrency:
  group: priority-1-claude-review-pr-${{ github.event.pull_request.number }}
  cancel-in-progress: false
```

### 4. Version Calculation Errors

#### Pattern: Version bump fails or incorrect
```
❌ Error: Could not extract current version
❌ Error: Invalid version format
❌ Error: Version calculation failed
```

**Root Causes**:
- Inconsistent version formats across files
- Missing or corrupted package.json
- Invalid semantic version format

**Diagnostic Steps**:
```bash
# Check version consistency
grep -r "Version:" . --include="*.php"
cat package.json | jq .version
git tag --list | tail -5

# Validate version format
node -e "console.log(require('./package.json').version)"
```

**Solutions**:
```yaml
# Fix version format issues:
1. Ensure package.json has valid semver
2. Update plugin file version format
3. Use conventional commit messages
4. Check git tag format consistency

# Enhanced analysis scripts:
- Use .github/scripts/version-analyzer.js
- Use .github/scripts/commit-parser.js
- Enable enhanced commit analysis
```

## 🛠️ Advanced Troubleshooting

### Workflow Debugging Mode

Enable detailed debugging by adding this to any workflow:

```yaml
- name: Enable Debug Logging
  run: |
    echo "ACTIONS_STEP_DEBUG=true" >> $GITHUB_ENV
    echo "ACTIONS_RUNNER_DEBUG=true" >> $GITHUB_ENV
```

### Script Debugging

For the extracted JavaScript scripts:

```bash
# Test version analyzer locally
export CURRENT_VERSION="1.2.3"
export COMMIT_MESSAGES="feat: add new feature"
export CHANGED_FILES="src/main.js\nREADME.md"
node .github/scripts/version-analyzer.js

# Test commit parser locally
export COMMIT_MESSAGES="fix: resolve bug\nfeat: add feature"
node .github/scripts/commit-parser.js
```

### Performance Analysis

Monitor workflow performance:

```bash
# Get workflow timing data
gh run list --json conclusion,createdAt,updatedAt,durationMs

# Analyze step timing
gh run view [RUN_ID] --json jobs | jq '.jobs[].steps[] | {name, conclusion, startedAt, completedAt}'

# Check resource usage
gh run view [RUN_ID] --log | grep -E "(memory|cpu|disk)"
```

## 🔧 Recovery Procedures

### Emergency Workflow Bypass

If workflows are completely broken:

```bash
# 1. Disable workflow temporarily
gh workflow disable [WORKFLOW_NAME]

# 2. Manual PR approval
gh pr review [PR_NUMBER] --approve

# 3. Manual merge with admin override
gh pr merge [PR_NUMBER] --admin --squash

# 4. Manual version bump
npm version patch
git push --tags
```

### Workflow Reset Procedure

To reset workflows to a known good state:

```bash
# 1. Backup current configuration
cp -r .github/workflows .github/workflows.backup

# 2. Reset to last known good commit
git checkout [GOOD_COMMIT] -- .github/workflows/

# 3. Test with a simple PR
gh pr create --title "Test workflow reset" --body "Testing"

# 4. Monitor and validate
gh run list --limit 3
```

### Data Recovery

If version or release data is corrupted:

```bash
# Recover version from git tags
LAST_TAG=$(git describe --tags --abbrev=0)
echo "Last known version: $LAST_TAG"

# Rebuild version history
git log --oneline --grep="version" --since="1 month ago"

# Validate package.json consistency
jq '.version' package.json
grep "Version:" *.php
```

## 📊 Monitoring Setup

### Health Check Automation

Create a monitoring workflow:

```yaml
name: Workflow Health Check
on:
  schedule:
    - cron: '0 */6 * * *'  # Every 6 hours

jobs:
  health-check:
    runs-on: ubuntu-latest
    steps:
      - name: Check Workflow Success Rate
        run: |
          # Get recent workflow runs
          TOTAL=$(gh run list --limit 50 --json conclusion | jq length)
          SUCCESS=$(gh run list --limit 50 --json conclusion | jq '[.[] | select(.conclusion == "success")] | length')
          RATE=$((SUCCESS * 100 / TOTAL))
          
          echo "Success rate: $RATE%"
          if [ $RATE -lt 90 ]; then
            echo "⚠️ Success rate below threshold!"
            # Send alert
          fi
```

### Alert Configuration

Set up alerts for critical failures:

```yaml
- name: Send Alert on Critical Failure
  if: failure()
  uses: actions/github-script@v7
  with:
    script: |
      // Send Slack/Teams notification
      // Log to monitoring system
      // Create GitHub issue for tracking
```

## 📈 Performance Optimization

### Timeout Optimization

Analyze and optimize timeout values:

```bash
# Analyze actual execution times
gh run list --json durationMs,conclusion | jq '
  group_by(.conclusion) | 
  map({
    conclusion: .[0].conclusion,
    avg_duration: (map(.durationMs) | add / length),
    max_duration: (map(.durationMs) | max)
  })
'

# Recommended timeout adjustments based on analysis:
# - Set timeouts to 150% of average execution time
# - Add buffer for peak load periods
# - Consider repository size and complexity
```

### Resource Optimization

```yaml
# Use appropriate runner types
runs-on: ubuntu-latest-4-cores  # For CPU-intensive tasks
runs-on: ubuntu-latest-8-cores  # For very large repositories

# Optimize script execution
- name: Optimized Script Execution
  run: |
    # Use parallel processing where possible
    # Cache intermediate results
    # Minimize external API calls
```

---

**Last Updated**: 2025-07-13  
**Troubleshooting Version**: 2.0 (with enhanced diagnostics)  
**Support**: Create an issue with workflow logs for additional help
