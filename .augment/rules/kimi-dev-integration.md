# Kimi-Dev Integration with Augment Code for WordPress Plugin Development

## Two-Stage Approach for WordPress Plugin Code Tasks

### Stage 1: File Localization for WordPress Plugins
When working on WordPress plugin issues, first identify the relevant files:

**WordPress Plugin Prompt Template:**
```
# File Localization Phase - WordPress Plugin Context
Given the following WordPress plugin issue/task: {ISSUE_DESCRIPTION}

Plugin context: {PLUGIN_CONTEXT}
WordPress version: {WP_VERSION}
PHP version: {PHP_VERSION}

Please identify the key files that need to be modified. Consider:
1. Main plugin file (blaze-wooless.php)
2. Class files in /app directory
3. Template files in /views directory
4. Asset files in /assets directory
5. Block files in /blocks directory
6. Helper functions in /lib directory
7. WordPress hooks and filters
8. Database schema changes
9. Admin interface files
10. Test files for WordPress functionality

Output format:
- Core plugin files: [main plugin files to modify]
- Class files: [OOP classes that need changes]
- Template files: [view/template files to update]
- Asset files: [CSS/JS files to modify]
- Test files: [WordPress test files to update/create]
- Database files: [migration or schema files]
```

### Stage 2: WordPress Plugin Code Editing
After file localization, perform WordPress-specific modifications:

**WordPress Plugin Editing Template:**
```
# Code Editing Phase - WordPress Plugin Context
Files to modify: {IDENTIFIED_FILES}
WordPress issue: {ISSUE_DESCRIPTION}
Plugin file content: {FILE_CONTENT}

Please provide WordPress plugin-specific code modifications:
1. Follow WordPress coding standards (WPCS)
2. Use proper WordPress hooks and filters
3. Implement proper sanitization and validation
4. Follow WordPress security best practices
5. Ensure compatibility with WordPress versions
6. Use WordPress database abstraction layer
7. Implement proper error handling
8. Add WordPress-specific documentation

Output format:
- WordPress standards compliance check
- Security considerations
- Hook/filter implementations
- Database query optimizations
- Code changes (with line numbers)
- WordPress test updates
- Compatibility verification steps
```

## Specialized Prompts for WordPress Plugin Tasks

### WordPress Bug Fixing
```
You are a WordPress plugin bug-fixing specialist. Use the two-stage approach:
1. First, locate all WordPress plugin files related to the bug
2. Then, provide WordPress-compliant fixes with comprehensive testing

Focus on:
- WordPress coding standards compliance
- Security vulnerabilities (SQL injection, XSS, CSRF)
- Performance optimization for WordPress
- Compatibility with WordPress core and popular plugins
- Proper use of WordPress APIs
- Database query optimization
```

### WordPress Test Writing
```
You are a WordPress plugin test-writing specialist. Use the two-stage approach:
1. First, identify WordPress functionality that needs testing
2. Then, write comprehensive WordPress test suites

Focus on:
- WordPress unit tests using WP_UnitTestCase
- Integration tests with WordPress core
- Database tests with WordPress test framework
- Admin interface testing
- Frontend functionality testing
- Plugin activation/deactivation tests
- Multisite compatibility tests
```

### WordPress Security Auditing
```
You are a WordPress security specialist. Use the two-stage approach:
1. First, identify potential security vulnerabilities in plugin files
2. Then, provide security hardening recommendations

Focus on:
- Input sanitization and validation
- Output escaping
- Nonce verification
- Capability checks
- SQL injection prevention
- XSS prevention
- CSRF protection
- File upload security
```

## Integration with BlazeCommerce WordPress Plugin Context

### Plugin-Specific Considerations
- **Plugin Name**: BlazeWooless (blaze-wooless.php)
- **Namespace**: BlazeWooless
- **Dependencies**: WooCommerce, WordPress
- **Architecture**: Object-oriented with singleton patterns
- **Database**: Custom tables and WordPress options
- **Frontend**: Gutenberg blocks and shortcodes
- **Admin**: Custom admin pages and settings

### WordPress Plugin Development Workflow

1. **Context Gathering**: Use Augment Code to understand plugin architecture
2. **File Localization**: Apply Kimi-Dev's approach to WordPress file structure
3. **Code Editing**: Implement WordPress-compliant solutions
4. **Testing**: Use WordPress testing framework
5. **Security Review**: Apply WordPress security best practices

### WordPress-Specific File Patterns

**Main Plugin Files:**
- `blaze-wooless.php` - Main plugin file
- `/app/*.php` - Core plugin classes
- `/lib/*.php` - Helper functions
- `/views/*.php` - Template files

**WordPress Integration Files:**
- Hook implementations
- Filter callbacks
- Admin menu pages
- Settings API usage
- Database schema
- Activation/deactivation hooks

**Asset Files:**
- `/assets/css/*.css` - Stylesheets
- `/assets/js/*.js` - JavaScript files
- `/blocks/*.js` - Gutenberg block scripts

## VS Code Integration for WordPress Development

### WordPress-Specific Commands
- **WordPress Standards Check**: Validate code against WPCS
- **Security Scan**: Check for common WordPress vulnerabilities
- **Performance Analysis**: Identify WordPress performance issues
- **Compatibility Test**: Test against multiple WordPress versions

### Integration Benefits for WordPress Plugins

- **Augment Code**: Excellent WordPress codebase understanding
- **Kimi-Dev**: Specialized software engineering with WordPress context
- **Combined**: WordPress-compliant solutions with comprehensive plugin knowledge
