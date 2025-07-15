# GitHub Workflow Troubleshooting Guide

## 🚨 Common Issues and Solutions

### **1. Composer Dependency Issues**

#### **Problem:** "Lock file is not up to date" error
```bash
Error: The lock file is not up to date with the latest changes in composer.json
```

**Solution:**
```yaml
# Workflow automatically handles this
- name: Install Composer dependencies
  run: |
    composer config --global allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
    if [ -f "composer.lock" ]; then
      if ! composer validate --no-check-publish --no-check-all; then
        rm composer.lock
      fi
    fi
    composer install --prefer-dist --no-progress --no-interaction --optimize-autoloader
```

#### **Problem:** "allow-plugins" security warnings
```bash
For additional security you should declare the allow-plugins config
```

**Solution:** Already fixed in composer.json:
```json
{
  "config": {
    "allow-plugins": {
      "dealerdirect/phpcodesniffer-composer-installer": true
    }
  }
}
```

### **2. Node.js Script Issues**

#### **Problem:** Script file not found
```bash
Error: Cannot find module '.github/scripts/file-change-analyzer.js'
```

**Solution:** Fallback logic automatically activates:
```yaml
if [ ! -f ".github/scripts/file-change-analyzer.js" ]; then
  echo "⚠️ Script not found, using fallback logic..."
  # Fallback implementation runs
fi
```

#### **Problem:** npm install failures
```bash
Error: npm ERR! network timeout
```

**Solution:** Enhanced error handling:
```yaml
- name: Install dependencies
  run: |
    npm install --no-audit --no-fund --timeout=60000 || {
      echo "⚠️ npm install failed, using minimal dependencies"
      npm init -y
      npm install semver js-yaml node-fetch --no-audit --no-fund
    }
```

### **3. Test Suite Issues**

#### **Problem:** PHPUnit not found
```bash
Error: vendor/bin/phpunit not found
```

**Solution:** Workflow validates before running:
```yaml
if [ ! -f "vendor/bin/phpunit" ]; then
  echo "❌ PHPUnit binary not found"
  exit 1
fi
```

#### **Problem:** Database connection failures
```bash
Error: Access denied for user 'root'@'localhost'
```

**Solution:** Fixed in phpunit.xml:
```xml
<const name="DB_PASSWORD" value="root" />
<const name="DB_HOST" value="127.0.0.1:3306" />
```

### **4. Claude AI Review Issues**

#### **Problem:** API key not configured
```bash
Error: ANTHROPIC_API_KEY not found
```

**Solution:** Graceful fallback:
```yaml
if [ -z "${{ secrets.ANTHROPIC_API_KEY }}" ]; then
  echo "⚠️ API key not configured - using fallback review"
  echo "response=Manual review recommended." >> $GITHUB_OUTPUT
  exit 0
fi
```

#### **Problem:** External action unavailable
```bash
Error: Action 'anthropics/claude-code-action@beta' not found
```

**Solution:** Replaced with internal implementation that provides fallback reviews.

### **5. Version Management Issues**

#### **Problem:** Git tag conflicts
```bash
Error: tag 'v1.2.0' already exists
```

**Solution:** Enhanced version validation:
```yaml
if git tag -l | grep -q "^v$NEW_VERSION$"; then
  echo "⚠️ Tag already exists, incrementing patch version"
  # Auto-increment logic
fi
```

#### **Problem:** Version mismatch between files
```bash
Error: package.json version differs from plugin file
```

**Solution:** Automatic synchronization:
```yaml
if [ "$VERSION_MISMATCH" = "true" ]; then
  echo "bump_type=patch" >> $GITHUB_OUTPUT
  echo "reason=Version mismatch fix" >> $GITHUB_OUTPUT
fi
```

## 🔧 Debugging Commands

### **Local Testing**

#### **Test Composer Setup**
```bash
composer validate --no-check-publish --no-check-all
composer install --dry-run
```

#### **Test Node.js Scripts**
```bash
node -c .github/scripts/file-change-analyzer.js
node .github/scripts/file-change-analyzer.js
```

#### **Test PHPUnit Configuration**
```bash
vendor/bin/phpunit --configuration phpunit.xml --dry-run
```

#### **Test Version Scripts**
```bash
node scripts/validate-version.js --verbose
node scripts/update-version.js --dry-run
```

### **Workflow Debugging**

#### **Enable Debug Mode**
Add to workflow environment:
```yaml
env:
  DEBUG: 'true'
  ACTIONS_STEP_DEBUG: 'true'
```

#### **Check Workflow Logs**
```bash
# View specific job logs
gh run view <run-id> --log --job <job-name>

# View failed steps
gh run view <run-id> --log | grep -A 10 -B 10 "Error"
```

## 📊 Monitoring and Alerts

### **Key Metrics to Monitor**

1. **Workflow Success Rate**
   - Target: >95% success rate
   - Alert if: <90% for 24 hours

2. **Test Coverage**
   - Target: >80% code coverage
   - Alert if: <70% coverage

3. **Build Time**
   - Target: <10 minutes total
   - Alert if: >15 minutes consistently

4. **Dependency Health**
   - Monitor for security vulnerabilities
   - Track outdated packages

### **Health Check Commands**

```bash
# Check workflow status
gh workflow list --repo blaze-commerce/blazecommerce-wp-plugin

# Check recent runs
gh run list --workflow=tests.yml --limit=10

# Check for failing patterns
gh run list --status=failure --limit=20
```

## 🛠️ Maintenance Tasks

### **Weekly Tasks**
- [ ] Review workflow success rates
- [ ] Check for dependency updates
- [ ] Validate test coverage reports
- [ ] Review error logs for patterns

### **Monthly Tasks**
- [ ] Update Node.js dependencies
- [ ] Review and update Composer dependencies
- [ ] Audit security configurations
- [ ] Performance optimization review

### **Quarterly Tasks**
- [ ] Comprehensive workflow review
- [ ] Update GitHub Actions versions
- [ ] Security audit of all configurations
- [ ] Documentation updates

## 🚀 Performance Optimization

### **Workflow Speed Improvements**

1. **Caching Strategy**
```yaml
- name: Cache Composer packages
  uses: actions/cache@v4
  with:
    path: vendor
    key: ${{ runner.os }}-php-${{ hashFiles('**/composer.lock') }}
```

2. **Parallel Execution**
```yaml
strategy:
  matrix:
    php-version: ['7.4', '8.0', '8.1']
  fail-fast: false
```

3. **Selective Testing**
```yaml
# Only run tests if relevant files changed
if: contains(github.event.head_commit.modified, 'app/') || 
    contains(github.event.head_commit.modified, 'tests/')
```

## 📞 Support and Escalation

### **Self-Service Resolution**
1. Check this troubleshooting guide
2. Review workflow logs in GitHub Actions
3. Test locally using provided commands
4. Check recent commits for breaking changes

### **Escalation Path**
1. **Level 1:** Team lead review
2. **Level 2:** DevOps team consultation
3. **Level 3:** External vendor support (if needed)

### **Emergency Procedures**
If all workflows are failing:
1. Disable failing workflows temporarily
2. Implement manual testing procedures
3. Investigate root cause
4. Apply hotfix
5. Re-enable workflows with monitoring

## 📚 Additional Resources

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [Composer Documentation](https://getcomposer.org/doc/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Testing](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)

---

**Last Updated:** 2025-07-13  
**Version:** 1.0.0  
**Maintainer:** BlazeCommerce DevOps Team
