---
title: "Changelog Path Fix"
description: "Documentation for the changelog path standardization fix"
category: "development"
version: "1.0.0"
last_updated: "2025-01-13"
author: "Blaze Commerce Team"
tags: ["changelog", "automation", "documentation", "fix"]
related_docs: ["automation.md", "usage.md"]
---

# Changelog Path Fix

## Overview

This document describes the fix implemented to align the automated changelog generation with the project's documentation organization guidelines. The change ensures that the `scripts/update-changelog.js` script correctly updates the changelog at `docs/reference/changelog.md` instead of creating/updating `CHANGELOG.md` in the root directory.

## Problem Statement

### **Issue Identified**
- **Discrepancy**: Automated scripts were creating/updating `CHANGELOG.md` in the root directory
- **Standard**: Project documentation guidelines require changelog at `docs/reference/changelog.md`
- **Impact**: Inconsistency between automated processes and documentation standards

### **Root Cause**
The `scripts/update-changelog.js` script was hardcoded to use `CHANGELOG.md` in the root directory, which conflicted with the established documentation organization structure.

## Solution Implementation

### **Changes Made**

#### 1. Updated Script Configuration
- **File**: `scripts/update-changelog.js`
- **Change**: Added `CHANGELOG_PATH` constant pointing to `docs/reference/changelog.md`
- **Impact**: All file operations now use the correct path

#### 2. Enhanced Directory Handling
- **Function**: `ensureChangelogDirectory()`
- **Purpose**: Automatically creates `docs/reference/` directory if it doesn't exist
- **Benefit**: Robust handling for new repositories or clean environments

#### 3. Frontmatter Preservation
- **Functions**: `extractFrontmatter()`, `createDefaultFrontmatter()`
- **Purpose**: Preserve and manage YAML frontmatter in changelog files
- **Features**:
  - Extracts existing frontmatter from changelog
  - Creates default frontmatter for new files
  - Updates `last_updated` field automatically

#### 4. Workflow Updates
- **Files**: `.github/workflows/release.yml`, `.github/workflows/auto-version.yml`
- **Changes**: Updated all references to use `docs/reference/changelog.md`
- **Impact**: GitHub Actions now work with the correct file location

### **Technical Details**

#### Path Configuration
```javascript
// Configuration for changelog file location
const CHANGELOG_PATH = 'docs/reference/changelog.md';
```

#### Directory Creation
```javascript
function ensureChangelogDirectory() {
  try {
    const changelogDir = path.dirname(CHANGELOG_PATH);
    if (!fs.existsSync(changelogDir)) {
      console.log(`📁 Creating directory: ${changelogDir}`);
      fs.mkdirSync(changelogDir, { recursive: true });
    }
    return true;
  } catch (error) {
    console.error(`❌ Error creating changelog directory: ${error.message}`);
    return false;
  }
}
```

#### Frontmatter Management
```javascript
function extractFrontmatter(content) {
  const frontmatterRegex = /^---\n([\s\S]*?)\n---\n([\s\S]*)$/;
  const match = content.match(frontmatterRegex);
  
  if (match) {
    return {
      frontmatter: match[1],
      content: match[2]
    };
  }
  
  return {
    frontmatter: null,
    content: content
  };
}
```

## Migration Process

### **For Existing Repositories**

1. **Manual Migration** (if needed):
   ```bash
   # If you have an existing CHANGELOG.md in root
   mkdir -p docs/reference
   mv CHANGELOG.md docs/reference/changelog.md
   ```

2. **Add Frontmatter** (if missing):
   ```yaml
   ---
   title: "Changelog"
   description: "Version history and release notes for the Blaze Commerce WordPress Plugin"
   category: "reference"
   version: "1.0.0"
   last_updated: "YYYY-MM-DD"
   author: "Blaze Commerce Team"
   tags: ["changelog", "releases", "version-history", "updates"]
   related_docs: ["index.md"]
   ---
   ```

3. **Test the Script**:
   ```bash
   npm run changelog:dry-run
   ```

### **For New Repositories**

The script will automatically:
1. Create the `docs/reference/` directory
2. Generate a new changelog with proper frontmatter
3. Follow the established format and structure

## Testing

### **Automated Tests**
A comprehensive test suite is available at `test/test-changelog-path-fix.js`:

```bash
# Run the test suite
node test/test-changelog-path-fix.js
```

### **Test Coverage**
- ✅ Correct path usage verification
- ✅ Frontmatter preservation testing
- ✅ Directory creation handling
- ✅ Workflow file updates validation

### **Manual Testing**
```bash
# Test dry-run mode
npm run changelog:dry-run

# Test with verbose output
npm run changelog:verbose

# Test with specific version
node scripts/update-changelog.js 1.2.3 --dry-run
```

## Benefits

### **Consistency**
- ✅ Aligns automated processes with documentation standards
- ✅ Maintains single source of truth for changelog location
- ✅ Follows established project organization guidelines

### **Maintainability**
- ✅ Centralized path configuration
- ✅ Robust error handling and directory creation
- ✅ Preserved frontmatter metadata

### **Automation**
- ✅ Seamless integration with existing workflows
- ✅ Automatic frontmatter management
- ✅ No manual intervention required

## Usage

### **Standard Commands**
```bash
# Update changelog for current package.json version
npm run changelog

# Preview changes without writing
npm run changelog:dry-run

# Detailed output
npm run changelog:verbose

# Technical format
npm run changelog:technical

# Grouped by scope
npm run changelog:grouped
```

### **Advanced Usage**
```bash
# Force override existing entry
node scripts/update-changelog.js --force

# Update for specific version
node scripts/update-changelog.js 1.2.3

# Include all commits (not just conventional)
node scripts/update-changelog.js --include-all
```

## Troubleshooting

### **Common Issues**

#### Directory Permissions
```bash
# If you get permission errors
sudo chown -R $USER:$USER docs/
chmod -R 755 docs/
```

#### Missing Dependencies
```bash
# Ensure all dependencies are installed
npm install
```

#### Git Repository Issues
```bash
# Ensure you're in a git repository
git status

# Check for uncommitted changes
git diff --name-only
```

### **Error Messages**

| Error | Solution |
|-------|----------|
| `❌ Error creating changelog directory` | Check file permissions |
| `❌ Could not read version from package.json` | Ensure package.json exists and is valid |
| `❌ Error writing changelog file` | Check directory permissions and disk space |

## Related Files

- `scripts/update-changelog.js` - Main changelog generation script
- `docs/reference/changelog.md` - The changelog file
- `.github/workflows/release.yml` - Release workflow
- `.github/workflows/auto-version.yml` - Auto-versioning workflow
- `test/test-changelog-path-fix.js` - Test suite
- `package.json` - NPM scripts configuration

## Future Enhancements

### **Potential Improvements**
- Configuration file for changelog path
- Multiple changelog format support
- Integration with release notes generation
- Automated changelog validation

### **Monitoring**
- Regular validation of path consistency
- Automated testing in CI/CD pipeline
- Documentation synchronization checks

## Conclusion

This fix ensures that the BlazeCommerce WordPress Plugin maintains consistency between its automated processes and documentation organization standards. The implementation is robust, well-tested, and maintains backward compatibility while providing enhanced functionality for frontmatter management and directory handling.
