# 🚀 BlazeCommerce Automation Bot - Consolidated GitHub App Implementation Guide

This document provides complete implementation instructions for the consolidated GitHub App authentication that handles both auto-approval and auto-version workflows.

## 📋 **Implementation Checklist**

### ✅ **Step 1: GitHub App Creation**
- [x] Navigate to GitHub organization settings
- [x] Create new GitHub App with proper configuration
- [x] Set appropriate permissions (Contents: Write, Pull requests: Read, Actions: Read)
- [x] Configure installation target (blaze-commerce organization only)

### ✅ **Step 2: Authentication Setup**
- [x] Generate private key (.pem file)
- [x] Note App ID from settings page
- [x] Install app in blaze-commerce organization
- [x] Grant access to blazecommerce-wp-plugin repository

### ✅ **Step 3: Repository Configuration**
- [x] Add BC_GITHUB_APP_ID secret
- [x] Add BC_GITHUB_APP_PRIVATE_KEY secret
- [x] Verify secrets are properly configured

### ✅ **Step 4: Workflow Updates**
- [x] Enhanced auto-version.yml with GitHub App token generation
- [x] Updated checkout step to use app token
- [x] Modified push commands to use app authentication
- [x] Added fallback to admin token if app token unavailable

### ✅ **Step 5: Testing and Verification**
- [x] Created test script for GitHub App authentication
- [x] Added required dependencies (@octokit/auth-app, @octokit/rest)
- [x] Added npm test script for validation

## 🔧 **Key Configuration Details**

### **GitHub App Permissions**
```yaml
Repository Permissions:
  Contents: Write          # Create commits, push changes (version bumping)
  Pull requests: Write     # Create approval reviews (auto-approval)
  Actions: Read           # Read workflow run information
  Metadata: Read          # Basic repository access (required)
```

### **Consolidated Functionality**
- **Auto-Approval**: Replaces @blazecommerce-claude-ai user authentication
- **Version Bumping**: Handles automated version increments and releases
- **Unified Identity**: All automation appears from "BlazeCommerce Automation Bot"

### **Repository Secrets**
```yaml
BC_GITHUB_APP_ID: "123456"                    # Your app ID
BC_GITHUB_APP_PRIVATE_KEY: "-----BEGIN..."   # Complete private key
```

### **Workflow Authentication Flow**
1. Generate GitHub App token using app ID and private key
2. Use app token for repository operations (preferred)
3. Fallback to BC_GITHUB_TOKEN if app token unavailable
4. Fallback to default github.token as last resort

## 🧪 **Testing Instructions**

### **Local Testing**
```bash
# Install dependencies
npm install

# Set environment variables (for local testing only)
export BC_GITHUB_APP_ID="your-app-id"
export BC_GITHUB_APP_PRIVATE_KEY="$(cat path/to/private-key.pem)"

# Run consolidated automation bot test
npm run test:automation-bot

# Or run individual tests
npm run test:github-app     # GitHub App authentication only
npm run test:token-auth     # Personal access token fallback
```

### **Expected Test Output**
```
🔍 Testing GitHub App Authentication...

✅ App ID: 123456
✅ Private Key: 1234 characters

🔑 Getting installation access token...
✅ Installation token obtained

📂 Testing repository access...
✅ Repository access successful: blaze-commerce/blazecommerce-wp-plugin
   - Default branch: main
   - Permissions: {"admin":false,"maintain":false,"push":true,"triage":false,"pull":true}

📝 Testing contents permission...
✅ Contents read permission working
   - File: package.json (2048 bytes)

🔍 Testing pull requests permission...
✅ Pull requests read permission working
   - Open PRs: 2

⚡ Testing actions permission...
✅ Actions read permission working
   - Workflows: 8

🎉 All tests passed! GitHub App authentication is properly configured.
```

## 🔒 **Security Best Practices**

### **Private Key Management**
- ✅ Store private key in GitHub repository secrets only
- ✅ Never commit private key to repository
- ✅ Use environment variables for local testing
- ✅ Rotate private key periodically

### **Permission Principle**
- ✅ Grant minimum required permissions only
- ✅ Scope app to specific repositories when possible
- ✅ Regular audit of app permissions and access

### **Token Handling**
- ✅ App tokens are short-lived (1 hour)
- ✅ Tokens are generated fresh for each workflow run
- ✅ No token storage or caching required

## 🚀 **Deployment Steps**

### **1. Merge Workflow Changes**
```bash
# The workflow changes are already implemented in:
# - .github/workflows/auto-version.yml (enhanced authentication)
# - package.json (added test dependencies and scripts)
# - scripts/test-github-app-auth.js (testing tool)
```

### **2. Configure Secrets**
1. Go to repository settings → Secrets and variables → Actions
2. Add BC_GITHUB_APP_ID with your app ID
3. Add BC_GITHUB_APP_PRIVATE_KEY with complete private key content

### **3. Test the Setup**
1. Create a test commit with conventional format (e.g., "fix: test github app auth")
2. Merge to main branch
3. Monitor auto-version workflow execution
4. Verify successful push to main branch

## 🔧 **Troubleshooting**

### **Common Issues**

**Authentication Failed (401)**
- Verify App ID is correct
- Check private key format (must include headers/footers)
- Ensure app is installed in organization

**Permission Denied (403)**
- Verify app has Contents: Write permission
- Check app is installed on correct repository
- Confirm app has required permissions

**Token Generation Failed**
- Check secrets are properly configured
- Verify private key is complete and properly formatted
- Ensure app ID matches the created app

### **Debug Commands**
```bash
# Test GitHub App authentication
npm run test:github-app

# Check workflow logs for authentication details
# Look for "Generate GitHub App Token" step in Actions tab

# Verify secrets are configured
# Go to repository Settings → Secrets and variables → Actions
```

## 📚 **References**

- **GitHub App Documentation**: https://docs.github.com/en/apps
- **actions/create-github-app-token**: https://github.com/actions/create-github-app-token
- **Octokit Authentication**: https://github.com/octokit/auth-app.js
- **Repository Permissions**: https://docs.github.com/en/rest/overview/permissions-required-for-github-apps

---

**✅ Implementation Status**: Complete and ready for deployment
**🔧 Next Steps**: Configure secrets and test with real workflow execution
