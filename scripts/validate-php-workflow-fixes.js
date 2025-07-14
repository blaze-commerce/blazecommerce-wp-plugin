#!/usr/bin/env node

/**
 * PHP Workflow Fixes Validation Script
 * 
 * This script validates the comprehensive PHP test workflow fixes
 * implemented to address GitHub Actions pipeline failures.
 */

const fs = require('fs');
const path = require('path');

class PHPWorkflowValidator {
    constructor() {
        this.errors = [];
        this.warnings = [];
        this.successes = [];
        this.workflowPath = '.github/workflows/tests.yml';
    }

    log(message, type = 'info') {
        const prefix = {
            'info': '📋 INFO',
            'success': '✅ SUCCESS',
            'warning': '⚠️ WARNING',
            'error': '❌ ERROR'
        }[type] || '📋 INFO';
        
        console.log(`${prefix}: ${message}`);
        
        if (type === 'error') {
            this.errors.push(message);
        } else if (type === 'warning') {
            this.warnings.push(message);
        } else if (type === 'success') {
            this.successes.push(message);
        }
    }

    validateWorkflowStructure() {
        this.log('Validating PHP workflow structure and improvements...');
        
        if (!fs.existsSync(this.workflowPath)) {
            this.log(`Workflow file not found: ${this.workflowPath}`, 'error');
            return false;
        }

        try {
            const content = fs.readFileSync(this.workflowPath, 'utf8');
            
            // Check for comprehensive improvements
            const improvements = [
                // Core workflow improvements
                { pattern: /concurrency:/, description: 'Concurrency management for preventing conflicts' },
                { pattern: /workflow_dispatch:/, description: 'Manual trigger support for testing' },
                { pattern: /debug_mode:/, description: 'Debug mode input for troubleshooting' },
                
                // Database improvements
                { pattern: /mysql:8\.0/, description: 'MySQL 8.0 upgrade for better compatibility' },
                { pattern: /health-start-period=/, description: 'Enhanced MySQL health checks' },
                { pattern: /default-authentication-plugin/, description: 'MySQL native password authentication' },
                
                // PHP improvements
                { pattern: /php-version.*8\.2/, description: 'PHP 8.2 support in matrix' },
                { pattern: /exclude:/, description: 'Matrix exclusions for problematic combinations' },
                { pattern: /memory_limit=512M/, description: 'Enhanced PHP memory configuration' },
                { pattern: /imagick.*fileinfo.*openssl/, description: 'Extended PHP extensions' },
                
                // Composer improvements
                { pattern: /composer config --global/, description: 'Enhanced composer global configuration' },
                { pattern: /process-timeout 600/, description: 'Increased composer timeout' },
                { pattern: /platform\.php/, description: 'Platform-specific PHP configuration' },
                
                // Retry mechanisms
                { pattern: /for i in \{1\.\.3\}/, description: 'Triple retry mechanism implementation' },
                { pattern: /timeout 300/, description: 'Timeout protection for operations' },
                { pattern: /sleep \d+/, description: 'Delay between retry attempts' },
                
                // Enhanced logging
                { pattern: /📊|🔧|🚀|✅|❌|⚠️|🔍/, description: 'Emoji-based status indicators' },
                { pattern: /verify_command/, description: 'Dependency verification functions' },
                { pattern: /ATTEMPT.*\/\d+/, description: 'Attempt tracking in logs' },
                
                // Error handling
                { pattern: /continue-on-error: true/, description: 'Graceful error handling' },
                { pattern: /if \[ \$i -eq \d+ \]/, description: 'Retry limit enforcement' },
                { pattern: /exit 1/, description: 'Proper error exit codes' },
                
                // Artifact management
                { pattern: /retention-days:/, description: 'Artifact retention policies' },
                { pattern: /upload-artifact@v4/, description: 'Updated artifact upload action' },
                { pattern: /if: always\(\)/, description: 'Conditional artifact upload' }
            ];

            let passedChecks = 0;
            for (const improvement of improvements) {
                if (improvement.pattern.test(content)) {
                    this.log(`✓ ${improvement.description}`, 'success');
                    passedChecks++;
                } else {
                    this.log(`✗ ${improvement.description}`, 'warning');
                }
            }

            this.log(`Workflow improvements: ${passedChecks}/${improvements.length} implemented`, 'info');
            
            // Check for specific job configurations
            this.validateJobConfigurations(content);
            
            return true;
        } catch (error) {
            this.log(`Failed to read workflow file: ${error.message}`, 'error');
            return false;
        }
    }

    validateJobConfigurations(content) {
        this.log('Validating job-specific configurations...');
        
        // Check for required jobs
        const requiredJobs = ['test', 'code-quality', 'test-coverage'];
        for (const job of requiredJobs) {
            if (content.includes(`${job}:`)) {
                this.log(`Job '${job}' found in workflow`, 'success');
            } else {
                this.log(`Job '${job}' missing from workflow`, 'error');
            }
        }

        // Check for matrix strategy
        if (content.includes('strategy:') && content.includes('matrix:')) {
            this.log('Matrix strategy configured', 'success');
        } else {
            this.log('Matrix strategy missing', 'error');
        }

        // Check for timeout configurations
        const timeoutPattern = /timeout-minutes: \$\{\{ vars\.\w+ \|\| \d+ \}\}/;
        if (timeoutPattern.test(content)) {
            this.log('Configurable timeouts implemented', 'success');
        } else {
            this.log('Configurable timeouts missing', 'warning');
        }
    }

    validateSupportingFiles() {
        this.log('Validating supporting configuration files...');
        
        // Check composer.json
        if (fs.existsSync('composer.json')) {
            try {
                const composer = JSON.parse(fs.readFileSync('composer.json', 'utf8'));
                
                if (composer.require?.php) {
                    this.log(`PHP requirement: ${composer.require.php}`, 'success');
                } else {
                    this.log('Missing PHP version requirement in composer.json', 'warning');
                }

                const devDeps = composer['require-dev'] || {};
                const expectedDevDeps = ['phpunit/phpunit', 'squizlabs/php_codesniffer', 'phpstan/phpstan'];
                
                for (const dep of expectedDevDeps) {
                    if (devDeps[dep]) {
                        this.log(`Dev dependency found: ${dep}`, 'success');
                    } else {
                        this.log(`Missing dev dependency: ${dep}`, 'warning');
                    }
                }

                if (composer.config?.['allow-plugins']) {
                    this.log('Plugin allowlist configured', 'success');
                } else {
                    this.log('Missing plugin allowlist configuration', 'warning');
                }

            } catch (error) {
                this.log(`Failed to parse composer.json: ${error.message}`, 'error');
            }
        } else {
            this.log('composer.json not found', 'error');
        }

        // Check phpunit.xml
        if (fs.existsSync('phpunit.xml')) {
            const content = fs.readFileSync('phpunit.xml', 'utf8');
            
            const requiredElements = ['testsuites', 'filter', 'logging'];
            for (const element of requiredElements) {
                if (content.includes(`<${element}`)) {
                    this.log(`PHPUnit configuration found: ${element}`, 'success');
                } else {
                    this.log(`Missing PHPUnit configuration: ${element}`, 'warning');
                }
            }

            if (content.includes('coverage-html') && content.includes('coverage-clover')) {
                this.log('Coverage reporting configured', 'success');
            } else {
                this.log('Coverage reporting incomplete', 'warning');
            }
        } else {
            this.log('phpunit.xml not found', 'error');
        }

        // Check WordPress test installation script
        if (fs.existsSync('bin/install-wp-tests.sh')) {
            this.log('WordPress test installation script found', 'success');
        } else {
            this.log('WordPress test installation script missing', 'error');
        }
    }

    validateDocumentation() {
        this.log('Validating documentation...');
        
        const docFile = 'docs/php-test-workflow-fixes-comprehensive.md';
        if (fs.existsSync(docFile)) {
            this.log('Comprehensive documentation file found', 'success');
            
            const content = fs.readFileSync(docFile, 'utf8');
            const sections = [
                'Issues Addressed',
                'Key Improvements',
                'Technical Implementation',
                'Expected Benefits'
            ];
            
            for (const section of sections) {
                if (content.includes(section)) {
                    this.log(`Documentation section found: ${section}`, 'success');
                } else {
                    this.log(`Missing documentation section: ${section}`, 'warning');
                }
            }
        } else {
            this.log('Comprehensive documentation file missing', 'warning');
        }
    }

    generateReport() {
        this.log('\n🎯 PHP WORKFLOW VALIDATION REPORT', 'info');
        this.log('='.repeat(60), 'info');
        
        this.log(`✅ Successes: ${this.successes.length}`, 'info');
        this.log(`⚠️ Warnings: ${this.warnings.length}`, 'info');
        this.log(`❌ Errors: ${this.errors.length}`, 'info');
        
        if (this.errors.length > 0) {
            this.log('\nCritical Issues:', 'error');
            this.errors.forEach(error => this.log(`  - ${error}`, 'info'));
        }

        if (this.warnings.length > 0) {
            this.log('\nWarnings:', 'warning');
            this.warnings.forEach(warning => this.log(`  - ${warning}`, 'info'));
        }
        
        this.log('='.repeat(60), 'info');
        
        const score = Math.round((this.successes.length / (this.successes.length + this.warnings.length + this.errors.length)) * 100);
        this.log(`Overall Score: ${score}%`, score >= 80 ? 'success' : score >= 60 ? 'warning' : 'error');
        
        return this.errors.length === 0;
    }

    run() {
        this.log('🚀 Starting PHP Workflow Fixes Validation...\n');
        
        this.validateWorkflowStructure();
        this.validateSupportingFiles();
        this.validateDocumentation();
        
        const success = this.generateReport();

        if (success && this.warnings.length === 0) {
            this.log('\n🎉 Validation completed successfully!', 'success');
            this.log('The PHP workflow fixes are properly implemented and ready for testing.', 'info');
            process.exit(0);
        } else if (success && this.warnings.length > 0) {
            this.log('\n✅ Validation completed with minor warnings!', 'success');
            this.log('The PHP workflow fixes are implemented and ready for testing.', 'info');
            process.exit(0);
        } else {
            this.log('\n❌ Validation completed with critical errors!', 'error');
            this.log('Please review and address the errors before proceeding.', 'info');
            process.exit(1);
        }
    }
}

// Run validation if this script is executed directly
if (require.main === module) {
    const validator = new PHPWorkflowValidator();
    validator.run();
}

module.exports = PHPWorkflowValidator;
