/**
 * BlazeCommerce Claude AI Review Bot - Error Handling Utilities
 * 
 * Comprehensive error handling with retry mechanisms, circuit breaker,
 * graceful degradation, and user notifications.
 */

const fs = require('fs');
const EventEmitter = require('events');

class ErrorHandler extends EventEmitter {
  constructor(options = {}) {
    super();

    this.maxRetries = options.maxRetries || 3;
    this.baseDelay = options.baseDelay || 1000; // 1 second
    this.maxDelay = options.maxDelay || 30000; // 30 seconds
    this.circuitBreakerThreshold = options.circuitBreakerThreshold || 5;
    this.circuitBreakerTimeout = options.circuitBreakerTimeout || 300000; // 5 minutes

    this.errorCounts = new Map();
    this.circuitBreakers = new Map();
    
    this.errorTypes = {
      ANTHROPIC_API_ERROR: 'anthropic_api',
      GITHUB_API_ERROR: 'github_api',
      NETWORK_ERROR: 'network',
      TIMEOUT_ERROR: 'timeout',
      VALIDATION_ERROR: 'validation',
      UNKNOWN_ERROR: 'unknown'
    };
  }

  /**
   * Execute function with retry logic and error handling
   */
  async executeWithRetry(operation, operationName, options = {}) {
    const maxRetries = options.maxRetries || this.maxRetries;
    const timeout = options.timeout || 60000; // 1 minute default
    
    // Check circuit breaker
    if (this.isCircuitBreakerOpen(operationName)) {
      throw new Error(`Circuit breaker is open for ${operationName}. Service temporarily unavailable.`);
    }
    
    let lastError;
    
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        console.log(`🔄 Executing ${operationName} (attempt ${attempt}/${maxRetries})`);
        
        // Execute with timeout
        const result = await this.executeWithTimeout(operation, timeout);
        
        // Reset error count on success
        this.resetErrorCount(operationName);
        
        console.log(`✅ ${operationName} completed successfully`);
        return result;
        
      } catch (error) {
        lastError = error;
        const errorType = this.classifyError(error);
        
        console.log(`❌ ${operationName} attempt ${attempt} failed:`, error.message);
        
        // Increment error count
        this.incrementErrorCount(operationName);
        
        // Check if we should retry
        if (attempt === maxRetries || !this.shouldRetry(error, errorType)) {
          break;
        }
        
        // Calculate delay with exponential backoff and jitter
        const delay = this.calculateDelay(attempt);
        console.log(`⏳ Waiting ${delay}ms before retry...`);
        await this.sleep(delay);
      }
    }
    
    // All retries failed
    this.handleFinalFailure(operationName, lastError);
    throw lastError;
  }

  /**
   * Execute operation with timeout
   */
  async executeWithTimeout(operation, timeout) {
    return new Promise(async (resolve, reject) => {
      const timeoutId = setTimeout(() => {
        reject(new Error(`Operation timed out after ${timeout}ms`));
      }, timeout);
      
      try {
        const result = await operation();
        clearTimeout(timeoutId);
        resolve(result);
      } catch (error) {
        clearTimeout(timeoutId);
        reject(error);
      }
    });
  }

  /**
   * Classify error type for appropriate handling
   */
  classifyError(error) {
    const message = error.message.toLowerCase();
    
    if (message.includes('anthropic') || message.includes('claude')) {
      return this.errorTypes.ANTHROPIC_API_ERROR;
    }
    
    if (message.includes('github') || message.includes('octokit')) {
      return this.errorTypes.GITHUB_API_ERROR;
    }
    
    if (message.includes('timeout') || message.includes('timed out')) {
      return this.errorTypes.TIMEOUT_ERROR;
    }
    
    if (message.includes('network') || message.includes('econnreset') || message.includes('enotfound')) {
      return this.errorTypes.NETWORK_ERROR;
    }
    
    if (message.includes('validation') || message.includes('invalid')) {
      return this.errorTypes.VALIDATION_ERROR;
    }
    
    return this.errorTypes.UNKNOWN_ERROR;
  }

  /**
   * Determine if error should trigger a retry
   */
  shouldRetry(error, errorType) {
    // Enhanced non-retryable error detection

    // Don't retry validation errors or authentication errors
    if (errorType === this.errorTypes.VALIDATION_ERROR) {
      return false;
    }

    // Non-retryable billing/payment errors
    if (error.message && (
        error.message.includes('402') ||
        error.message.includes('billing') ||
        error.message.includes('payment') ||
        error.message.includes('quota exceeded') ||
        error.message.includes('subscription')
    )) {
      return false;
    }

    // Authentication/authorization errors
    if (error.message.includes('401') || error.message.includes('403')) {
      return false;
    }

    // Not found errors
    if (error.message.includes('404')) {
      return false;
    }

    // Bad request errors (client-side issues)
    if (error.message.includes('400') || error.message.includes('422')) {
      return false;
    }

    // Retry for temporary issues
    return [
      this.errorTypes.ANTHROPIC_API_ERROR,
      this.errorTypes.GITHUB_API_ERROR,
      this.errorTypes.NETWORK_ERROR,
      this.errorTypes.TIMEOUT_ERROR,
      this.errorTypes.UNKNOWN_ERROR
    ].includes(errorType);
  }

  /**
   * Calculate delay with exponential backoff and jitter
   */
  calculateDelay(attempt) {
    // Exponential backoff: 1s, 2s, 4s, 8s...
    const exponentialDelay = this.baseDelay * Math.pow(2, attempt - 1);
    
    // Add jitter (±25%)
    const jitter = exponentialDelay * 0.25 * (Math.random() * 2 - 1);
    
    // Apply limits
    const delay = Math.min(this.maxDelay, exponentialDelay + jitter);
    
    return Math.max(this.baseDelay, delay);
  }

  /**
   * Sleep for specified milliseconds
   */
  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Increment error count for circuit breaker
   */
  incrementErrorCount(operationName) {
    const count = this.errorCounts.get(operationName) || 0;
    this.errorCounts.set(operationName, count + 1);
    
    // Check if circuit breaker should open
    if (count + 1 >= this.circuitBreakerThreshold) {
      this.openCircuitBreaker(operationName);
    }
  }

  /**
   * Reset error count on success
   */
  resetErrorCount(operationName) {
    this.errorCounts.set(operationName, 0);
    this.closeCircuitBreaker(operationName);
  }

  /**
   * Open circuit breaker
   */
  openCircuitBreaker(operationName) {
    const circuitBreakerData = {
      operation: operationName,
      openedAt: Date.now(),
      timestamp: new Date().toISOString()
    };

    console.log(`🔴 Circuit breaker opened for ${operationName}`);
    this.circuitBreakers.set(operationName, {
      isOpen: true,
      openedAt: Date.now()
    });

    // Emit circuit breaker event for monitoring
    this.emit('circuit-breaker-opened', circuitBreakerData);
  }

  /**
   * Close circuit breaker
   */
  closeCircuitBreaker(operationName) {
    if (this.circuitBreakers.has(operationName)) {
      console.log(`🟢 Circuit breaker closed for ${operationName}`);
      this.circuitBreakers.delete(operationName);
    }
  }

  /**
   * Check if circuit breaker is open
   */
  isCircuitBreakerOpen(operationName) {
    const breaker = this.circuitBreakers.get(operationName);
    
    if (!breaker || !breaker.isOpen) {
      return false;
    }
    
    // Check if timeout has passed
    if (Date.now() - breaker.openedAt > this.circuitBreakerTimeout) {
      this.closeCircuitBreaker(operationName);
      return false;
    }
    
    return true;
  }

  /**
   * Handle final failure after all retries
   */
  handleFinalFailure(operationName, error) {
    const errorType = this.classifyError(error);
    const errorCount = this.errorCounts.get(operationName) || 0;

    const errorData = {
      operation: operationName,
      error: error.message,
      type: errorType,
      errorCount,
      timestamp: new Date().toISOString(),
      stack: error.stack
    };

    console.error(`💥 Final failure for ${operationName}:`, errorData);

    // Emit error event for monitoring
    this.emit('error', errorData);
    this.emit('final-failure', errorData);

    // Log to file for debugging
    this.logError(operationName, error, errorType);
  }

  /**
   * Log error to file for debugging (async)
   */
  async logError(operationName, error, errorType) {
    try {
      const logEntry = {
        timestamp: new Date().toISOString(),
        operation: operationName,
        error: error.message,
        stack: error.stack,
        type: errorType,
        errorCount: this.errorCounts.get(operationName) || 0
      };

      const logFile = '.github/claude-bot-errors.log';
      const logLine = JSON.stringify(logEntry) + '\n';

      await fs.promises.appendFile(logFile, logLine);
    } catch (logError) {
      console.error('Failed to log error:', logError.message);
    }
  }

  /**
   * Generate user-friendly error message
   */
  generateUserErrorMessage(operationName, error, errorType) {
    const timestamp = new Date().toISOString();
    
    switch (errorType) {
      case this.errorTypes.ANTHROPIC_API_ERROR:
        return `## ⚠️ Claude AI Service Temporarily Unavailable

**Operation**: ${operationName}
**Timestamp**: ${timestamp}

The Claude AI service is currently experiencing issues. This is typically due to:
- High API load (Error 529)
- Temporary service maintenance
- Rate limiting

**What this means**:
- Your code changes are not the problem
- This is a temporary service issue
- The operation will be retried automatically

**Next steps**:
- The bot will retry automatically on your next commit
- Check [Anthropic Status](https://status.anthropic.com/) for service updates
- Consider manual review if urgent

*Estimated resolution time: 1-60 minutes*`;

      case this.errorTypes.GITHUB_API_ERROR:
        return `## ⚠️ GitHub API Service Issue

**Operation**: ${operationName}
**Timestamp**: ${timestamp}

There was an issue communicating with the GitHub API. This could be due to:
- GitHub API rate limiting
- Temporary GitHub service issues
- Network connectivity problems

**Next steps**:
- The operation will be retried automatically
- Check [GitHub Status](https://www.githubstatus.com/) for service updates
- Verify repository permissions if the issue persists

*The bot will retry automatically on your next commit.*`;

      case this.errorTypes.TIMEOUT_ERROR:
        return `## ⏰ Operation Timeout

**Operation**: ${operationName}
**Timestamp**: ${timestamp}

The operation exceeded the maximum allowed time limit. This could be due to:
- High service load
- Large file analysis
- Network latency issues

**Next steps**:
- The operation will be retried with optimized parameters
- Consider breaking large changes into smaller commits
- The bot will retry automatically on your next commit

*Timeout limits are in place to ensure responsive service.*`;

      case this.errorTypes.NETWORK_ERROR:
        return `## 🌐 Network Connectivity Issue

**Operation**: ${operationName}
**Timestamp**: ${timestamp}

There was a network connectivity issue preventing the operation from completing.

**Next steps**:
- The operation will be retried automatically
- This is typically a temporary issue
- The bot will retry on your next commit

*Network issues usually resolve within a few minutes.*`;

      default:
        return `## ❌ Unexpected Error

**Operation**: ${operationName}
**Timestamp**: ${timestamp}
**Error**: ${error.message}

An unexpected error occurred during the operation.

**Next steps**:
- The operation will be retried automatically
- If this error persists, please check the workflow logs
- Consider opening an issue if the problem continues

*The bot will retry automatically on your next commit.*`;
    }
  }

  /**
   * Check service health
   */
  async checkServiceHealth() {
    const health = {
      timestamp: new Date().toISOString(),
      services: {},
      overall: 'healthy'
    };
    
    // Check GitHub API
    try {
      const response = await fetch('https://api.github.com/rate_limit');
      health.services.github = response.ok ? 'healthy' : 'degraded';
    } catch (error) {
      health.services.github = 'unhealthy';
      health.overall = 'degraded';
    }
    
    // Check Anthropic API (basic connectivity)
    try {
      const response = await fetch('https://api.anthropic.com');
      health.services.anthropic = response.ok ? 'healthy' : 'degraded';
    } catch (error) {
      health.services.anthropic = 'unhealthy';
      health.overall = 'degraded';
    }
    
    // Check circuit breakers
    health.circuitBreakers = {};
    for (const [operation, breaker] of this.circuitBreakers.entries()) {
      health.circuitBreakers[operation] = {
        isOpen: breaker.isOpen,
        openedAt: breaker.openedAt
      };
      
      if (breaker.isOpen) {
        health.overall = 'degraded';
      }
    }
    
    return health;
  }

  /**
   * Graceful degradation - continue with limited functionality
   */
  async gracefulDegradation(operationName, fallbackOperation) {
    console.log(`🔄 Attempting graceful degradation for ${operationName}`);
    
    try {
      const result = await fallbackOperation();
      console.log(`✅ Graceful degradation successful for ${operationName}`);
      return result;
    } catch (error) {
      console.log(`❌ Graceful degradation failed for ${operationName}:`, error.message);
      throw error;
    }
  }
}

// Export for use in workflows
module.exports = ErrorHandler;

// CLI usage for health checks
if (require.main === module) {
  const errorHandler = new ErrorHandler();
  
  errorHandler.checkServiceHealth()
    .then(health => {
      console.log('🏥 Service Health Check:');
      console.log(JSON.stringify(health, null, 2));
      
      if (health.overall !== 'healthy') {
        process.exit(1);
      }
    })
    .catch(error => {
      console.error('Health check failed:', error.message);
      process.exit(1);
    });
}
