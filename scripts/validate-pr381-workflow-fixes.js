#!/usr/bin/env node

/**
 * Validation Script for PR #381 GitHub Actions Workflow Fixes
 * 
 * This script validates that the comprehensive fixes implemented for PR #381
 * are working correctly by testing workflow configurations and monitoring
 * recent execution results.
 * 
 * Usage: node scripts/validate-pr381-workflow-fixes.js
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

class WorkflowValidator {
    constructor() {
        this.workflowFile = '.github/workflows/tests.yml';
        this.validationResults = {
            configurationChecks: {},
            recentRunAnalysis: {},
            overallStatus: 'unknown'
        };
        
        console.log('🔍 Starting GitHub Actions Workflow Validation for PR #381 Fixes');
        console.log(`📁 Workflow file: ${this.workflowFile}`);
    }

    log(message, level = 'INFO') {
        const timestamp = new Date().toISOString();
        const prefix = level === 'ERROR' ? '❌' : level === 'WARN' ? '⚠️' : level === 'SUCCESS' ? '✅' : 'ℹ️';
        console.log(`${prefix} [${timestamp}] ${message}`);
    }

    validateWorkflowConfiguration() {
        this.log('🔧 Validating workflow configuration...');
        
        try {
            // Read and parse workflow file
            const workflowContent = fs.readFileSync(this.workflowFile, 'utf8');
            const workflow = yaml.load(workflowContent);
            
            const checks = {
                mysqlServiceConfig: this.validateMySQLServiceConfig(workflow),
                containerValidationStep: this.validateContainerValidationStep(workflow),
                healthCheckConfig: this.validateHealthCheckConfig(workflow),
                timeoutConfiguration: this.validateTimeoutConfiguration(workflow),
                errorHandlingPatterns: this.validateErrorHandlingPatterns(workflow)
            };
            
            this.validationResults.configurationChecks = checks;
            
            // Summary
            const passedChecks = Object.values(checks).filter(check => check.status === 'pass').length;
            const totalChecks = Object.keys(checks).length;
            
            this.log(`📊 Configuration validation: ${passedChecks}/${totalChecks} checks passed`);
            
            if (passedChecks === totalChecks) {
                this.log('✅ All configuration checks passed!', 'SUCCESS');
                return true;
            } else {
                this.log(`⚠️ ${totalChecks - passedChecks} configuration issues found`, 'WARN');
                return false;
            }
            
        } catch (error) {
            this.log(`❌ Error validating workflow configuration: ${error.message}`, 'ERROR');
            return false;
        }
    }

    validateMySQLServiceConfig(workflow) {
        this.log('🔍 Checking MySQL service configuration...');
        
        try {
            const testJob = workflow.jobs.test;
            const coverageJob = workflow.jobs['test-coverage'];
            
            const checks = [];
            
            // Check both jobs have MySQL service
            [testJob, coverageJob].forEach((job, index) => {
                const jobName = index === 0 ? 'test' : 'test-coverage';
                
                if (!job.services || !job.services.mysql) {
                    checks.push(`${jobName} job missing MySQL service`);
                    return;
                }
                
                const mysql = job.services.mysql;
                
                // Check enhanced configuration
                const requiredEnvVars = [
                    'MYSQL_AUTHENTICATION_PLUGIN',
                    'MYSQL_CHARSET',
                    'MYSQL_COLLATION'
                ];
                
                requiredEnvVars.forEach(envVar => {
                    if (!mysql.env || !mysql.env[envVar]) {
                        checks.push(`${jobName} job missing ${envVar} environment variable`);
                    }
                });
                
                // Check enhanced options
                const requiredOptions = [
                    '--health-retries=10',
                    '--health-start-period=60s',
                    '--innodb-buffer-pool-size=256M'
                ];
                
                const options = mysql.options || '';
                requiredOptions.forEach(option => {
                    if (!options.includes(option)) {
                        checks.push(`${jobName} job missing MySQL option: ${option}`);
                    }
                });
            });
            
            if (checks.length === 0) {
                this.log('✅ MySQL service configuration is correct', 'SUCCESS');
                return { status: 'pass', message: 'MySQL service properly configured' };
            } else {
                this.log(`❌ MySQL service configuration issues: ${checks.join(', ')}`, 'ERROR');
                return { status: 'fail', message: `Issues found: ${checks.join(', ')}` };
            }
            
        } catch (error) {
            return { status: 'error', message: `Validation error: ${error.message}` };
        }
    }

    validateContainerValidationStep(workflow) {
        this.log('🔍 Checking container validation step...');
        
        try {
            const testJob = workflow.jobs.test;
            const coverageJob = workflow.jobs['test-coverage'];
            
            const checks = [];
            
            [testJob, coverageJob].forEach((job, index) => {
                const jobName = index === 0 ? 'test' : 'test-coverage';
                
                if (!job.steps) {
                    checks.push(`${jobName} job has no steps`);
                    return;
                }
                
                // Find container validation step
                const validationStep = job.steps.find(step => 
                    step.name && step.name.includes('Validate container services')
                );
                
                if (!validationStep) {
                    checks.push(`${jobName} job missing container validation step`);
                    return;
                }
                
                // Check validation step content
                const runScript = validationStep.run || '';
                const requiredPatterns = [
                    'MAX_ATTEMPTS=120',
                    'mysqladmin ping',
                    'nc -z 127.0.0.1 3306',
                    'docker ps --filter',
                    'Progressive wait strategy'
                ];
                
                requiredPatterns.forEach(pattern => {
                    if (!runScript.includes(pattern)) {
                        checks.push(`${jobName} validation step missing: ${pattern}`);
                    }
                });
            });
            
            if (checks.length === 0) {
                this.log('✅ Container validation steps are correct', 'SUCCESS');
                return { status: 'pass', message: 'Container validation properly implemented' };
            } else {
                this.log(`❌ Container validation issues: ${checks.join(', ')}`, 'ERROR');
                return { status: 'fail', message: `Issues found: ${checks.join(', ')}` };
            }
            
        } catch (error) {
            return { status: 'error', message: `Validation error: ${error.message}` };
        }
    }

    validateHealthCheckConfig(workflow) {
        this.log('🔍 Checking health check configuration...');
        
        try {
            const testJob = workflow.jobs.test;
            const mysql = testJob.services.mysql;
            
            if (!mysql || !mysql.options) {
                return { status: 'fail', message: 'MySQL service or options not found' };
            }
            
            const options = mysql.options;
            const healthChecks = {
                'health-interval': '5s',
                'health-timeout': '15s',
                'health-retries': '10',
                'health-start-period': '60s'
            };
            
            const issues = [];
            Object.entries(healthChecks).forEach(([param, expectedValue]) => {
                const pattern = new RegExp(`--${param}=${expectedValue}`);
                if (!pattern.test(options)) {
                    issues.push(`${param} should be ${expectedValue}`);
                }
            });
            
            if (issues.length === 0) {
                this.log('✅ Health check configuration is optimal', 'SUCCESS');
                return { status: 'pass', message: 'Health checks properly configured' };
            } else {
                this.log(`⚠️ Health check configuration could be improved: ${issues.join(', ')}`, 'WARN');
                return { status: 'warn', message: `Improvements needed: ${issues.join(', ')}` };
            }
            
        } catch (error) {
            return { status: 'error', message: `Validation error: ${error.message}` };
        }
    }

    validateTimeoutConfiguration(workflow) {
        this.log('🔍 Checking timeout configuration...');
        
        try {
            const testJob = workflow.jobs.test;
            const coverageJob = workflow.jobs['test-coverage'];
            
            const checks = [];
            
            // Check job-level timeouts
            if (!testJob['timeout-minutes']) {
                checks.push('test job missing timeout-minutes');
            }
            
            if (!coverageJob['timeout-minutes']) {
                checks.push('test-coverage job missing timeout-minutes');
            }
            
            if (checks.length === 0) {
                this.log('✅ Timeout configuration is present', 'SUCCESS');
                return { status: 'pass', message: 'Timeouts properly configured' };
            } else {
                this.log(`❌ Timeout configuration issues: ${checks.join(', ')}`, 'ERROR');
                return { status: 'fail', message: `Issues found: ${checks.join(', ')}` };
            }
            
        } catch (error) {
            return { status: 'error', message: `Validation error: ${error.message}` };
        }
    }

    validateErrorHandlingPatterns(workflow) {
        this.log('🔍 Checking error handling patterns...');
        
        try {
            const testJob = workflow.jobs.test;
            
            // Check for established error handling patterns
            const setupStep = testJob.steps.find(step => 
                step.name && step.name.includes('Setup WordPress test environment')
            );
            
            if (!setupStep) {
                return { status: 'fail', message: 'WordPress setup step not found' };
            }
            
            const runScript = setupStep.run || '';
            const errorPatterns = [
                'for attempt in {1..5}',
                'Progressive timeout increase',
                'Enhanced error handling',
                'Comprehensive diagnostics',
                'Fallback strategies'
            ];
            
            const foundPatterns = errorPatterns.filter(pattern => 
                runScript.includes(pattern)
            );
            
            if (foundPatterns.length >= 3) {
                this.log('✅ Error handling patterns are implemented', 'SUCCESS');
                return { status: 'pass', message: `Found ${foundPatterns.length}/${errorPatterns.length} error handling patterns` };
            } else {
                this.log(`⚠️ Limited error handling patterns found: ${foundPatterns.length}/${errorPatterns.length}`, 'WARN');
                return { status: 'warn', message: `Only ${foundPatterns.length}/${errorPatterns.length} patterns found` };
            }
            
        } catch (error) {
            return { status: 'error', message: `Validation error: ${error.message}` };
        }
    }

    async analyzeRecentRuns() {
        this.log('📊 Analyzing recent workflow runs...');
        
        try {
            // Get recent workflow runs
            const command = `gh api repos/blaze-commerce/blazecommerce-wp-plugin/actions/workflows/tests.yml/runs --jq '.workflow_runs[0:5] | .[] | {id: .id, status: .status, conclusion: .conclusion, created_at: .created_at, head_branch: .head_branch}'`;
            
            const output = execSync(command, { encoding: 'utf8' });
            const runs = output.trim().split('\n')
                .filter(line => line.trim())
                .map(line => JSON.parse(line));
            
            if (runs.length === 0) {
                this.log('📭 No recent workflow runs found');
                return { status: 'unknown', message: 'No recent runs to analyze' };
            }
            
            // Analyze runs
            const analysis = {
                total: runs.length,
                successful: runs.filter(r => r.conclusion === 'success').length,
                failed: runs.filter(r => r.conclusion === 'failure').length,
                running: runs.filter(r => r.status === 'in_progress').length
            };
            
            const successRate = analysis.total > 0 
                ? (analysis.successful / analysis.total * 100).toFixed(1)
                : 0;
            
            this.log(`📈 Recent runs analysis: ${analysis.successful}/${analysis.total} successful (${successRate}%)`);
            
            this.validationResults.recentRunAnalysis = {
                ...analysis,
                successRate: parseFloat(successRate),
                runs: runs
            };
            
            if (successRate >= 80) {
                this.log('✅ Recent workflow runs show good success rate', 'SUCCESS');
                return { status: 'pass', message: `${successRate}% success rate` };
            } else {
                this.log(`⚠️ Recent workflow runs show low success rate: ${successRate}%`, 'WARN');
                return { status: 'warn', message: `${successRate}% success rate (below 80% threshold)` };
            }
            
        } catch (error) {
            this.log(`❌ Error analyzing recent runs: ${error.message}`, 'ERROR');
            return { status: 'error', message: `Analysis error: ${error.message}` };
        }
    }

    generateValidationReport() {
        const report = `# Workflow Validation Report - PR #381 Fixes

## 📊 Validation Summary

**Validation Date:** ${new Date().toISOString()}
**Workflow File:** ${this.workflowFile}
**Overall Status:** ${this.validationResults.overallStatus}

## 🔧 Configuration Checks

${Object.entries(this.validationResults.configurationChecks).map(([check, result]) => {
    const status = result.status === 'pass' ? '✅' : result.status === 'warn' ? '⚠️' : '❌';
    return `### ${check}
**Status:** ${status} ${result.status.toUpperCase()}
**Details:** ${result.message}`;
}).join('\n\n')}

## 📈 Recent Run Analysis

${this.validationResults.recentRunAnalysis.total ? `
**Total Runs Analyzed:** ${this.validationResults.recentRunAnalysis.total}
**Successful:** ${this.validationResults.recentRunAnalysis.successful}
**Failed:** ${this.validationResults.recentRunAnalysis.failed}
**Running:** ${this.validationResults.recentRunAnalysis.running}
**Success Rate:** ${this.validationResults.recentRunAnalysis.successRate}%

${this.validationResults.recentRunAnalysis.successRate >= 80 ? 
  '✅ **SUCCESS** - Workflow fixes are working effectively' : 
  '⚠️ **WARNING** - Success rate below target threshold'}
` : 'No recent runs available for analysis'}

## 🎯 Recommendations

${this.generateRecommendations()}

---
*Report generated by validation script at ${new Date().toISOString()}*
`;

        const reportFile = path.join(__dirname, '..', 'docs', 'workflow-validation-report-pr381.md');
        fs.writeFileSync(reportFile, report);
        this.log(`📝 Validation report saved: ${reportFile}`);
    }

    generateRecommendations() {
        const configPassed = Object.values(this.validationResults.configurationChecks)
            .filter(check => check.status === 'pass').length;
        const configTotal = Object.keys(this.validationResults.configurationChecks).length;
        
        const successRate = this.validationResults.recentRunAnalysis.successRate || 0;
        
        if (configPassed === configTotal && successRate >= 80) {
            return `✅ **All validations passed successfully**
- Workflow configuration is optimal
- Recent runs show good success rate
- Continue monitoring for consistency
- No immediate action required`;
        } else if (configPassed >= configTotal * 0.8) {
            return `⚠️ **Minor issues detected**
- Most configuration checks passed
- Consider addressing remaining configuration issues
- Monitor success rate trends
- Implement additional improvements if needed`;
        } else {
            return `🚨 **Critical issues require attention**
- Multiple configuration problems detected
- Success rate may be impacted
- Review and fix configuration issues immediately
- Consider rolling back if problems persist`;
        }
    }

    async runValidation() {
        this.log('🚀 Starting comprehensive workflow validation...');
        
        // Run all validation checks
        const configValid = this.validateWorkflowConfiguration();
        const runAnalysis = await this.analyzeRecentRuns();
        
        // Determine overall status
        if (configValid && runAnalysis.status === 'pass') {
            this.validationResults.overallStatus = 'pass';
            this.log('✅ All validations passed successfully!', 'SUCCESS');
        } else if (configValid || runAnalysis.status !== 'error') {
            this.validationResults.overallStatus = 'warn';
            this.log('⚠️ Validation completed with warnings', 'WARN');
        } else {
            this.validationResults.overallStatus = 'fail';
            this.log('❌ Validation failed - issues require attention', 'ERROR');
        }
        
        // Generate report
        this.generateValidationReport();
        
        this.log('📋 Validation completed - check report for details');
        
        return this.validationResults.overallStatus === 'pass';
    }
}

// Main execution
if (require.main === module) {
    const validator = new WorkflowValidator();
    
    validator.runValidation().then(success => {
        process.exit(success ? 0 : 1);
    }).catch(error => {
        console.error('❌ Fatal error in validation:', error);
        process.exit(1);
    });
}

module.exports = WorkflowValidator;
