# CI/CD & DevOps Standards Rule

**Priority: Auto**

**Description:** Establish continuous integration and deployment practices for WordPress plugin development to ensure code quality, automated testing, and reliable deployments.

## GitHub Actions Pipeline Requirements

### 1. Required Workflows

#### Code Quality Workflow
```yaml
name: Code Quality Check
on: [push, pull_request]
jobs:
  php-lint:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['7.4', '8.0', '8.1', '8.2']
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: mbstring, intl, gd, xml, dom, json, fileinfo, curl, zip, iconv
      - name: PHP Lint
        run: find . -name "*.php" -exec php -l {} \;
      
  phpcs:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install PHPCS
        run: |
          composer global require "squizlabs/php_codesniffer=*"
          composer global require "wp-coding-standards/wpcs"
          phpcs --config-set installed_paths ~/.composer/vendor/wp-coding-standards/wpcs
      - name: Run PHPCS
        run: phpcs --standard=WordPress --extensions=php --ignore=*/vendor/*,*/node_modules/* .
```

#### WordPress Compatibility Testing
```yaml
name: WordPress Compatibility
on: [push, pull_request]
jobs:
  wordpress-test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        wordpress-version: ['5.8', '5.9', '6.0', '6.1', '6.2', '6.3', '6.4']
        php-version: ['7.4', '8.0', '8.1']
    services:
      mysql:
        image: mysql:5.7
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: mbstring, intl, gd, xml, dom, json, fileinfo, curl, zip, iconv, mysql
      
      - name: Install WordPress Test Suite
        run: |
          bash bin/install-wp-tests.sh wordpress_test root root 127.0.0.1:3306 ${{ matrix.wordpress-version }}
      
      - name: Install Composer Dependencies
        run: composer install --no-dev --optimize-autoloader
      
      - name: Run PHPUnit Tests
        run: vendor/bin/phpunit
```

### 2. Security and Performance Workflows

#### Security Scanning
```yaml
name: Security Scan
on: [push, pull_request]
jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: PHP Security Checker
        uses: StephaneBour/actions-php-security-checker@1.1
      - name: Scan for secrets
        run: |
          # Scan for potential secrets in PHP files
          if grep -r "password\|api_key\|secret\|token" --include="*.php" --exclude-dir=vendor --exclude-dir=tests .; then
            echo "Potential secrets found in code"
            exit 1
          fi
      - name: WordPress Security Scan
        run: |
          # Check for common WordPress security issues
          if grep -r "eval\|base64_decode\|file_get_contents.*http" --include="*.php" --exclude-dir=vendor .; then
            echo "Potentially dangerous functions found"
            exit 1
          fi
```

#### Plugin Validation
```yaml
name: Plugin Validation
on: [push, pull_request]
jobs:
  validate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install WP-CLI
        run: |
          curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
          chmod +x wp-cli.phar
          sudo mv wp-cli.phar /usr/local/bin/wp
      - name: Validate Plugin Structure
        run: |
          # Check for required plugin files
          if [ ! -f "blazecommerce.php" ]; then
            echo "Main plugin file missing"
            exit 1
          fi
          if [ ! -f "readme.txt" ]; then
            echo "readme.txt missing"
            exit 1
          fi
```

## WordPress Plugin Deployment

### 1. Plugin Packaging
```bash
#!/bin/bash
# scripts/build-plugin.sh

# Plugin build script
PLUGIN_NAME="blazecommerce"
VERSION=$(grep "Version:" ${PLUGIN_NAME}.php | awk '{print $2}')
BUILD_DIR="build"
PACKAGE_DIR="${BUILD_DIR}/${PLUGIN_NAME}"

echo "Building ${PLUGIN_NAME} version ${VERSION}..."

# Clean build directory
rm -rf ${BUILD_DIR}
mkdir -p ${PACKAGE_DIR}

# Copy plugin files
cp -r includes/ ${PACKAGE_DIR}/
cp -r admin/ ${PACKAGE_DIR}/
cp -r public/ ${PACKAGE_DIR}/
cp -r languages/ ${PACKAGE_DIR}/
cp ${PLUGIN_NAME}.php ${PACKAGE_DIR}/
cp readme.txt ${PACKAGE_DIR}/
cp LICENSE ${PACKAGE_DIR}/

# Install production dependencies
cd ${PACKAGE_DIR}
composer install --no-dev --optimize-autoloader
rm composer.json composer.lock

# Remove development files
find . -name "*.md" -not -name "README.md" -delete
find . -name ".git*" -delete
find . -name "phpunit.xml*" -delete
find . -name "tests" -type d -exec rm -rf {} +

# Create zip package
cd ..
zip -r ${PLUGIN_NAME}-${VERSION}.zip ${PLUGIN_NAME}/

echo "Plugin package created: ${BUILD_DIR}/${PLUGIN_NAME}-${VERSION}.zip"
```

### 2. Automated Deployment
```yaml
name: Deploy Plugin
on:
  push:
    tags:
      - 'v*'
jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      
      - name: Build Plugin Package
        run: |
          chmod +x scripts/build-plugin.sh
          ./scripts/build-plugin.sh
      
      - name: Deploy to WordPress.org
        if: startsWith(github.ref, 'refs/tags/')
        run: |
          # WordPress.org SVN deployment script
          # This would contain the actual deployment logic
          echo "Deploying to WordPress.org repository"
      
      - name: Create GitHub Release
        uses: actions/create-release@v1
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        with:
          tag_name: ${{ github.ref }}
          release_name: Release ${{ github.ref }}
          draft: false
          prerelease: false
```

## Development Environment Setup

### 1. Local Development with Docker
```yaml
# docker-compose.yml
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DEBUG: 1
    volumes:
      - ./:/var/www/html/wp-content/plugins/blazecommerce
      - wordpress_data:/var/www/html
    depends_on:
      - db
  
  db:
    image: mysql:5.7
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: rootpassword
    volumes:
      - db_data:/var/lib/mysql
  
  wpcli:
    image: wordpress:cli
    volumes:
      - ./:/var/www/html/wp-content/plugins/blazecommerce
      - wordpress_data:/var/www/html
    depends_on:
      - db
      - wordpress

volumes:
  wordpress_data:
  db_data:
```

### 2. Development Scripts
```bash
#!/bin/bash
# scripts/setup-dev.sh

echo "Setting up BlazeCommerce plugin development environment..."

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "Docker is required but not installed. Please install Docker first."
    exit 1
fi

# Start Docker containers
docker-compose up -d

# Wait for WordPress to be ready
echo "Waiting for WordPress to be ready..."
sleep 30

# Install WooCommerce
docker-compose run --rm wpcli plugin install woocommerce --activate

# Activate our plugin
docker-compose run --rm wpcli plugin activate blazecommerce

# Import sample data
docker-compose run --rm wpcli plugin install wordpress-importer --activate
docker-compose run --rm wpcli import /var/www/html/wp-content/plugins/blazecommerce/sample-data.xml --authors=create

echo "Development environment ready at http://localhost:8080"
echo "Admin: http://localhost:8080/wp-admin (admin/password)"
```

## Testing Infrastructure

### 1. PHPUnit Configuration
```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
    convertErrorsToExceptions="true"
    convertNoticesToExceptions="true"
    convertWarningsToExceptions="true"
    processIsolation="false"
    stopOnFailure="false"
    syntaxCheck="false">
    
    <testsuites>
        <testsuite name="BlazeCommerce Test Suite">
            <directory>./tests/</directory>
        </testsuite>
    </testsuites>
    
    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">./includes/</directory>
            <directory suffix=".php">./admin/</directory>
            <directory suffix=".php">./public/</directory>
            <exclude>
                <directory>./tests/</directory>
                <directory>./vendor/</directory>
            </exclude>
        </whitelist>
    </filter>
    
    <logging>
        <log type="coverage-html" target="./tests/coverage"/>
        <log type="coverage-clover" target="./tests/coverage.xml"/>
    </logging>
</phpunit>
```

### 2. Test Bootstrap
```php
<?php
// tests/bootstrap.php

// WordPress test environment
$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    require dirname(dirname(__FILE__)) . '/blazecommerce.php';
}
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

require $_tests_dir . '/includes/bootstrap.php';

// Load WooCommerce for testing
require_once dirname(dirname(__FILE__)) . '/vendor/woocommerce/woocommerce.php';
```

## Performance Monitoring

### 1. Performance Testing
```php
<?php
// tests/performance/test-performance.php

class BlazeCommerce_Performance_Test extends WP_UnitTestCase {
    
    public function test_product_query_performance() {
        // Create test products
        $products = array();
        for ($i = 0; $i < 100; $i++) {
            $products[] = $this->factory->post->create(array(
                'post_type' => 'product',
                'post_status' => 'publish'
            ));
        }
        
        // Measure query performance
        $start_time = microtime(true);
        $start_memory = memory_get_usage();
        
        $blazecommerce = new BlazeCommerce_Product_Manager();
        $results = $blazecommerce->get_products(array('limit' => 50));
        
        $end_time = microtime(true);
        $end_memory = memory_get_usage();
        
        $execution_time = $end_time - $start_time;
        $memory_usage = $end_memory - $start_memory;
        
        // Assert performance thresholds
        $this->assertLessThan(1.0, $execution_time, 'Query should complete in under 1 second');
        $this->assertLessThan(10 * 1024 * 1024, $memory_usage, 'Memory usage should be under 10MB');
    }
}
```

## Monitoring and Logging

### 1. Error Tracking
```php
<?php
// includes/class-error-tracker.php

class BlazeCommerce_Error_Tracker {
    
    public static function init() {
        add_action('wp_loaded', array(__CLASS__, 'setup_error_handling'));
    }
    
    public static function setup_error_handling() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            set_error_handler(array(__CLASS__, 'handle_error'));
            register_shutdown_function(array(__CLASS__, 'handle_fatal_error'));
        }
    }
    
    public static function handle_error($errno, $errstr, $errfile, $errline) {
        if (strpos($errfile, 'blazecommerce') !== false) {
            error_log(sprintf(
                '[BlazeCommerce Error] %s in %s on line %d',
                $errstr,
                $errfile,
                $errline
            ));
        }
        
        return false; // Let WordPress handle the error
    }
    
    public static function handle_fatal_error() {
        $error = error_get_last();
        if ($error && strpos($error['file'], 'blazecommerce') !== false) {
            error_log(sprintf(
                '[BlazeCommerce Fatal Error] %s in %s on line %d',
                $error['message'],
                $error['file'],
                $error['line']
            ));
        }
    }
}
```

## WordPress Plugin Context

These CI/CD practices apply specifically to:
- WordPress plugin development and testing
- WooCommerce integration validation
- Multi-version WordPress compatibility
- PHP version compatibility testing
- Plugin security scanning
- Performance optimization
- Automated deployment to WordPress.org
- Development environment management
