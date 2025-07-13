---
title: "Automation Guide"
description: "Automated workflows for version management, releases, and plugin packaging in the BlazeCommerce WordPress Plugin"
category: "development"
version: "1.0.0"
last_updated: "2025-07-13"
author: "Blaze Commerce Team"
tags: ["automation", "version-management", "releases", "workflows", "ci-cd"]
related_docs: ["usage.md", "claude.md"]
---

# BlazeCommerce WordPress Plugin - Automation Guide

## Overview

This repository includes automated workflows for version management, releases, and plugin packaging. The automation ensures consistency and reduces manual errors in the release process.

## Version Management

### Automatic Version Bumping

The plugin version is automatically bumped based on conventional commit messages:

- `feat:` → Minor version bump (1.5.1 → 1.6.0)
- `fix:` or `perf:` → Patch version bump (1.5.1 → 1.5.2)
- `feat!:` or `BREAKING CHANGE` → Major version bump (1.5.1 → 2.0.0)

### Manual Version Bumping

You can also manually bump versions using npm scripts:

```bash
# Patch version (1.5.1 → 1.5.2)
npm run version:patch

# Minor version (1.5.1 → 1.6.0)
npm run version:minor

# Major version (1.5.1 → 2.0.0)
npm run version:major
```

## Release Process

### Automatic Releases

1. **Commit with conventional format**: `feat: add new feature`
2. **Push to main branch**: Version is automatically bumped
3. **Create release**: `npm run release` (creates tag and triggers GitHub Actions)

### Manual Release Process

1. **Update version**: `npm run version:patch` (or minor/major)
2. **Build assets**: `npm run build`
3. **Update changelog**: `npm run changelog`
4. **Commit changes**: `git add . && git commit -m "chore(release): bump version to X.X.X"`
5. **Create release**: `npm run release`

## GitHub Actions Workflows

### 1. Auto Version Bump (`auto-version.yml`)

- **Trigger**: Push to main branch
- **Action**: Automatically bumps version based on commit message
- **Updates**: package.json, blaze-wooless.php, CHANGELOG.md

### 2. Release Workflow (`release.yml`)

- **Trigger**: Push of version tag (v*)
- **Actions**:
  - Builds the plugin
  - Creates plugin ZIP file
  - Generates release notes from changelog
  - Creates GitHub release with assets

## File Structure

```
├── .github/workflows/
│   ├── auto-version.yml      # Automatic version bumping
│   ├── release.yml           # Release creation
│   └── .zipignore           # Files to exclude from plugin ZIP
├── scripts/
│   ├── update-version.js     # Updates plugin version in PHP files
│   ├── create-release.js     # Creates and pushes release tags
│   └── update-changelog.js   # Generates changelog from commits
├── package.json              # Version management and scripts
└── .augment-guidelines       # Development guidelines
```

## Available Scripts

- `npm run version:patch` - Bump patch version
- `npm run version:minor` - Bump minor version  
- `npm run version:major` - Bump major version
- `npm run build` - Build blocks and assets
- `npm run release` - Create and push release tag
- `npm run changelog` - Update changelog from git commits
- `npm run prepare-release` - Full release preparation

## Conventional Commits

Use these commit message formats for automatic version bumping:

```bash
feat: add new feature                    # Minor bump
fix: resolve bug in product sync         # Patch bump
perf: improve query performance          # Patch bump
feat!: breaking API change               # Major bump
feat: add feature

BREAKING CHANGE: API has changed         # Major bump
```

## Plugin ZIP Creation

The release workflow automatically creates a plugin ZIP file that:

- Excludes development files (node_modules, .git, etc.)
- Includes only production-ready code
- Is named `blazecommerce-wp-plugin-X.X.X.zip`
- Is attached to the GitHub release

## Troubleshooting

### Version Mismatch

If versions get out of sync, run:
```bash
npm run update-plugin-version
```

### Failed Release

If a release fails, check:
1. All changes are committed
2. BC_GITHUB_TOKEN secret has proper permissions (contents: write)
3. Tag doesn't already exist

### Build Issues

For build problems:
```bash
cd blocks
npm install
npm run build
```

## Recent Workflow Fixes (2025-07-13)

### Auto-Version Workflow Improvements

The auto-version workflow has been updated with the following fixes:

1. **Git Configuration**: Fixed Git user configuration to use proper GitHub Actions bot identity
   - Changed from `action@github.com` to `github-actions[bot]@users.noreply.github.com`
   - Updated user name to `github-actions[bot]`

2. **Token Permissions**: Enhanced token handling and permissions
   - Added `actions: read` permission for better workflow access
   - Improved token fallback logic: `BC_GITHUB_TOKEN || github.token`

3. **Commit Process**: Added comprehensive error handling and debugging
   - Added git status checks before and after commit operations
   - Enhanced error messages for troubleshooting failed commits
   - Added verification steps for successful push operations

### Release Workflow Improvements

The release workflow has been updated with:

1. **Permissions**: Added proper permissions for each job
   - `validate-release`: `contents: read, actions: read`
   - `build-and-release`: `contents: write, actions: read`
   - `workflow-summary`: `contents: read`

2. **Release Creation**: Enhanced GitHub release creation process
   - Added `fail_on_unmatched_files: true` for better error detection
   - Improved token handling with fallback logic
   - Added release verification step to confirm successful creation

3. **Error Handling**: Better debugging and error reporting throughout the workflow

### Testing Commit Messages

To test the fixed workflows, use these commit message formats:

- **Patch bump**: `fix: repair auto-version workflow commit issues`
- **Minor bump**: `feat: add enhanced error handling to release workflow`
- **Major bump**: `feat!: restructure workflow permissions (BREAKING CHANGE)`

## Best Practices

1. **Always use conventional commits** for automatic version management
2. **Test locally** before pushing to main
3. **Review changelog** before creating releases
4. **Keep dependencies updated** in both root and blocks package.json
5. **Follow semantic versioning** principles
6. **Monitor workflow runs** in GitHub Actions for any failures
7. **Check branch protection rules** if commits fail to push
