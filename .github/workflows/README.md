# GitHub Actions Workflows

This document explains the automated version bumping and release process for the BlazeCommerce WordPress Plugin.

## Overview

The repository uses a **PR-based version preparation** approach that respects branch protection rules while maintaining automation. The process consists of three main workflows:

1. **PR Version Preparation** (`pr-version-prep.yml`) - Analyzes PRs and prepares version bumps
2. **Main Tag Creation** (`main-tag-creation.yml`) - Creates release tags after PR merge
3. **Release Creation** (`release.yml`) - Creates GitHub releases with ZIP artifacts

## Workflow Process

### 1. Developer Creates PR

When you create a PR against the `main` branch with conventional commits:

```bash
git commit -m "feat: add new product filter functionality"
git commit -m "fix: resolve cart calculation bug"
```

### 2. Automatic Version Analysis

The `pr-version-prep.yml` workflow automatically:

- ✅ Analyzes commits in the PR for conventional commit patterns
- ✅ Determines the appropriate version bump type (major/minor/patch)
- ✅ Updates `package.json` and `blaze-wooless.php` with new version
- ✅ Generates changelog entries
- ✅ Commits changes back to the PR branch
- ✅ Comments on the PR with version bump information

### 3. PR Review and Merge

Developers can review the version changes as part of the normal PR process. The PR will include:

- Updated version numbers in all relevant files
- Generated changelog entries
- Clear indication of the version bump type and reasoning

### 4. Automatic Tag Creation

When the PR is merged to `main`, the `main-tag-creation.yml` workflow:

- ✅ Detects the version change in `package.json`
- ✅ Validates version format and consistency
- ✅ Creates an annotated git tag (e.g., `v1.6.0`)
- ✅ Pushes the tag to trigger the release workflow

### 5. Automatic Release Creation

When a version tag is created, the `release.yml` workflow:

- ✅ Builds the plugin ZIP file with proper exclusions
- ✅ Extracts release notes from the changelog
- ✅ Creates a GitHub release with the ZIP artifact attached
- ✅ Publishes the release for download

## Conventional Commit Patterns

The system recognizes these conventional commit patterns:

| Pattern | Version Bump | Example |
|---------|--------------|---------|
| `feat:` | Minor (1.5.0 → 1.6.0) | `feat: add user authentication` |
| `fix:` | Patch (1.5.0 → 1.5.1) | `fix: resolve login redirect issue` |
| `perf:` | Patch (1.5.0 → 1.5.1) | `perf: optimize database queries` |
| `feat!:` or `BREAKING CHANGE` | Major (1.5.0 → 2.0.0) | `feat!: redesign API structure` |

### Non-Version-Bumping Commits

These commit types don't trigger version bumps:
- `docs:` - Documentation changes
- `style:` - Code formatting changes
- `refactor:` - Code refactoring without feature changes
- `test:` - Adding or updating tests
- `chore:` - Build process or auxiliary tool changes

## Workflow Files

### `pr-version-prep.yml`

**Triggers:** PR opened/updated against main branch
**Purpose:** Analyze commits and prepare version changes in PR

**Key Features:**
- Analyzes only commits in the PR (not entire history)
- Skips if PR is from a fork (security)
- Avoids duplicate commits if version already updated
- Updates PR with informative comments
- Uses `[skip ci]` to prevent recursive triggers

### `main-tag-creation.yml`

**Triggers:** Push to main with `package.json` changes
**Purpose:** Create release tags after PR merge

**Key Features:**
- Only runs on merge commits (not direct pushes)
- Validates version format (semantic versioning)
- Checks version consistency across files
- Skips if tag already exists
- Creates annotated tags with detailed messages

### `release.yml`

**Triggers:** Version tags pushed (e.g., `v1.6.0`)
**Purpose:** Create GitHub releases with ZIP artifacts

**Key Features:**
- Builds production-ready plugin ZIP
- Extracts changelog content for release notes
- Supports custom exclusion rules via `.zipignore`
- Fallback to auto-generated release notes
- Comprehensive error handling and validation

### `auto-version.yml` (DISABLED)

This workflow has been disabled and replaced by the PR-based approach. It's kept for reference and emergency use only.

## Configuration

### Required Secrets

- `AUTOMATION_TOKEN` - GitHub token with repository write access
- `BC_GITHUB_TOKEN` - Fallback token (optional)

### Token Permissions

The automation token needs these permissions:
- **Contents:** Write (for creating tags and releases)
- **Pull Requests:** Write (for commenting on PRs)
- **Metadata:** Read (for repository access)

### Branch Protection

The workflows are designed to work with branch protection rules:
- ✅ Requires pull requests for changes to main
- ✅ Requires status checks before merging
- ✅ No need to bypass branch protection

## Troubleshooting

### Common Issues

**1. Version bump not triggered**
- Check that commits follow conventional commit format
- Ensure PR is against main branch (not from fork)
- Verify workflow has proper permissions

**2. Tag creation failed**
- Check that version format is valid (x.y.z)
- Ensure versions are consistent across files
- Verify automation token has write access

**3. Release creation failed**
- Check that tag was created successfully
- Verify ZIP build process completed
- Ensure release workflow has proper permissions

### Manual Intervention

If automation fails, you can manually:

1. **Create version tag:**
   ```bash
   git tag -a v1.6.0 -m "Release version 1.6.0"
   git push origin v1.6.0
   ```

2. **Trigger release workflow:**
   The release workflow will automatically trigger when the tag is pushed.

## Benefits of This Approach

✅ **Respects Branch Protection** - No direct pushes to main branch
✅ **Version Review** - Version changes are reviewable in PRs
✅ **No Conflicts** - Prevents concurrent PR version conflicts
✅ **Clean History** - Tags only created on successful merges
✅ **Automated** - Still fully automated, just PR-based
✅ **Secure** - Works with fork PRs and security restrictions
✅ **Flexible** - Easy to skip automation when needed

## Migration Notes

- The old `auto-version.yml` workflow has been disabled
- Existing functionality is preserved but moved to PR-based approach
- No changes needed to commit message formats
- Branch protection rules can remain as-is
