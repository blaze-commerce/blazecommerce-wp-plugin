# GitHub Actions Authentication Fix

## Issue Summary

**Problem**: GitHub Actions workflow run #16271912769 failed with the error:
```
Failed to setup GitHub token: Error: Invalid OIDC token.
```

**Root Cause**: The `anthropics/claude-code-action@beta` workflows were not using the repository's GitHub App authentication setup, causing them to fall back to OIDC authentication which was failing.

**Affected Workflows**:
- `.github/workflows/claude-code-review.yml` (Priority 2: Claude AI Code Review)
- `.github/workflows/claude.yml` (Priority 7: Claude Code)

## Solution Implemented

### 1. Added GitHub App Token Generation

Both affected workflows now include a GitHub App token generation step that follows the same pattern used in other repository workflows:

```yaml
- name: Generate GitHub App Token
  id: app_token
  if: env.BC_GITHUB_APP_ID != '' && env.BC_GITHUB_APP_PRIVATE_KEY != ''
  env:
    BC_GITHUB_APP_ID: ${{ secrets.BC_GITHUB_APP_ID }}
    BC_GITHUB_APP_PRIVATE_KEY: ${{ secrets.BC_GITHUB_APP_PRIVATE_KEY }}
  uses: actions/create-github-app-token@v1
  with:
    app-id: ${{ secrets.BC_GITHUB_APP_ID }}
    private-key: ${{ secrets.BC_GITHUB_APP_PRIVATE_KEY }}
    owner: ${{ github.repository_owner }}
    repositories: ${{ github.event.repository.name }}
```

### 2. Updated Token Usage

The `anthropics/claude-code-action@beta` now receives the proper authentication token:

```yaml
- name: Run Claude Code Review
  uses: anthropics/claude-code-action@beta
  with:
    anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
    github_token: ${{ steps.app_token.outputs.token || secrets.BOT_GITHUB_TOKEN || github.token }}
```

### 3. Enhanced Checkout Authentication

The checkout step now uses the same token fallback pattern:

```yaml
- name: Checkout repository
  uses: actions/checkout@v4
  with:
    token: ${{ steps.app_token.outputs.token || secrets.BOT_GITHUB_TOKEN || github.token }}
    fetch-depth: 1
```

### 4. Added Authentication Verification

Both workflows now include a verification step to confirm authentication status:

```yaml
- name: Verify Authentication
  run: |
    echo "🔐 AUTHENTICATION STATUS:"
    if [ -n "${{ steps.app_token.outputs.token }}" ]; then
      echo "✅ GitHub App token generated successfully"
      echo "🤖 Using BlazeCommerce Automation Bot authentication"
    elif [ -n "${{ secrets.BOT_GITHUB_TOKEN }}" ]; then
      echo "⚠️ Using BOT_GITHUB_TOKEN fallback"
    else
      echo "⚠️ Using default github.token"
    fi
```

### 5. Added Manual Testing Support

Both workflows now support manual testing via `workflow_dispatch`:

```yaml
on:
  # ... existing triggers ...
  workflow_dispatch:
    inputs:
      pr_number:  # or issue_number for claude.yml
        description: 'PR number to review'
        required: true
        type: string
```

## Authentication Flow

The fix implements a three-tier authentication fallback system:

1. **Primary**: GitHub App token (`BC_GITHUB_APP_ID` + `BC_GITHUB_APP_PRIVATE_KEY`)
   - Provides "BlazeCommerce Automation Bot" identity
   - Short-lived, secure tokens
   - Granular permissions

2. **Fallback**: Bot token (`BOT_GITHUB_TOKEN`)
   - Personal access token fallback
   - Used if GitHub App authentication fails

3. **Last Resort**: Default token (`github.token`)
   - Built-in GitHub Actions token
   - Limited permissions but always available

## Files Modified

### `.github/workflows/claude-code-review.yml`
- Added GitHub App token generation
- Updated anthropics action with github_token parameter
- Enhanced checkout with token authentication
- Added authentication verification step
- Added workflow_dispatch trigger for testing

### `.github/workflows/claude.yml`
- Added GitHub App token generation
- Updated anthropics action with github_token parameter
- Enhanced checkout with token authentication
- Added authentication verification step
- Added workflow_dispatch trigger for testing

## Testing Instructions

### Manual Testing

1. **Test claude-code-review.yml**:
   ```bash
   # Go to Actions tab in GitHub
   # Select "Priority 2: Claude AI Code Review"
   # Click "Run workflow"
   # Enter a PR number
   # Click "Run workflow"
   ```

2. **Test claude.yml**:
   ```bash
   # Go to Actions tab in GitHub
   # Select "Priority 7: Claude Code"
   # Click "Run workflow"
   # Enter an issue/PR number (optional)
   # Click "Run workflow"
   ```

### Verification Steps

1. Check workflow logs for authentication status
2. Verify no "Invalid OIDC token" errors
3. Confirm Claude actions execute successfully
4. Validate proper token usage in logs

## Security Considerations

- GitHub App tokens are short-lived (1 hour)
- Tokens are generated fresh for each workflow run
- No token storage or caching
- Follows principle of least privilege
- Maintains audit trail through BlazeCommerce Automation Bot identity

## Troubleshooting

### Common Issues

**GitHub App Token Generation Fails**:
- Verify `BC_GITHUB_APP_ID` secret is set correctly
- Check `BC_GITHUB_APP_PRIVATE_KEY` secret format
- Ensure GitHub App is installed on the repository

**Authentication Still Fails**:
- Check if `BOT_GITHUB_TOKEN` secret exists as fallback
- Verify repository permissions
- Review workflow logs for specific error messages

**Claude Action Still Gets OIDC Errors**:
- Ensure `github_token` parameter is properly passed
- Check if the anthropics action version supports the parameter
- Verify token has required permissions (contents, pull-requests, issues)

## Related Documentation

- [GitHub App Implementation Guide](./github-app-implementation-guide.md)
- [BlazeCommerce Automation Bot Setup](./blazecommerce-automation-bot-setup.md)
- [GitHub App Migration Guide](./github-app-migration-guide.md)
