# Prerelease Versioning Strategy

## Overview

The BlazeCommerce WordPress plugin now supports **automatic prerelease versioning** based on branch names. This enables teams to create alpha, beta, and release candidate versions automatically without manual intervention, following semantic versioning best practices.

## 🌿 **Branch-Based Prerelease Strategy**

### **Automatic Prerelease Assignment**

The system automatically assigns prerelease identifiers based on the branch where changes are pushed:

| Branch Pattern | Prerelease Type | Example Version | Use Case |
|----------------|-----------------|-----------------|----------|
| `feature/*` | **alpha** | `1.9.0-alpha.1` | Feature development and testing |
| `develop` | **beta** | `1.9.0-beta.1` | Integration testing and QA |
| `release/*` | **rc** (release candidate) | `1.9.0-rc.1` | Pre-production testing |
| `main`/`master` | **stable** | `1.9.0` | Production releases |

### **Prerelease Numbering**

The system intelligently handles prerelease numbering:

```bash
# First prerelease of a version
1.8.0 → 1.9.0-alpha.1  (feature branch)

# Subsequent prereleases of same type
1.9.0-alpha.1 → 1.9.0-alpha.2  (same feature branch)
1.9.0-alpha.2 → 1.9.0-alpha.3  (same feature branch)

# Different prerelease type (version increment + new prerelease)
1.9.0-alpha.3 → 1.9.1-beta.1  (develop branch)
1.9.1-beta.1 → 1.9.2-rc.1     (release branch)
1.9.2-rc.1 → 1.9.2            (main branch - stable release)
```

## 🔄 **Workflow Integration**

### **Branch Detection**

The workflow automatically detects the current branch and assigns the appropriate prerelease type:

```yaml
# Feature branch example
Branch: feature/user-authentication
Result: 1.9.0-alpha.1

# Develop branch example  
Branch: develop
Result: 1.9.0-beta.1

# Release branch example
Branch: release/v1.9.0
Result: 1.9.0-rc.1

# Main branch example
Branch: main
Result: 1.9.0 (stable)
```

### **Conflict Resolution**

The system handles prerelease conflicts intelligently:

1. **Same Prerelease Type**: Increments prerelease number
2. **Different Prerelease Type**: Increments version and starts new prerelease sequence
3. **Tag Conflicts**: Uses auto-increment logic to find next available version

## 📋 **Examples**

### **Example 1: Feature Development**

```bash
# Developer creates feature branch
git checkout -b feature/payment-integration

# Makes changes and pushes
git commit -m "feat: add PayPal integration"
git push origin feature/payment-integration

# Workflow result:
🌿 Determining prerelease type based on branch...
📋 Current branch: feature/payment-integration
🔬 Feature branch detected → alpha prerelease
📦 Final version will be: 1.9.0-alpha.1
```

### **Example 2: Integration Testing**

```bash
# Feature merged to develop
git checkout develop
git merge feature/payment-integration
git push origin develop

# Workflow result:
🌿 Determining prerelease type based on branch...
📋 Current branch: develop
🧪 Develop branch detected → beta prerelease
📦 Final version will be: 1.9.0-beta.1
```

### **Example 3: Release Preparation**

```bash
# Create release branch
git checkout -b release/v1.9.0
git push origin release/v1.9.0

# Workflow result:
🌿 Determining prerelease type based on branch...
📋 Current branch: release/v1.9.0
🚀 Release branch detected → release candidate
📦 Final version will be: 1.9.0-rc.1
```

### **Example 4: Production Release**

```bash
# Release branch merged to main
git checkout main
git merge release/v1.9.0
git push origin main

# Workflow result:
🌿 Determining prerelease type based on branch...
📋 Current branch: main
📦 Main branch detected → stable release
📦 Final version will be: 1.9.0
```

### **Example 5: Prerelease Increments**

```bash
# Multiple commits on same feature branch
git checkout feature/payment-integration

# First commit
git commit -m "feat: add PayPal integration"
# Result: 1.9.0-alpha.1

# Second commit  
git commit -m "fix: resolve PayPal API timeout"
# Result: 1.9.0-alpha.2

# Third commit
git commit -m "docs: update PayPal integration guide"
# Result: 1.9.0-alpha.3
```

## 🎯 **Benefits**

### **✅ Automatic Prerelease Management**
- No manual prerelease identifier specification required
- Consistent prerelease naming across the team
- Automatic prerelease number incrementation

### **✅ GitFlow Integration**
- Works seamlessly with GitFlow branching strategy
- Supports feature, develop, release, and main branches
- Encourages proper branching practices

### **✅ Semantic Versioning Compliance**
- Follows semantic versioning prerelease standards
- Proper version precedence (alpha < beta < rc < stable)
- Compatible with npm, composer, and other package managers

### **✅ Testing and QA Support**
- Clear distinction between development stages
- Easy identification of version stability
- Supports parallel development workflows

## 🔧 **Configuration**

### **Supported Branch Patterns**

The system recognizes these branch patterns:

```bash
# Feature branches (alpha)
feature/user-auth
feature/payment-gateway
feature/admin-dashboard

# Develop branch (beta)
develop

# Release branches (rc)
release/v1.9.0
release/1.9.0
release/major-update

# Main branches (stable)
main
master
```

### **Customization Options**

While the default branch-based strategy works for most teams, the system can be extended to support:

- **Manual Override**: Commit message flags to override branch-based assignment
- **Custom Branch Patterns**: Additional branch patterns for specific workflows
- **Prerelease Policies**: Team-specific prerelease numbering policies

## 🧪 **Testing**

### **Test Coverage**

The prerelease strategy includes comprehensive test coverage:

```bash
npm run test:auto-increment

# Tests include:
✅ Standard version increments
✅ Prerelease version increments  
✅ Prerelease number incrementation
✅ Cross-prerelease type transitions
✅ Branch-based strategy simulation
```

### **Test Scenarios**

```javascript
// Feature branch (alpha)
'1.0.0' + minor + alpha → '1.1.0-alpha.1'

// Develop branch (beta)  
'1.0.0' + minor + beta → '1.1.0-beta.1'

// Release branch (rc)
'1.0.0' + minor + rc → '1.1.0-rc.1'

// Prerelease increment
'1.1.0-alpha.1' + patch + alpha → '1.1.0-alpha.2'

// Prerelease type change
'1.1.0-alpha.3' + patch + beta → '1.1.1-beta.1'
```

## 🚀 **Migration Guide**

### **For Existing Projects**

1. **No Breaking Changes**: Existing main branch workflows continue unchanged
2. **Gradual Adoption**: Teams can adopt prerelease branches incrementally
3. **Backward Compatibility**: All existing version tags remain valid

### **Best Practices**

1. **Branch Naming**: Use descriptive feature branch names
2. **Merge Strategy**: Use pull requests for better version control
3. **Testing**: Test prerelease versions thoroughly before promoting
4. **Documentation**: Update deployment docs to handle prerelease versions

## 📊 **Version Precedence**

Semantic versioning precedence is maintained:

```bash
1.9.0-alpha.1 < 1.9.0-alpha.2 < 1.9.0-beta.1 < 1.9.0-rc.1 < 1.9.0
```

This ensures proper version ordering in package managers and deployment systems.

## 🔍 **Troubleshooting**

### **Common Issues**

1. **Wrong Prerelease Type**: Check branch name matches expected patterns
2. **Version Conflicts**: System auto-resolves using conflict resolution logic
3. **Missing Prerelease**: Verify branch is pushed to trigger workflow

### **Debugging**

```bash
# Check current branch
git branch --show-current

# Verify workflow triggers
git log --oneline -5

# Test version calculation locally
node -e "console.log(require('./scripts/semver-utils').incrementVersion('1.0.0', 'minor', 'alpha'))"
```

The prerelease versioning strategy provides a robust, automatic solution for managing development, testing, and release versions while maintaining semantic versioning compliance and team workflow efficiency.
