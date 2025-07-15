# Claude AI Cost Optimization Guide

## Overview

This document explains the cost optimization features implemented in our Claude AI GitHub Actions workflows to reduce API costs from ~$35/day to ~$5-8/day (85% reduction).

## 🎯 Key Optimizations Implemented

### 1. Intelligent Model Selection

The system automatically chooses between Claude Haiku (cheap) and Claude Sonnet 4 (expensive) based on:

#### **Claude Sonnet 4 Triggers:**
- **Critical Files**: Security, auth, payment, admin, core, API files
- **Large Changes**: PRs with >200 total changes
- **Medium PHP Changes**: PRs with >100 changes in PHP files
- **Manual Override**: Force Sonnet via workflow dispatch

#### **Claude Haiku Triggers:**
- Documentation changes
- Configuration updates  
- Small bug fixes
- Non-critical file modifications

### 2. Conditional Execution

Reviews only run for:
- First-time contributors
- External contributors  
- PRs labeled with `needs-review` or `external-review`
- Manual workflow dispatch

### 3. File Filtering

Workflows skip execution for:
- Documentation files (`docs/**`, `**.md`)
- Test files (`tests/**`, `test/**`)
- Configuration files (`package-lock.json`, `composer.lock`)
- README and changelog files

### 4. Review Caching

- Prevents duplicate reviews of the same commit
- Caches based on PR number + commit SHA
- Shows cost savings notification when skipped

### 5. Optimized Prompts

- Reduced from 138 lines to ~20 lines (85% token reduction)
- Focused on critical issues only
- Maintains same review quality standards

## 💰 Cost Impact

| Optimization | Before | After | Savings |
|-------------|--------|-------|---------|
| **Model Selection** | 100% Sonnet | 70% Haiku, 30% Sonnet | 68% |
| **Conditional Execution** | All PRs | External only | 60% |
| **File Filtering** | All changes | Code only | 40% |
| **Review Caching** | Always review | Skip duplicates | 20% |
| **Prompt Optimization** | 138 lines | 20 lines | 85% tokens |

**Combined Expected Savings: 85% (from $35/day to $5-8/day)**

## 🔧 Configuration

### Environment Variables

```yaml
# Required secrets (same as before)
ANTHROPIC_API_KEY: Your Claude API key
GITHUB_TOKEN: GitHub access token
```

### Workflow Dispatch Options

```yaml
inputs:
  pr_number: PR number to review
  force_sonnet: Force Claude Sonnet (bypass intelligent selection)
```

### Labels for Manual Control

- `needs-review`: Force review for internal PRs
- `external-review`: Mark external contributor PRs
- `skip-review`: Skip review entirely (add to PR title)

## 📊 Monitoring

### Cost Tracking

Monitor costs at: [Anthropic Console](https://console.anthropic.com/usage)

### Workflow Logs

Each run shows:
- Selected model and reason
- Cache hit/miss status
- File patterns matched
- Cost optimization decisions

### Example Log Output

```
💰 CLAUDE AI COST-OPTIMIZED REVIEW STARTING
============================================
📋 PR NUMBER: 123
🤖 SELECTED MODEL: claude-3-haiku-20240307
📝 SELECTION REASON: Simple changes (45 changes, no critical files)
💾 CACHE STATUS: MISS (Proceeding)
⏰ EXECUTION TIME: 2024-01-15 10:30:00 UTC
```

## 🚀 Usage Examples

### Automatic Model Selection

```yaml
# PR with security changes → Sonnet
files: ['src/auth/login.php', 'includes/security.php']
result: claude-3-5-sonnet-20241022

# PR with docs changes → Haiku  
files: ['README.md', 'docs/api.md']
result: claude-3-haiku-20240307
```

### Manual Override

```bash
# Force expensive model for thorough review
gh workflow run "Priority 2: Claude AI Code Review (Cost Optimized)" \
  -f pr_number=123 \
  -f force_sonnet=true
```

### Cache Behavior

```yaml
# First review of commit abc123
cache_key: claude-review-123-abc123
cache_hit: false → Runs review

# Second review of same commit  
cache_key: claude-review-123-abc123
cache_hit: true → Skips review, saves cost
```

## 🔍 Troubleshooting

### High Costs

1. Check if too many PRs are triggering Sonnet
2. Verify file patterns are working correctly
3. Ensure caching is functioning
4. Review conditional execution logic

### Missing Reviews

1. Check if PR author association is correct
2. Verify required labels are present
3. Ensure file patterns include your changes
4. Check workflow dispatch permissions

### Model Selection Issues

1. Review file pattern matching in logs
2. Verify PR size calculations
3. Check for manual override flags
4. Validate critical file patterns

## 📈 Performance Metrics

Track these metrics to monitor optimization effectiveness:

- **Daily API costs** (target: <$8/day)
- **Review coverage** (maintain >95% for external PRs)
- **Cache hit rate** (target: >30%)
- **Haiku vs Sonnet ratio** (target: 70/30)

## 🔄 Future Enhancements

Potential additional optimizations:

1. **Time-based throttling**: Limit reviews per hour
2. **Author-based rules**: Different thresholds per contributor
3. **Repository-specific patterns**: Custom rules per repo
4. **Batch processing**: Group multiple small PRs
5. **Progressive review**: Start with Haiku, escalate if needed

## 📞 Support

For issues or questions about cost optimization:

1. Check workflow logs for detailed decision reasoning
2. Review this documentation for configuration options
3. Test with workflow dispatch for debugging
4. Monitor Anthropic console for usage patterns
