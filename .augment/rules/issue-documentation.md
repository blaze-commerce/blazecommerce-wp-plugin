# Issue Documentation Rule

**Priority: ALWAYS**

**Description:** Mandate comprehensive documentation of all issues, tests, and resolutions for AI learning and institutional knowledge.

## Core Requirements

### Documentation Template
```markdown
# Issue: [Brief Description]

## Problem
- **Symptoms**: What was observed
- **Impact**: Functionality affected
- **Environment**: WordPress/WooCommerce/PHP/plugin versions

## Root Cause
- **Investigation**: What was debugged
- **Findings**: What was discovered
- **Cause**: Underlying technical issue

## Solution
- **Code**: PHP modifications made
- **Database**: Schema/data changes
- **Config**: WordPress/WooCommerce settings
- **Testing**: Verification method

## Prevention
- **Monitoring**: Detection method
- **Guidelines**: Prevention measures
```

### WordPress Plugin Focus
- Hook/filter implementation problems
- WooCommerce integration conflicts
- Database query optimizations
- WordPress version compatibility
- Custom post type/meta field issues

- PHP version compatibility problems
- WordPress core function usage issues
- Plugin conflict resolutions
- Performance optimizations
- Security vulnerability fixes

### Security Compliance (CRITICAL)
- **NEVER** include credentials, API keys, tokens, passwords
- **NEVER** include database strings or server details
- **NEVER** include user personal information
- Use placeholder: `[REPLACE_WITH_ACTUAL_VALUE_FROM_USER_CREDENTIALS]`
- Sanitize code examples, remove production URLs, anonymize data

### File Organization
- **Location**: `/.augment/rules/issues/` (primary), `/docs/troubleshooting/` (backup)
- **Naming**: `YYYY-MM-DD-issue-brief-description.md`
- **Format**: Markdown with descriptive names

### Workflow Integration
- Document issues during development
- Include resolution in commit messages
- Reference in pull requests
- Document test cases revealing issues
- Note performance impact

### Common Issue Examples

#### WooCommerce Hook Issues
```php
// Fixed hook implementation
add_action('woocommerce_order_status_changed', 'blazecommerce_update_order_status', 10, 4);
function blazecommerce_update_order_status($order_id, $old_status, $new_status, $order) {
    if (!$order instanceof WC_Order) return;
    blazecommerce_update_custom_order_status($order_id, $new_status);
}
```

#### Performance Optimization
- **Before**: Query time: 2.5s, Memory: 128MB
- **After**: Query time: 0.3s, Memory: 64MB
- **Solution**: Added indexes, optimized WP_Query, implemented caching

### Issue Categories
- **Plugin Conflicts**: Hook priorities, JS/CSS conflicts, admin interface
- **WordPress Core**: Deprecated functions, version compatibility, REST API, Gutenberg
- **Performance**: Query optimization, memory usage, caching, asset loading

## Enforcement
**ALWAYS** priority - cannot be bypassed. Ensures:
1. Institutional knowledge preservation
2. AI pattern recognition
3. Quality improvement
4. Team efficiency
5. Plugin stability
