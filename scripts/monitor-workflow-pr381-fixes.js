#!/usr/bin/env node

/**
 * GitHub Actions Workflow Monitoring Script for PR #381 Fixes
 * 
 * This script monitors the GitHub Actions workflow execution to track
 * the success rate of the comprehensive fixes implemented for PR #381.
 * 
 * Features:
 * - Real-time workflow run monitoring
 * - Success rate tracking by job type
 * - Error pattern detection and reporting
 * - Comprehensive diagnostics and alerts
 * 
 * Usage: node scripts/monitor-workflow-pr381-fixes.js
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

class WorkflowMonitor {
    constructor() {
        this.startTime = new Date();
        this.monitoringResults = {
            totalRuns: 0,
            successfulRuns: 0,
            failedRuns: 0,
            jobResults: {},
            errorPatterns: {},
            lastUpdate: null
        };
        
        this.reportFile = path.join(__dirname, '..', 'docs', 'workflow-monitoring-pr381-report.md');
        this.logFile = path.join(__dirname, '..', 'logs', 'workflow-monitoring.log');
        
        // Ensure directories exist
        this.ensureDirectories();
        
        console.log('🚀 Starting GitHub Actions Workflow Monitor for PR #381 Fixes');
        console.log(`📊 Monitoring started at: ${this.startTime.toISOString()}`);
        console.log(`📝 Report file: ${this.reportFile}`);
        console.log(`📋 Log file: ${this.logFile}`);
    }

    ensureDirectories() {
        const dirs = [
            path.dirname(this.reportFile),
            path.dirname(this.logFile)
        ];
        
        dirs.forEach(dir => {
            if (!fs.existsSync(dir)) {
                fs.mkdirSync(dir, { recursive: true });
                console.log(`📁 Created directory: ${dir}`);
            }
        });
    }

    log(message, level = 'INFO') {
        const timestamp = new Date().toISOString();
        const logEntry = `[${timestamp}] [${level}] ${message}`;
        
        console.log(logEntry);
        
        // Append to log file
        try {
            fs.appendFileSync(this.logFile, logEntry + '\n');
        } catch (error) {
            console.error('Failed to write to log file:', error.message);
        }
    }

    async getRecentWorkflowRuns() {
        try {
            this.log('🔍 Fetching recent workflow runs...');
            
            // Get recent workflow runs for the tests workflow
            const command = `gh api repos/blaze-commerce/blazecommerce-wp-plugin/actions/workflows/tests.yml/runs --paginate --jq '.workflow_runs[] | select(.created_at > "${this.getMonitoringStartTime()}") | {id: .id, status: .status, conclusion: .conclusion, created_at: .created_at, head_branch: .head_branch, head_sha: .head_sha}'`;
            
            const output = execSync(command, { encoding: 'utf8' });
            const runs = output.trim().split('\n')
                .filter(line => line.trim())
                .map(line => JSON.parse(line));
            
            this.log(`📊 Found ${runs.length} workflow runs since monitoring started`);
            return runs;
            
        } catch (error) {
            this.log(`❌ Error fetching workflow runs: ${error.message}`, 'ERROR');
            return [];
        }
    }

    async getWorkflowJobs(runId) {
        try {
            const command = `gh api repos/blaze-commerce/blazecommerce-wp-plugin/actions/runs/${runId}/jobs --jq '.jobs[] | {id: .id, name: .name, status: .status, conclusion: .conclusion, started_at: .started_at, completed_at: .completed_at}'`;
            
            const output = execSync(command, { encoding: 'utf8' });
            const jobs = output.trim().split('\n')
                .filter(line => line.trim())
                .map(line => JSON.parse(line));
            
            return jobs;
            
        } catch (error) {
            this.log(`❌ Error fetching jobs for run ${runId}: ${error.message}`, 'ERROR');
            return [];
        }
    }

    getMonitoringStartTime() {
        // Return ISO string for time 1 hour ago to catch recent runs
        const oneHourAgo = new Date(Date.now() - 60 * 60 * 1000);
        return oneHourAgo.toISOString();
    }

    analyzeJobResults(jobs) {
        const analysis = {
            total: jobs.length,
            successful: 0,
            failed: 0,
            running: 0,
            containerFailures: 0,
            mysqlFailures: 0,
            testFailures: 0,
            jobBreakdown: {}
        };

        jobs.forEach(job => {
            // Track by job name
            if (!analysis.jobBreakdown[job.name]) {
                analysis.jobBreakdown[job.name] = {
                    total: 0,
                    successful: 0,
                    failed: 0
                };
            }
            
            analysis.jobBreakdown[job.name].total++;
            
            if (job.status === 'completed') {
                if (job.conclusion === 'success') {
                    analysis.successful++;
                    analysis.jobBreakdown[job.name].successful++;
                } else {
                    analysis.failed++;
                    analysis.jobBreakdown[job.name].failed++;
                    
                    // Analyze failure patterns
                    if (job.name.includes('test (')) {
                        analysis.testFailures++;
                        
                        // Check for specific failure patterns
                        if (job.name.includes('Initialize containers')) {
                            analysis.containerFailures++;
                        }
                    }
                }
            } else {
                analysis.running++;
            }
        });

        return analysis;
    }

    async monitorWorkflows() {
        try {
            this.log('🔄 Starting workflow monitoring cycle...');
            
            const runs = await this.getRecentWorkflowRuns();
            
            if (runs.length === 0) {
                this.log('📭 No recent workflow runs found');
                return;
            }

            let totalJobs = [];
            
            for (const run of runs) {
                this.log(`📋 Analyzing run ${run.id} (${run.status}/${run.conclusion})`);
                
                const jobs = await this.getWorkflowJobs(run.id);
                totalJobs = totalJobs.concat(jobs);
                
                // Update run statistics
                this.monitoringResults.totalRuns++;
                
                if (run.status === 'completed') {
                    if (run.conclusion === 'success') {
                        this.monitoringResults.successfulRuns++;
                    } else {
                        this.monitoringResults.failedRuns++;
                    }
                }
            }

            // Analyze all jobs
            const jobAnalysis = this.analyzeJobResults(totalJobs);
            this.monitoringResults.jobResults = jobAnalysis;
            this.monitoringResults.lastUpdate = new Date().toISOString();

            // Calculate success rates
            const runSuccessRate = this.monitoringResults.totalRuns > 0 
                ? (this.monitoringResults.successfulRuns / this.monitoringResults.totalRuns * 100).toFixed(1)
                : 0;
                
            const jobSuccessRate = jobAnalysis.total > 0 
                ? (jobAnalysis.successful / jobAnalysis.total * 100).toFixed(1)
                : 0;

            this.log(`📊 MONITORING SUMMARY:`);
            this.log(`   Workflow Runs: ${this.monitoringResults.totalRuns} total, ${this.monitoringResults.successfulRuns} successful (${runSuccessRate}%)`);
            this.log(`   Jobs: ${jobAnalysis.total} total, ${jobAnalysis.successful} successful (${jobSuccessRate}%)`);
            this.log(`   Container Failures: ${jobAnalysis.containerFailures}`);
            this.log(`   MySQL Failures: ${jobAnalysis.mysqlFailures}`);
            this.log(`   Test Failures: ${jobAnalysis.testFailures}`);

            // Generate detailed report
            await this.generateReport();

            // Check for critical issues
            if (jobSuccessRate < 80) {
                this.log(`🚨 ALERT: Job success rate (${jobSuccessRate}%) is below 80% threshold!`, 'WARN');
            }
            
            if (jobAnalysis.containerFailures > 0) {
                this.log(`🚨 ALERT: ${jobAnalysis.containerFailures} container initialization failures detected!`, 'WARN');
            }

        } catch (error) {
            this.log(`❌ Error during monitoring cycle: ${error.message}`, 'ERROR');
        }
    }

    async generateReport() {
        const report = this.buildMarkdownReport();
        
        try {
            fs.writeFileSync(this.reportFile, report);
            this.log(`📝 Report updated: ${this.reportFile}`);
        } catch (error) {
            this.log(`❌ Error writing report: ${error.message}`, 'ERROR');
        }
    }

    buildMarkdownReport() {
        const { monitoringResults } = this;
        const runSuccessRate = monitoringResults.totalRuns > 0 
            ? (monitoringResults.successfulRuns / monitoringResults.totalRuns * 100).toFixed(1)
            : 0;
            
        const jobSuccessRate = monitoringResults.jobResults.total > 0 
            ? (monitoringResults.jobResults.successful / monitoringResults.jobResults.total * 100).toFixed(1)
            : 0;

        return `# GitHub Actions Workflow Monitoring Report - PR #381 Fixes

## 📊 Executive Summary

**Monitoring Period:** ${this.startTime.toISOString()} - ${monitoringResults.lastUpdate || 'In Progress'}
**Last Updated:** ${new Date().toISOString()}

### Overall Statistics
- **Workflow Runs:** ${monitoringResults.totalRuns} total, ${monitoringResults.successfulRuns} successful (**${runSuccessRate}%** success rate)
- **Individual Jobs:** ${monitoringResults.jobResults.total} total, ${monitoringResults.jobResults.successful} successful (**${jobSuccessRate}%** success rate)
- **Container Failures:** ${monitoringResults.jobResults.containerFailures}
- **MySQL Failures:** ${monitoringResults.jobResults.mysqlFailures}
- **Test Failures:** ${monitoringResults.jobResults.testFailures}

### Success Rate Analysis
${jobSuccessRate >= 95 ? '✅ **EXCELLENT** - Success rate meets target (≥95%)' : 
  jobSuccessRate >= 80 ? '⚠️ **GOOD** - Success rate above minimum threshold (≥80%)' : 
  '🚨 **CRITICAL** - Success rate below acceptable threshold (<80%)'}

## 📋 Job Breakdown

${Object.entries(monitoringResults.jobResults.jobBreakdown || {}).map(([jobName, stats]) => {
    const successRate = stats.total > 0 ? (stats.successful / stats.total * 100).toFixed(1) : 0;
    const status = successRate >= 95 ? '✅' : successRate >= 80 ? '⚠️' : '❌';
    return `### ${jobName}
- **Total:** ${stats.total}
- **Successful:** ${stats.successful}
- **Failed:** ${stats.failed}
- **Success Rate:** ${successRate}% ${status}`;
}).join('\n\n')}

## 🔍 Analysis and Recommendations

${this.generateRecommendations()}

---
*Report generated by workflow monitoring script at ${new Date().toISOString()}*
`;
    }

    generateRecommendations() {
        const { jobResults } = this.monitoringResults;
        const jobSuccessRate = jobResults.total > 0 
            ? (jobResults.successful / jobResults.total * 100)
            : 0;

        if (jobSuccessRate >= 95) {
            return `✅ **All systems operating normally**
- Workflow fixes are working effectively
- Continue monitoring for any edge cases
- Consider optimizing performance if needed`;
        } else if (jobSuccessRate >= 80) {
            return `⚠️ **Minor issues detected**
- Success rate is acceptable but could be improved
- Monitor for patterns in remaining failures
- Consider implementing additional fallback mechanisms`;
        } else {
            return `🚨 **Critical issues require immediate attention**
- Success rate is below acceptable threshold
- Container initialization failures need investigation
- MySQL service configuration may need adjustment
- Consider implementing emergency fallback strategies`;
        }
    }

    async startMonitoring(intervalMinutes = 5) {
        this.log(`🔄 Starting continuous monitoring (${intervalMinutes} minute intervals)`);
        
        // Initial monitoring cycle
        await this.monitorWorkflows();
        
        // Set up periodic monitoring
        setInterval(async () => {
            await this.monitorWorkflows();
        }, intervalMinutes * 60 * 1000);
        
        this.log('✅ Monitoring system initialized and running');
    }
}

// Main execution
if (require.main === module) {
    const monitor = new WorkflowMonitor();
    
    // Handle graceful shutdown
    process.on('SIGINT', () => {
        monitor.log('🛑 Monitoring stopped by user');
        process.exit(0);
    });
    
    // Start monitoring
    monitor.startMonitoring(5).catch(error => {
        console.error('❌ Fatal error in monitoring system:', error);
        process.exit(1);
    });
}

module.exports = WorkflowMonitor;
