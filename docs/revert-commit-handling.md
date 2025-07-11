# Revert Commit Handling in Auto-Version System

## Overview

The BlazeCommerce auto-version system now includes intelligent revert commit handling that properly manages version bumps when commits are reverted. This ensures semantic versioning accuracy by canceling out reverted changes.

## How It Works

### Smart Revert Cancellation

When the system encounters revert commits, it performs smart matching to cancel out the original commits:

1. **Identifies revert commits** using conventional commit patterns
2. **Parses revert targets** to understand what's being reverted
3. **Matches reverts with original commits** in the same commit range
4. **Cancels matched pairs** from version bump calculation
5. **Processes remaining commits** for final version determination

### Supported Revert Formats

The system recognizes multiple revert commit formats:

#### 1. Conventional Revert Format
```
revert: feat: add new dashboard
revert: fix(auth): resolve login issue
revert: feat!: breaking change
```

#### 2. GitHub-Style Revert Format
```
Revert "feat: add new dashboard"
Revert "fix(auth): resolve login issue"
```

#### 3. Manual Revert Format
```
revert: feat(api): add new endpoint
```

## Examples

### Example 1: Complete Cancellation
**Scenario**: Feature added and immediately reverted

```bash
# Commits in PR:
1. feat: add new dashboard
2. revert: feat: add new dashboard

# Result: 
Version: 1.8.0 → 1.8.0 (no change)
Reasoning: "Processed 1 revert(s) - cancelled matching commits"
```

### Example 2: Partial Cancellation
**Scenario**: Multiple features, one reverted

```bash
# Commits in PR:
1. feat: add feature A
2. feat: add feature B
3. revert: feat: add feature A

# Result:
Version: 1.8.0 → 1.9.0 (MINOR bump)
Reasoning: "Found 1 new feature(s)" (only feature B remains)
```

### Example 3: Mixed Changes with Revert
**Scenario**: Feature and fix, feature reverted

```bash
# Commits in PR:
1. feat: add new feature
2. fix: resolve bug
3. revert: feat: add new feature

# Result:
Version: 1.8.0 → 1.8.1 (PATCH bump)
Reasoning: "Found 1 fix(es)" (only the fix remains)
```

### Example 4: Unmatched Revert
**Scenario**: Revert of commit outside current range

```bash
# Commits in PR:
1. revert: feat: some external feature
2. feat: add new feature

# Result:
Version: 1.8.0 → 1.9.0 (MINOR bump)
Reasoning: "Found 1 new feature(s)" (unmatched revert ignored)
```

## Configuration

### Enable/Disable Revert Handling

Revert handling is enabled by default but can be controlled:

```javascript
const result = determineBumpType(commits, {
  enableRevertHandling: true,  // Default: true
  verbose: true
});
```

### Backward Compatibility

When revert handling is disabled, the system behaves as before:

```javascript
// Old behavior (counts all commits)
const result = determineBumpType([
  'feat: add feature',
  'revert: feat: add feature'
], { enableRevertHandling: false });

// Result: MINOR bump (both commits counted)
```

## Technical Implementation

### Core Functions

#### `parseRevertTarget(description)`
Parses revert commit descriptions to extract the target commit information.

```javascript
const target = parseRevertTarget('feat: add new dashboard');
// Returns: { type: 'feat', scope: null, breaking: false, description: 'add new dashboard' }
```

#### `analyzeCommitsWithReverts(commits, options)`
Performs smart revert analysis and returns net commits after cancellation.

```javascript
const analysis = analyzeCommitsWithReverts([
  'feat: add feature',
  'revert: feat: add feature'
]);
// Returns: { netCommits: [], revertMatches: [{ ... }] }
```

### Matching Logic

Reverts are matched with original commits based on:

1. **Commit type** (feat, fix, etc.)
2. **Scope** (if present)
3. **Breaking change flag**
4. **Description text**

All criteria must match exactly for successful cancellation.

### Edge Cases Handled

#### 1. Scoped Commits
```bash
feat(api): add endpoint
revert: feat(api): add endpoint  # ✅ Matches (scope included)
```

#### 2. Breaking Changes
```bash
feat!: breaking change
revert: feat!: breaking change   # ✅ Matches (breaking flag preserved)
```

#### 3. Partial Matches
```bash
feat: add feature A
revert: feat: add feature B      # ❌ No match (different descriptions)
```

#### 4. Multiple Reverts
```bash
feat: feature A
feat: feature B
revert: feat: feature A
revert: feat: feature B          # ✅ Both features cancelled
```

## Benefits

### 1. Semantic Versioning Accuracy
- Version numbers accurately reflect actual functionality changes
- Reverted features don't increment version numbers
- Consumers get correct expectations about version contents

### 2. Clean Version History
- No "false" version bumps for reverted changes
- Version changelog reflects actual delivered features
- Easier to track real functionality progression

### 3. Developer Workflow Support
- Supports common development patterns (feature flags, rollbacks)
- Handles GitHub's automatic revert commit format
- Works with manual revert commits

### 4. Backward Compatibility
- Existing workflows continue to work unchanged
- Optional feature that can be disabled if needed
- No breaking changes to existing APIs

## Testing

The revert handling system includes comprehensive tests covering:

- ✅ Basic revert commit recognition
- ✅ Multiple revert target formats
- ✅ Complete and partial cancellation scenarios
- ✅ Version bump calculations with reverts
- ✅ Unmatched revert handling
- ✅ GitHub-style revert format support
- ✅ Complex scenarios with scopes and breaking changes
- ✅ Edge cases and error conditions

Run tests with:
```bash
node scripts/test-revert-commit-handling.js
```

## Migration

### For Existing Projects

No migration is required. The feature is:
- **Enabled by default** for new version calculations
- **Backward compatible** with existing workflows
- **Non-breaking** for current implementations

### For Custom Implementations

If you have custom version bump logic, you can integrate revert handling:

```javascript
// Before
const result = determineBumpType(commits);

// After (with revert handling)
const result = determineBumpType(commits, { 
  enableRevertHandling: true 
});
```

## Troubleshooting

### Common Issues

#### 1. Revert Not Matching
**Problem**: Revert commit doesn't cancel the original
**Solution**: Ensure exact match of type, scope, and description

#### 2. Unexpected Version Bump
**Problem**: Version still increments despite revert
**Solution**: Check revert commit format and target parsing

#### 3. GitHub Revert Not Recognized
**Problem**: GitHub-generated revert commits not working
**Solution**: Verify the commit message format matches `Revert "original commit"`

### Debug Mode

Enable verbose logging to troubleshoot:

```javascript
const result = determineBumpType(commits, { 
  enableRevertHandling: true,
  verbose: true 
});
```

This will show detailed revert analysis including:
- Revert commit detection
- Target parsing results
- Matching process
- Final net commits calculation
