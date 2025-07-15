# Intelligent Commit Scanning Enhancement

## Overview

The auto-version workflow has been enhanced with intelligent commit scanning that implements:

1. **Chronological commit scanning** from the last version tag to HEAD
2. **Gap detection** to identify commits that should have triggered version bumps but didn't
3. **Cumulative analysis** with proper semantic versioning prioritization (breaking > feature > fix > patch)
4. **Enhanced conflict resolution** using existing `findNextAvailableVersion()` functionality

## Key Features

### 1. Intelligent Commit History Analysis

The new `analyzeCommitHistoryWithGapDetection()` function provides comprehensive commit analysis:

```javascript
const analysis = analyzeCommitHistoryWithGapDetection({
  verbose: true,
  includeGapDetection: true,
  maxCommitsToAnalyze: 1000,
  maxTagsToAnalyze: 10,
  enableCumulativeAnalysis: true
});
```

**Returns:**
- `finalBumpType`: Recommended version bump (major/minor/patch/none)
- `confidence`: Analysis confidence level (high/medium/low)
- `gapDetection`: Results of gap detection analysis
- `cumulativeAnalysis`: Cumulative commit analysis since last tag
- `recommendations`: Array of actionable recommendations

### 2. Gap Detection

The `detectVersionGaps()` function identifies version bumps that should have occurred:

```javascript
const gaps = detectVersionGaps({
  verbose: true,
  maxTagsToAnalyze: 10
});
```

**Gap Detection Logic:**
- Analyzes commits between consecutive version tags
- Compares actual version bumps vs. recommended bumps based on commit content
- Identifies insufficient version bumps (e.g., patch when minor was needed)
- Provides severity scoring for each gap

### 3. Version History Analysis

The `getVersionHistory()` function provides chronological version information:

```javascript
const history = getVersionHistory({
  verbose: true,
  limit: 50
});
```

**Returns:**
- Chronologically sorted version tags
- Commit information for each version
- Version metadata (hash, date, subject)

### 4. Enhanced Bump Type Analysis

The existing `determineBumpType()` function now integrates with:
- Gap detection results
- Cumulative analysis across multiple commits
- Priority-based resolution for mixed commit types

## Workflow Integration

### GitHub Actions Enhancement

The `.github/workflows/auto-version.yml` now includes:

1. **Intelligent Analysis Step**: Runs gap detection and cumulative analysis
2. **Enhanced Bump Type Determination**: Uses intelligent analysis when available
3. **Gap Detection Reporting**: Reports version gaps in workflow output
4. **Backward Compatibility**: Falls back to traditional analysis if needed

### Environment Variables

New environment variables for configuration:

```yaml
USE_INTELLIGENT_ANALYSIS: "true"    # Enable intelligent analysis
ENABLE_GAP_DETECTION: "true"        # Enable gap detection
LIMITED_COMMIT_LIMIT: "25"          # Limit for mismatch scenarios
FALLBACK_COMMIT_LIMIT: "50"         # Fallback commit limit
```

### BumpTypeAnalyzer Enhancement

The `.github/scripts/bump-type-analyzer.js` now includes:

- `analyzeIntelligent()`: Main intelligent analysis method
- Enhanced reasoning with gap detection insights
- Confidence scoring and recommendation generation
- Fallback to traditional analysis on errors

## Usage Examples

### Basic Intelligent Analysis

```bash
# Run with intelligent analysis enabled
node .github/scripts/bump-type-analyzer.js false none true
```

### Testing the Enhancement

```bash
# Run the test suite
node scripts/test-intelligent-analysis.js

# Test specific functions
node -e "
const { analyzeCommitHistoryWithGapDetection } = require('./scripts/semver-utils');
console.log(analyzeCommitHistoryWithGapDetection({ verbose: true }));
"
```

### Manual Gap Detection

```bash
# Check for version gaps
node -e "
const { detectVersionGaps } = require('./scripts/semver-utils');
console.log(detectVersionGaps({ verbose: true }));
"
```

## Expected Behavior Examples

### Example 1: Feature and Fix Commits

**Scenario**: Commits since last tag include `feat:` and `fix:` commits

**Traditional Analysis**: Might only detect the most recent commit type
**Intelligent Analysis**: Detects both, recommends `minor` bump (features take precedence)

**Output**:
```
Final bump type: minor
Reasoning: Found 2 new feature(s) | Found 1 fix(es) | Cumulative analysis suggests minor bump
Confidence: high
```

### Example 2: Gap Detection

**Scenario**: Previous version bump was `patch` but commits suggested `minor`

**Gap Detection Output**:
```
Gap detected: v1.2.0 → v1.2.1 should have been minor bump (was patch)
Current gap: 3 commits since v1.2.1 suggest minor bump
```

### Example 3: No Previous Tags

**Scenario**: Repository has no version tags

**Behavior**: 
- Analyzes recent commits (up to `FALLBACK_COMMIT_LIMIT`)
- Provides recommendations for initial versioning
- Suggests appropriate starting version

## Configuration Options

### Intelligent Analysis Options

```javascript
{
  verbose: true,                    // Enable detailed logging
  includeGapDetection: true,        // Enable gap detection
  maxCommitsToAnalyze: 1000,        // Max commits to analyze
  maxTagsToAnalyze: 10,             // Max version tags to check
  enableCumulativeAnalysis: true    // Enable cumulative analysis
}
```

### Gap Detection Options

```javascript
{
  verbose: true,                    // Enable detailed logging
  maxTagsToAnalyze: 10             // Max version tags to analyze
}
```

## Compatibility

### Backward Compatibility

- All existing functions remain unchanged
- Traditional analysis still available as fallback
- Existing workflow steps continue to work
- No breaking changes to `semver-utils.js` exports

### Integration with Existing Tools

- Works with existing `findNextAvailableVersion()` for conflict resolution
- Integrates with current `determineBumpType()` logic
- Compatible with existing GitHub Actions workflow structure
- Maintains existing error handling and logging patterns

## Performance Considerations

### Memory Management

- Configurable commit limits to prevent memory issues
- Streaming support for large repositories (existing feature)
- Efficient git command execution with `safeGitExec()`

### Execution Time

- Gap detection limited to recent version tags
- Intelligent analysis with reasonable commit limits
- Fallback to traditional analysis for performance-critical scenarios

## Troubleshooting

### Common Issues

1. **Gap Detection Fails**: Check git tag format and repository history
2. **Intelligent Analysis Errors**: Falls back to traditional analysis automatically
3. **Performance Issues**: Reduce `maxCommitsToAnalyze` and `maxTagsToAnalyze`

### Debug Mode

Enable verbose logging for detailed analysis:

```bash
export DEBUG=true
node scripts/test-intelligent-analysis.js
```

## Future Enhancements

Potential future improvements:

1. **Machine Learning Integration**: Learn from historical patterns
2. **Custom Gap Detection Rules**: Repository-specific gap detection logic
3. **Integration with PR Analysis**: Analyze PR content for better bump decisions
4. **Performance Optimization**: Further optimize for very large repositories
