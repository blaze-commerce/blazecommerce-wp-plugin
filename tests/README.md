# BlazeCommerce WordPress Plugin Tests

This directory contains comprehensive test suites for the BlazeCommerce WordPress Plugin, ensuring reliability and preventing regressions.

## 🚨 MANDATORY Testing Requirements

**All PRs MUST include test files.** No exceptions. This is now a hard requirement for all code changes.

## Test Structure

```
tests/
├── unit/                    # Unit tests for individual functions/classes
├── integration/             # Integration tests for WordPress/WooCommerce
├── cli/                     # CLI command tests
├── fixtures/                # Test data and mock objects
├── helpers/                 # Test helper functions
├── coverage/                # Generated coverage reports
├── bootstrap.php            # Test environment setup
└── README.md               # This file
```

## Test Categories

### 1. Unit Tests (`/tests/unit/`)
- Test individual functions and methods in isolation
- Mock external dependencies
- Fast execution
- High coverage requirements (80%+)

**Example**: `test-base-collection-sync-collection.php`

### 2. Integration Tests (`/tests/integration/`)
- Test WordPress/WooCommerce integration points
- Test complete workflows and user scenarios
- Database operations and API interactions
- Blue-green deployment scenarios

**Example**: `test-integration-blue-green-with-variations.php`

### 3. CLI Tests (`/tests/cli/`)
- Test all WP-CLI commands and flags
- Validate command output and side effects
- Error handling and edge cases
- Performance benchmarks

**Example**: `test-cli-product-sync-all-with-variations.php`

## Running Tests

### Prerequisites

1. **Install Dependencies**:
   ```bash
   composer install
   ```

2. **Setup WordPress Test Environment**:
   ```bash
   bash bin/install-wp-tests.sh wordpress_test root root localhost latest
   ```

3. **Install WooCommerce** (for integration tests):
   ```bash
   cd /tmp/wordpress/wp-content/plugins
   wget https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip
   unzip woocommerce.latest-stable.zip
   ```

### Running Test Suites

**All Tests**:
```bash
vendor/bin/phpunit
```

**Unit Tests Only**:
```bash
vendor/bin/phpunit --testsuite="BlazeCommerce Unit Tests"
```

**Integration Tests Only**:
```bash
vendor/bin/phpunit --testsuite="BlazeCommerce Integration Tests"
```

**CLI Tests Only**:
```bash
vendor/bin/phpunit --testsuite="BlazeCommerce CLI Tests"
```

**With Coverage Report**:
```bash
vendor/bin/phpunit --coverage-html tests/coverage/html
```

**Specific Test File**:
```bash
vendor/bin/phpunit tests/unit/test-base-collection-sync-collection.php
```

## Test Coverage Requirements

- **Minimum Coverage**: 80% for all new code
- **Unit Tests**: Must cover all public methods
- **Integration Tests**: Must cover all user-facing workflows
- **CLI Tests**: Must cover all command flags and combinations
- **Error Handling**: Must test all exception scenarios

## Writing New Tests

### 1. Test File Naming Convention

- Unit tests: `test-{class-name}.php`
- Integration tests: `test-integration-{feature}.php`
- CLI tests: `test-cli-{command}.php`

### 2. Test Class Structure

```php
<?php
/**
 * Test: {Feature Name}
 * 
 * Purpose: {What this test validates}
 * Scope: {Unit/Integration/CLI}
 * Dependencies: {Required plugins/data}
 * 
 * @package BlazeWooless
 * @subpackage Tests
 */

class Test_{FeatureName} extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Test setup
    }
    
    /**
     * Test: {Specific functionality}
     * 
     * @covers {ClassName::methodName}
     */
    public function test_{specific_functionality}() {
        // Arrange
        $expected = 'expected_value';
        
        // Act
        $result = $this->call_method_under_test();
        
        // Assert
        $this->assertEquals($expected, $result);
    }
    
    public function tearDown(): void {
        // Test cleanup
        parent::tearDown();
    }
}
```

### 3. Using Test Helpers

```php
// Create test products
$variable_product = BlazeCommerce_Test_Helper::create_variable_product();
$simple_product = BlazeCommerce_Test_Helper::create_simple_product();

// Mock Typesense client
$mock_client = BlazeCommerce_Test_Helper::mock_typesense_client();

// Enable/disable aliases
BlazeCommerce_Test_Helper::enable_aliases();
BlazeCommerce_Test_Helper::disable_aliases();

// Clean up test data
BlazeCommerce_Test_Helper::cleanup_test_data();
```

### 4. Using Test Fixtures

```php
// Get sample product data
$product_data = BlazeCommerce_Product_Fixtures::get_variable_product_data();
$expected_data = BlazeCommerce_Product_Fixtures::get_expected_variable_product_typesense_data();

// Get collection scenarios
$scenarios = BlazeCommerce_Collection_Fixtures::get_blue_green_scenarios();
```

## Continuous Integration

Tests run automatically on:
- **Pull Requests**: All test suites must pass
- **Push to main/develop**: Full test suite with coverage report
- **Multiple PHP versions**: 7.4, 8.0, 8.1, 8.2
- **Multiple WordPress versions**: Latest, 6.3, 6.2

### GitHub Actions Workflow

The `.github/workflows/tests.yml` file defines:
- Automated test execution
- Coverage reporting
- Code quality checks
- Multi-version compatibility testing

## Test Data Management

### Fixtures
- **Product Fixtures**: Sample products with various configurations
- **Collection Fixtures**: Mock collection and alias data
- **Error Scenarios**: Edge cases and error conditions

### Cleanup
- Tests automatically clean up created data
- Use `BlazeCommerce_Test_Helper::cleanup_test_data()` in tearDown
- Database is reset between test runs

## Debugging Tests

### Enable Debug Output
```bash
vendor/bin/phpunit --debug
```

### View Coverage Report
```bash
vendor/bin/phpunit --coverage-html tests/coverage/html
open tests/coverage/html/index.html
```

### Test Specific Scenarios
```php
// Enable WordPress debug mode in tests
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Use error_log for debugging
error_log('Debug info: ' . print_r($data, true));
```

## Performance Testing

### Benchmarking
- CLI commands should complete within reasonable time limits
- Memory usage should be monitored for large datasets
- Database queries should be optimized

### Load Testing
- Test with large numbers of products
- Test concurrent sync operations
- Validate memory usage patterns

## Security Testing

### Input Validation
- Test with malicious input data
- Validate sanitization functions
- Test SQL injection prevention

### Access Control
- Test user permission checks
- Validate API authentication
- Test privilege escalation prevention

## Contributing Test Cases

When adding new functionality:

1. **Write tests first** (TDD approach recommended)
2. **Cover all code paths** including error conditions
3. **Add integration tests** for user-facing features
4. **Update fixtures** if new data structures are introduced
5. **Document test purpose** in file headers
6. **Ensure tests are deterministic** and don't depend on external services

## Test Environment Variables

```bash
# WordPress Test Configuration
export WP_TESTS_DIR=/tmp/wordpress-tests-lib
export WP_CORE_DIR=/tmp/wordpress/

# Database Configuration
export DB_NAME=wordpress_test
export DB_USER=root
export DB_PASSWORD=root
export DB_HOST=localhost

# BlazeCommerce Test Configuration
export BLAZE_COMMERCE_TESTING=true
export TYPESENSE_TESTING_MODE=true
```

## Troubleshooting

### Common Issues

1. **Tests fail with database errors**:
   ```bash
   # Recreate test database
   bash bin/install-wp-tests.sh wordpress_test root root localhost latest
   ```

2. **WooCommerce not found**:
   ```bash
   # Install WooCommerce in test environment
   cd /tmp/wordpress/wp-content/plugins
   wget https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip
   unzip woocommerce.latest-stable.zip
   ```

3. **Coverage reports not generating**:
   ```bash
   # Ensure Xdebug is installed
   php -m | grep xdebug
   ```

4. **Memory limit errors**:
   ```bash
   # Increase PHP memory limit
   php -d memory_limit=512M vendor/bin/phpunit
   ```

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/)
- [WooCommerce Testing Guide](https://github.com/woocommerce/woocommerce/wiki/How-to-set-up-WooCommerce-development-environment)
- [BlazeCommerce Development Guidelines](../.augment-guidelines)
