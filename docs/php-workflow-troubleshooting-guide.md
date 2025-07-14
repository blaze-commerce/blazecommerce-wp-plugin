# PHP Test Workflow Troubleshooting Guide

## 🚨 Common Issues and Solutions

This guide provides solutions for common issues that may occur with the PHP test workflows after implementing the comprehensive fixes.

## 🔧 Quick Diagnostics

### 1. Workflow Validation
```bash
# Run the validation script to check workflow configuration
node scripts/validate-php-workflow-fixes.js

# Monitor workflow execution in real-time
node scripts/monitor-workflow-execution.js
```

### 2. Manual Workflow Trigger
```bash
# Trigger workflow manually with debug mode
gh workflow run "Tests" --field debug_mode=true
```

## 🐛 Issue Categories

### Database Connection Issues

**Symptoms:**
- MySQL connection timeouts
- Database authentication failures
- "Can't connect to MySQL server" errors

**Solutions:**
1. **Check MySQL Service Health:**
   ```yaml
   # Verify health check configuration in workflow
   options: >-
     --health-cmd="mysqladmin ping --silent"
     --health-interval=10s
     --health-timeout=10s
     --health-retries=5
     --health-start-period=30s
   ```

2. **Verify Connection Parameters:**
   ```bash
   # Test connection in workflow step
   mysql -h 127.0.0.1 -P 3306 -u root -proot -e "SELECT 1"
   ```

3. **Check Authentication Plugin:**
   ```yaml
   # Ensure native password authentication
   --default-authentication-plugin=mysql_native_password
   ```

### Composer Dependency Issues

**Symptoms:**
- Package installation failures
- Version conflicts
- Memory limit errors during installation

**Solutions:**
1. **Clear Composer Cache:**
   ```bash
   composer clear-cache
   rm composer.lock
   composer install
   ```

2. **Check Platform Requirements:**
   ```bash
   composer config platform.php 8.1
   composer check-platform-reqs
   ```

3. **Increase Memory Limit:**
   ```yaml
   ini-values: |
     memory_limit=512M
   ```

### WordPress Test Environment Issues

**Symptoms:**
- WordPress installation failures
- SVN connectivity issues
- Missing test files

**Solutions:**
1. **Check SVN Connectivity:**
   ```bash
   # Test SVN access
   svn info https://develop.svn.wordpress.org/trunk/
   ```

2. **Verify Test Environment:**
   ```bash
   # Check required directories
   ls -la /tmp/wordpress-tests-lib/includes/
   ls -la /tmp/wordpress/
   ```

3. **Manual WordPress Setup:**
   ```bash
   # Run installation script manually
   bash -x bin/install-wp-tests.sh wordpress_test root root 127.0.0.1:3306 latest
   ```

### PHP Version Compatibility Issues

**Symptoms:**
- PHP extension loading failures
- Syntax errors on specific PHP versions
- Memory or execution time limits

**Solutions:**
1. **Check PHP Extensions:**
   ```bash
   php -m | grep -E "(mysql|mysqli|pdo_mysql)"
   ```

2. **Verify PHP Configuration:**
   ```bash
   php -i | grep -E "(memory_limit|max_execution_time)"
   ```

3. **Test PHP Version Matrix:**
   ```yaml
   # Exclude problematic combinations
   exclude:
     - php-version: '8.2'
       wordpress-version: '6.3'
   ```

## 🔍 Debugging Techniques

### Enable Debug Mode
```bash
# Trigger workflow with debug mode enabled
gh workflow run "Tests" --field debug_mode=true
```

### Check Workflow Logs
```bash
# View recent workflow runs
gh run list --workflow="Tests"

# View specific run details
gh run view [RUN_ID] --log
```

### Local Testing
```bash
# Test composer installation locally
composer install --prefer-dist --no-progress --no-interaction

# Test PHPUnit configuration
vendor/bin/phpunit --configuration phpunit.xml --dry-run

# Validate workflow syntax
yamllint .github/workflows/tests.yml
```

## 📊 Performance Optimization

### Reduce Execution Time
1. **Optimize Composer Cache:**
   ```yaml
   - name: Cache Composer packages
     uses: actions/cache@v4
     with:
       path: |
         vendor
         ~/.composer/cache
   ```

2. **Parallel Job Execution:**
   ```yaml
   strategy:
     fail-fast: false
     max-parallel: 4
   ```

3. **Selective Test Execution:**
   ```bash
   # Run specific test suites
   vendor/bin/phpunit --testsuite="Unit Tests"
   ```

### Memory Management
```yaml
# Increase PHP memory limits
ini-values: |
  memory_limit=512M
  max_execution_time=300
```

## 🚨 Emergency Procedures

### Workflow Completely Failing
1. **Revert to Previous Version:**
   ```bash
   git checkout main -- .github/workflows/tests.yml
   ```

2. **Disable Problematic Jobs:**
   ```yaml
   # Temporarily disable failing jobs
   if: false
   ```

3. **Use Minimal Configuration:**
   ```yaml
   # Reduce matrix to essential combinations only
   php-version: ['8.1']
   wordpress-version: ['latest']
   ```

### Critical Production Issues
1. **Skip CI Temporarily:**
   ```bash
   git commit -m "fix: critical issue [skip ci]"
   ```

2. **Emergency Hotfix:**
   ```bash
   # Create emergency branch
   git checkout -b hotfix/emergency-fix
   # Apply minimal fix
   # Push directly to main if necessary
   ```

## 📞 Support Resources

### Internal Resources
- **Workflow Documentation:** `docs/php-test-workflow-fixes-comprehensive.md`
- **Validation Script:** `scripts/validate-php-workflow-fixes.js`
- **Monitoring Script:** `scripts/monitor-workflow-execution.js`

### External Resources
- **GitHub Actions Documentation:** https://docs.github.com/en/actions
- **PHPUnit Documentation:** https://phpunit.de/documentation.html
- **Composer Documentation:** https://getcomposer.org/doc/
- **WordPress Testing:** https://make.wordpress.org/core/handbook/testing/

## 🔄 Maintenance Schedule

### Weekly Checks
- [ ] Review workflow success rates
- [ ] Check for new PHP/WordPress version compatibility
- [ ] Update dependencies if needed

### Monthly Reviews
- [ ] Analyze performance metrics
- [ ] Review and update documentation
- [ ] Test with latest WordPress/PHP versions

### Quarterly Updates
- [ ] Review and optimize workflow configuration
- [ ] Update GitHub Actions versions
- [ ] Performance benchmarking and optimization

## 📈 Success Metrics

### Target Performance
- **Success Rate:** ≥ 95%
- **Average Execution Time:** ≤ 15 minutes
- **First-Time Success Rate:** ≥ 90%

### Monitoring
```bash
# Check recent success rates
gh run list --workflow="Tests" --limit=20 --json=conclusion
```

---

**Last Updated:** $(date)
**Version:** 1.0.0
**Maintainer:** BlazeCommerce Development Team
