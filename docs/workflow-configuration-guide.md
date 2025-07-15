# GitHub Workflow Configuration Guide

## 🎯 Overview

This guide documents the configurable aspects of our GitHub workflow system, including timeout values, security settings, and troubleshooting procedures.

## ⚙️ Repository Variables Configuration

### Timeout Configuration Variables

Configure these variables in your repository settings under **Settings → Secrets and variables → Actions → Variables**:

#### Claude AI Workflow Timeouts
```yaml
# Primary Claude AI review workflow timeout (default: 15 minutes)
CLAUDE_REVIEW_TIMEOUT: 15

# Claude AI retry attempt timeout (default: 8 minutes)
CLAUDE_RETRY_TIMEOUT: 8

# Claude AI final attempt timeout (default: 12 minutes)
CLAUDE_FINAL_TIMEOUT: 12

# Claude AI backoff delay between retries (default: 4 seconds)
CLAUDE_BACKOFF_DELAY: 4

# Claude dependency check timeout (default: 3 minutes)
CLAUDE_DEPENDENCY_CHECK_TIMEOUT: 3

# Claude approval gate timeout (default: 5 minutes)
CLAUDE_APPROVAL_GATE_TIMEOUT: 5
```

#### Version and Release Workflow Timeouts
```yaml
# Auto-version workflow timeout (default: 20 minutes)
AUTO_VERSION_TIMEOUT: 20

# Release validation timeout (default: 10 minutes)
RELEASE_VALIDATION_TIMEOUT: 10

# Release build and upload timeout (default: 15 minutes)
RELEASE_BUILD_TIMEOUT: 15
```

### Security Configuration Variables

```yaml
# Maximum allowed prompt length for Claude AI (default: 50000)
CLAUDE_MAX_PROMPT_LENGTH: 50000

# Enable enhanced security logging (default: true)
SECURITY_AUDIT_LOGGING: true

# Token validation strictness (default: strict)
TOKEN_VALIDATION_MODE: strict
```

## 🔒 Security Best Practices

### GitHub Token Management

#### Required Permissions by Workflow

**Claude AI Review Workflow (`claude-pr-review.yml`)**:
- `contents: read` - Read repository content for analysis
- `pull-requests: write` - Comment on PRs and approve/request changes
- `issues: write` - Create comments on PR discussions
- `statuses: write` - Create status checks for approval gate
- `checks: write` - Create check runs for workflow status
- `actions: read` - Read workflow run information for dependencies
- `id-token: write` - OIDC token for secure authentication

**Claude Approval Gate Workflow (`claude-approval-gate.yml`)**:
- `contents: read` - Read repository content for validation
- `pull-requests: read` - Read PR information and reviews
- `statuses: write` - Create status checks for merge protection
- `checks: write` - Create check runs for approval status
- `actions: read` - Read workflow run information for dependencies

**Auto-Version Workflow (`auto-version.yml`)**:
- `contents: write` - Update version files and create commits
- `pull-requests: read` - Read PR information for version analysis
- `actions: read` - Read workflow run information

**Release Workflow (`release.yml`)**:
- `contents: write` - Create releases and upload assets (build job only)
- `contents: read` - Read repository content and tags (validation job)
- `actions: read` - Read workflow run information

#### Token Types and Usage

**BOT_GITHUB_TOKEN (Recommended)**:
- Enhanced permissions across repositories
- Dedicated bot account with minimal required scopes
- Better security isolation
- Cross-repository operations support

**github.token (Fallback)**:
- Automatic repository-scoped permissions
- Limited to current repository only
- No additional configuration required
- Suitable for single-repository operations

### Security Validation Features

1. **Input Validation**: All external inputs are validated for length and content
2. **Secure Temporary Files**: All temporary files use cryptographically secure random names
3. **Permission Auditing**: Runtime validation and logging of token permissions
4. **Principle of Least Privilege**: Each workflow has minimal required permissions

## 🔧 Priority Enforcement System

### Workflow Execution Order

Our workflows implement a 3-tier priority system with explicit dependencies:

```
Priority 1: Workflow Pre-flight Check
     ↓ (explicit dependency)
Priority 2: Claude AI Code Review
     ↓ (explicit dependency)
Priority 3: Claude AI Approval Gate
     ↓ (merge triggers)
Priority 4-5: Auto-Version & Release (parallel)
```

### Dependency Implementation

The `claude-approval-gate.yml` workflow includes a dependency check job that:
1. Waits for Priority 1 (Claude Review) to complete
2. Validates the completion status
3. Only proceeds when dependencies are satisfied

### Concurrency Controls

- **PR-specific concurrency**: Allows multiple PRs to be processed simultaneously
- **Repository-level concurrency**: Prevents conflicts in post-merge workflows
- **No cancellation policy**: Ensures workflow completion for critical operations

## 📊 Monitoring and Observability

### Workflow Health Indicators

Monitor these key metrics for workflow health:

1. **Success Rates**:
   - Claude AI review success rate (target: >95%)
   - Auto-approval accuracy (target: >90%)
   - Version bump success rate (target: >98%)

2. **Performance Metrics**:
   - Average Claude review time (target: <5 minutes)
   - Workflow queue time (target: <2 minutes)
   - End-to-end PR processing time (target: <10 minutes)

3. **Error Patterns**:
   - Claude AI service timeouts
   - Token permission failures
   - Version calculation errors

### Logging and Debugging

Each workflow provides comprehensive logging:

- **Security Audit Logs**: Token usage and permission validation
- **Performance Logs**: Timing information for each step
- **Error Diagnostics**: Detailed failure analysis with recovery suggestions
- **Dependency Tracking**: Workflow execution order validation

## 🛠️ Troubleshooting Guide

### Common Issues and Solutions

#### Claude AI Service Issues

**Symptom**: Claude AI review fails with timeout
**Causes**: 
- Service overload
- Large PR size
- Network connectivity issues

**Solutions**:
1. Check `CLAUDE_REVIEW_TIMEOUT` setting (increase if needed)
2. Break large PRs into smaller chunks
3. Retry by pushing a small update

**Symptom**: All Claude AI retry attempts fail
**Causes**:
- Service outage
- API key issues
- Rate limiting

**Solutions**:
1. Check Anthropic service status
2. Validate `ANTHROPIC_API_KEY` secret
3. Wait for automatic retry or manual intervention

#### Token Permission Issues

**Symptom**: "Insufficient permissions" errors
**Causes**:
- Missing required permissions
- Token scope limitations
- Repository settings conflicts

**Solutions**:
1. Verify token permissions match requirements
2. Use `BOT_GITHUB_TOKEN` for enhanced permissions
3. Check repository branch protection settings

#### Version Calculation Issues

**Symptom**: Version bump fails or calculates incorrectly
**Causes**:
- Invalid current version format
- Missing commit message patterns
- File change detection issues

**Solutions**:
1. Verify version format in `package.json` and plugin files
2. Use conventional commit format (`feat:`, `fix:`, etc.)
3. Check file change patterns and exclusions

### Emergency Procedures

#### Workflow System Failure

1. **Immediate Actions**:
   - Check GitHub Actions status page
   - Verify repository secrets and variables
   - Review recent workflow changes

2. **Temporary Workarounds**:
   - Manual PR review and approval
   - Manual version bumping
   - Direct merge with admin override

3. **Recovery Steps**:
   - Restore from known good workflow configuration
   - Re-run failed workflows after fixes
   - Update documentation with lessons learned

#### Security Incident Response

1. **Detection**: Monitor for unusual token usage or permission escalation
2. **Containment**: Revoke compromised tokens immediately
3. **Investigation**: Review audit logs and workflow execution history
4. **Recovery**: Generate new tokens and update secrets
5. **Prevention**: Implement additional security controls if needed

## 📈 Performance Optimization

### Workflow Efficiency Tips

1. **Timeout Tuning**:
   - Monitor actual execution times
   - Adjust timeouts based on repository size
   - Use shorter timeouts for faster feedback

2. **Concurrency Optimization**:
   - Ensure proper concurrency group naming
   - Avoid unnecessary workflow cancellations
   - Balance parallelism with resource usage

3. **Resource Management**:
   - Use appropriate runner types
   - Optimize script execution time
   - Minimize external API calls

### Scaling Considerations

As your repository grows, consider:

1. **Increased Timeouts**: Larger codebases need more processing time
2. **Enhanced Caching**: Cache dependencies and build artifacts
3. **Workflow Splitting**: Break complex workflows into smaller, focused jobs
4. **Resource Allocation**: Use more powerful runners for intensive operations

---

**Last Updated**: 2025-07-13  
**Configuration Version**: 2.0 (with priority enforcement and enhanced security)  
**Compatibility**: GitHub Actions, Anthropic Claude API v1
