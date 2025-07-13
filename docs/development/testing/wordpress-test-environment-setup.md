# WordPress Test Environment Setup

## Overview

This document describes the WordPress test environment setup for the BlazeCommerce WordPress plugin, including dependency requirements, troubleshooting, and recent improvements made to fix GitHub Actions workflow failures.

## Dependencies

### System Dependencies

The WordPress test environment requires the following system dependencies:

#### Required Dependencies
- **Subversion (SVN)** - Required for downloading WordPress test files from the WordPress.org SVN repository
- **curl or wget** - Required for downloading WordPress core files and other resources
- **unzip** - Required for extracting WordPress nightly builds
- **tar** - Required for extracting WordPress release archives
- **mysql-client** - Required for database operations (mysql, mysqladmin)
- **sed** - Required for text processing (usually pre-installed)
- **grep** - Required for pattern matching (usually pre-installed)

#### Installation Commands

**Ubuntu/Debian:**
```bash
sudo apt-get update
sudo apt-get install -y subversion curl unzip mysql-client
```

**CentOS/RHEL:**
```bash
sudo yum install subversion curl unzip mysql
```

**macOS:**
```bash
brew install subversion mysql-client
```

### PHP Dependencies

The test environment also requires specific PHP extensions:
- dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
- mysql, mysqli, pdo_mysql, bcmath, soap, intl, gd, exif, iconv

## GitHub Actions Workflow

### Test Matrix

The workflow runs tests against multiple PHP and WordPress versions:

**PHP Versions:**
- 7.4
- 8.0  
- 8.1

**WordPress Versions:**
- latest
- 6.3
- 6.2

### Workflow Jobs

1. **test** - Main test job with matrix strategy
2. **code-quality** - PHP_CodeSniffer and PHPStan analysis
3. **test-coverage** - Coverage report generation

## Recent Fixes (PR #337)

### Problem

The GitHub Actions workflow was failing during WordPress test environment setup with the error:
```
svn: command not found
```

This occurred because the `bin/install-wp-tests.sh` script uses SVN commands to download WordPress test files, but SVN was not installed on the GitHub Actions runners.

### Solution

#### 1. Workflow Enhancements

Added comprehensive system dependency installation to `.github/workflows/tests.yml`:

```yaml
- name: Install system dependencies
  run: |
    echo "DEBUG: Installing system dependencies for WordPress testing..."
    
    # Update package list
    sudo apt-get update -qq
    
    # Install Subversion (required by install-wp-tests.sh)
    echo "INSTALLING: Subversion (SVN)..."
    sudo apt-get install -y subversion
    
    # Verify SVN installation
    if ! command -v svn &> /dev/null; then
      echo "ERROR: SVN installation failed - svn command not found"
      exit 1
    fi
    echo "SUCCESS: SVN installed successfully - $(svn --version --quiet)"
    
    # Verify other required tools are available
    echo "VERIFYING: Checking other required dependencies..."
    # ... additional verification steps
```

#### 2. Script Improvements

Enhanced `bin/install-wp-tests.sh` with:

- **Dependency checking function** that validates all required tools before proceeding
- **Comprehensive error handling** for SVN operations with detailed error messages
- **Improved download function** with better error reporting
- **Network connectivity validation** for SVN repository access

#### 3. Error Handling Improvements

- **MySQL readiness checks** - Wait for MySQL service to be available before proceeding
- **SVN connectivity validation** - Test connection to WordPress SVN repository
- **Directory creation verification** - Ensure test directories are properly created
- **Graceful failure handling** - Provide clear error messages and troubleshooting hints

## Troubleshooting

### Common Issues

#### SVN Command Not Found
**Error:** `svn: command not found`
**Solution:** Install Subversion using the appropriate package manager for your system.

#### MySQL Connection Failed
**Error:** `ERROR 2002 (HY000): Can't connect to local MySQL server`
**Solution:** Ensure MySQL service is running and accessible. In GitHub Actions, wait for the MySQL service to be ready.

#### WordPress Test Files Download Failed
**Error:** `Failed to download WordPress test includes from SVN`
**Possible Causes:**
- Network connectivity issues
- SVN server unavailability  
- Invalid WordPress version tag

**Solution:** Check network connectivity and verify the WordPress version is valid.

#### Permission Denied
**Error:** `Permission denied` when running scripts
**Solution:** Ensure the script is executable: `chmod +x bin/install-wp-tests.sh`

### Debug Steps

1. **Verify Dependencies:**
   ```bash
   # Check if SVN is installed
   svn --version
   
   # Check if MySQL client is available
   mysql --version
   
   # Check if other tools are available
   curl --version
   wget --version
   unzip -v
   tar --version
   ```

2. **Test SVN Connectivity:**
   ```bash
   # Test connection to WordPress SVN
   svn info https://develop.svn.wordpress.org/trunk/
   ```

3. **Test MySQL Connection:**
   ```bash
   # Test MySQL connection
   mysql -h 127.0.0.1 -P 3306 -u root -proot -e "SELECT 1"
   ```

4. **Check Script Permissions:**
   ```bash
   # Verify script is executable
   ls -la bin/install-wp-tests.sh
   ```

## Testing the Setup

### Local Testing

To test the WordPress environment setup locally:

```bash
# Make script executable
chmod +x bin/install-wp-tests.sh

# Run the setup script
bash bin/install-wp-tests.sh wordpress_test root root localhost latest

# Run PHPUnit tests
vendor/bin/phpunit --configuration phpunit.xml
```

### GitHub Actions Testing

The workflow automatically tests the setup across multiple PHP and WordPress versions. Monitor the workflow runs for any failures and check the logs for detailed error information.

## Best Practices

1. **Always verify dependencies** before running the test setup
2. **Use comprehensive error handling** in scripts
3. **Provide clear error messages** with troubleshooting hints
4. **Test across multiple environments** to ensure compatibility
5. **Monitor workflow runs** for early detection of issues
6. **Keep dependencies up to date** but maintain compatibility

## Related Files

- `.github/workflows/tests.yml` - Main test workflow
- `bin/install-wp-tests.sh` - WordPress test environment setup script
- `phpunit.xml` - PHPUnit configuration
- `composer.json` - PHP dependencies
- `tests/bootstrap.php` - Test bootstrap file

## References

- [WordPress Test Suite Documentation](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)
- [GitHub Actions Ubuntu Runner](https://github.com/actions/runner-images/blob/main/images/linux/Ubuntu2204-Readme.md)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
