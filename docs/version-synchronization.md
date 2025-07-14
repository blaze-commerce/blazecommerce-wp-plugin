# Version Synchronization System

This document describes the enhanced version synchronization system that ensures proper alignment between git tags and version references in all files.

## Overview

The version synchronization system prevents issues like the v1.14.1 release where git tags were created but version files were not properly synchronized. It provides:

- **Comprehensive validation** of version consistency across all files
- **Automatic detection** of version mismatches between git tags and files
- **Automatic resolution** of version conflicts (optional)
- **Context-aware validation** for pre-bump and post-bump scenarios
- **GitHub Actions integration** with proper workflow validation
- **Detailed logging** and error reporting for debugging
- **Safe rollback** capabilities with backup creation

### Recent Fixes (v1.14.1 Issue Resolution)

This system specifically addresses the v1.14.1 release issue where:
- **Problem**: Git tag `v1.14.1` was created but all files still contained `1.14.0`
- **Root Cause**: GitHub Actions workflows failed due to post-bump validation incorrectly flagging success as failure
- **Solution**: Context-aware validation with `--no-conflicts` flag for post-bump scenarios

## Components

### 1. Version Sync Validator (`scripts/version-sync-validator.js`)

Validates that git tags match version references in all files.

**Usage:**
```bash
# Validate current git tag against files
node scripts/version-sync-validator.js

# Validate specific tag
node scripts/version-sync-validator.js --tag v1.14.1

# Verbose output
node scripts/version-sync-validator.js --verbose

# NPM script shortcuts
npm run validate-version-sync
npm run validate-version-sync:verbose
```

**Features:**
- Extracts version from git tags (removes 'v' prefix)
- Checks all version files: package.json, blaze-wooless.php, blocks/package.json, README.md
- Compares tag version with file versions
- Generates detailed mismatch reports
- Provides resolution suggestions

### 2. Version Mismatch Fix (`scripts/fix-version-mismatch.js`)

Automatically fixes version mismatches by updating all files to match a target version.

**Usage:**
```bash
# Fix all files to match version 1.14.1
node scripts/fix-version-mismatch.js 1.14.1

# Dry run (show what would change)
node scripts/fix-version-mismatch.js 1.14.1 --dry-run

# Verbose output
node scripts/fix-version-mismatch.js 1.14.1 --verbose

# Skip backup creation
node scripts/fix-version-mismatch.js 1.14.1 --no-backup

# Skip post-fix validation
node scripts/fix-version-mismatch.js 1.14.1 --no-validate

# NPM script shortcut
npm run fix-version-mismatch 1.14.1
```

**Features:**
- Creates automatic backups before making changes
- Updates all version files consistently
- Validates changes after update
- Provides rollback on failure
- Supports dry-run mode for testing

### 3. Enhanced Workflows

#### Release Workflow (`.github/workflows/release.yml`)

**New validation steps:**
1. **File consistency validation** - Ensures all files have consistent versions
2. **Tag-to-files synchronization** - Validates git tag matches file versions
3. **Automatic mismatch resolution** - Optionally fixes mismatches automatically
4. **Comprehensive logging** - Detailed validation reports

**Configuration:**
```yaml
env:
  AUTO_FIX_VERSION_MISMATCH: "false"  # Set to "true" to enable auto-fix
```

#### Auto-Version Workflow (`.github/workflows/auto-version.yml`)

**New validation steps:**
1. **Post-update validation** - Ensures all files were updated correctly
2. **Pre-tag validation** - Validates consistency before creating git tag
3. **Post-tag validation** - Verifies tag matches file versions
4. **Enhanced summary** - Comprehensive validation status reporting

## Version Files Tracked

The system tracks versions in these files:

| File | Location | Description |
|------|----------|-------------|
| `package.json` | `.version` | NPM package version |
| `blaze-wooless.php` | Plugin Header | WordPress plugin header |
| `blaze-wooless.php` | PHP Constant | `BLAZE_COMMERCE_VERSION` constant |
| `blocks/package.json` | `.version` | Blocks package version |
| `README.md` | Version Badge | `**Version:** X.X.X` badge |

## Error Handling

### Common Issues and Solutions

**1. Version Mismatch Between Tag and Files**
```
Error: Git tag v1.14.1 does not match file versions
```

**Solutions:**
- Enable auto-fix: Set `AUTO_FIX_VERSION_MISMATCH=true` in workflow
- Manual fix: `node scripts/fix-version-mismatch.js 1.14.1`
- Update files manually to match tag version

**2. File Consistency Issues**
```
Error: Version files are inconsistent after update
```

**Solutions:**
- Run validation: `npm run validate-version-sync:verbose`
- Fix inconsistencies: `node scripts/fix-version-mismatch.js <target-version>`
- Check for file permission issues

**3. Git Tag Creation Failures**
```
Error: Tag v1.14.1 already exists
```

**Solutions:**
- Use version conflict resolution in auto-version workflow
- Manually increment to next available version
- Delete existing tag if appropriate (with caution)

## Workflow Integration

### Release Workflow Behavior

1. **Tag Created** → Workflow triggered
2. **File Validation** → Check version consistency
3. **Tag Validation** → Compare tag with files
4. **Mismatch Detection** → Either abort or auto-fix
5. **Build & Release** → Proceed only if synchronized

### Auto-Version Workflow Behavior

1. **Version Update** → Update all version files
2. **Post-Update Check** → Validate file consistency
3. **Pre-Tag Check** → Ensure ready for tagging
4. **Tag Creation** → Create git tag
5. **Post-Tag Check** → Verify tag matches files

## Best Practices

### For Developers

1. **Always validate before manual releases:**
   ```bash
   npm run validate-version-sync:verbose
   ```

2. **Use dry-run for testing fixes:**
   ```bash
   node scripts/fix-version-mismatch.js 1.14.1 --dry-run
   ```

3. **Check validation after manual version changes:**
   ```bash
   npm run validate-version && npm run validate-version-sync
   ```

### For CI/CD

1. **Enable auto-fix for automated workflows:**
   ```yaml
   env:
     AUTO_FIX_VERSION_MISMATCH: "true"
   ```

2. **Use comprehensive logging:**
   ```yaml
   - run: node scripts/version-sync-validator.js --verbose
   ```

3. **Validate before and after version operations:**
   ```yaml
   - name: Pre-operation validation
     run: npm run validate-version-sync
   
   - name: Update versions
     run: # ... version update logic
   
   - name: Post-operation validation  
     run: npm run validate-version-sync
   ```

## Troubleshooting

### Debug Commands

```bash
# Check current version status
npm run validate-version-sync:verbose

# Check what files would be updated
node scripts/fix-version-mismatch.js 1.14.1 --dry-run --verbose

# View git tags
git tag | grep -E '^v[0-9]' | sort -V

# Check file versions manually
grep -r "1\.14\." package.json blaze-wooless.php README.md blocks/package.json
```

### Recovery Procedures

**If version update fails:**
1. Check backup directory: `.backup/version-fix-*`
2. Restore from backup if needed
3. Run validation to identify issues
4. Fix issues and retry

**If git tag is wrong:**
1. Delete incorrect tag: `git tag -d v1.14.1`
2. Fix version files: `node scripts/fix-version-mismatch.js 1.14.1`
3. Create correct tag: `git tag v1.14.1`
4. Push tag: `git push origin v1.14.1`

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `AUTO_FIX_VERSION_MISMATCH` | `false` | Enable automatic mismatch resolution |
| `DEBUG_MODE` | `false` | Enable debug output in workflows |

### Script Options

All scripts support these common options:
- `--verbose, -v` - Detailed output
- `--help, -h` - Show help message

Fix script specific options:
- `--dry-run` - Show changes without applying
- `--no-backup` - Skip backup creation
- `--no-validate` - Skip post-fix validation

## Migration Guide

### From Previous System

The new system is backward compatible. Existing scripts continue to work:

```bash
# Old way (still works)
npm run validate-version

# New way (enhanced)
npm run validate-version-sync
```

### Updating Existing Workflows

Add these steps to existing workflows:

```yaml
- name: Validate version synchronization
  run: npm run validate-version-sync:verbose

- name: Fix version mismatches (optional)
  if: failure()
  run: node scripts/fix-version-mismatch.js ${{ env.TARGET_VERSION }}
```

## GitHub Actions Workflow Fixes

### Context-Aware Validation

The workflows now use context-aware validation to distinguish between pre-bump and post-bump scenarios:

#### Pre-Bump Validation
```yaml
# Standard validation with conflict checking
- name: 🔍 Validate Current Version
  run: node scripts/validate-version.js --verbose
```

#### Post-Bump Validation
```yaml
# Post-bump validation without conflict checking
- name: 🔍 Validate Version Consistency (Post-Bump)
  run: node scripts/validate-version.js --verbose --no-conflicts
```

### Auto-Version Workflow Fixes

The auto-version workflow has been updated to:

1. **Use `--no-conflicts` flag** for post-bump validation steps
2. **Prevent false positive failures** when validating after version updates
3. **Provide clear step names** indicating validation context
4. **Include detailed error reporting** for debugging

### Release Workflow Integration

The release workflow includes:

1. **Semantic version validation** using `scripts/semver-utils.js`
2. **Tag-to-files synchronization** validation before release creation
3. **Comprehensive error handling** with detailed logging

### New NPM Scripts for Workflows

```bash
# Post-bump validation (no conflict checking)
npm run validate-version:post-bump

# Automatic resolution
npm run fix-version-mismatch:auto

# No-conflicts validation
npm run validate-version:no-conflicts
```

## Enhanced Error Handling

The validation scripts now provide actionable error messages with specific commands to resolve issues:

### Improved Error Messages

When validation fails, you'll see:

```
❌ Version validation failed. Please fix the issues above.

🔧 QUICK FIXES:
   • Run: npm run fix-version-mismatch:auto
   • Or: node scripts/validate-version.js --apply-resolution

📚 DOCUMENTATION:
   • See: docs/version-synchronization.md
   • Troubleshooting: docs/version-synchronization.md#troubleshooting
```

### Common Error Scenarios

#### Version Mismatch Between Files
```bash
# Quick fix
npm run fix-version-mismatch:auto

# Manual fix to specific version
node scripts/fix-version-mismatch.js 1.14.1 --verbose
```

#### Post-Bump Validation Failures
```bash
# Use no-conflicts validation
npm run validate-version:post-bump

# Or directly
node scripts/validate-version.js --verbose --no-conflicts
```

#### Tag-to-Files Synchronization Issues
```bash
# Check synchronization
npm run validate-version-sync:verbose

# Fix automatically
npm run fix-version-mismatch:auto
```

## Troubleshooting

### Workflow Validation Failures

#### Problem: Auto-version workflow fails on post-bump validation
**Solution**: Ensure the workflow uses `--no-conflicts` flag:
```yaml
- name: 🔍 Validate Version Consistency (Post-Bump)
  run: node scripts/validate-version.js --verbose --no-conflicts
```

#### Problem: "Version conflicts detected" after successful version bump
**Cause**: Using standard validation instead of post-bump validation
**Solution**: Use the correct validation context:
```bash
# Wrong (will fail after version bump)
npm run validate-version

# Correct (for post-bump scenarios)
npm run validate-version:post-bump
```

#### Problem: Git tag exists but files show different version
**Solution**: Use automatic resolution:
```bash
# Analyze the issue
npm run validate-version-sync:verbose

# Fix automatically
npm run fix-version-mismatch:auto

# Or fix to specific version
node scripts/fix-version-mismatch.js [VERSION] --verbose
```

### Common Issues and Solutions

| Issue | Command | Description |
|-------|---------|-------------|
| Version mismatch | `npm run fix-version-mismatch:auto` | Automatically resolve version conflicts |
| Post-bump validation fails | `npm run validate-version:post-bump` | Use no-conflicts validation |
| Need detailed analysis | `npm run validate-version:verbose` | Get comprehensive validation report |
| Tag-files sync issues | `npm run validate-version-sync:verbose` | Check tag-to-files synchronization |
| Workflow debugging | Check workflow logs | Look for context-aware validation steps |

### Getting Help

1. **Check Documentation**: This file contains comprehensive usage examples
2. **Run Verbose Mode**: Add `--verbose` to any validation command for detailed output
3. **Use Analysis Mode**: `node scripts/validate-version.js --analyze` for comprehensive analysis
4. **Check Workflow Logs**: GitHub Actions logs show detailed validation steps
