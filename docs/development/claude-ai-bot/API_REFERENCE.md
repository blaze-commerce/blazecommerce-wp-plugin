# 📚 BlazeCommerce Claude AI Review Bot - API Reference

This document provides detailed API reference for all JavaScript modules in the Claude AI Review Bot system.

## 🔧 Core Modules

### VerificationEngine

**File**: `scripts/verification-engine.js`

#### Constructor

```javascript
new VerificationEngine(options)
```

**Parameters:**
- `options.githubToken` (string, required) - GitHub API token
- `options.owner` (string, required) - Repository owner
- `options.repo` (string, required) - Repository name  
- `options.prNumber` (string|number, required) - Pull request number

**Throws:**
- `Error` - If any required parameter is missing

**Example:**
```javascript
const engine = new VerificationEngine({
  githubToken: process.env.BOT_GITHUB_TOKEN,
  owner: 'blaze-commerce',
  repo: 'blazecommerce-wp-plugin',
  prNumber: 323
});
```

#### Methods

##### `runVerification()`

Executes the complete verification process with retry logic.

**Returns:** `Promise<Object>`
- `success` (boolean) - Whether verification completed successfully
- `results` (Object) - Verification results if successful
- `error` (string) - Error message if failed

**Example:**
```javascript
const result = await engine.runVerification();
if (result.success) {
  console.log('Verification completed:', result.results);
} else {
  console.error('Verification failed:', result.error);
}
```

##### `checkRateLimit()`

Checks GitHub API rate limit and waits if necessary.

**Returns:** `Promise<void>`

**Example:**
```javascript
await engine.checkRateLimit();
// Safe to make GitHub API calls
```

##### `getPRData()`

Retrieves pull request data from GitHub API.

**Returns:** `Promise<Object>` - GitHub PR data object

##### `getChangedFiles()`

Gets list of changed files in the PR with pagination.

**Returns:** `Promise<Array>` - Array of file objects with metadata

##### `getClaudeReviews()`

Retrieves Claude AI review comments from the PR.

**Returns:** `Promise<Array>` - Array of comment objects

---

### RecommendationTracker

**File**: `scripts/recommendation-tracker.js`

#### Constructor

```javascript
new RecommendationTracker(options)
```

**Parameters:**
- `options.prNumber` (string|number) - Pull request number
- `options.trackingFile` (string, optional) - Path to tracking file
- `options.stateFile` (string, optional) - Path to state file

**Example:**
```javascript
const tracker = new RecommendationTracker({
  prNumber: 323,
  trackingFile: 'CLAUDE_REVIEW_TRACKING.md'
});
```

#### Methods

##### `initializeTracking(prData, recommendations)`

Initializes tracking for a new PR.

**Parameters:**
- `prData` (Object) - GitHub PR data
- `recommendations` (Array) - Array of recommendation objects

**Returns:** `Promise<Object>` - Initial tracking data

##### `updateRecommendationStatus(recommendationId, newStatus, confidence, evidence)`

Updates the status of a specific recommendation.

**Parameters:**
- `recommendationId` (string) - Unique recommendation ID
- `newStatus` (string) - New status ('pending', 'partial', 'addressed', 'verified')
- `confidence` (number) - Confidence score (0-1)
- `evidence` (Array, optional) - Evidence of implementation

**Returns:** `Promise<Object>` - Updated tracking data

##### `getTrackingStatus()`

Gets current tracking status summary.

**Returns:** `Promise<Object|null>` - Status summary or null if no data

##### `isAutoApprovalReady(trackingData)`

Checks if auto-approval criteria are met.

**Parameters:**
- `trackingData` (Object) - Current tracking data

**Returns:** `Object`
- `ready` (boolean) - Whether auto-approval is ready
- `requiredAddressed` (boolean) - All required items addressed
- `importantAddressed` (boolean) - All important items addressed
- `pendingRequired` (number) - Count of pending required items
- `pendingImportant` (number) - Count of pending important items

---

### ErrorHandler

**File**: `scripts/error-handling-utils.js`

#### Constructor

```javascript
new ErrorHandler(options)
```

**Parameters:**
- `options.maxRetries` (number, optional) - Maximum retry attempts (default: 3)
- `options.baseDelay` (number, optional) - Base delay in ms (default: 1000)
- `options.maxDelay` (number, optional) - Maximum delay in ms (default: 30000)
- `options.circuitBreakerThreshold` (number, optional) - Failures before circuit breaker opens (default: 5)
- `options.circuitBreakerTimeout` (number, optional) - Circuit breaker timeout in ms (default: 300000)

#### Methods

##### `executeWithRetry(operation, operationName, options)`

Executes an operation with retry logic and error handling.

**Parameters:**
- `operation` (Function) - Async function to execute
- `operationName` (string) - Name for logging and circuit breaker
- `options.maxRetries` (number, optional) - Override default max retries
- `options.timeout` (number, optional) - Operation timeout in ms

**Returns:** `Promise<any>` - Result of the operation

**Example:**
```javascript
const result = await errorHandler.executeWithRetry(
  async () => {
    // Your operation here
    return await someApiCall();
  },
  'api-operation',
  { timeout: 30000 }
);
```

##### `classifyError(error)`

Classifies an error for appropriate handling.

**Parameters:**
- `error` (Error) - Error object to classify

**Returns:** `string` - Error type classification

##### `calculateDelay(attempt)`

Calculates delay for exponential backoff with jitter.

**Parameters:**
- `attempt` (number) - Current attempt number

**Returns:** `number` - Delay in milliseconds

##### `checkServiceHealth()`

Checks health of external services.

**Returns:** `Promise<Object>` - Health status object

---

## 🔧 Configuration

### claude-bot-config.js

**File**: `scripts/claude-bot-config.js`

#### Configuration Sections

##### `API`
- `ANTHROPIC_TIMEOUT` (number) - Claude API timeout in ms
- `GITHUB_TIMEOUT` (number) - GitHub API timeout in ms
- `MAX_RETRIES` (number) - Maximum retry attempts
- `BASE_DELAY` (number) - Base delay for retries in ms
- `RATE_LIMIT_THRESHOLD` (number) - Minimum remaining requests before waiting

##### `VERIFICATION`
- `CONFIDENCE_THRESHOLD` (number) - Minimum confidence for "addressed" status
- `PARTIAL_CONFIDENCE_THRESHOLD` (number) - Minimum confidence for "partial" status
- `MAX_FILE_BATCH_SIZE` (number) - Files per batch for pagination
- `RELEVANCE_THRESHOLD` (number) - Minimum relevance score for file matching

##### `ERROR_HANDLING`
- `CIRCUIT_BREAKER_THRESHOLD` (number) - Failures before circuit breaker opens
- `CIRCUIT_BREAKER_TIMEOUT` (number) - Circuit breaker timeout in ms
- `MAX_RETRY_ATTEMPTS` (number) - Maximum retry attempts
- `EXPONENTIAL_BACKOFF_BASE` (number) - Base delay for exponential backoff

##### `TIMEOUTS`
- `INITIAL_REVIEW` (number) - Initial review timeout in ms
- `VERIFICATION` (number) - Verification timeout in ms
- `AUTO_APPROVAL` (number) - Auto-approval timeout in ms

##### `PATHS`
- `TRACKING_FILE` (string) - Path to tracking file
- `STATE_FILE` (string) - Path to state file
- `ERROR_LOG` (string) - Path to error log file
- `GITHUB_DIR` (string) - GitHub directory path

##### `CATEGORIES`
Object mapping recommendation categories to metadata:
- `REQUIRED` - Critical issues that must be fixed
- `IMPORTANT` - Significant improvements recommended
- `SUGGESTION` - Optional enhancements

##### `STATUSES`
Object mapping recommendation statuses to metadata:
- `PENDING` - Not yet addressed
- `PARTIAL` - Partially implemented
- `ADDRESSED` - Fully addressed
- `VERIFIED` - Verified and confirmed

## 🧪 Testing

### ClaudeBotTestSuite

**File**: `scripts/test-claude-bot.js`

#### Methods

##### `runAllTests()`

Runs the complete test suite.

**Returns:** `Promise<void>`

**Example:**
```javascript
const testSuite = new ClaudeBotTestSuite();
await testSuite.runAllTests();
```

##### `runTest(testName, testFunction)`

Runs a single test with error handling.

**Parameters:**
- `testName` (string) - Name of the test
- `testFunction` (Function) - Test function to execute

**Returns:** `Promise<void>`

## 🔄 Workflow Integration

### GitHub Actions Usage

The Claude AI bot integrates with GitHub Actions through the workflow file:

```yaml
- name: Run Verification
  run: |
    node scripts/verification-engine.js
  env:
    BOT_GITHUB_TOKEN: ${{ secrets.BOT_GITHUB_TOKEN }}
    ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY }}
    PR_NUMBER: ${{ github.event.number }}
```

### Environment Variables

Required environment variables:
- `BOT_GITHUB_TOKEN` - GitHub API token for bot operations
- `ANTHROPIC_API_KEY` - Claude AI API key
- `GITHUB_REPOSITORY_OWNER` - Repository owner (auto-set by GitHub)
- `GITHUB_REPOSITORY_NAME` - Repository name (auto-set by GitHub)
- `PR_NUMBER` - Pull request number (auto-set by GitHub)

## 🚨 Error Handling

### Error Types

The system classifies errors into categories:
- `anthropic_api` - Claude AI service errors
- `github_api` - GitHub API errors
- `network` - Network connectivity issues
- `timeout` - Operation timeout errors
- `validation` - Input validation errors
- `unknown` - Unclassified errors

### Retry Logic

Operations are retried with exponential backoff:
1. First retry: 1 second delay
2. Second retry: 2 second delay  
3. Third retry: 4 second delay

### Circuit Breaker

After 5 consecutive failures, the circuit breaker opens for 5 minutes to prevent cascading failures.

## 📊 Monitoring

### Health Checks

Use the health check endpoint:
```javascript
const health = await errorHandler.checkServiceHealth();
console.log('Service health:', health);
```

### Error Logging

Errors are logged to `.github/claude-bot-errors.log` with structured data:
```json
{
  "timestamp": "2025-07-12T10:00:00.000Z",
  "operation": "verification-process",
  "error": "Rate limit exceeded",
  "type": "github_api",
  "errorCount": 3
}
```
