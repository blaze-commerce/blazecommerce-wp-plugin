# ⚠️ CRITICAL: Claude Action Version Requirements

## 🚨 Important Notice

**The `anthropics/claude-code-action` MUST use the `@beta` tag. This is a critical requirement that differs from standard GitHub Actions version pinning practices.**

## 📋 Quick Reference

### ✅ CORRECT Usage:
```yaml
uses: anthropics/claude-code-action@beta
```

### ❌ INCORRECT Usage (Will Cause Failures):
```yaml
uses: anthropics/claude-code-action@v1.0.0  # Version does not exist!
uses: anthropics/claude-code-action@latest  # Not recommended by Anthropic
uses: anthropics/claude-code-action@main    # Not stable
uses: anthropics/claude-code-action@1.0.0   # Version does not exist!
```

## 🔍 Why `@beta` is Required

### 1. Official Anthropic Recommendation
- Anthropic explicitly recommends using the `@beta` tag in their documentation
- This is the officially supported and maintained version reference
- The `@beta` tag is treated as the stable release by Anthropic

### 2. No Semantic Versioning
- Unlike most GitHub Actions, the Claude action does not use semantic versioning
- Tags like `@v1.0.0`, `@v2.0.0`, etc., do not exist
- Attempting to use these non-existent tags will cause immediate workflow failures

### 3. Continuous Integration with Claude API
- The action is designed to work with the evolving Claude API
- The `@beta` tag ensures compatibility with the latest Claude models and features
- Pinning to a specific commit or non-existent version breaks this integration

### 4. Anthropic's Development Model
- Anthropic maintains the `@beta` tag as their stable reference point
- Updates are thoroughly tested before being applied to the `@beta` tag
- This approach ensures users always have access to the latest stable features

## 🛡️ Security Considerations

### Why This is an Exception to Version Pinning
While security best practices typically recommend pinning GitHub Actions to specific versions, the Claude action is a special case:

1. **Trusted Source**: The action is maintained directly by Anthropic
2. **No Alternative**: No stable version tags exist to pin to
3. **Official Recommendation**: Anthropic's own documentation specifies `@beta`
4. **Controlled Updates**: Anthropic manages the `@beta` tag responsibly

### Risk Mitigation
- The action is maintained by a reputable AI company (Anthropic)
- Updates to `@beta` are tested and controlled
- The action has limited scope (AI code review only)
- We implement retry logic to handle any temporary issues

## 🔧 Implementation Examples

### In PR Review Workflow:
```yaml
- name: Claude AI Review
  uses: anthropics/claude-code-action@beta
  with:
    anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
    direct_prompt: ${{ steps.prepare-context.outputs.review_prompt }}
```

### In Interactive Claude Workflow:
```yaml
- name: Run Claude Code
  id: claude
  uses: anthropics/claude-code-action@beta
  with:
    anthropic_api_key: ${{ secrets.ANTHROPIC_API_KEY }}
```

## 🚫 Common Errors and Solutions

### Error: "Action not found"
```
Error: Could not find action 'anthropics/claude-code-action@v1.0.0'
```

**Solution**: Change to `@beta`
```yaml
# Wrong:
uses: anthropics/claude-code-action@v1.0.0

# Correct:
uses: anthropics/claude-code-action@beta
```

### Error: "Invalid action reference"
```
Error: Invalid action reference 'anthropics/claude-code-action@latest'
```

**Solution**: Use the official `@beta` tag
```yaml
# Wrong:
uses: anthropics/claude-code-action@latest

# Correct:
uses: anthropics/claude-code-action@beta
```

## 📚 Documentation References

### Official Anthropic Documentation
- [Claude Code Action GitHub Repository](https://github.com/anthropics/claude-code-action)
- [Anthropic API Documentation](https://docs.anthropic.com/)

### Internal Documentation
- [GitHub Workflows Optimization](./github-workflows-optimization.md)
- [Workflow Stability Improvements](./workflow-stability-improvements.md)

## 🔄 Maintenance Guidelines

### For Developers:
1. **Never** change `@beta` to a version number
2. **Always** use `@beta` for new Claude action implementations
3. **Verify** that any workflow changes maintain the `@beta` tag

### For Code Reviews:
1. **Check** that Claude action uses `@beta` tag
2. **Reject** any PRs that attempt to pin Claude action to specific versions
3. **Educate** team members about this special requirement

### For Documentation Updates:
1. **Maintain** this exception in all workflow documentation
2. **Update** examples to always show `@beta` usage
3. **Include** warnings about version pinning in relevant docs

## ⚡ Quick Validation

To verify all Claude actions are using the correct tag:

```bash
# Check all workflow files for Claude action usage
grep -r "anthropics/claude-code-action" .github/workflows/

# Expected output should show only @beta tags:
# .github/workflows/claude-pr-review.yml:        uses: anthropics/claude-code-action@beta
# .github/workflows/claude.yml:        uses: anthropics/claude-code-action@beta
```

## 🎯 Summary

- **ALWAYS** use `anthropics/claude-code-action@beta`
- **NEVER** attempt to pin to version numbers (they don't exist)
- **REMEMBER** this is an exception to normal version pinning practices
- **EDUCATE** team members about this critical requirement

---

**Last Updated**: 2025-07-13  
**Status**: Critical - Must be followed for all Claude action implementations  
**Next Review**: When Anthropic releases official versioning (if ever)
