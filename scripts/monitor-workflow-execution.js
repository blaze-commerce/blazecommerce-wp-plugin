#!/usr/bin/env node

/**
 * Workflow Execution Monitor
 * 
 * Monitors GitHub Actions workflow execution and provides real-time status updates
 * for the PHP test workflow fixes implementation.
 */

const { execSync } = require('child_process');
const fs = require('fs');

class WorkflowMonitor {
    constructor() {
        this.workflowName = 'Tests';
        this.checkInterval = 30000; // 30 seconds
        this.maxChecks = 40; // 20 minutes total
        this.checksPerformed = 0;
    }

    log(message, type = 'info') {
        const timestamp = new Date().toISOString().split('T')[1].split('.')[0];
        const prefix = {
            'info': '📋 INFO',
            'success': '✅ SUCCESS',
            'warning': '⚠️ WARNING',
            'error': '❌ ERROR',
            'running': '🔄 RUNNING',
            'pending': '⏳ PENDING'
        }[type] || '📋 INFO';
        
        console.log(`[${timestamp}] ${prefix}: ${message}`);
    }

    async getWorkflowRuns() {
        try {
            // Get recent workflow runs for the current branch
            const result = execSync('gh run list --workflow="Tests" --limit=5 --json=status,conclusion,createdAt,url,databaseId', {
                encoding: 'utf8',
                stdio: 'pipe'
            });
            
            return JSON.parse(result);
        } catch (error) {
            this.log(`Failed to get workflow runs: ${error.message}`, 'error');
            return [];
        }
    }

    async getWorkflowJobs(runId) {
        try {
            const result = execSync(`gh run view ${runId} --json=jobs`, {
                encoding: 'utf8',
                stdio: 'pipe'
            });
            
            const data = JSON.parse(result);
            return data.jobs || [];
        } catch (error) {
            this.log(`Failed to get workflow jobs: ${error.message}`, 'error');
            return [];
        }
    }

    formatDuration(startTime, endTime) {
        if (!startTime) return 'N/A';
        
        const start = new Date(startTime);
        const end = endTime ? new Date(endTime) : new Date();
        const duration = Math.round((end - start) / 1000);
        
        const minutes = Math.floor(duration / 60);
        const seconds = duration % 60;
        
        return `${minutes}m ${seconds}s`;
    }

    async monitorLatestRun() {
        this.log('Starting workflow execution monitoring...', 'info');
        this.log(`Monitoring interval: ${this.checkInterval / 1000}s`, 'info');
        this.log(`Maximum monitoring time: ${(this.maxChecks * this.checkInterval) / 60000}m`, 'info');
        this.log('='.repeat(80), 'info');

        while (this.checksPerformed < this.maxChecks) {
            try {
                const runs = await this.getWorkflowRuns();
                
                if (runs.length === 0) {
                    this.log('No workflow runs found', 'warning');
                    break;
                }

                const latestRun = runs[0];
                const status = latestRun.status;
                const conclusion = latestRun.conclusion;
                const runId = latestRun.databaseId;
                
                this.log(`\nWorkflow Run #${runId}`, 'info');
                this.log(`Status: ${status}`, status === 'completed' ? 'success' : 'running');
                
                if (conclusion) {
                    this.log(`Conclusion: ${conclusion}`, conclusion === 'success' ? 'success' : 'error');
                }

                // Get job details
                const jobs = await this.getWorkflowJobs(runId);
                
                if (jobs.length > 0) {
                    this.log('\nJob Status:', 'info');
                    
                    for (const job of jobs) {
                        const jobStatus = job.status;
                        const jobConclusion = job.conclusion;
                        const duration = this.formatDuration(job.startedAt, job.completedAt);
                        
                        let statusIcon = '⏳';
                        if (jobStatus === 'completed') {
                            statusIcon = jobConclusion === 'success' ? '✅' : '❌';
                        } else if (jobStatus === 'in_progress') {
                            statusIcon = '🔄';
                        }
                        
                        this.log(`  ${statusIcon} ${job.name} (${duration})`, 'info');
                        
                        // Show matrix job details if available
                        if (job.name.includes('(') && job.name.includes(')')) {
                            const matrixInfo = job.name.match(/\((.*?)\)/);
                            if (matrixInfo) {
                                this.log(`    Matrix: ${matrixInfo[1]}`, 'info');
                            }
                        }
                    }
                }

                // Check if workflow is complete
                if (status === 'completed') {
                    this.log('\n' + '='.repeat(80), 'info');
                    
                    if (conclusion === 'success') {
                        this.log('🎉 Workflow completed successfully!', 'success');
                        this.log('All PHP test workflow fixes are working correctly.', 'success');
                    } else {
                        this.log('❌ Workflow completed with failures', 'error');
                        this.log('Please check the workflow logs for details.', 'error');
                        this.log(`Workflow URL: ${latestRun.url}`, 'info');
                    }
                    
                    // Generate summary report
                    this.generateSummaryReport(jobs, latestRun);
                    break;
                }

                this.checksPerformed++;
                
                if (this.checksPerformed < this.maxChecks) {
                    this.log(`\nNext check in ${this.checkInterval / 1000}s... (${this.checksPerformed}/${this.maxChecks})`, 'info');
                    await new Promise(resolve => setTimeout(resolve, this.checkInterval));
                }

            } catch (error) {
                this.log(`Monitoring error: ${error.message}`, 'error');
                break;
            }
        }

        if (this.checksPerformed >= this.maxChecks) {
            this.log('⏰ Maximum monitoring time reached', 'warning');
            this.log('Workflow may still be running. Check GitHub Actions for current status.', 'info');
        }
    }

    generateSummaryReport(jobs, run) {
        this.log('\n📊 EXECUTION SUMMARY REPORT', 'info');
        this.log('='.repeat(80), 'info');
        
        const totalJobs = jobs.length;
        const successfulJobs = jobs.filter(job => job.conclusion === 'success').length;
        const failedJobs = jobs.filter(job => job.conclusion === 'failure').length;
        const cancelledJobs = jobs.filter(job => job.conclusion === 'cancelled').length;
        
        this.log(`Total Jobs: ${totalJobs}`, 'info');
        this.log(`Successful: ${successfulJobs}`, successfulJobs > 0 ? 'success' : 'info');
        this.log(`Failed: ${failedJobs}`, failedJobs > 0 ? 'error' : 'info');
        this.log(`Cancelled: ${cancelledJobs}`, cancelledJobs > 0 ? 'warning' : 'info');
        
        const successRate = totalJobs > 0 ? Math.round((successfulJobs / totalJobs) * 100) : 0;
        this.log(`Success Rate: ${successRate}%`, successRate >= 80 ? 'success' : 'warning');
        
        // Matrix job analysis
        const matrixJobs = jobs.filter(job => job.name.includes('(') && job.name.includes(')'));
        if (matrixJobs.length > 0) {
            this.log('\n📋 Matrix Job Analysis:', 'info');
            
            const phpVersions = new Set();
            const wpVersions = new Set();
            
            matrixJobs.forEach(job => {
                const matrixInfo = job.name.match(/\((.*?)\)/);
                if (matrixInfo) {
                    const parts = matrixInfo[1].split(',').map(p => p.trim());
                    parts.forEach(part => {
                        if (part.startsWith('php-')) {
                            phpVersions.add(part.replace('php-', ''));
                        } else if (part.startsWith('wp-')) {
                            wpVersions.add(part.replace('wp-', ''));
                        }
                    });
                }
            });
            
            this.log(`PHP Versions Tested: ${Array.from(phpVersions).join(', ')}`, 'info');
            this.log(`WordPress Versions Tested: ${Array.from(wpVersions).join(', ')}`, 'info');
        }
        
        this.log('\n🔗 Workflow URL:', 'info');
        this.log(run.url, 'info');
        this.log('='.repeat(80), 'info');
    }

    async run() {
        try {
            // Check if GitHub CLI is available
            execSync('gh --version', { stdio: 'pipe' });
            this.log('GitHub CLI detected', 'success');
        } catch (error) {
            this.log('GitHub CLI not found. Please install gh CLI to use this monitor.', 'error');
            this.log('Visit: https://cli.github.com/', 'info');
            process.exit(1);
        }

        await this.monitorLatestRun();
    }
}

// Run monitor if this script is executed directly
if (require.main === module) {
    const monitor = new WorkflowMonitor();
    monitor.run().catch(error => {
        console.error('❌ Monitor failed:', error.message);
        process.exit(1);
    });
}

module.exports = WorkflowMonitor;
