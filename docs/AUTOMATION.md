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
2. GitHub token has proper permissions
3. Tag doesn't already exist

### Build Issues

For build problems:
```bash
cd blocks
npm install
npm run build
```

## Best Practices

1. **Always use conventional commits** for automatic version management
2. **Test locally** before pushing to main
3. **Review changelog** before creating releases
4. **Keep dependencies updated** in both root and blocks package.json
5. **Follow semantic versioning** principles
