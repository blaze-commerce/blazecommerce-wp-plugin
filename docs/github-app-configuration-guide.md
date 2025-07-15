# GitHub App Configuration Guide for Claude AI Approval Gate

## Overview

The Claude AI Approval Gate workflow requires a GitHub App for enhanced permissions and authentication. This guide provides step-by-step instructions for creating and configuring the GitHub App.

---

## 1. GitHub App Creation

### 1.1 Create New GitHub App

1. Navigate to your organization settings:
   - Go to `https://github.com/organizations/blaze-commerce/settings/apps`
   - Or: **Organization Settings** → **Developer settings** → **GitHub Apps**

2. Click **"New GitHub App"**

3. Fill in basic information:
   ```
   GitHub App name: BlazeCommerce Claude AI Bot
   Description: Automated Claude AI approval system for BlazeCommerce repositories
   Homepage URL: https://github.com/blaze-commerce/blazecommerce-wp-plugin
   ```

### 1.2 Configure App Settings

**Webhook Configuration:**
```
Webhook URL: (leave blank - not needed for this app)
Webhook secret: (leave blank)
SSL verification: Disabled (since no webhook)
```

**Permissions:**

**Repository permissions:**
```
✅ Actions: Read
✅ Contents: Read  
✅ Issues: Write
✅ Metadata: Read
✅ Pull requests: Write
```

**Organization permissions:**
```
❌ All disabled (not needed)
```

**Account permissions:**
```
❌ All disabled (not needed)
```

**Subscribe to events:**
```
✅ Issue comment
✅ Pull request
✅ Pull request review
```

### 1.3 Installation Settings

**Where can this GitHub App be installed?**
- Select: **"Only on this account"** (blaze-commerce organization)

**User authorization:**
- **Request user authorization (OAuth) during installation**: ❌ Disabled
- **Enable Device Flow**: ❌ Disabled

---

## 2. App Installation and Configuration

### 2.1 Install App on Repository

1. After creating the app, click **"Install App"** in the left sidebar
2. Select **"blaze-commerce"** organization
3. Choose **"Only select repositories"**
4. Select **"blazecommerce-wp-plugin"** repository
5. Click **"Install"**

### 2.2 Generate Private Key

1. Go back to the app settings page
2. Scroll down to **"Private keys"** section
3. Click **"Generate a private key"**
4. Download the `.pem` file (keep it secure!)

### 2.3 Get App ID

1. In the app settings page, note the **"App ID"** number
2. This will be used as `BC_GITHUB_APP_ID` secret

---

## 3. Repository Secrets Configuration

### 3.1 Add App ID Secret

1. Navigate to repository settings:
   `https://github.com/blaze-commerce/blazecommerce-wp-plugin/settings/secrets/actions`

2. Click **"New repository secret"**

3. Configure:
   ```
   Name: BC_GITHUB_APP_ID
   Value: [Your App ID number, e.g., 123456]
   ```

### 3.2 Add Private Key Secret

1. Click **"New repository secret"** again

2. Configure:
   ```
   Name: BC_GITHUB_APP_PRIVATE_KEY
   Value: [Contents of the .pem file, including -----BEGIN RSA PRIVATE KEY----- and -----END RSA PRIVATE KEY-----]
   ```

**Important**: Copy the entire contents of the `.pem` file, including the header and footer lines.

---

## 4. Workflow Integration Verification

### 4.1 Test App Authentication

The Claude approval workflow includes this authentication step:

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
```

### 4.2 Verify Token Usage

The generated token is used for:
- Reading PR information
- Writing approval comments
- Updating PR status
- Managing labels and reviews

```yaml
github-token: ${{ steps.app_token.outputs.token || secrets.GITHUB_TOKEN }}
```

---

## 5. Testing and Validation

### 5.1 Test App Installation

1. Create a test PR in the repository
2. Add a comment that triggers Claude review
3. Verify the workflow runs without authentication errors

### 5.2 Check App Permissions

1. Go to **Settings** → **Integrations** → **GitHub Apps**
2. Find "BlazeCommerce Claude AI Bot"
3. Verify it shows as "Installed" with correct permissions

### 5.3 Monitor Workflow Logs

1. Go to **Actions** tab in repository
2. Run the Claude Approval Gate workflow
3. Check logs for successful app token generation:
   ```
   ✅ App token generated successfully
   ✅ Using GitHub App authentication
   ```

---

## 6. Troubleshooting

### 6.1 Common Issues

**Issue**: "App authentication failed"
**Solution**: 
- Verify App ID is correct number (not string)
- Check private key includes header/footer lines
- Ensure app is installed on repository

**Issue**: "Insufficient permissions"
**Solution**:
- Verify app has required repository permissions
- Check app installation scope includes target repository
- Confirm permissions match requirements in section 1.2

**Issue**: "Token generation timeout"
**Solution**:
- Check private key format (should be PEM format)
- Verify app ID matches the created app
- Ensure secrets are properly configured

### 6.2 Validation Commands

**Check app installation:**
```bash
curl -H "Authorization: token $GITHUB_TOKEN" \
  https://api.github.com/repos/blaze-commerce/blazecommerce-wp-plugin/installation
```

**Test token generation:**
```bash
# This should be done within the workflow context
# Check workflow logs for token generation success
```

---

## 7. Security Best Practices

### 7.1 Private Key Security

- ✅ Store private key only in GitHub Secrets
- ✅ Never commit private key to repository
- ✅ Regenerate key if compromised
- ✅ Limit app permissions to minimum required

### 7.2 App Scope Limitation

- ✅ Install app only on required repositories
- ✅ Use organization-level app (not personal)
- ✅ Regular review of app permissions
- ✅ Monitor app usage in audit logs

### 7.3 Token Management

- ✅ Tokens are automatically scoped to installation
- ✅ Tokens expire after 1 hour (handled by action)
- ✅ No manual token management required
- ✅ Fallback to GITHUB_TOKEN if app unavailable

---

## 8. Maintenance and Updates

### 8.1 Regular Maintenance

**Monthly:**
- Review app permissions and usage
- Check for any security alerts
- Verify app is still properly installed

**Quarterly:**
- Consider rotating private key
- Review app scope and permissions
- Update app description if needed

### 8.2 App Updates

**When to update:**
- New permissions required for features
- Security recommendations from GitHub
- Changes in workflow requirements

**How to update:**
1. Modify app settings in GitHub
2. Update repository secrets if needed
3. Test workflow functionality
4. Monitor for any issues

---

## 9. Alternative Authentication

### 9.1 Fallback to GITHUB_TOKEN

If GitHub App is not configured, workflows will fall back to the default `GITHUB_TOKEN`:

```yaml
github-token: ${{ steps.app_token.outputs.token || secrets.GITHUB_TOKEN }}
```

**Limitations of GITHUB_TOKEN:**
- Cannot trigger other workflows
- Limited cross-repository permissions
- May have rate limiting restrictions

### 9.2 Personal Access Token (Not Recommended)

While possible, using a personal access token is not recommended because:
- Tied to individual user account
- Broader permissions than needed
- No automatic expiration
- Security risk if user leaves organization

---

## 10. Configuration Checklist

### Pre-Deployment Checklist

- [ ] GitHub App created with correct permissions
- [ ] App installed on blazecommerce-wp-plugin repository
- [ ] Private key generated and downloaded
- [ ] `BC_GITHUB_APP_ID` secret configured
- [ ] `BC_GITHUB_APP_PRIVATE_KEY` secret configured
- [ ] Workflow authentication tested
- [ ] App permissions verified

### Post-Deployment Validation

- [ ] Claude approval workflow runs successfully
- [ ] App token generation works in workflow logs
- [ ] PR approval functionality works
- [ ] No authentication errors in workflow runs
- [ ] Fallback to GITHUB_TOKEN works if needed

---

**Configuration Date**: January 15, 2025  
**App Name**: BlazeCommerce Claude AI Bot  
**Repository**: blaze-commerce/blazecommerce-wp-plugin  
**Status**: Ready for Production
