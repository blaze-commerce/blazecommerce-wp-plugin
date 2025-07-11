# How the Auto-Version Workflow Handles Multiple Commits in a PR

## Quick Answer

**The workflow analyzes ALL commits collectively and makes a SINGLE version bump based on the highest-priority change type found across all commits.**

It does NOT increment the version for each commit individually. Instead, it follows semantic versioning priority rules to determine one final version bump.

## Detailed Behavior

### 1. Commit Collection Process

The workflow collects all commits since the last version tag (or last 50 commits if no tags exist):

```bash
# Get commits since last tag
COMMITS=$(git log --oneline --no-merges --format="%s" ${LAST_TAG}..HEAD)
```

### 2. Priority-Based Analysis

The system analyzes ALL commits and applies this priority hierarchy:

1. **MAJOR (Breaking Changes)** - Highest priority
2. **MINOR (Features)** - Medium priority  
3. **PATCH (Fixes/Performance)** - Lowest priority

### 3. Single Version Bump Decision

Based on the highest-priority change type found across ALL commits:

```javascript
// Priority logic (simplified)
if (hasBreakingChanges) {
  bumpType = 'major';
} else if (hasFeatures) {
  bumpType = 'minor';
} else if (hasFixes) {
  bumpType = 'patch';
} else {
  bumpType = 'none';
}
```

## Examples

### Example 1: Mixed Commit Types
**PR Commits:**
```
1. fix: resolve authentication issue
2. feat: add new user dashboard  
3. fix: correct typo in documentation
4. chore: update dependencies
5. feat: implement dark mode toggle
```

**Analysis:**
- Breaking changes: 0
- Features: 2 (`feat:` commits)
- Fixes: 2 (`fix:` commits)
- Other: 1 (`chore:` commit)

**Result:** `MINOR` version bump (1.8.0 → 1.9.0)
**Reasoning:** Features have higher priority than fixes

### Example 2: Only Fixes
**PR Commits:**
```
1. fix: bug A
2. fix: bug B  
3. fix: bug C
```

**Result:** `PATCH` version bump (1.8.0 → 1.8.1)

### Example 3: Breaking Change Present
**PR Commits:**
```
1. fix: minor bug
2. feat: new feature
3. feat!: breaking API change
```

**Result:** `MAJOR` version bump (1.8.0 → 2.0.0)
**Reasoning:** Breaking change overrides everything else

### Example 4: Non-Conventional Commits
**PR Commits:**
```
1. Update README
2. Refactor code
3. Add tests
```

**Result:** `NONE` - No version bump
**Reasoning:** No conventional commit patterns detected

## Workflow Implementation

### GitHub Actions Logic (.github/workflows/auto-version.yml)

```bash
# Analyze all commits at once
while IFS= read -r commit; do
  if [breaking change pattern]; then
    HAS_BREAKING=true
  elif [feature pattern]; then
    HAS_FEATURE=true
  elif [fix pattern]; then
    HAS_FIX=true
  fi
done <<< "$COMMITS"

# Single decision based on highest priority
if [ "$HAS_BREAKING" = true ]; then
  BUMP_TYPE=major
elif [ "$HAS_FEATURE" = true ]; then
  BUMP_TYPE=minor
elif [ "$HAS_FIX" = true ]; then
  BUMP_TYPE=patch
else
  BUMP_TYPE=none
fi
```

### JavaScript Logic (scripts/semver-utils.js)

```javascript
function determineBumpType(commits, options) {
  const analysis = {
    breaking: 0,
    features: 0,
    fixes: 0
  };

  // Analyze ALL commits
  for (const commit of commits) {
    const parsed = parseConventionalCommit(commit);
    if (parsed.breaking) analysis.breaking++;
    else if (parsed.type === 'feat') analysis.features++;
    else if (parsed.type === 'fix') analysis.fixes++;
  }

  // Single decision based on priority
  if (analysis.breaking > 0) return 'major';
  if (analysis.features > 0) return 'minor';
  if (analysis.fixes > 0) return 'patch';
  return 'none';
}
```

## Key Points

1. **One Version Bump Per PR**: The workflow makes exactly one version change, not multiple incremental changes

2. **Highest Priority Wins**: If a PR contains both fixes and features, it will be a MINOR bump (features win)

3. **Breaking Changes Override Everything**: Even one breaking change makes it a MAJOR bump regardless of other commits

4. **Collective Analysis**: All commits are analyzed together, not sequentially

5. **Semantic Versioning Compliance**: Follows semver rules strictly (MAJOR.MINOR.PATCH)

## Timing

The version bump happens:
1. **After** all commits are pushed to the PR branch
2. **When** the PR is merged to main (or the workflow is triggered)
3. **Once** per workflow run, not per commit

This ensures clean version history and proper semantic versioning compliance.
