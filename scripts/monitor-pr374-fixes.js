#!/usr/bin/env node

/**
 * PR #374 Workflow Fixes Monitor
 * 
 * Monitors the effectiveness of the comprehensive PHP test workflow fixes
 * implemented to address the failures in PR #374.
 */

const { execSync } = require('child_process');
const fs = require('fs');

class PR374FixesMonitor {
    constructor() {
        this.branchName = 'fix/github-workflow-php-tests';
        this.checkInterval = 15 * 60 * 1000; // 15 minutes
        this.maxMonitoringTime = 4 * 60 * 60 * 1000; // 4 hours
        this.startTime = Date.now();
        this.results = [];
    }

    log(message, type = 'info') {
        const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
        const prefix = {
            'info': '📋 INFO',
            'success': '✅ SUCCESS',
            'warning': '⚠️ WARNING',
            'error': '❌ ERROR',
            'monitoring': '👁️ MONITORING',
            'analysis': '🔍 ANALYSIS'
        }[type] || '📋 INFO';
        
        console.log(`[${timestamp}] ${prefix}: ${message}`);
    }

    async getWorkflowRuns() {
        try {
            const result = execSync(`gh run list --branch="${this.branchName}" --workflow="Tests" --limit=10 --json=status,conclusion,createdAt,url,databaseId,headSha`, {
                encoding: 'utf8',
                stdio: 'pipe'
            });
            
            return JSON.parse(result);
        } catch (error) {
            this.log(`Failed to get workflow runs: ${error.message}`, 'error');
            return [];
        }
    }

    async getJobDetails(runId) {
        try {
            const result = execSync(`gh run view ${runId} --json=jobs`, {
                encoding: 'utf8',
                stdio: 'pipe'
            });
            
            const data = JSON.parse(result);
            return data.jobs || [];
        } catch (error) {
            this.log(`Failed to get job details for run ${runId}: ${error.message}`, 'error');
            return [];
        }
    }

    analyzeFailurePatterns(jobs) {
        const patterns = {
            'wordpress_setup': 0,
            'woocommerce_install': 0,
            'phpunit_execution': 0,
            'database_connection': 0,
            'composer_issues': 0,
            'timeout_errors': 0,
            'memory_errors': 0,
            'unknown': 0
        };

        for (const job of jobs) {
            if (job.conclusion === 'failure') {
                // This is a simplified pattern detection
                // In a real implementation, you'd analyze the job logs
                const jobName = job.name.toLowerCase();
                
                if (jobName.includes('setup') || jobName.includes('wordpress')) {
                    patterns.wordpress_setup++;
                } else if (jobName.includes('woocommerce')) {
                    patterns.woocommerce_install++;
                } else if (jobName.includes('test') || jobName.includes('phpunit')) {
                    patterns.phpunit_execution++;
                } else {
                    patterns.unknown++;
                }
            }
        }

        return patterns;
    }

    calculateSuccessRate(runs) {
        if (runs.length === 0) return 0;
        
        const successful = runs.filter(run => run.conclusion === 'success').length;
        return Math.round((successful / runs.length) * 100);
    }

    async generateReport() {
        this.log('Generating comprehensive monitoring report...', 'analysis');
        
        const runs = await this.getWorkflowRuns();
        const successRate = this.calculateSuccessRate(runs);
        
        const report = {
            timestamp: new Date().toISOString(),
            monitoring_duration: Math.round((Date.now() - this.startTime) / 1000 / 60),
            total_runs: runs.length,
            success_rate: successRate,
            runs_analysis: [],
            overall_patterns: {
                wordpress_setup: 0,
                woocommerce_install: 0,
                phpunit_execution: 0,
                database_connection: 0,
                composer_issues: 0,
                timeout_errors: 0,
                memory_errors: 0,
                unknown: 0
            }
        };

        // Analyze each run
        for (const run of runs.slice(0, 5)) { // Analyze last 5 runs
            const jobs = await this.getJobDetails(run.databaseId);
            const patterns = this.analyzeFailurePatterns(jobs);
            
            // Add to overall patterns
            Object.keys(patterns).forEach(key => {
                report.overall_patterns[key] += patterns[key];
            });

            report.runs_analysis.push({
                run_id: run.databaseId,
                status: run.status,
                conclusion: run.conclusion,
                created_at: run.createdAt,
                url: run.url,
                total_jobs: jobs.length,
                successful_jobs: jobs.filter(job => job.conclusion === 'success').length,
                failed_jobs: jobs.filter(job => job.conclusion === 'failure').length,
                failure_patterns: patterns
            });
        }

        // Save report
        const reportPath = 'docs/pr374-monitoring-report.json';
        fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
        
        return report;
    }

    displayReport(report) {
        this.log('\n📊 PR #374 FIXES MONITORING REPORT', 'analysis');
        this.log('='.repeat(60), 'info');
        
        this.log(`Monitoring Duration: ${report.monitoring_duration} minutes`, 'info');
        this.log(`Total Workflow Runs: ${report.total_runs}`, 'info');
        this.log(`Success Rate: ${report.success_rate}%`, report.success_rate >= 80 ? 'success' : 'warning');
        
        if (report.runs_analysis.length > 0) {
            this.log('\n📋 Recent Runs Analysis:', 'info');
            
            report.runs_analysis.forEach((run, index) => {
                const status = run.conclusion === 'success' ? '✅' : 
                              run.conclusion === 'failure' ? '❌' : '⏳';
                
                this.log(`  ${status} Run #${run.run_id}: ${run.successful_jobs}/${run.total_jobs} jobs successful`, 'info');
                
                if (run.failed_jobs > 0) {
                    const topFailures = Object.entries(run.failure_patterns)
                        .filter(([_, count]) => count > 0)
                        .sort(([_, a], [__, b]) => b - a)
                        .slice(0, 3);
                    
                    if (topFailures.length > 0) {
                        this.log(`    Top failures: ${topFailures.map(([pattern, count]) => `${pattern}(${count})`).join(', ')}`, 'warning');
                    }
                }
            });
        }
        
        this.log('\n🔍 Overall Failure Patterns:', 'info');
        const sortedPatterns = Object.entries(report.overall_patterns)
            .filter(([_, count]) => count > 0)
            .sort(([_, a], [__, b]) => b - a);
        
        if (sortedPatterns.length > 0) {
            sortedPatterns.forEach(([pattern, count]) => {
                this.log(`  ${pattern}: ${count} occurrences`, count > 5 ? 'warning' : 'info');
            });
        } else {
            this.log('  No significant failure patterns detected! 🎉', 'success');
        }
        
        this.log('='.repeat(60), 'info');
        
        // Recommendations
        if (report.success_rate < 80) {
            this.log('\n⚠️ RECOMMENDATIONS:', 'warning');
            this.log('- Success rate below target (80%). Consider additional fixes.', 'warning');
            
            if (sortedPatterns.length > 0) {
                const topPattern = sortedPatterns[0][0];
                this.log(`- Focus on fixing: ${topPattern} (most common failure)`, 'warning');
            }
        } else if (report.success_rate >= 95) {
            this.log('\n🎉 EXCELLENT RESULTS:', 'success');
            this.log('- Success rate meets/exceeds target (95%)!', 'success');
            this.log('- Workflow fixes are highly effective.', 'success');
        } else {
            this.log('\n✅ GOOD RESULTS:', 'success');
            this.log('- Success rate is acceptable (80-95%).', 'success');
            this.log('- Minor optimizations may be beneficial.', 'info');
        }
    }

    async monitorCycle() {
        this.log(`Starting monitoring cycle for branch: ${this.branchName}`, 'monitoring');
        
        const runs = await this.getWorkflowRuns();
        
        if (runs.length === 0) {
            this.log('No workflow runs found for this branch', 'warning');
            return false;
        }

        const latestRun = runs[0];
        this.log(`Latest run: #${latestRun.databaseId} - Status: ${latestRun.status} - Conclusion: ${latestRun.conclusion}`, 'info');
        
        // Check if we have enough data for analysis
        const completedRuns = runs.filter(run => run.status === 'completed');
        
        if (completedRuns.length >= 3) {
            const report = await this.generateReport();
            this.displayReport(report);
            
            // Check if we should continue monitoring
            if (report.success_rate >= 95 && completedRuns.length >= 5) {
                this.log('🎉 Success rate target achieved with sufficient data. Monitoring complete!', 'success');
                return false; // Stop monitoring
            }
        } else {
            this.log(`Waiting for more completed runs (${completedRuns.length}/3 minimum)`, 'info');
        }
        
        return true; // Continue monitoring
    }

    async run() {
        this.log('🚀 Starting PR #374 Workflow Fixes Monitor', 'monitoring');
        this.log(`Branch: ${this.branchName}`, 'info');
        this.log(`Check Interval: ${this.checkInterval / 1000 / 60} minutes`, 'info');
        this.log(`Max Monitoring Time: ${this.maxMonitoringTime / 1000 / 60 / 60} hours`, 'info');
        this.log('='.repeat(60), 'info');

        try {
            // Check if GitHub CLI is available
            execSync('gh --version', { stdio: 'pipe' });
            this.log('GitHub CLI detected', 'success');
        } catch (error) {
            this.log('GitHub CLI not found. Please install gh CLI to use this monitor.', 'error');
            process.exit(1);
        }

        while (Date.now() - this.startTime < this.maxMonitoringTime) {
            try {
                const shouldContinue = await this.monitorCycle();
                
                if (!shouldContinue) {
                    break;
                }
                
                this.log(`Next check in ${this.checkInterval / 1000 / 60} minutes...`, 'info');
                await new Promise(resolve => setTimeout(resolve, this.checkInterval));
                
            } catch (error) {
                this.log(`Monitoring error: ${error.message}`, 'error');
                this.log('Continuing monitoring...', 'info');
                await new Promise(resolve => setTimeout(resolve, 60000)); // Wait 1 minute on error
            }
        }

        this.log('📊 Monitoring session completed', 'monitoring');
        
        // Generate final report
        try {
            const finalReport = await this.generateReport();
            this.log('\n📋 FINAL MONITORING REPORT:', 'analysis');
            this.displayReport(finalReport);
        } catch (error) {
            this.log(`Failed to generate final report: ${error.message}`, 'error');
        }
    }
}

// Run monitor if this script is executed directly
if (require.main === module) {
    const monitor = new PR374FixesMonitor();
    monitor.run().catch(error => {
        console.error('❌ Monitor failed:', error.message);
        process.exit(1);
    });
}

module.exports = PR374FixesMonitor;
