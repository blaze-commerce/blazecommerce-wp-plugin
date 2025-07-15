# 🤖 BlazeCommerce Automation Bot - Complete Setup Guide

This guide provides step-by-step instructions for creating and configuring the consolidated "BlazeCommerce Automation Bot" GitHub App.

## 📋 **GitHub App Configuration Form**

Use these exact values when creating your GitHub App at:
`https://github.com/organizations/blaze-commerce/settings/apps/new`

### **Basic Information**
```yaml
GitHub App name: "BlazeCommerce Automation Bot"
Description: "Consolidated automation bot for auto-approval and version management in BlazeCommerce repositories"
Homepage URL: "https://github.com/blaze-commerce/blazecommerce-wp-plugin"
```

### **Identifying and Authorizing Users**
```yaml
Callback URL: [LEAVE EMPTY]
☐ Request user authorization (OAuth) during installation
☐ Enable Device Flow
☐ Expire user authorization tokens
```

### **Post Installation**
```yaml
Setup URL: [LEAVE EMPTY]
☐ Redirect on update
```

### **Webhook**
```yaml
☐ Active
Webhook URL: [LEAVE EMPTY]
Webhook secret: [LEAVE EMPTY]
```

## 🔐 **Repository Permissions**

**CRITICAL**: Set these exact permissions (reference: register_github_app.md lines 51-115):

### **Required Permissions**
```yaml
Actions: Read                      # Line 54-55: Workflows, workflow runs and artifacts
Contents: Write                    # Line 74-75: Repository contents, commits, branches, releases
Metadata: Read                     # Line 92-93: Repository metadata (always required)
Pull requests: Write               # Line 100-101: Pull requests and related data
```

### **Optional Permissions** (for enhanced functionality)
```yaml
Commit statuses: Write             # Line 72-73: Commit statuses
Checks: Write                      # Line 60-61: Checks
```

## 🏢 **Organization Permissions**
```yaml
[NO ORGANIZATION PERMISSIONS REQUIRED]
```

## 👤 **User Permissions**
```yaml
[NO USER PERMISSIONS REQUIRED]
```

## 🎯 **Installation Settings**
```yaml
Where can this GitHub App be installed?
● Only on this account (@blaze-commerce)

Repository access:
● Selected repositories
  ☑ blazecommerce-wp-plugin
```

## 🔑 **Post-Creation Steps**

### **1. Generate Private Key**
1. After creating the app, scroll to "Private keys" section
2. Click "Generate a private key"
3. Download the `.pem` file
4. **IMPORTANT**: Store this file securely - you'll need its contents for secrets

### **2. Note App ID**
1. On the app settings page, note the **App ID** (displayed prominently)
2. **IMPORTANT**: You'll need this number for the `BC_GITHUB_APP_ID` secret

### **3. Install the App**
1. Click "Install App" in the left sidebar
2. Click "Install" next to blaze-commerce organization
3. Choose "Selected repositories" → Select `blazecommerce-wp-plugin`
4. Click "Install"

## 🔧 **Repository Secrets Configuration**

Add these secrets to your repository at:
`https://github.com/blaze-commerce/blazecommerce-wp-plugin/settings/secrets/actions`

### **BC_GITHUB_APP_ID**
```yaml
Name: BC_GITHUB_APP_ID
Value: [Your App ID from step 2 above, e.g., "123456"]
```

### **BC_GITHUB_APP_PRIVATE_KEY**
```yaml
Name: BC_GITHUB_APP_PRIVATE_KEY
Value: [Complete contents of the .pem file, including headers]
```

**Example private key format**:
```
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA1234567890abcdef...
[multiple lines of key content]
...xyz789
-----END RSA PRIVATE KEY-----
```

## ✅ **Verification Steps**

### **1. Test Authentication**
```bash
# Install dependencies
npm install

# Set environment variables for testing
export BC_GITHUB_APP_ID="your-app-id"
export BC_GITHUB_APP_PRIVATE_KEY="$(cat path/to/private-key.pem)"

# Run comprehensive test
npm run test:automation-bot
```

### **2. Expected Test Output**
```
🤖 Testing BlazeCommerce Automation Bot...

✅ App ID: 123456
✅ Private Key: 1234 characters

🔑 Getting installation access token...
✅ Installation token obtained

📂 Testing repository access...
✅ Repository access successful: blaze-commerce/blazecommerce-wp-plugin

📝 Testing contents permission (version bumping)...
✅ Contents read permission working
✅ Contents write permission available

🔍 Testing pull requests permission (auto-approval)...
✅ Pull requests read permission working
✅ Pull requests write permission available

⚡ Testing actions permission (workflow integration)...
✅ Actions read permission working

🔐 Testing authentication identity...
✅ Authenticated as: blazecommerce-automation-bot[bot] (Bot)
🤖 Confirmed: Using GitHub App authentication

🎉 BlazeCommerce Automation Bot test completed successfully!
```

## 🚀 **Deployment**

### **1. Merge the Integration Branch**
```bash
# The feature/github-app-integration branch contains:
# - Updated auto-version.yml workflow
# - Updated claude-auto-approval.yml workflow  
# - Test scripts and documentation
```

### **2. Monitor First Runs**
1. **Auto-Approval Test**: Create a test PR and trigger Claude review
2. **Version Bump Test**: Create a fix commit and verify version increment
3. **Check Logs**: Verify "Using GitHub App token" messages in workflow logs

## 🔒 **Security Best Practices**

### **Private Key Management**
- ✅ Store private key only in GitHub repository secrets
- ✅ Never commit private key to repository
- ✅ Rotate private key every 90 days
- ✅ Use environment variables for local testing only

### **Permission Auditing**
- ✅ Review app permissions quarterly
- ✅ Monitor app activity in organization audit log
- ✅ Remove unused permissions promptly
- ✅ Document permission changes

### **Access Control**
- ✅ Limit app installation to specific repositories
- ✅ Review installation scope regularly
- ✅ Monitor for unauthorized installations
- ✅ Use organization-level app management

## 🔧 **Troubleshooting**

### **Common Issues**

**Authentication Failed (401)**
- Verify App ID matches created app
- Check private key format (complete with headers)
- Ensure app is installed in organization

**Permission Denied (403)**
- Verify Contents: Write permission granted
- Verify Pull requests: Write permission granted
- Check app installation on correct repository

**App Not Found (404)**
- Confirm app is installed in blaze-commerce organization
- Verify repository name spelling
- Check app installation scope

### **Debug Commands**
```bash
# Test individual components
npm run test:github-app      # GitHub App authentication only
npm run test:token-auth      # Fallback token authentication
npm run test:automation-bot  # Complete functionality test
```

---

**✅ Setup Status**: Complete configuration guide
**🎯 Estimated Setup Time**: 30-45 minutes
**🔧 Support**: Use test scripts for validation at each step
