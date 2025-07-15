# GitHub Actions Workflow Quick Reference

## 🚀 New Simplified Workflows

### Test Workflow (`tests.yml`)
- **Trigger**: Push to `main`/`develop`, PRs, manual dispatch
- **Features**: Auto-detects service availability, graceful degradation
- **Modes**: `full` (all services) → `basic` (MySQL only) → `minimal` (syntax only)

### Auto Version (`auto-version.yml`)  
- **Trigger**: Push to `main` branch only
- **Features**: Semantic versioning, auto-commit and tag
- **Modes**: `auto`, `patch`, `minor`, `major`

### Claude Approval (`claude-approval-gate.yml`)
- **Trigger**: Claude comment with "FINAL VERDICT"
- **Features**: Auto-approval, label management
- **Fallback**: Manual review when Claude API unavailable

## 🔧 New Scripts

### Health Check
```bash
scripts/health-check.sh [auto|full|basic]
```
- Checks service availability
- Returns recommended test mode
- Creates health check logs

### Circuit Breaker
```bash
scripts/circuit-breaker.sh [service|all]
```
- Monitors external service health
- Implements failure thresholds
- Automatic recovery attempts

### Test Runner
```bash
scripts/run-tests.sh [mode] [php-version] [wp-version] [debug]
```
- Executes tests with graceful degradation
- Handles service failures automatically
- Comprehensive logging

### Setup Fallbacks
```bash
scripts/setup-local-fallbacks.sh
```
- Creates local service caches
- Sets up offline test modes
- Initializes fallback templates

## 🎯 Test Modes Explained

### Full Mode
- **Requirements**: MySQL + WordPress SVN/API
- **Features**: Complete test suite, coverage reports
- **Use Case**: Normal CI/CD operations

### Basic Mode  
- **Requirements**: MySQL only
- **Features**: Unit tests, database tests
- **Use Case**: WordPress services unavailable

### Minimal Mode
- **Requirements**: None (offline)
- **Features**: Syntax validation, composer checks
- **Use Case**: All external services down

## 🔄 Circuit Breaker States

### CLOSED (Normal)
- Service is healthy
- All requests allowed
- Monitoring for failures

### OPEN (Failed)
- Service has failed multiple times
- All requests blocked
- Using fallback mechanisms

### HALF_OPEN (Testing)
- Testing service recovery
- Limited requests allowed
- Ready to close or re-open

## 🚨 Troubleshooting

### Workflow Failures
1. Check health check logs: `/tmp/health-check.log`
2. Review circuit breaker status: `/tmp/circuit-breaker.log`
3. Examine test execution: `/tmp/test-execution.log`

### Service Issues
```bash
# Check specific service
scripts/circuit-breaker.sh mysql_service

# Reset circuit breaker
rm -rf /tmp/circuit-breaker-cache

# Setup fallbacks
scripts/setup-local-fallbacks.sh
```

### Common Issues

#### JavaScript Syntax Errors in GitHub Actions
**Symptoms**: `SyntaxError: Unexpected token ';'` in `actions/github-script@v7`
**Root Cause**: Unsafe template literal interpolation patterns
**Solutions**:
```yaml
# ❌ UNSAFE - Direct interpolation
script: |
  const prNumber = ${{ steps.pr-info.outputs.pr-number }};

# ✅ SAFE - Environment variables
env:
  PR_NUMBER: ${{ steps.pr-info.outputs.pr-number }}
script: |
  const prNumber = parseInt(process.env.PR_NUMBER);
```
**Prevention**: Run `scripts/validate-javascript-syntax.sh` before committing

#### MySQL Connection Failed
- **Cause**: MySQL service not ready
- **Solution**: Workflow will auto-fallback to minimal mode
- **Manual Fix**: Check MySQL service configuration

#### WordPress SVN Timeout
- **Cause**: WordPress.org connectivity issues  
- **Solution**: Circuit breaker will use local cache
- **Manual Fix**: Run `scripts/setup-local-fallbacks.sh`

#### Claude API Unavailable
- **Cause**: Anthropic API issues
- **Solution**: Uses fallback approval templates
- **Manual Fix**: Manual PR review required

## 📊 Monitoring

### Key Files to Watch
- `/tmp/health-check.log` - Service availability
- `/tmp/circuit-breaker.log` - Circuit breaker events
- `/tmp/test-execution.log` - Test run details
- `/tmp/circuit-breaker-cache/` - Circuit states

### Success Indicators
- ✅ Health check completes in <30 seconds
- ✅ Circuit breakers remain CLOSED
- ✅ Tests complete in <20 minutes
- ✅ Fallback usage <10% of runs

### Warning Signs
- ⚠️ Multiple circuit breakers OPEN
- ⚠️ Frequent fallback mode usage
- ⚠️ Test execution time >20 minutes
- ⚠️ Repeated service failures

## 🔧 Manual Overrides

### Force Test Mode
```yaml
# In workflow dispatch
test_mode: basic  # Override auto-detection
```

### Force Version Bump
```yaml  
# In workflow dispatch
version_type: minor  # Override auto-detection
```

### Reset Circuit Breakers
```bash
# Clear all circuit breaker state
rm -rf /tmp/circuit-breaker-cache
```

## 📈 Performance Expectations

### Before (Complex Workflows)
- Test execution: 30-45 minutes
- Failure rate: ~50% (860 failures)
- Manual intervention: High
- Recovery time: Hours

### After (Simplified Workflows)
- Test execution: 15-20 minutes
- Failure rate: <5% (target)
- Manual intervention: Minimal
- Recovery time: Automatic

## 🎯 Best Practices

### For Developers
1. **Monitor workflow runs** for new failure patterns
2. **Use manual dispatch** for testing specific scenarios
3. **Check logs** when workflows behave unexpectedly
4. **Report persistent issues** to the team

### For Maintainers
1. **Review circuit breaker logs** weekly
2. **Update fallback mechanisms** as needed
3. **Monitor success rates** and adjust thresholds
4. **Document new failure patterns** for future fixes

## 🆘 Emergency Procedures

### Complete Workflow Failure
1. Check all service health: `scripts/circuit-breaker.sh all`
2. Reset all circuit breakers: `rm -rf /tmp/circuit-breaker-cache`
3. Setup fresh fallbacks: `scripts/setup-local-fallbacks.sh`
4. Run minimal tests: `scripts/run-tests.sh minimal 8.1 latest true`

### Rollback to Previous Version
```bash
git checkout HEAD~1 -- .github/workflows/
git push origin main
```

---

**Quick Help**: For immediate assistance, check logs in `/tmp/` directory or run scripts with debug mode enabled.
