#!/bin/bash
set -e

echo "=== BlazeCommerce WordPress Plugin - Complete Development Environment Setup ==="

# Update system packages
sudo apt-get update -y

# Install PHP and required extensions
sudo apt-get install -y php8.1 php8.1-cli php8.1-common php8.1-curl php8.1-mbstring php8.1-xml php8.1-zip php8.1-mysql php8.1-gd php8.1-intl php8.1-bcmath php8.1-soap php8.1-xdebug

# Install Composer
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
    sudo chmod +x /usr/local/bin/composer
fi

# Add to PATH and make available immediately
echo 'export PATH="/usr/local/bin:$PATH"' >> $HOME/.profile
echo 'export PATH="./vendor/bin:$PATH"' >> $HOME/.profile
echo 'export PATH="./node_modules/.bin:$PATH"' >> $HOME/.profile

export PATH="/usr/local/bin:$PATH"
export PATH="./vendor/bin:$PATH"
export PATH="./node_modules/.bin:$PATH"

# Install PHP dependencies
composer install --no-interaction --prefer-dist
composer require --dev phpunit/phpunit "^9.0" --no-interaction
composer require --dev mockery/mockery --no-interaction
composer require --dev brain/monkey --no-interaction

# Install Node.js dependencies
npm install
cd blocks && npm install && cd ..

# Create test directories
mkdir -p tests/unit tests/integration tests/coverage

# Create PHPUnit configuration
cat > phpunit.xml << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" verbose="true">
    <testsuites>
        <testsuite name="BlazeCommerce Plugin Tests">
            <directory>./tests/unit/</directory>
        </testsuite>
        <testsuite name="Integration Tests">
            <directory>./tests/integration/</directory>
        </testsuite>
    </testsuites>
    <filter>
        <whitelist processUncoveredFilesFromWhitelist="true">
            <directory suffix=".php">./app/</directory>
            <exclude>
                <directory>./vendor/</directory>
                <directory>./tests/</directory>
                <directory>./node_modules/</directory>
            </exclude>
        </whitelist>
    </filter>
</phpunit>
EOF

# Create bootstrap
cat > tests/bootstrap.php << 'EOF'
<?php
require_once __DIR__ . '/../vendor/autoload.php';
\Brain\Monkey\setUp();

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/../');
if (!defined('WP_CONTENT_DIR')) define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
if (!defined('WP_PLUGIN_DIR')) define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
if (!defined('BLAZE_WOOLESS_PLUGIN_DIR')) define('BLAZE_WOOLESS_PLUGIN_DIR', __DIR__ . '/../');
if (!defined('BLAZE_WOOLESS_PLUGIN_URL')) define('BLAZE_WOOLESS_PLUGIN_URL', 'http://localhost/wp-content/plugins/blazecommerce-wp-plugin/');

register_shutdown_function(function() { \Brain\Monkey\tearDown(); });
EOF

# Create working tests
cat > tests/unit/BasicFunctionalityTest.php << 'EOF'
<?php
use PHPUnit\Framework\TestCase;

class BasicFunctionalityTest extends TestCase
{
    public function testComposerAutoloadWorks()
    {
        $this->assertTrue(class_exists('BlazeWooless\TypesenseClient'));
        $this->assertTrue(class_exists('BlazeWooless\Collections\CollectionAliasManager'));
    }

    public function testPHPVersionIsCompatible()
    {
        $this->assertGreaterThanOrEqual('7.4.0', PHP_VERSION);
    }

    public function testRequiredExtensionsAreLoaded()
    {
        $this->assertTrue(extension_loaded('json'));
        $this->assertTrue(extension_loaded('curl'));
        $this->assertTrue(extension_loaded('mbstring'));
    }

    public function testProjectStructureExists()
    {
        $this->assertDirectoryExists(__DIR__ . '/../../app');
        $this->assertDirectoryExists(__DIR__ . '/../../vendor');
        $this->assertFileExists(__DIR__ . '/../../composer.json');
        $this->assertFileExists(__DIR__ . '/../../package.json');
    }
}
EOF

cat > tests/integration/ExistingTestsIntegrationTest.php << 'EOF'
<?php
use PHPUnit\Framework\TestCase;

class ExistingTestsIntegrationTest extends TestCase
{
    public function testAliasImplementationTestFileExists()
    {
        $testFile = __DIR__ . '/../../test/test-alias-implementation.php';
        $this->assertFileExists($testFile);
        
        $content = file_get_contents($testFile);
        $this->assertStringContainsString('TypesenseClient', $content);
        $this->assertStringContainsString('CollectionAliasManager', $content);
    }

    public function testExportImportTestFileExists()
    {
        $testFile = __DIR__ . '/../../tests/export-import-test.php';
        $this->assertFileExists($testFile);
        
        $content = file_get_contents($testFile);
        $this->assertStringContainsString('ExportImportSettings', $content);
    }

    public function testMainPluginFileExists()
    {
        $this->assertFileExists(__DIR__ . '/../../blaze-wooless.php');
    }

    public function testAppDirectoryStructure()
    {
        $appDir = __DIR__ . '/../../app';
        $this->assertDirectoryExists($appDir);
        
        $expectedDirs = ['Collections', 'Extensions', 'Features', 'Settings'];
        foreach ($expectedDirs as $dir) {
            $this->assertDirectoryExists($appDir . '/' . $dir);
        }
    }

    public function testComposerDependenciesInstalled()
    {
        $vendorDir = __DIR__ . '/../../vendor';
        $this->assertDirectoryExists($vendorDir);
        
        $expectedPackages = [
            'typesense/typesense-php',
            'php-http/curl-client',
            'symfony/http-client',
            'phpunit/phpunit',
            'brain/monkey'
        ];
        
        foreach ($expectedPackages as $package) {
            $this->assertDirectoryExists($vendorDir . '/' . $package);
        }
    }

    public function testNodeDependenciesInstalled()
    {
        $this->assertDirectoryExists(__DIR__ . '/../../node_modules');
        $this->assertDirectoryExists(__DIR__ . '/../../blocks/node_modules');
    }
}
EOF

# Update composer.json with test scripts
php -r "
\$composer = json_decode(file_get_contents('composer.json'), true);
\$composer['scripts'] = [
    'test' => 'phpunit',
    'test:unit' => 'phpunit --testsuite=\"BlazeCommerce Plugin Tests\"',
    'test:integration' => 'phpunit --testsuite=\"Integration Tests\"',
    'test:coverage' => 'phpunit --coverage-html tests/coverage/html',
    'test:basic' => 'phpunit tests/unit/BasicFunctionalityTest.php'
];
file_put_contents('composer.json', json_encode(\$composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
"

echo ""
echo "=== SETUP COMPLETE ==="
echo "✅ PHP 8.1 with extensions installed"
echo "✅ Composer and dependencies installed"
echo "✅ Node.js dependencies installed"
echo "✅ PHPUnit testing framework configured"
echo "✅ Test structure created"
echo ""
echo "Available commands:"
echo "  composer test           - Run all tests"
echo "  composer test:unit      - Run unit tests"
echo "  composer test:integration - Run integration tests"
echo "  composer test:basic     - Run basic functionality tests"
echo ""
echo "Test files:"
echo "  tests/unit/BasicFunctionalityTest.php"
echo "  tests/integration/ExistingTestsIntegrationTest.php"
echo ""
echo "Configuration:"
echo "  phpunit.xml - PHPUnit configuration"
echo "  tests/bootstrap.php - Test bootstrap"