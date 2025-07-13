# GitHub Actions Output Formatting Guidelines

## Overview

This document provides guidelines for preventing GitHub Actions output formatting errors that can cause workflow failures. These errors typically manifest as:

- `Unable to process file command 'output' successfully`
- `Invalid format 'ℹ️ Starting Claude review processing v1 for PR #337'`

## Root Cause

GitHub Actions has strict requirements for output formatting. **Emojis and non-ASCII characters** in console output or GitHub output assignments cause parsing failures and workflow errors.

## Critical Rules

### ❌ NEVER Use These in GitHub Actions Output

1. **Emojis in echo statements** that set GitHub outputs:
   ```yaml
   # ❌ WRONG - Will cause GitHub Actions failure
   echo "✅ Success message" >> $GITHUB_OUTPUT
   
   # ✅ CORRECT - Use plain text
   echo "SUCCESS: Success message" >> $GITHUB_OUTPUT
   ```

2. **Emojis in console.log statements** in JavaScript files called by workflows:
   ```javascript
   // ❌ WRONG - Will cause GitHub Actions failure
   console.log(`ℹ️ Starting process for PR #${prNumber}`);
   
   // ✅ CORRECT - Use plain text prefixes
   console.log(`INFO: Starting process for PR #${prNumber}`);
   ```

3. **Special characters in GitHub output values**:
   ```yaml
   # ❌ WRONG - Special characters can break parsing
   echo "status=🔄 Processing..." >> $GITHUB_OUTPUT
   
   # ✅ CORRECT - Use descriptive text
   echo "status=Processing" >> $GITHUB_OUTPUT
   ```

### ✅ Recommended Alternatives

| Instead of | Use |
|------------|-----|
| `ℹ️` | `INFO:` |
| `✅` | `SUCCESS:` |
| `❌` | `ERROR:` |
| `⚠️` | `WARNING:` |
| `🔍` | `DEBUG:` |
| `🤖` | `BOT:` |
| `🎯` | `TARGET:` |
| `🔄` | `PROCESSING:` |
| `📝` | `NOTE:` |
| `🎉` | `COMPLETED:` |

## Implementation Guidelines

### 1. YAML Workflow Files

**Safe echo statements:**
```yaml
- name: Debug Step
  run: |
    echo "INFO: Starting workflow step"
    echo "DEBUG: Processing PR #${{ github.event.pull_request.number }}"
    echo "SUCCESS: Step completed successfully"
```

**Safe GitHub output assignments:**
```yaml
- name: Set Output
  id: step-id
  run: |
    echo "result=success" >> $GITHUB_OUTPUT
    echo "message=Processing completed" >> $GITHUB_OUTPUT
    echo "count=5" >> $GITHUB_OUTPUT
```

### 2. JavaScript Files

**Safe logging patterns:**
```javascript
// Use a standardized logger without emojis
class Logger {
  static info(message) {
    console.log(`INFO: ${message}`);
  }
  
  static success(message) {
    console.log(`SUCCESS: ${message}`);
  }
  
  static warning(message) {
    console.log(`WARNING: ${message}`);
  }
  
  static error(message) {
    console.error(`ERROR: ${message}`);
  }
}
```

**Safe GitHub Actions output:**
```javascript
// When outputting for GitHub Actions
console.log(`result=success`);
console.log(`has_errors=false`);
console.log(`message=Process completed successfully`);
```

### 3. Multi-line Output Handling

**Escape multi-line values properly:**
```javascript
// For multi-line GitHub Actions output
const multilineValue = "Line 1\\nLine 2\\nLine 3";
console.log(`multiline_output=${multilineValue}`);
```

## Testing

### Automated Testing

Use the provided test script to verify output formatting:

```bash
node .github/scripts/test-output-formatting.js
```

This script will:
- Scan all YAML workflow files for problematic echo statements
- Check JavaScript files for console.log statements with emojis
- Identify known problematic patterns
- Generate a detailed report

### Manual Testing

1. **Search for emojis in workflow files:**
   ```bash
   grep -r "[^\x00-\x7F]" .github/workflows/
   ```

2. **Search for emojis in script files:**
   ```bash
   grep -r "[^\x00-\x7F]" .github/scripts/
   ```

3. **Test workflow locally** using act or similar tools before pushing

## Common Failure Patterns

### Pattern 1: Logger Class with Emojis
```javascript
// ❌ This will cause failures when used in GitHub Actions
class Logger {
  static info(message) {
    console.log(`ℹ️ ${message}`); // Emoji causes failure
  }
}
```

### Pattern 2: Workflow Echo with Emojis
```yaml
# ❌ This will cause GitHub Actions output parsing failure
- name: Debug
  run: |
    echo "🔍 DEBUG: Processing started" # Emoji causes failure
```

### Pattern 3: GitHub Output with Special Characters
```yaml
# ❌ This will cause output processing failure
- name: Set Status
  run: |
    echo "status=✅ Complete" >> $GITHUB_OUTPUT # Emoji causes failure
```

## Emergency Fix Checklist

When GitHub Actions workflows fail with output formatting errors:

1. **Identify the failing step** from the GitHub Actions log
2. **Search for emojis** in the relevant files:
   - YAML workflow files (`.github/workflows/`)
   - JavaScript script files (`.github/scripts/`)
3. **Replace all emojis** with plain text alternatives
4. **Test the fix** using the automated test script
5. **Commit and push** the changes
6. **Verify** the workflow runs successfully

## Prevention

1. **Use the provided Logger class** in all JavaScript files
2. **Run the test script** before committing workflow changes
3. **Set up pre-commit hooks** to catch emoji usage
4. **Code review checklist** should include output formatting verification
5. **Document any new patterns** that cause issues

## Related Files

- `.github/scripts/file-change-analyzer.js` - Contains the standardized Logger class
- `.github/scripts/test-output-formatting.js` - Automated testing script
- `.github/workflows/claude-pr-review.yml` - Main workflow file (fixed)
- `.github/workflows/claude-approval-gate.yml` - Approval workflow (fixed)

## Support

If you encounter new output formatting issues:

1. Run the test script to identify problems
2. Follow the patterns in this document for fixes
3. Update this documentation with new patterns if needed
4. Consider adding new test cases to prevent regression

---

**Remember: GitHub Actions requires strict ASCII-only output formatting. When in doubt, use plain text!**
