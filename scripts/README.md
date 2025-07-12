# Scripts Directory

This directory contains utility scripts used by the GitHub workflows for intelligent version bumping and release creation.

## Scripts Overview

### `get-ignore-patterns.sh`
**Purpose**: Centralized configuration for file patterns that should be ignored during version bump and release decisions.

**Usage**: 
```bash
bash scripts/get-ignore-patterns.sh
```

**Output**: Newline-separated list of ignore patterns

**Pattern Types**:
- **Directory patterns** (ending with `/`): Match all files in directory
- **Exact file matches**: Match specific filenames
- **File extension patterns** (starting with `.`): Match files with specific extensions

### `check-file-changes.sh`
**Purpose**: Analyzes changed files and determines if version bump or release creation is needed.

**Usage**:
```bash
# Standard mode
echo "file1.txt\nfile2.js" | bash scripts/check-file-changes.sh /dev/stdin

# Performance mode for large changesets
echo "many_files..." | bash scripts/check-file-changes.sh /dev/stdin --performance-mode
```

**Exit Codes**:
- `0`: Action needed (version bump/release should proceed)
- `1`: Skip action (only ignored files changed)
- `2`: Error occurred

**Features**:
- Handles filenames with spaces and special characters
- Performance optimization for large changesets (>50 files)
- Comprehensive logging with timestamps
- Precise pattern matching to prevent false positives

## Testing

### Running Tests
```bash
# Run the test suite
bash tests/test-file-detection.sh
```

### Test Coverage
The test suite covers:
- Documentation changes (should be ignored)
- Source code changes (should trigger action)
- Mixed changes scenarios
- Files with spaces in names
- Directory pattern matching
- File extension pattern precision
- False positive prevention
- Edge cases (empty input, etc.)

## Ignore Patterns

### Current Patterns
The ignore patterns are designed to skip version bumps for:

1. **Version-managed files**: Files that are updated by the version bump process itself
   - `CHANGELOG.md`, `package.json`, `package-lock.json`, etc.

2. **CI/CD and tooling**: Development infrastructure that doesn't affect end users
   - `.github/`, `scripts/`, `tests/`, `bin/`

3. **Documentation**: Internal documentation that doesn't require versioning
   - `docs/`, `CONTRIBUTING.md`, `README.md`, etc.

4. **Development configuration**: Files used only during development
   - `phpunit.xml`, `jest.config.js`, etc.

5. **Auto-generated files**: Files created by build processes
   - `composer.lock`, `vendor/`, lock files

6. **Editor and system files**: Files created by editors or operating systems
   - `.gitignore`, `.DS_Store`, `.vscode/`, etc.

### Adding New Patterns
To add new ignore patterns:

1. Edit `scripts/get-ignore-patterns.sh`
2. Add the pattern following the existing format
3. Test with `bash tests/test-file-detection.sh`
4. Commit the changes

### Pattern Syntax
- **Directory patterns**: End with `/` (e.g., `tests/`)
- **Exact files**: Just the filename (e.g., `package.json`)
- **File extensions**: Start with `.` (e.g., `.DS_Store`)
- **Basename matches**: Files in any directory (handled automatically)

## Performance Considerations

### Standard Mode
- Used for changesets with ≤50 files
- O(n×m) complexity where n=files, m=patterns
- Provides detailed logging for each file

### Performance Mode
- Automatically enabled for changesets with >50 files
- Uses git pathspec for optimized pattern matching
- Significantly faster for large repositories

### Benchmarks
- Standard mode: ~1ms per file
- Performance mode: ~10ms total for any changeset size
- Memory usage: <10MB for typical repositories

## Troubleshooting

### Common Issues

**Script not found errors**:
```bash
❌ Required script 'scripts/check-file-changes.sh' not found
```
- Ensure you're running from the repository root
- Check that the script has execute permissions: `chmod +x scripts/check-file-changes.sh`

**Pattern matching issues**:
- Run the test suite to verify pattern behavior
- Check the logs for detailed file-by-file analysis
- Ensure patterns follow the documented syntax

**Performance issues**:
- Performance mode is automatically enabled for large changesets
- Consider reducing the number of patterns if needed
- Monitor workflow execution times

### Debugging
Enable detailed logging by running scripts manually:
```bash
# Test specific files
echo -e "test.js\ndocs/readme.md" | bash scripts/check-file-changes.sh /dev/stdin

# Check pattern loading
bash scripts/get-ignore-patterns.sh
```

## Integration with Workflows

These scripts are integrated into:
- `.github/workflows/auto-version.yml`: For intelligent version bumping
- `.github/workflows/release.yml`: For conditional release creation

The workflows automatically:
- Detect changeset size and enable performance mode when needed
- Provide clear feedback about decisions made
- Handle errors gracefully with fallback mechanisms
- Maintain consistent behavior across both workflows
