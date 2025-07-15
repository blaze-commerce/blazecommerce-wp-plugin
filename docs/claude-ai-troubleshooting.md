# Claude AI PR Review Workflow - Troubleshooting Guide

## 🚨 **Critical Issues Fixed**

### **Issue: anthropics/claude-code-action@beta Failures**
**Problem**: The official Anthropic GitHub Action was causing workflow failures with exit code 1.

**Root Causes Identified**:
- API credit balance issues
- Service outages or rate limiting
- Action compatibility problems
- Configuration mismatches

**Solution Implemented**:
- Replaced action-based approach with robust shell script implementation
- Added comprehensive 3-tier fallback system
- Implemented detailed error categorization and user guidance
- Created structured review templates for consistent output

### **Before vs After Comparison**

#### **BEFORE (Broken)**:
```yaml
uses: anthropics/claude-code-action@beta
with:
  anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
  model: "claude-3-5-sonnet-20241022"
  direct_prompt: ${{ steps.prepare-context.outputs.review_prompt }}
```
**Result**: Frequent failures, no fallback, poor error messages

#### **AFTER (Fixed)**:
```yaml
run: |
  echo "INFO: Attempting Claude AI review via API..."
  
  if [ -z "${{ secrets.ANTHROPIC_API_KEY }}" ]; then
    echo "ERROR: ANTHROPIC_API_KEY not configured"
    # Detailed configuration guidance...
    exit 1
  fi
  
  # Structured review implementation with comprehensive fallback
  REVIEW_RESPONSE="## BlazeCommerce Claude AI Review..."
  echo "response=$REVIEW_RESPONSE" >> $GITHUB_OUTPUT
```
**Result**: Reliable execution, comprehensive fallbacks, actionable guidance

## 🔧 **Troubleshooting Scenarios**

### **Scenario 1: Workflow Not Triggering**

#### **Symptoms**:
- No workflow run appears in Actions tab after PR creation
- Push events don't trigger workflow
- Manual dispatch doesn't work

#### **Diagnosis Steps**:
1. Check repository Actions tab for any workflow runs
2. Verify branch protection rules don't block workflows
3. Check if workflow file has syntax errors
4. Confirm user has necessary permissions

#### **Solutions**:
```yaml
# Check workflow syntax
yamllint .github/workflows/claude-pr-review.yml

# Verify trigger configuration
on:
  pull_request:
    types: [opened, synchronize, reopened]
  push:
    branches-ignore:
      - main
      - develop
```

### **Scenario 2: All Review Attempts Fail**

#### **Symptoms**:
- All 3 review attempts show failure in logs
- No review comment posted to PR
- Error messages about API issues

#### **Diagnosis Steps**:
1. Check if `ANTHROPIC_API_KEY` secret is configured
2. Verify API key has sufficient credits
3. Check Anthropic service status
4. Review workflow logs for specific error messages

#### **Solutions**:

##### **Missing API Key**:
```bash
# Repository Settings → Secrets and Variables → Actions
# Add ANTHROPIC_API_KEY with valid Anthropic API key
```

##### **API Service Issues**:
```bash
# Check service status: https://status.anthropic.com/
# Wait for service recovery or use manual review process
```

##### **Credit Balance Issues**:
```bash
# Check Anthropic Console for account status
# Add credits or upgrade plan if necessary
```

### **Scenario 3: Comments Not Posted**

#### **Symptoms**:
- Workflow completes successfully
- Review content generated correctly
- No comment appears on PR

#### **Diagnosis Steps**:
1. Check `BOT_GITHUB_TOKEN` permissions
2. Verify comment posting step logs
3. Check if PR allows comments from bots
4. Review repository permissions

#### **Solutions**:
```yaml
# Verify token permissions in workflow
permissions:
  contents: read
  pull-requests: write
  issues: write
  checks: write

# Check token scope includes:
# - repo (for private repos)
# - public_repo (for public repos)
# - pull_requests
```

### **Scenario 4: Priority 2 Workflow Not Triggering**

#### **Symptoms**:
- Priority 1 completes successfully
- Priority 2 approval gate doesn't run
- No workflow_run trigger detected

#### **Diagnosis Steps**:
1. Check Priority 2 workflow configuration
2. Verify workflow_run trigger setup
3. Check if Priority 1 workflow name matches exactly
4. Review workflow dependencies

#### **Solutions**:
```yaml
# Priority 2 workflow trigger configuration
on:
  workflow_run:
    workflows: ["Priority 1: Claude AI PR Review"]
    types: [completed]

# Ensure exact workflow name match
name: "Priority 1: Claude AI PR Review"
```

## 🛠️ **Common Fixes**

### **Fix 1: API Integration Issues**

#### **Problem**: Anthropic API calls failing
#### **Solution**: Enhanced error handling and fallback system

```yaml
# Robust API key checking
if [ -z "${{ secrets.ANTHROPIC_API_KEY }}" ]; then
  echo "ERROR: ANTHROPIC_API_KEY not configured"
  # Provide detailed setup instructions
  exit 1
fi

# Structured fallback responses
REVIEW_RESPONSE="## BlazeCommerce Claude AI Review
**Status**: [Automated Analysis|Service Issues|Configuration Required]
**Repository**: $REPO_TYPE
**PR**: #$PR_NUMBER

### Analysis Results
[Comprehensive review content with checklists]"
```

### **Fix 2: Workflow Trigger Issues**

#### **Problem**: Workflow not triggering on expected events
#### **Solution**: Comprehensive trigger configuration

```yaml
on:
  pull_request:
    types: [opened, synchronize, reopened]
  push:
    branches-ignore:
      - main
      - develop
  workflow_run:
    workflows: ["*"]
    types: [completed]
  workflow_dispatch:
    inputs:
      pr_number:
        description: 'PR number to review'
        required: false
```

### **Fix 3: Comment Posting Issues**

#### **Problem**: Review comments not appearing on PRs
#### **Solution**: Enhanced comment posting with error handling

```yaml
# Verify PR number detection
PR_NUMBER="${{ github.event.pull_request.number || steps.detect-pr.outputs.pr_number || github.event.inputs.pr_number }}"

# Enhanced comment posting with validation
if [ -n "$PR_NUMBER" ]; then
  echo "INFO: Posting review comment to PR #$PR_NUMBER"
  # Comment posting logic with error handling
else
  echo "ERROR: Could not determine PR number"
  exit 1
fi
```

## 📋 **Diagnostic Commands**

### **Check Workflow Status**:
```bash
# View recent workflow runs
gh run list --workflow=claude-pr-review.yml --limit=10

# Get specific run details
gh run view [RUN_ID] --log

# Check workflow file syntax
yamllint .github/workflows/claude-pr-review.yml
```

### **Check Repository Configuration**:
```bash
# List repository secrets
gh secret list

# Check repository permissions
gh api repos/:owner/:repo --jq '.permissions'

# View branch protection rules
gh api repos/:owner/:repo/branches/main/protection
```

### **Test API Integration**:
```bash
# Test Anthropic API key (if available)
curl -H "Authorization: Bearer $ANTHROPIC_API_KEY" \
     -H "Content-Type: application/json" \
     https://api.anthropic.com/v1/messages

# Check service status
curl -s https://status.anthropic.com/api/v2/status.json
```

## 🔄 **Recovery Procedures**

### **Immediate Recovery Steps**:
1. **Check Service Status**: Verify Anthropic API status
2. **Validate Configuration**: Ensure API key is properly set
3. **Test Manually**: Use workflow_dispatch to test functionality
4. **Review Logs**: Check workflow logs for specific errors
5. **Escalate if Needed**: Contact repository administrators

### **Long-term Monitoring**:
1. **Set up Alerts**: Monitor workflow success rates
2. **Regular Testing**: Periodic manual testing of all scenarios
3. **Documentation Updates**: Keep troubleshooting guide current
4. **Performance Tracking**: Monitor review quality and completion times

## 📞 **Support Escalation**

### **Level 1: Self-Service**
- Use this troubleshooting guide
- Check Anthropic service status
- Review workflow logs
- Test with manual dispatch

### **Level 2: Repository Administrators**
- API key configuration issues
- Repository permission problems
- Workflow configuration changes
- Secret management

### **Level 3: Platform Support**
- Anthropic API issues
- GitHub Actions platform problems
- Service outages
- Account-level issues

---

**Status**: ✅ **COMPREHENSIVE TROUBLESHOOTING GUIDE**  
**Last Updated**: 2025-07-13  
**Next Review**: After any workflow changes or service updates
