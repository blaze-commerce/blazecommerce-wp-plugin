# GitHub Actions Security Best Practices

## 🛡️ Preventing JavaScript Injection Vulnerabilities

This guide provides comprehensive best practices for preventing JavaScript injection vulnerabilities in GitHub Actions workflows, based on lessons learned from fixing the PR #337 syntax error.

## 🚨 Common Vulnerability Patterns

### 1. Unsafe Template Literal Interpolation

**❌ VULNERABLE:**
```yaml
- uses: actions/github-script@v7
  with:
    script: |
      const userInput = `${{ github.event.comment.body }}`;
      const commitSha = `${{ github.sha }}`;
```

**✅ SECURE:**
```yaml
- uses: actions/github-script@v7
  env:
    USER_INPUT: ${{ github.event.comment.body }}
    COMMIT_SHA: ${{ github.sha }}
  with:
    script: |
      const userInput = process.env.USER_INPUT || '';
      const commitSha = process.env.COMMIT_SHA || '';
```

### 2. Unescaped Commit Hashes

**❌ VULNERABLE:**
```yaml
script: |
  const comment = `Commit SHA: \`${{ github.sha }}\``;
  // If github.sha = "b224c03", this becomes:
  // const comment = `Commit SHA: \`b224c03\``;
  // Which causes: SyntaxError: Unexpected identifier 'b224c03'
```

**✅ SECURE:**
```yaml
env:
  COMMIT_SHA: ${{ github.sha }}
script: |
  const commitSha = process.env.COMMIT_SHA || 'unknown';
  const comment = `Commit SHA: ${commitSha.substring(0, 7)}`;
```

### 3. Dynamic Content in Scripts

**❌ VULNERABLE:**
```yaml
script: |
  const output = `${{ steps.previous.outputs.content }}`;
  // Content could contain: `; malicious code; //`
```

**✅ SECURE:**
```yaml
env:
  STEP_OUTPUT: ${{ steps.previous.outputs.content }}
script: |
  const output = process.env.STEP_OUTPUT || '';
  // Validate and sanitize if needed
  if (output.includes('`') || output.includes('${')) {
    throw new Error('Unsafe content detected');
  }
```

## 🔧 Implementation Guidelines

### 1. Always Use Environment Variables

**Rule**: Never interpolate GitHub expressions directly into JavaScript template literals.

```yaml
# ✅ CORRECT PATTERN
env:
  GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
  PR_NUMBER: ${{ github.event.pull_request.number }}
  USER_INPUT: ${{ github.event.inputs.user_input }}
with:
  script: |
    const token = process.env.GITHUB_TOKEN;
    const prNumber = parseInt(process.env.PR_NUMBER) || 0;
    const userInput = process.env.USER_INPUT || '';
```

### 2. Validate and Sanitize Input

```javascript
// Input validation function
function validateInput(input, type = 'string') {
  if (!input || typeof input !== 'string') {
    return '';
  }
  
  // Check for dangerous patterns
  const dangerousPatterns = [
    /`[a-f0-9]{7,40}`/g,  // Commit hashes in backticks
    /\$\{[^}]*\}/g,       // Template expressions
    /eval\s*\(/g,         // Eval calls
    /Function\s*\(/g      // Function constructor
  ];
  
  for (const pattern of dangerousPatterns) {
    if (pattern.test(input)) {
      throw new Error(`Dangerous pattern detected: ${pattern}`);
    }
  }
  
  return input.trim();
}

// Usage in workflow
const safeInput = validateInput(process.env.USER_INPUT);
```

### 3. Use Safe Output Generation

```javascript
// Safe multiline output for GitHub Actions
function createSafeOutput(key, value) {
  if (typeof value === 'string' && value.includes('\n')) {
    const delimiter = `EOF_${key.toUpperCase()}_${Date.now()}`;
    return `${key}<<${delimiter}\n${value}\n${delimiter}`;
  } else {
    // Escape special characters
    const safeValue = String(value)
      .replace(/\\/g, '\\\\')
      .replace(/`/g, '\\`')
      .replace(/\$/g, '\\$')
      .replace(/"/g, '\\"')
      .replace(/'/g, "\\'");
    return `${key}=${safeValue}`;
  }
}
```

## 🔍 Detection and Prevention Tools

### 1. Automated Vulnerability Scanning

Use the provided vulnerability scanner:

```bash
# Scan all workflows
node .github/scripts/check-workflow-vulnerabilities.js

# Expected output for secure workflows:
# ✅ No critical vulnerabilities found
```

### 2. Pre-commit Hooks

Add to `.github/hooks/pre-commit`:

```bash
#!/bin/bash
echo "Scanning GitHub Actions workflows for vulnerabilities..."
if node .github/scripts/check-workflow-vulnerabilities.js; then
  echo "✅ Workflow security scan passed"
else
  echo "❌ Workflow security scan failed"
  echo "Please fix vulnerabilities before committing"
  exit 1
fi
```

### 3. Code Review Checklist

**GitHub Actions Security Review Checklist:**

- [ ] No direct GitHub expression interpolation in template literals
- [ ] All external input passed via environment variables
- [ ] Input validation implemented for user-provided data
- [ ] No unescaped commit hashes or special characters
- [ ] Safe output generation for multiline content
- [ ] Error handling implemented for all script blocks
- [ ] Vulnerability scanner passes without critical issues

## 📋 Workflow Templates

### Secure GitHub Script Template

```yaml
- name: Secure GitHub Script Action
  uses: actions/github-script@v7
  env:
    # Pass all dynamic content via environment variables
    GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
    PR_NUMBER: ${{ github.event.pull_request.number }}
    COMMIT_SHA: ${{ github.sha }}
    USER_INPUT: ${{ github.event.inputs.user_input }}
  with:
    script: |
      // Import validation utilities
      const { validateInput, createSafeOutput } = require('./.github/scripts/security-utils');
      
      try {
        // Safely access environment variables
        const prNumber = parseInt(process.env.PR_NUMBER) || 0;
        const commitSha = process.env.COMMIT_SHA || 'unknown';
        const userInput = validateInput(process.env.USER_INPUT);
        
        // Your secure script logic here
        const result = await performSecureOperation(prNumber, commitSha, userInput);
        
        // Safe output generation
        const output = createSafeOutput('result', result);
        console.log(output);
        
      } catch (error) {
        console.error('Script execution failed:', error.message);
        // Use enhanced error handler for safe fallback
        const { EnhancedErrorHandler } = require('./.github/scripts/enhanced-error-handler');
        const handler = new EnhancedErrorHandler();
        handler.handleJavaScriptSyntaxError(error);
        throw error;
      }
```

## 🚀 Migration Guide

### Migrating Existing Workflows

1. **Identify Vulnerable Patterns**:
   ```bash
   node .github/scripts/check-workflow-vulnerabilities.js
   ```

2. **Replace Template Literal Interpolation**:
   ```yaml
   # Before
   script: |
     const value = `${{ github.event.input }}`;
   
   # After
   env:
     INPUT_VALUE: ${{ github.event.input }}
   script: |
     const value = process.env.INPUT_VALUE || '';
   ```

3. **Add Input Validation**:
   ```javascript
   const safeValue = validateInput(process.env.INPUT_VALUE);
   ```

4. **Test Changes**:
   ```bash
   node .github/scripts/tests/workflow-syntax-fix.test.js
   ```

## 📚 Additional Resources

- [GitHub Actions Security Hardening](https://docs.github.com/en/actions/security-guides/security-hardening-for-github-actions)
- [Enhanced Error Handler Documentation](./enhanced-error-handler-guide.md)
- [Vulnerability Scanner Guide](./vulnerability-scanner-guide.md)

---

**Remember**: Security is an ongoing process. Regularly scan your workflows and stay updated with the latest security best practices.
