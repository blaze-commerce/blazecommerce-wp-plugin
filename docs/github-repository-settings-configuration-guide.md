# GitHub Repository Settings Configuration Guide

## Overview

This guide provides specific configuration steps for GitHub repository settings to ensure optimal functionality of the implemented workflow fixes for the BlazeCommerce WordPress plugin repository.

---

## 1. Repository Settings Analysis

### 1.1 Branch Protection Rules Configuration

**Current Workflow Names to Configure:**
- `Tests` (main test workflow)
- `Claude AI Approval Gate` (approval workflow)
- `Auto Version` (version management)
- `Create Release` (release workflow)

**Recommended Branch Protection Settings:**

#### For `main` branch:
```yaml
Required Status Checks:
  - Tests / health-check
  - Tests / run-tests
  - Claude AI Approval Gate / claude-approval
  
Require branches to be up to date: ✅ Enabled
Require status checks to pass: ✅ Enabled
Require conversation resolution: ✅ Enabled
Restrict pushes that create files: ❌ Disabled (for auto-version)
```

#### For `develop` branch:
```yaml
Required Status Checks:
  - Tests / health-check
  - Tests / run-tests
  
Require branches to be up to date: ✅ Enabled
Require status checks to pass: ✅ Enabled
Allow force pushes: ❌ Disabled
```

**Configuration Steps:**
1. Navigate to **Settings** → **Branches**
2. Click **Add rule** or edit existing rule for `main`
3. Configure status checks as listed above
4. Repeat for `develop` branch

### 1.2 Required Status Checks Update

**Old Status Checks to Remove:**
- Any priority-based workflow checks
- Complex multi-stage approval checks
- Deprecated workflow names

**New Status Checks to Add:**
```
✅ Tests / health-check
✅ Tests / run-tests  
✅ Claude AI Approval Gate / claude-approval (for main branch only)
✅ Auto Version / version-check (optional)
```

### 1.3 Repository Variables Configuration

**Required Variables:**

| Variable Name | Value | Description |
|---------------|-------|-------------|
| `TEST_TIMEOUT` | `20` | Test execution timeout in minutes |
| `CIRCUIT_BREAKER_TIMEOUT` | `300` | Circuit breaker timeout in seconds |
| `HEALTH_CHECK_RETRIES` | `3` | Health check retry attempts |
| `WORDPRESS_VERSION` | `latest` | WordPress version for testing |
| `PHP_VERSION` | `8.1` | PHP version for testing |

**Configuration Steps:**
1. Navigate to **Settings** → **Secrets and variables** → **Actions**
2. Click **Variables** tab
3. Add each variable with specified values

---

## 2. GitHub Actions Configuration

### 2.1 Workflow Permissions Verification

**Required Permissions by Workflow:**

#### Tests Workflow:
```yaml
permissions:
  contents: read
  actions: read
  checks: write
```

#### Claude Approval Gate:
```yaml
permissions:
  contents: read
  pull-requests: write
  actions: read
  issues: read
```

#### Auto Version:
```yaml
permissions:
  contents: write
  pull-requests: write
  actions: read
```

#### Release Workflow:
```yaml
permissions:
  contents: write
  releases: write
```

### 2.2 Repository Secrets Configuration

**Required Secrets:**

| Secret Name | Purpose | Configuration |
|-------------|---------|---------------|
| `BC_GITHUB_APP_ID` | GitHub App ID for Claude approval | App ID number |
| `BC_GITHUB_APP_PRIVATE_KEY` | GitHub App private key | PEM format private key |
| `GITHUB_TOKEN` | Default GitHub token | Auto-provided by GitHub |

**Optional Secrets for Enhanced Functionality:**
| Secret Name | Purpose | Configuration |
|-------------|---------|---------------|
| `CLAUDE_API_KEY` | Direct Claude API access | Anthropic API key |
| `MYSQL_ROOT_PASSWORD` | Database testing | `root` (for testing) |
| `WP_TEST_DB_PASSWORD` | WordPress test DB | Test database password |

**Configuration Steps:**
1. Navigate to **Settings** → **Secrets and variables** → **Actions**
2. Click **Secrets** tab
3. Add each required secret

### 2.3 GitHub App Configuration

**For Claude AI Approval Gate:**

**App Permissions Required:**
```yaml
Repository permissions:
  - Contents: Read
  - Issues: Write
  - Pull requests: Write
  - Actions: Read
  - Metadata: Read

Account permissions:
  - None required
```

**Webhook Events:**
```yaml
- issue_comment
- pull_request
- pull_request_review
```

**Installation:**
1. Create GitHub App in organization settings
2. Install app on repository
3. Add app ID and private key to secrets

---

## 3. Security and Access Controls

### 3.1 Team Permissions Review

**Recommended Team Access Levels:**

| Team | Access Level | Justification |
|------|--------------|---------------|
| **Core Developers** | Admin | Full workflow management |
| **Contributors** | Write | Can trigger workflows, create PRs |
| **External Contributors** | Read | Can view workflows, limited actions |
| **Automation Bots** | Write | Workflow execution permissions |

### 3.2 External Service Authentication

**WordPress SVN Access:**
- **Type**: Public access (no authentication required)
- **Circuit Breaker**: Configured for `wordpress_svn` service
- **Fallback**: Local WordPress test library

**WordPress.org API:**
- **Type**: Public API (no authentication required)
- **Circuit Breaker**: Configured for `wordpress_api` service
- **Rate Limiting**: Handled by circuit breaker

**Claude AI Integration:**
- **Type**: GitHub App authentication
- **Required**: `BC_GITHUB_APP_ID` and `BC_GITHUB_APP_PRIVATE_KEY`
- **Fallback**: Local approval templates

### 3.3 Environment Variables for Circuit Breakers

**Required Environment Variables:**

```bash
# Circuit Breaker Configuration
CIRCUIT_BREAKER_CACHE_DIR="/tmp/circuit-breaker-cache"
CIRCUIT_BREAKER_TIMEOUT="300"
CIRCUIT_BREAKER_MAX_FAILURES="3"

# Service Endpoints
WORDPRESS_SVN_ENDPOINT="https://develop.svn.wordpress.org/trunk/"
WORDPRESS_API_ENDPOINT="https://api.wordpress.org/core/version-check/1.7/"
CLAUDE_API_ENDPOINT="https://api.anthropic.com/v1/"

# Fallback Configuration
FALLBACK_CACHE_DIR="/tmp/blazecommerce-fallbacks"
ENABLE_FALLBACK_MODE="true"
```

**Configuration in Repository Variables:**
1. Add each variable in **Settings** → **Secrets and variables** → **Actions** → **Variables**

---

## 4. Notification and Integration Settings

### 4.1 Webhook Configuration

**Required Webhooks:**

| Event | URL | Purpose |
|-------|-----|---------|
| `issue_comment` | GitHub Actions | Claude approval detection |
| `pull_request` | GitHub Actions | Test execution trigger |
| `push` | GitHub Actions | Auto-version and release |

**Webhook Settings:**
1. Navigate to **Settings** → **Webhooks**
2. Verify GitHub Actions webhook is active
3. Ensure events listed above are selected

### 4.2 PR and Issue Templates

**Updated PR Template** (`.github/pull_request_template.md`):
```markdown
## Description
Brief description of changes

## Testing
- [ ] Tests pass locally
- [ ] Claude AI review requested (comment `@claude review this PR`)

## Checklist
- [ ] Code follows project standards
- [ ] Documentation updated if needed
- [ ] Breaking changes documented

## Claude AI Review
This PR will be automatically reviewed by Claude AI. The approval gate will check for:
- Final verdict with ✅ APPROVED status
- No blocking issues marked as REQUIRED or IMPORTANT
```

### 4.3 Third-Party Integration Updates

**Integrations Requiring Updates:**

1. **Code Quality Tools** (if any):
   - Update status check names to match new workflows
   - Verify integration with simplified workflow structure

2. **Deployment Tools**:
   - Update to trigger on `Create Release` workflow completion
   - Verify compatibility with new release process

3. **Monitoring Tools**:
   - Update to monitor new workflow names
   - Configure alerts for circuit breaker activations

---

## 5. Configuration Validation Checklist

### 5.1 Pre-Deployment Validation

**Branch Protection:**
- [ ] Main branch requires `Tests / health-check` status
- [ ] Main branch requires `Tests / run-tests` status  
- [ ] Main branch requires `Claude AI Approval Gate / claude-approval` status
- [ ] Develop branch has appropriate protections

**Secrets and Variables:**
- [ ] `BC_GITHUB_APP_ID` configured
- [ ] `BC_GITHUB_APP_PRIVATE_KEY` configured
- [ ] `TEST_TIMEOUT` variable set
- [ ] All circuit breaker variables configured

**Permissions:**
- [ ] Workflow permissions match requirements
- [ ] Team access levels appropriate
- [ ] GitHub App permissions correct

### 5.2 Post-Deployment Validation

**Workflow Execution:**
- [ ] Test workflow runs successfully on PR
- [ ] Claude approval gate activates on comments
- [ ] Auto-version triggers on main branch push
- [ ] Release workflow creates releases properly

**Circuit Breaker Testing:**
- [ ] Circuit breakers activate on service failures
- [ ] Fallback mechanisms work correctly
- [ ] Recovery happens after timeout period

**Integration Testing:**
- [ ] PR templates work with new process
- [ ] Status checks appear correctly
- [ ] Notifications work as expected

---

## 6. Troubleshooting Common Issues

### 6.1 Status Check Failures

**Issue**: Status checks not appearing
**Solution**: 
1. Verify workflow names match branch protection rules
2. Check workflow permissions
3. Ensure workflows are enabled

### 6.2 Claude Approval Not Working

**Issue**: Claude approval gate not triggering
**Solution**:
1. Verify `BC_GITHUB_APP_ID` and `BC_GITHUB_APP_PRIVATE_KEY` secrets
2. Check GitHub App installation and permissions
3. Verify comment format includes required markers

### 6.3 Circuit Breaker Issues

**Issue**: Circuit breakers not activating
**Solution**:
1. Check circuit breaker cache directory permissions
2. Verify timeout and failure threshold variables
3. Test service connectivity manually

---

## 7. Monitoring and Maintenance

### 7.1 Regular Monitoring Tasks

**Weekly:**
- [ ] Review workflow success rates
- [ ] Check circuit breaker activation logs
- [ ] Monitor performance metrics

**Monthly:**
- [ ] Review and rotate secrets if needed
- [ ] Update WordPress and PHP versions
- [ ] Validate fallback mechanisms

### 7.2 Performance Optimization

**Metrics to Track:**
- Workflow execution times
- Circuit breaker activation frequency
- Fallback mechanism usage
- Test success rates

**Optimization Actions:**
- Adjust timeout values based on performance
- Update circuit breaker thresholds
- Optimize test execution modes

---

**Configuration Date**: January 15, 2025  
**Last Updated**: January 15, 2025  
**Status**: Ready for Implementation
