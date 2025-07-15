# Auto-Increment Version System

## Overview

The BlazeCommerce WordPress plugin now features a **bulletproof auto-increment version system** with **prerelease support** that automatically resolves version conflicts by finding the next available version. This eliminates CI/CD failures due to version conflicts and ensures the workflow always succeeds.

### **Key Features**
- ✅ **Auto-increment conflict resolution** - Never fails due to version conflicts
- ✅ **Branch-based prerelease versioning** - Automatic alpha/beta/rc versions
- ✅ **Semantic versioning compliance** - Proper version precedence and formatting
- ✅ **WordPress plugin best practices** - Optimized for plugin directory standards

> **New**: See [Prerelease Versioning Strategy](prerelease-versioning-strategy.md) for detailed information about alpha/beta/rc versioning.

## How It Works

### 1. Standard Version Calculation

The system first calculates the new version based on conventional commits:

```bash
# Examples:
fix: bug → 1.9.0 → 1.9.1 (patch)
feat: new feature → 1.9.0 → 1.10.0 (minor)  
feat!: breaking change → 1.9.0 → 2.0.0 (major)
```

### 2. Automatic Conflict Resolution

If the calculated version's git tag already exists, the system automatically finds the next available version:

```bash
# Example scenario:
Calculated version: 1.9.1
Git tag v1.9.1 already exists ❌

Auto-resolution process:
- Try v1.9.2 → exists ❌
- Try v1.9.3 → exists ❌  
- Try v1.9.4 → available ✅

Final version: 1.9.4
```

### 3. Transparent Logging

The system provides detailed logging of the resolution process:

```bash
🔍 Checking for git tag conflicts...
⚠️  Git tag conflict detected:
   Calculated version: 1.9.1
   Git tag v1.9.1 already exists
🔄 Auto-resolving by finding next available version...
✅ Found available version: 1.9.4 (tag v1.9.4 doesn't exist)
✅ Git tag conflict resolved:
   Original calculated version: 1.9.1
   Final resolved version: 1.9.4
   Resolution: Auto-incremented patch version to avoid tag conflict
```

## Benefits

### ✅ **Never Fails Due to Version Conflicts**
- No more CI/CD pipeline failures
- Automatic resolution without manual intervention
- Bulletproof version progression

### ✅ **Maintains Semantic Versioning**
- Respects conventional commit analysis
- Uses patch increments for conflict resolution
- Preserves version meaning and progression

### ✅ **Full Transparency**
- Detailed logging of resolution process
- Clear indication when auto-resolution occurs
- Audit trail of version decisions

### ✅ **Backward Compatible**
- Works with existing conventional commit patterns
- No changes needed to commit message format
- Seamless integration with current workflow

## Troubleshooting

### Common Issues and Solutions

#### **Issue 1: "Version conflict resolution failed"**

**Symptoms:**
```bash
❌ Error: Version conflict resolution failed (exit code: 1)
   Original version: 1.9.1
   Troubleshooting steps:
   1. Check git repository integrity: git fsck
   2. Verify tag permissions: git tag --list | head -5
   3. Check network connectivity if using remote repository
   4. Review semver-utils.js for potential issues
```

**Causes & Solutions:**
- **Git Repository Issues**: Run `git fsck` to check repository integrity
- **Permission Problems**: Ensure the workflow has proper git permissions
- **Network Issues**: Check connectivity to remote repository
- **Corrupted Tags**: Clean up corrupted tags with `git tag -d <tag-name>`

#### **Issue 2: "Invalid version format"**

**Symptoms:**
```bash
❌ Invalid version format: 1.9.1-alpha+build
   Expected format: X.Y.Z or X.Y.Z-prerelease+build
```

**Causes & Solutions:**
- **Malformed Version**: Check conventional commit analysis logic
- **Regex Issues**: Verify TAG_NAME_REGEX in config.js supports your versioning scheme
- **Character Encoding**: Ensure no hidden characters in version strings

#### **Issue 3: "No available version found within reasonable range"**

**Symptoms:**
```bash
❌ No available version found within reasonable range
```

**Causes & Solutions:**
- **Too Many Existing Tags**: Increase `maxAttempts` in findNextAvailableVersion
- **Tag Naming Conflicts**: Review existing tag naming patterns
- **Repository Cleanup**: Remove unnecessary tags to free up version space

#### **Issue 4: "test:version-system script not found"**

**Symptoms:**
```bash
⚠️  test:version-system script not found, running fallback validation...
```

**Causes & Solutions:**
- **Missing Script**: Add the script to package.json
- **Dependency Issues**: Install required test dependencies
- **Fallback Success**: This is acceptable - fallback validation will run

#### **Issue 5: Race Conditions**

**Symptoms:**
```bash
❌ Error: Resolved version tag already exists
   This indicates a race condition or logic error
```

**Causes & Solutions:**
- **Concurrent Builds**: Ensure only one version bump runs at a time
- **Cache Issues**: Clear version cache and retry
- **Logic Error**: Check findNextAvailableVersion implementation

### Multiple Conflicts Behavior

When multiple conflicts are encountered, the system:

1. **Starts with calculated version** (e.g., 1.9.1)
2. **Increments patch version** until available tag found
3. **Logs each attempt** for transparency
4. **Stops at first available** version (e.g., 1.9.4)
5. **Validates final version** before proceeding

**Maximum Attempts:** 50 (configurable)
**Strategy:** Always patch increment for conflict resolution
**Fallback:** Fails gracefully with detailed error messages

### Performance Considerations

#### **Large Repositories**
- **Memory Usage**: Monitor memory consumption for repos with 1000+ tags
- **Network Latency**: Consider local git operations vs remote checks
- **Batch Processing**: System processes commits in batches for efficiency

#### **Optimization Tips**
- **Tag Cleanup**: Regularly clean up old/unused tags
- **Local Caching**: Use local git repository when possible
- **Parallel Builds**: Avoid concurrent version bumps

### Debugging Commands

```bash
# Check repository integrity
git fsck --full

# List recent tags
git tag --sort=-version:refname | head -10

# Verify tag existence
git rev-parse --verify v1.9.1

# Check git configuration
git config --list | grep user

# Test semver utilities
node -e "console.log(require('./scripts/semver-utils').getCurrentVersion())"

# Run auto-increment tests
npm run test:auto-increment

# Validate version format
node -e "console.log(require('./scripts/semver-utils').isValidSemver('1.9.1-alpha.1+build.1'))"
```

## Technical Implementation

### Workflow Integration

The auto-increment logic is integrated into the GitHub Actions workflow at two key points:

1. **Version Calculation Step** (`.github/workflows/auto-version.yml`)
   - Calculates initial version based on commits
   - Checks for git tag conflicts
   - Auto-resolves conflicts using `findNextAvailableVersion()`

2. **Post-Bump Validation Step**
   - Validates final version consistency
   - Uses `--no-conflicts` flag for post-bump validation
   - Confirms git tag availability

### Core Functions

The system leverages existing utilities from `scripts/semver-utils.js`:

```javascript
// Check if git tag exists
tagExists(tagName) → boolean

// Find next available version
findNextAvailableVersion(version, bumpType, options) → string
```

## Examples

### Example 1: No Conflicts
```bash
Commits: fix: resolve authentication bug
Calculated version: 1.9.0 → 1.9.1
Git tag v1.9.1: ✅ Available
Final version: 1.9.1

🔍 Checking for git tag conflicts...
📊 Conflict Resolution Details:
   Repository: https://github.com/blaze-commerce/blazecommerce-wp-plugin
   Current branch: main
   Total existing tags: 45
🏷️  Checking tag: v1.9.1
✅ No git tag conflicts detected for v1.9.1
   Tag is available for use
```

### Example 2: Single Conflict
```bash
Commits: feat: add user dashboard
Calculated version: 1.9.0 → 1.10.0
Git tag v1.10.0: ❌ Exists
Auto-resolution: v1.10.1 ✅ Available
Final version: 1.10.1

🔍 Checking for git tag conflicts...
🏷️  Checking tag: v1.10.0
⚠️  Git tag conflict detected:
   Calculated version: 1.10.0
   Git tag v1.10.0 already exists
   Tag creation date: 2024-12-15 10:30:45 +0000
🔄 Auto-resolving by finding next available version...
🔍 Starting conflict resolution process...
   Base version: 1.10.0
   Strategy: patch increment
✅ Resolution successful: 1.10.1
✅ Git tag conflict resolved successfully:
   Original calculated version: 1.10.0
   Final resolved version: 1.10.1
   Resolution method: Auto-incremented patch version
   Verified tag availability: ✅
```

### Example 3: Multiple Conflicts
```bash
Commits: fix: critical security patch
Calculated version: 1.9.0 → 1.9.1
Git tag v1.9.1: ❌ Exists
Git tag v1.9.2: ❌ Exists
Git tag v1.9.3: ❌ Exists
Git tag v1.9.4: ✅ Available
Final version: 1.9.4

🔍 Checking for git tag conflicts...
🏷️  Checking tag: v1.9.1
⚠️  Git tag conflict detected:
   Calculated version: 1.9.1
   Git tag v1.9.1 already exists
   Tag creation date: 2024-12-10 14:22:33 +0000
🔄 Auto-resolving by finding next available version...
🔍 Starting conflict resolution process...
   Base version: 1.9.1
   Strategy: patch increment
✅ Found available version: 1.9.4 (tag v1.9.4 doesn't exist)
✅ Resolution successful: 1.9.4
✅ Git tag conflict resolved successfully:
   Original calculated version: 1.9.1
   Final resolved version: 1.9.4
   Resolution method: Auto-incremented patch version
   Attempts made: 3
   Verified tag availability: ✅
```

### Example 4: Semantic Versioning with Prerelease
```bash
Commits: feat!: breaking API changes (prerelease)
Calculated version: 1.9.0 → 2.0.0-alpha.1
Git tag v2.0.0-alpha.1: ✅ Available
Final version: 2.0.0-alpha.1

🔍 Checking for git tag conflicts...
📊 Conflict Resolution Details:
   Repository: https://github.com/blaze-commerce/blazecommerce-wp-plugin
   Current branch: develop
   Total existing tags: 47
🏷️  Checking tag: v2.0.0-alpha.1
✅ No git tag conflicts detected for v2.0.0-alpha.1
   Tag is available for use
```

## Configuration

### Maximum Attempts

The system has a built-in safety limit to prevent infinite loops:

```javascript
// Default: 100 attempts
// Will try versions 1.9.1, 1.9.2, ..., 1.9.100
// If all exist, throws error (extremely unlikely scenario)
```

### Bump Type Override

When auto-resolution occurs, the bump type is updated to `patch` since the final resolution uses patch increments:

```bash
Original bump type: minor (feat: new feature)
After auto-resolution: patch (due to conflict resolution)
Final version: Uses patch increments from calculated base
```

## Monitoring and Debugging

### Success Indicators

Look for these log messages to confirm the system is working:

```bash
✅ No git tag conflicts detected for v1.9.1
✅ Git tag conflict resolved: 1.9.1 → 1.9.4  
✅ Post-bump validation passed successfully
```

### Troubleshooting

If the workflow fails, check for:

1. **Network/Git Issues**: Cannot check git tags
2. **Semver Utility Errors**: JavaScript execution problems
3. **Extreme Conflicts**: 100+ consecutive existing versions (very unlikely)

### Log Analysis

The workflow provides comprehensive logging:

```bash
📦 Current version: 1.9.0
🔄 Bump type: patch
📦 Calculated version: 1.9.1
🔍 Checking for git tag conflicts...
⚠️  Git tag conflict detected: v1.9.1 already exists
🔄 Auto-resolving by finding next available version...
✅ Found available version: 1.9.4
📦 Final version will be: 1.9.4
```

## Migration Notes

### From Previous System

- **No breaking changes**: Existing workflows continue to work
- **Enhanced reliability**: Fewer CI/CD failures
- **Better logging**: More detailed version resolution information

### Rollback Plan

If needed, the auto-increment logic can be disabled by:

1. Removing the git tag conflict checking section
2. Reverting to the original version calculation logic
3. The system will fall back to failing on conflicts (previous behavior)

## Future Enhancements

### Potential Improvements

1. **Configurable Bump Strategy**: Allow minor/major increments for resolution
2. **Custom Conflict Patterns**: Handle specific version ranges
3. **Integration Hooks**: Notify external systems of auto-resolution
4. **Analytics**: Track frequency and patterns of auto-resolution

### Compatibility

This system is designed to be:
- ✅ **Future-proof**: Works with any version numbering
- ✅ **Scalable**: Handles high-frequency releases
- ✅ **Maintainable**: Clear, documented, and testable code
- ✅ **Reliable**: Comprehensive error handling and validation

The auto-increment version system ensures that version conflicts never block your CI/CD pipeline while maintaining semantic versioning principles and providing full transparency into the resolution process.
