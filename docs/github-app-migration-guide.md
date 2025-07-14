# 🔄 Migration Guide: Consolidating to BlazeCommerce Automation Bot

This guide covers the migration from separate authentication methods to a unified GitHub App approach.

## 📊 **Current State vs. Target State**

### **Before (Current)**
```yaml
Auto-Approval Workflow:
  Authentication: BOT_GITHUB_TOKEN
  Identity: @blazecommerce-claude-ai user
  Permissions: Personal Access Token scope

Auto-Version Workflow:
  Authentication: BC_GITHUB_TOKEN or github.token
  Identity: Various (user or default bot)
  Permissions: Personal Access Token or default scope
```

### **After (Target)**
```yaml
Both Workflows:
  Authentication: GitHub App (BC_GITHUB_APP_ID + BC_GITHUB_APP_PRIVATE_KEY)
  Identity: BlazeCommerce Automation Bot
  Permissions: Granular GitHub App permissions
  Fallback: BOT_GITHUB_TOKEN / BC_GITHUB_TOKEN
```

## 🎯 **Migration Benefits**

### **Security Improvements**
- ✅ **Granular Permissions**: Only required permissions granted
- ✅ **Short-lived Tokens**: App tokens expire in 1 hour
- ✅ **Audit Trail**: Clear bot identity for all actions
- ✅ **Revocation Control**: Easy to disable/modify app access

### **Operational Benefits**
- ✅ **Unified Identity**: All automation from one source
- ✅ **Simplified Management**: One app instead of multiple tokens
- ✅ **Better Monitoring**: Centralized automation tracking
- ✅ **Consistent Behavior**: Same authentication across workflows

## 📋 **Migration Checklist**

### **Phase 1: GitHub App Creation**
- [ ] Create "BlazeCommerce Automation Bot" GitHub App
- [ ] Configure required permissions (Contents: Write, Pull requests: Write, Actions: Read)
- [ ] Install app in blaze-commerce organization
- [ ] Generate and securely store private key

### **Phase 2: Repository Configuration**
- [ ] Add BC_GITHUB_APP_ID secret
- [ ] Add BC_GITHUB_APP_PRIVATE_KEY secret
- [ ] Keep existing BOT_GITHUB_TOKEN and BC_GITHUB_TOKEN as fallbacks
- [ ] Test authentication with provided scripts

### **Phase 3: Workflow Updates**
- [ ] Deploy updated auto-version.yml workflow
- [ ] Deploy updated claude-auto-approval.yml workflow
- [ ] Verify GitHub App token generation steps
- [ ] Test fallback authentication paths

### **Phase 4: Testing & Validation**
- [ ] Run `npm run test:automation-bot` locally
- [ ] Test auto-approval with real PR
- [ ] Test version bumping with fix commit
- [ ] Monitor workflow logs for authentication method used

### **Phase 5: Cleanup (Optional)**
- [ ] Remove old BOT_GITHUB_TOKEN after successful migration
- [ ] Update documentation references
- [ ] Archive @blazecommerce-claude-ai user if no longer needed

## 🔧 **GitHub App Configuration Details**

### **App Name**: `BlazeCommerce Automation Bot`

### **Required Permissions** (Reference: register_github_app.md)
```yaml
Repository Permissions:
  Contents: Write                    # Line 74-75: Repository contents, commits, branches, releases
  Pull requests: Write               # Line 100-101: Create approval reviews
  Actions: Read                      # Line 54-55: Workflows, workflow runs and artifacts
  Metadata: Read                     # Line 92-93: Repository metadata (always required)
```

### **Installation Settings**
```yaml
Installation Target: "Only on this account"
Account: @blaze-commerce organization
Repository Access: "Selected repositories" → blazecommerce-wp-plugin
```

## 🧪 **Testing Strategy**

### **Pre-Migration Testing**
```bash
# Test current authentication methods
export BOT_GITHUB_TOKEN="current-bot-token"
npm run test:token-auth

export BC_GITHUB_TOKEN="current-admin-token"  
npm run test:token-auth
```

### **Post-Migration Testing**
```bash
# Test GitHub App authentication
export BC_GITHUB_APP_ID="your-app-id"
export BC_GITHUB_APP_PRIVATE_KEY="$(cat private-key.pem)"
npm run test:automation-bot
```

### **Integration Testing**
1. **Auto-Approval Test**:
   - Create test PR
   - Trigger Claude AI review workflow
   - Verify BlazeCommerce Automation Bot approval

2. **Version Bump Test**:
   - Create fix commit on main
   - Verify auto-version workflow triggers
   - Confirm version bump and tag creation

## 🔒 **Security Considerations**

### **GitHub App Security**
- ✅ **Private Key Protection**: Store only in GitHub secrets
- ✅ **Permission Minimization**: Only required permissions granted
- ✅ **Installation Scope**: Limited to specific repositories
- ✅ **Token Lifecycle**: Automatic 1-hour expiration

### **Fallback Security**
- ✅ **Token Rotation**: Regular rotation of fallback tokens
- ✅ **Scope Limitation**: Minimal required scopes for PATs
- ✅ **Access Monitoring**: Regular audit of token usage

### **Migration Security**
- ✅ **Gradual Rollout**: Test thoroughly before full deployment
- ✅ **Rollback Plan**: Keep fallback tokens during transition
- ✅ **Monitoring**: Watch for authentication failures

## 🚨 **Potential Issues & Solutions**

### **Issue 1: GitHub App Installation Permissions**
**Problem**: Can't install GitHub App in organization
**Solution**: Ensure you have organization admin permissions

### **Issue 2: Private Key Format**
**Problem**: Authentication fails with private key
**Solution**: Ensure complete key including headers/footers

### **Issue 3: Permission Denied**
**Problem**: GitHub App lacks required permissions
**Solution**: Verify Contents: Write and Pull requests: Write are granted

### **Issue 4: Workflow Authentication Failures**
**Problem**: Workflows fail to authenticate
**Solution**: Check secret configuration and fallback token availability

## 📈 **Success Metrics**

### **Technical Metrics**
- ✅ All workflows authenticate successfully with GitHub App
- ✅ Fallback authentication works when app unavailable
- ✅ No authentication-related workflow failures
- ✅ Consistent bot identity across all automated actions

### **Operational Metrics**
- ✅ Reduced token management overhead
- ✅ Improved audit trail clarity
- ✅ Faster troubleshooting of automation issues
- ✅ Enhanced security posture

## 🔄 **Rollback Plan**

If issues arise during migration:

1. **Immediate Rollback**:
   ```bash
   # Revert workflow files to use BOT_GITHUB_TOKEN
   git revert <migration-commit-hash>
   ```

2. **Partial Rollback**:
   - Keep GitHub App for version bumping
   - Revert auto-approval to BOT_GITHUB_TOKEN

3. **Full Rollback**:
   - Remove GitHub App secrets
   - Restore original workflow configurations
   - Continue with existing token-based authentication

---

**✅ Migration Status**: Ready for implementation
**🎯 Expected Completion**: 1-2 hours for full migration
**🔧 Support**: Use test scripts for validation at each phase
