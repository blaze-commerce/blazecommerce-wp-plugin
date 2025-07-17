---
type: "agent_requested"
description: "Git workflow and pull request standards for WordPress plugin development"
---

# Git & PR Standards Rule

## Conventional Commits Format

### 1. Commit Message Structure
```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### 2. Commit Types for WordPress Plugin
- **feat**: New feature (new functionality, WooCommerce integration)
- **fix**: Bug fix (plugin fix, compatibility issue, functionality bug)
- **docs**: Documentation changes
- **style**: Code style changes (formatting, missing semicolons, etc.)
- **refactor**: Code refactoring without changing functionality
- **perf**: Performance improvements
- **test**: Adding or updating tests
- **chore**: Maintenance tasks (dependency updates, build changes)
- **security**: Security-related changes
- **compat**: WordPress/WooCommerce compatibility updates

### 3. Scope Examples for WordPress Plugin
- **admin**: Admin interface functionality
- **api**: REST API endpoints
- **database**: Database operations and schema
- **hooks**: WordPress hooks and filters
- **woocommerce**: WooCommerce integration
- **orders**: Order management functionality
- **products**: Product management functionality
- **sync**: Data synchronization features
- **settings**: Plugin settings and configuration
- **i18n**: Internationalization and translations

### 4. Commit Message Examples
```
feat(woocommerce): add order status synchronization with external API
fix(database): resolve order data migration issue for large datasets
docs(api): update REST API endpoint documentation
perf(hooks): optimize product sync hook performance
security(admin): add nonce verification to settings form
compat(wordpress): update deprecated function calls for WordPress 6.4
test(orders): add unit tests for order processing functionality
```

## Branch Management Standards

### 1. Branch Naming Convention
- **Feature branches**: `feature/order-sync-integration`
- **Bug fix branches**: `fix/admin-settings-validation-error`
- **Hotfix branches**: `hotfix/security-nonce-validation`
- **Release branches**: `release/v1.3.0`
- **Documentation branches**: `docs/update-api-documentation`
- **Compatibility branches**: `compat/wordpress-6-4-support`

### 2. Branch Workflow
```bash
# Create feature branch from main
git checkout main
git pull origin main
git checkout -b feature/product-sync-enhancement

# Work on feature with atomic commits
git add includes/class-product-sync.php
git commit -m "feat(products): add enhanced product synchronization logic"

git add admin/partials/sync-settings.php
git commit -m "feat(admin): add sync settings interface"

git add tests/unit/test-product-sync.php
git commit -m "test(products): add unit tests for product sync functionality"

# Push feature branch
git push origin feature/product-sync-enhancement

# Create pull request
# After approval and merge, delete feature branch
git branch -d feature/product-sync-enhancement
```

### 3. Protected Branch Rules
- **main**: Require PR reviews, status checks, up-to-date branches
- **develop**: Require PR reviews, allow force pushes for maintainers
- No direct pushes to protected branches
- Require linear history for main branch
- Require all CI checks to pass (PHP lint, PHPCS, tests)

## Pull Request Standards

### 1. PR Title Format
Follow conventional commit format for PR titles:
```
feat(woocommerce): integrate order status synchronization for external API
fix(admin): resolve settings validation and nonce verification issues
docs(database): update database schema and migration documentation
perf(sync): optimize product synchronization performance and memory usage
```

### 2. PR Description Template
```markdown
## Description
Brief description of changes made and problem solved.

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Security fix
- [ ] WordPress/WooCommerce compatibility update

## WordPress/WooCommerce Context
- WordPress version tested: 6.4+
- WooCommerce version tested: 8.0+
- PHP version tested: 8.1+
- Plugin version: 1.2.0

## Testing Checklist
- [ ] Code follows WordPress coding standards (PHPCS)
- [ ] Self-review of code completed
- [ ] Code is properly documented with PHPDoc
- [ ] Unit tests added/updated and passing
- [ ] Integration tests added/updated and passing
- [ ] WordPress compatibility tested
- [ ] WooCommerce compatibility tested
- [ ] Database migrations tested (if applicable)
- [ ] Admin interface tested
- [ ] Security considerations addressed (nonces, sanitization, escaping)
- [ ] Performance impact assessed

## WordPress Standards Checklist
- [ ] All user inputs sanitized
- [ ] All outputs properly escaped
- [ ] Nonces implemented for forms and AJAX
- [ ] User capability checks in place
- [ ] Database queries use prepared statements
- [ ] Hooks and filters properly implemented
- [ ] Internationalization implemented (if applicable)
- [ ] No PHP errors or warnings
- [ ] Plugin activation/deactivation tested

## Screenshots/Videos
Include screenshots or videos demonstrating the changes, especially for admin interface changes.

### Before
[Screenshot or description of previous state]

### After
[Screenshot or description of new state]

## Database Changes
- [ ] No database changes
- [ ] Database schema changes (include migration script)
- [ ] New custom tables created
- [ ] Existing table modifications

If database changes are included, describe:
- Migration strategy
- Rollback procedure
- Performance impact
- Data integrity measures

## Performance Impact
- Memory usage impact: [Measured impact]
- Database query impact: [Number of queries added/removed]
- Page load impact: [Measured impact on admin/frontend]
- Caching considerations: [Any caching implications]

## Security Considerations
- Input validation implemented: [Yes/No - describe]
- Output escaping implemented: [Yes/No - describe]
- Nonce verification: [Yes/No - describe]
- Capability checks: [Yes/No - describe]
- SQL injection prevention: [Yes/No - describe]

## Related Issues
Closes #123
Fixes #456
Related to #789

## Deployment Notes
Any special deployment considerations:
- Plugin activation/deactivation steps
- Database migration requirements
- Configuration changes needed
- Third-party service updates

## Breaking Changes
List any breaking changes and migration steps if applicable:
- API changes
- Database schema changes
- Configuration changes
- Deprecated functionality
```

### 3. PR Review Requirements
- **Minimum reviewers**: 1 for minor changes, 2 for major changes
- **Required checks**: All CI/CD pipelines must pass
- **Code quality**: Must meet WordPress coding standards
- **Testing**: All tests must pass with adequate coverage
- **Security**: Security review for all changes
- **Compatibility**: WordPress and WooCommerce compatibility verified

## Code Review Guidelines

### 1. Review Checklist
- [ ] Code follows WordPress coding standards
- [ ] Security best practices implemented
- [ ] Performance considerations addressed
- [ ] Database operations are optimized
- [ ] Error handling is comprehensive
- [ ] WordPress hooks used appropriately
- [ ] WooCommerce integration follows best practices
- [ ] Tests are comprehensive and meaningful
- [ ] Documentation is updated
- [ ] No sensitive data exposed

### 2. Review Comments Standards
```markdown
# Security feedback
**Security Issue**: User input not sanitized before database insertion
**Suggestion**: Use sanitize_text_field() and prepared statements
**Example**: 
```php
$user_input = sanitize_text_field($_POST['user_data']);
$wpdb->prepare("INSERT INTO table (column) VALUES (%s)", $user_input);
```

# Performance feedback
**Performance Issue**: N+1 query problem in product loop
**Suggestion**: Use WP_Query with proper meta_query or custom SQL
**Impact**: Current implementation may cause timeout on large datasets

# WordPress Standards feedback
**Standards Issue**: Direct database access without using WordPress APIs
**Suggestion**: Use WordPress database abstraction layer
**Reference**: WordPress Coding Standards - Database section
```

## Git Workflow Best Practices

### 1. Commit Best Practices
- Make atomic commits (one logical change per commit)
- Write clear, descriptive commit messages in present tense
- Commit frequently with meaningful messages
- Reference issue numbers when applicable
- Keep commits focused and avoid mixing concerns

### 2. Merge Strategies
- **Squash and merge**: For feature branches with multiple commits
- **Merge commit**: For release branches to preserve history
- **Rebase and merge**: For clean linear history when appropriate

### 3. Conflict Resolution
```bash
# Resolve merge conflicts
git checkout feature-branch
git rebase main
# Resolve conflicts in files
git add resolved-files
git rebase --continue
git push --force-with-lease origin feature-branch
```

## Release Management

### 1. Version Numbering
Follow semantic versioning (SemVer):
- **MAJOR**: Breaking changes (API changes, major refactors)
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

### 2. Release Process
```bash
# Create release branch
git checkout -b release/v1.3.0

# Update version in plugin file
# Update readme.txt changelog
# Update composer.json version
# Final testing and bug fixes

# Merge to main
git checkout main
git merge release/v1.3.0

# Tag release
git tag -a v1.3.0 -m "Release version 1.3.0"
git push origin v1.3.0

# Deploy to WordPress.org (if applicable)
# Merge back to develop
git checkout develop
git merge main
```

### 3. Changelog Maintenance
```markdown
# Changelog

## [1.3.0] - 2025-01-17
### Added
- Enhanced product synchronization with external API
- New admin settings for sync configuration
- REST API endpoints for order management

### Fixed
- Database migration issues for large datasets
- Admin settings validation errors
- WooCommerce compatibility issues

### Changed
- Improved performance for product sync operations
- Updated WordPress compatibility to 6.4+
- Enhanced error handling and logging

### Security
- Added nonce verification to all admin forms
- Improved input sanitization and output escaping
- Enhanced user capability checks
```

## WordPress Plugin Context

These Git and PR standards apply specifically to:
- WordPress plugin development
- WooCommerce integration development
- Database schema and migration management
- Admin interface development
- REST API endpoint development
- Hook and filter implementation
- Security and performance optimization
- WordPress/WooCommerce compatibility maintenance
