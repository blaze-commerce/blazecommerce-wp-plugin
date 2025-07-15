---
title: "Quick Usage Guide"
description: "Daily development workflow and usage guide for the BlazeCommerce WordPress Plugin"
category: "development"
version: "1.0.0"
last_updated: "2025-01-09"
author: "Blaze Commerce Team"
tags: ["usage", "development", "workflow", "commands", "daily-tasks"]
related_docs: ["automation.md", "claude.md"]
---

# BlazeCommerce WordPress Plugin - Quick Usage Guide

## Daily Development Workflow

### 1. Making Changes
```bash
# Make your code changes
# Edit files in app/, assets/, blocks/, etc.

# If you modified blocks, build them
npm run build:blocks
```

### 2. Committing Changes
Use conventional commit messages for automatic version bumping:

```bash
# For new features (minor version bump)
git commit -m "feat: add new product filter functionality"

# For bug fixes (patch version bump)  
git commit -m "fix: resolve issue with cart synchronization"

# For breaking changes (major version bump)
git commit -m "feat!: redesign API endpoints"
# or
git commit -m "feat: new feature

BREAKING CHANGE: API endpoints have changed"
```

### 3. Version Management

#### Automatic (Recommended)
Just push to main with conventional commits:
```bash
git push origin main
# Version is automatically bumped based on commit message
```

#### Manual
```bash
# Bump patch version (1.5.2 → 1.5.3)
npm run version:patch

# Bump minor version (1.5.2 → 1.6.0)
npm run version:minor

# Bump major version (1.5.2 → 2.0.0)
npm run version:major
```

### 4. Creating Releases
```bash
# Automatic release creation
npm run release

# This will:
# 1. Build the plugin
# 2. Create a git tag
# 3. Push the tag to GitHub
# 4. Trigger GitHub Actions to create the release
```

## Quick Commands

```bash
# Build blocks only
npm run build:blocks

# Update changelog from git commits
npm run changelog

# Prepare everything for release
npm run prepare-release

# Create and push release tag
npm run release
```

## File Locations

- **Plugin main file**: `blaze-wooless.php` (contains version info)
- **Version management**: `package.json` and `scripts/update-version.js`
- **Build configuration**: `blocks/package.json`
- **GitHub workflows**: `.github/workflows/`
- **Guidelines**: `.augment-guidelines`

## Troubleshooting

### Version out of sync
```bash
npm run update-plugin-version
```

### Build issues
```bash
cd blocks
npm install
npm run build
```

### Release failed
Check that:
1. All changes are committed
2. You have push permissions
3. Tag doesn't already exist

## Best Practices

1. **Always use conventional commits** for automatic version management
2. **Test locally** before pushing
3. **Build blocks** after making changes to block code
4. **Review changelog** before creating releases
5. **Keep dependencies updated** in both package.json files
