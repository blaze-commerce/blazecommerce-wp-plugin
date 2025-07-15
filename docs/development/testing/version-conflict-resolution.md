# Version Conflict Resolution Testing

This document outlines the comprehensive testing strategy for the version conflict resolution feature implemented in the auto-version workflow.

## Overview

The version conflict resolution feature automatically handles cases where the calculated new version equals the current version by forcing a patch increment. This prevents workflow failures while maintaining semantic versioning principles.

## Test Suite Components

### 1. Unit Tests (`scripts/test-version-system.js`)

Enhanced with specific tests for version conflict resolution:

#### New Test Cases Added:

- **Version Conflict Resolution**: Tests the core conflict resolution logic
  - Verifies `incrementVersion()` behavior with current version
  - Ensures incremented versions are always greater than current
  - Tests major, minor, and patch increments from current version

- **Version Increment Edge Cases**: Tests error handling and edge scenarios
  - Prerelease version handling
  - Invalid version input handling
  - Invalid bump type handling

- **Validation Script Behavior**: Tests validation system integration
  - Validates that version format checking works correctly
  - Ensures validation accepts valid versions regardless of conflicts
  - Tests error handling in validation scenarios

#### Running Unit Tests:
```bash
npm run test:version-system
```

### 2. Workflow Scenario Tests (`scripts/test-workflow-scenarios.js`)

Comprehensive testing of different commit scenarios:

#### Test Scenarios:

1. **Version Conflict Resolution**
   - Non-conventional commits (README updates, typo fixes)
   - Expected: `none` bump type → conflict resolution → `patch` bump
   - Verifies automatic conflict resolution triggers

2. **Breaking Changes Detection**
   - Commits with `feat!:` or `BREAKING CHANGE`
   - Expected: `major` bump type
   - Verifies no conflict resolution needed

3. **Feature Addition**
   - Commits with `feat:` prefix
   - Expected: `minor` bump type
   - Verifies normal workflow operation

4. **Bug Fixes**
   - Commits with `fix:` prefix
   - Expected: `patch` bump type
   - Verifies normal patch increments

5. **Performance Improvements**
   - Commits with `perf:` prefix
   - Expected: `patch` bump type
   - Verifies performance improvements trigger patches

6. **Mixed Commit Types**
   - Multiple commit types in one batch
   - Expected: Highest priority bump type
   - Verifies priority handling

#### Running Workflow Tests:
```bash
npm run test:workflow-scenarios
```

### 3. Validation Flag Tests (`scripts/test-validation-flags.js`)

Tests the `--no-conflicts` flag functionality:

#### Test Cases:

1. **Validation with Conflict Checking**
   - Tests normal validation behavior
   - May fail if version conflicts exist (expected)

2. **Validation without Conflict Checking**
   - Tests `--no-conflicts` flag functionality
   - Should always pass for version consistency

3. **Direct Conflict Checking**
   - Tests `checkVersionConflicts()` function directly
   - Verifies conflict detection logic

4. **Version Consistency Validation**
   - Ensures file consistency checking still works
   - Validates across package.json, blaze-wooless.php, blocks/package.json

5. **Command Line Argument Parsing**
   - Tests various flag combinations
   - Verifies correct argument interpretation

#### Running Validation Tests:
```bash
npm run test:validation-flags
```

### 4. Complete Test Suite

Run all version-related tests:
```bash
npm run test:version-complete
```

## Workflow Integration Testing

### Manual Testing Scenarios

1. **Create a Non-Conventional Commit**:
   ```bash
   git commit -m "Update documentation"
   git push origin main
   ```
   - Expected: Workflow triggers conflict resolution
   - Result: Version bumped from 1.8.0 to 1.8.1

2. **Create a Feature Commit**:
   ```bash
   git commit -m "feat: add new user dashboard"
   git push origin main
   ```
   - Expected: Normal minor version bump
   - Result: Version bumped to 1.9.0

3. **Create a Breaking Change**:
   ```bash
   git commit -m "feat!: redesign API endpoints"
   git push origin main
   ```
   - Expected: Normal major version bump
   - Result: Version bumped to 2.0.0

### Validation Script Testing

Test the validation script with different flags:

```bash
# Test with conflict checking (may fail if conflicts exist)
node scripts/validate-version.js --verbose

# Test without conflict checking (should pass)
node scripts/validate-version.js --verbose --no-conflicts

# Test consistency only
node scripts/validate-version.js --no-conflicts
```

## Expected Behavior

### Normal Operation
- Conventional commits trigger appropriate version bumps
- Validation passes with `--no-conflicts` flag
- Workflow completes successfully

### Conflict Resolution
- Non-conventional commits trigger conflict resolution
- Version automatically incremented to next patch
- Clear logging shows conflict resolution process
- Workflow continues normally after resolution

### Error Scenarios
- Invalid version formats are rejected
- File inconsistencies are detected
- Git operation failures are handled gracefully

## Continuous Integration

The test suite is integrated into the CI/CD pipeline:

```yaml
- name: Run version system tests
  run: npm run test:version-complete
```

This ensures all version conflict resolution functionality is tested on every commit.

## Troubleshooting

### Common Issues

1. **Test Failures**: Check that all dependencies are installed
2. **Git Errors**: Ensure git is configured and repository is clean
3. **Version Inconsistencies**: Run `npm run validate-version` to check file consistency

### Debug Commands

```bash
# Check current version state
npm run version:check

# Validate version consistency
npm run validate-version:verbose

# Test specific scenarios
npm run test:workflow-scenarios

# Test validation flags
npm run test:validation-flags
```

## Contributing

When adding new version-related functionality:

1. Add unit tests to `test-version-system.js`
2. Add workflow scenarios to `test-workflow-scenarios.js`
3. Update validation tests if needed
4. Run complete test suite: `npm run test:version-complete`
5. Update this documentation

## References

- [Auto-Version Workflow](../../.github/workflows/auto-version.yml)
- [Version Utilities](../../scripts/semver-utils.js)
- [Validation Script](../../scripts/validate-version.js)
- [Conventional Commits](https://www.conventionalcommits.org/)
