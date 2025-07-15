# 🛠️ BlazeCommerce Claude AI Review Bot - Troubleshooting Guide

This guide helps resolve common issues with the Claude AI Review Bot system.

## 🧪 Testing and Validation

### Run Comprehensive Test Suite
```bash
# Run all tests
node scripts/test-claude-bot.js

# Test specific components
node -e "const VE = require('./scripts/verification-engine'); try { new VE(); } catch(e) { console.log('✅ Validation working'); }"

# Test error handling with monitoring
node -e "const EH = require('./scripts/error-handling-utils'); const eh = new EH(); eh.on('error', (data) => console.log('Error event:', data)); eh.checkServiceHealth();"

# Check environment-specific configuration
NODE_ENV=development node -e "const config = require('./scripts/claude-bot-config'); console.log('Config loaded:', config.LOGGING.LEVEL);"
NODE_ENV=production node -e "const config = require('./scripts/claude-bot-config'); console.log('Config loaded:', config.LOGGING.LEVEL);"
```

### Performance Testing
```bash
# Test file size limits
node -e "const config = require('./scripts/claude-bot-config'); console.log('Max file size:', config.GITHUB.MAX_FILE_SIZE);"

# Test path validation
node -e "const RT = require('./scripts/recommendation-tracker'); try { new RT({trackingFile: '../../sensitive-file'}); } catch(e) { console.log('✅ Path protection working:', e.message); }"
```

## 🚨 Common Issues

### Issue: Bot Not Responding to PRs
**Symptoms**: No review comments appear on new PRs

**Possible Causes & Solutions**:
1. **Missing Secrets**: Check GitHub repository secrets
   ```bash
   # Verify secrets are set (in GitHub repo settings)
   ANTHROPIC_API_KEY
   BOT_GITHUB_TOKEN
   ```

2. **Workflow Not Triggered**: Check workflow file syntax
   ```bash
   # Validate workflow syntax
   yamllint .github/workflows/claude-pr-review.yml
   ```

3. **Organization Validation**: Ensure repository is in `blaze-commerce` organization

### Issue: Rate Limiting Errors
**Symptoms**: "Rate limit exceeded" errors in workflow logs

**Solutions**:
1. **Check Rate Limit Status**:
   ```bash
   curl -H "Authorization: token YOUR_TOKEN" https://api.github.com/rate_limit
   ```

2. **Increase Delays**: Adjust configuration for production
   ```javascript
   // In claude-bot-config.production.js
   API: {
     BASE_DELAY: 3000, // Increase delay
     RATE_LIMIT_THRESHOLD: 20 // Higher threshold
   }
   ```

### Issue: Large File Processing Failures
**Symptoms**: Memory errors or timeouts with large PRs

**Solutions**:
1. **Check File Limits**:
   ```bash
   node -e "const config = require('./scripts/claude-bot-config'); console.log('Limits:', {maxFileSize: config.GITHUB.MAX_FILE_SIZE, maxFiles: config.GITHUB.MAX_TOTAL_FILES});"
   ```

2. **Adjust Limits for Production**:
   ```javascript
   // In claude-bot-config.production.js
   GITHUB: {
     MAX_FILE_SIZE: 1048576, // 1MB
     MAX_TOTAL_FILES: 100
   }
   ```

### Issue: Path Traversal Errors
**Symptoms**: "Path traversal attempt detected" errors

**Solutions**:
1. **Validate File Paths**:
   ```bash
   node -e "const RT = require('./scripts/recommendation-tracker'); console.log('Valid path:', new RT({trackingFile: 'test.md'}).trackingFile);"
   ```

2. **Check for Invalid Characters**:
   - Ensure file paths don't contain `..`
   - Avoid absolute paths outside `.github` directory
   - Check for null bytes in paths

## 🔧 Configuration Issues

### Environment-Specific Configuration
```bash
# Test development config
NODE_ENV=development node -e "const config = require('./scripts/claude-bot-config'); console.log('Dev timeouts:', config.TIMEOUTS);"

# Test production config
NODE_ENV=production node -e "const config = require('./scripts/claude-bot-config'); console.log('Prod timeouts:', config.TIMEOUTS);"
```

### Circuit Breaker Issues
**Symptoms**: "Circuit breaker is open" errors

**Solutions**:
1. **Check Circuit Breaker Status**:
   ```bash
   node -e "const EH = require('./scripts/error-handling-utils'); const eh = new EH(); eh.on('circuit-breaker-opened', (data) => console.log('CB opened:', data));"
   ```

2. **Reset Circuit Breaker**: Wait for timeout or restart workflow

## 📊 Monitoring and Debugging

### Enable Error Event Monitoring
```javascript
const ErrorHandler = require('./scripts/error-handling-utils');
const errorHandler = new ErrorHandler();

// Listen for error events
errorHandler.on('error', (errorData) => {
  console.log('Error occurred:', errorData);
});

errorHandler.on('circuit-breaker-opened', (data) => {
  console.log('Circuit breaker opened:', data);
});

errorHandler.on('final-failure', (data) => {
  console.log('Final failure:', data);
});
```

### Check Error Logs
```bash
# View error log
cat .github/claude-bot-errors.log

# Monitor error log in real-time
tail -f .github/claude-bot-errors.log
```

### Debug Configuration Loading
```bash
# Check which config is loaded
node -e "console.log('NODE_ENV:', process.env.NODE_ENV); const config = require('./scripts/claude-bot-config'); console.log('Loaded config type:', config.LOGGING?.LEVEL || 'base');"
```

## 🔍 Verification Commands

### Test All Components
```bash
# Run comprehensive test suite
npm test || node scripts/test-claude-bot.js

# Test individual components
node -e "const VE = require('./scripts/verification-engine'); console.log('VerificationEngine loaded');"
node -e "const RT = require('./scripts/recommendation-tracker'); console.log('RecommendationTracker loaded');"
node -e "const EH = require('./scripts/error-handling-utils'); console.log('ErrorHandler loaded');"
```

### Validate Security Features
```bash
# Test input validation
node -e "try { new (require('./scripts/verification-engine'))({}); } catch(e) { console.log('✅ Input validation working:', e.message); }"

# Test path traversal protection
node -e "try { new (require('./scripts/recommendation-tracker'))({trackingFile: '../../../etc/passwd'}); } catch(e) { console.log('✅ Path protection working:', e.message); }"
```

## 🆘 Getting Help

### Workflow Logs
1. Go to GitHub Actions tab in repository
2. Click on failed workflow run
3. Expand failed step to see detailed logs
4. Look for error messages and stack traces

### Service Status
- **GitHub API**: https://www.githubstatus.com/
- **Anthropic API**: https://status.anthropic.com/

### Debug Mode
Set environment variables for verbose logging:
```bash
NODE_ENV=development
DEBUG=claude-bot:*
```

### Contact Support
If issues persist:
1. Check workflow logs for specific error messages
2. Verify all secrets are properly configured
3. Test with a small PR first
4. Review the API reference documentation

## 📈 Performance Optimization

### For Large Repositories
```javascript
// Adjust configuration for better performance
module.exports = {
  GITHUB: {
    PER_PAGE: 20,           // Smaller batches
    MAX_FILE_SIZE: 512000,  // 512KB limit
    MAX_TOTAL_FILES: 50     // Fewer files
  },
  TIMEOUTS: {
    INITIAL_REVIEW: 1800000 // 30 minutes
  }
};
```

### Memory Usage Monitoring
```bash
# Check memory usage during operation
node --max-old-space-size=512 scripts/verification-engine.js
```

This troubleshooting guide covers the most common issues and their solutions. For additional help, refer to the API reference and system documentation.
