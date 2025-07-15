#!/usr/bin/env node

/**
 * Comprehensive Auto-Approval Issue Testing and Monitoring Script
 * 
 * This script continuously monitors the auto-approval workflow issue where
 * the bot review shows approved status but auto-approval is being skipped.
 * 
 * Usage: node scripts/test-auto-approval-issue.js
 */

const { Octokit } = require('@octokit/rest');
const fs = require('fs');
const path = require('path');

// Configuration
const CONFIG = {
    owner: 'blaze-commerce',
    repo: 'blazecommerce-wp-plugin',
    targetWorkflowRun: '16276160042',
    targetJob: '45955443148',
    monitoringInterval: 30000, // 30 seconds
    maxMonitoringTime: 1800000, // 30 minutes
    logFile: 'auto-approval-test-results.log'
};

// Initialize GitHub client
const octokit = new Octokit({
    auth: process.env.GITHUB_TOKEN || process.env.BOT_GITHUB_TOKEN
});

class AutoApprovalTester {
    constructor() {
        this.startTime = new Date();
        this.testResults = [];
        this.logFile = path.join(__dirname, '..', CONFIG.logFile);
        this.initializeLog();
    }

    initializeLog() {
        const header = `
=== AUTO-APPROVAL ISSUE TESTING LOG ===
Start Time: ${this.startTime.toISOString()}
Target Workflow Run: ${CONFIG.targetWorkflowRun}
Target Job: ${CONFIG.targetJob}
Repository: ${CONFIG.owner}/${CONFIG.repo}

ISSUE DESCRIPTION:
Auto-approval is being skipped even though bot review shows approved status.
This script will continuously monitor and test the issue until resolved.

========================================

`;
        fs.writeFileSync(this.logFile, header);
        console.log(`📝 Log file initialized: ${this.logFile}`);
    }

    log(message, level = 'INFO') {
        const timestamp = new Date().toISOString();
        const logEntry = `[${timestamp}] [${level}] ${message}\n`;
        
        fs.appendFileSync(this.logFile, logEntry);
        
        const colorMap = {
            'INFO': '\x1b[36m',    // Cyan
            'SUCCESS': '\x1b[32m', // Green
            'WARNING': '\x1b[33m', // Yellow
            'ERROR': '\x1b[31m',   // Red
            'CRITICAL': '\x1b[35m' // Magenta
        };
        
        console.log(`${colorMap[level] || ''}${logEntry.trim()}\x1b[0m`);
    }

    async analyzeWorkflowRun(runId) {
        try {
            this.log(`🔍 Analyzing workflow run: ${runId}`);
            
            // Get workflow run details
            const { data: run } = await octokit.rest.actions.getWorkflowRun({
                owner: CONFIG.owner,
                repo: CONFIG.repo,
                run_id: runId
            });

            this.log(`📊 Workflow: ${run.name}`);
            this.log(`📅 Created: ${run.created_at}`);
            this.log(`📈 Status: ${run.status}`);
            this.log(`🎯 Conclusion: ${run.conclusion}`);

            // Get jobs for this run
            const { data: jobs } = await octokit.rest.actions.listJobsForWorkflowRun({
                owner: CONFIG.owner,
                repo: CONFIG.repo,
                run_id: runId
            });

            this.log(`🔧 Jobs found: ${jobs.total_count}`);
            
            for (const job of jobs.jobs) {
                this.log(`  📋 Job: ${job.name} - Status: ${job.status} - Conclusion: ${job.conclusion}`);
                
                if (job.id.toString() === CONFIG.targetJob) {
                    await this.analyzeJobDetails(job);
                }
            }

            return { run, jobs: jobs.jobs };
        } catch (error) {
            this.log(`❌ Error analyzing workflow run: ${error.message}`, 'ERROR');
            throw error;
        }
    }

    async analyzeJobDetails(job) {
        try {
            this.log(`🔍 Analyzing target job: ${job.name} (${job.id})`);
            
            // Get job logs
            try {
                const { data: logs } = await octokit.rest.actions.downloadJobLogsForWorkflowRun({
                    owner: CONFIG.owner,
                    repo: CONFIG.repo,
                    job_id: job.id
                });
                
                this.log(`📜 Job logs retrieved (${logs.length} bytes)`);
                
                // Analyze logs for specific patterns
                const logContent = logs.toString();
                this.analyzeJobLogs(logContent);
                
            } catch (logError) {
                this.log(`⚠️ Could not retrieve job logs: ${logError.message}`, 'WARNING');
            }

        } catch (error) {
            this.log(`❌ Error analyzing job details: ${error.message}`, 'ERROR');
        }
    }

    analyzeJobLogs(logContent) {
        this.log(`🔍 Analyzing job logs for auto-approval patterns...`);
        
        const patterns = [
            { name: 'Should Run Check', regex: /should_run.*?(\w+)/gi },
            { name: 'PR Number Detection', regex: /PR.*?(\d+)/gi },
            { name: 'Claude Approval Status', regex: /Claude.*?approval.*?(\w+)/gi },
            { name: 'Bot Authentication', regex: /Authenticated.*?as.*?(\w+)/gi },
            { name: 'Review Creation', regex: /Successfully created.*?review/gi },
            { name: 'Error Messages', regex: /ERROR.*?:(.*)/gi },
            { name: 'Skip Reasons', regex: /skipping.*?because.*?(.*)/gi }
        ];

        for (const pattern of patterns) {
            const matches = [...logContent.matchAll(pattern.regex)];
            if (matches.length > 0) {
                this.log(`🎯 ${pattern.name}: Found ${matches.length} matches`);
                matches.forEach((match, index) => {
                    this.log(`  ${index + 1}. ${match[0]}`);
                });
            } else {
                this.log(`❌ ${pattern.name}: No matches found`);
            }
        }
    }

    async checkPRStatus(prNumber) {
        try {
            this.log(`🔍 Checking PR #${prNumber} status...`);
            
            // Get PR details
            const { data: pr } = await octokit.rest.pulls.get({
                owner: CONFIG.owner,
                repo: CONFIG.repo,
                pull_number: prNumber
            });

            this.log(`📋 PR #${prNumber}: ${pr.title}`);
            this.log(`📈 State: ${pr.state}`);
            this.log(`📅 Updated: ${pr.updated_at}`);

            // Get PR reviews
            const { data: reviews } = await octokit.rest.pulls.listReviews({
                owner: CONFIG.owner,
                repo: CONFIG.repo,
                pull_number: prNumber
            });

            this.log(`👥 Reviews: ${reviews.length} total`);
            
            const botReviews = reviews.filter(review => 
                review.user.login.includes('blazecommerce') || 
                review.user.login.includes('claude')
            );
            
            this.log(`🤖 Bot Reviews: ${botReviews.length}`);
            
            botReviews.forEach((review, index) => {
                this.log(`  ${index + 1}. ${review.user.login} - ${review.state} - ${review.submitted_at}`);
            });

            // Get PR comments
            const { data: comments } = await octokit.rest.issues.listComments({
                owner: CONFIG.owner,
                repo: CONFIG.repo,
                issue_number: prNumber
            });

            const claudeComments = comments.filter(comment => 
                comment.user.login.includes('blazecommerce-automation-bot') &&
                comment.body.includes('FINAL VERDICT')
            );

            this.log(`💬 Claude Comments: ${claudeComments.length}`);
            
            claudeComments.forEach((comment, index) => {
                const verdict = comment.body.includes('APPROVED') ? 'APPROVED' : 
                               comment.body.includes('BLOCKED') ? 'BLOCKED' : 'UNKNOWN';
                this.log(`  ${index + 1}. ${verdict} - ${comment.created_at}`);
            });

            return { pr, reviews, comments: claudeComments };
        } catch (error) {
            this.log(`❌ Error checking PR status: ${error.message}`, 'ERROR');
            throw error;
        }
    }

    async createTestPR() {
        try {
            this.log(`🧪 Creating test PR to reproduce the issue...`);
            
            const testContent = `# Auto-Approval Test PR

This PR is created to test and fix the auto-approval issue.

## Test Details
- Created: ${new Date().toISOString()}
- Purpose: Reproduce and fix auto-approval skipping issue
- Expected: Claude should approve and bot should auto-approve

## Changes
- Simple documentation update to trigger workflows
- Should result in APPROVED status from Claude
- Should trigger auto-approval from bot

---
Test PR for auto-approval issue investigation.
`;

            // Create a simple test file
            const testFileName = `test-auto-approval-${Date.now()}.md`;
            
            const { data: pr } = await octokit.rest.pulls.create({
                owner: CONFIG.owner,
                repo: CONFIG.repo,
                title: '🧪 TEST: Auto-Approval Issue Investigation',
                head: 'test-auto-approval-fix',
                base: 'main',
                body: testContent
            });

            this.log(`✅ Test PR created: #${pr.number}`, 'SUCCESS');
            this.log(`🔗 URL: ${pr.html_url}`);
            
            return pr;
        } catch (error) {
            this.log(`❌ Error creating test PR: ${error.message}`, 'ERROR');
            throw error;
        }
    }

    async monitorWorkflows() {
        this.log(`🔄 Starting continuous workflow monitoring...`);
        
        const startTime = Date.now();
        let iteration = 0;
        
        while (Date.now() - startTime < CONFIG.maxMonitoringTime) {
            iteration++;
            this.log(`🔄 Monitoring iteration ${iteration}`);
            
            try {
                // Check recent workflow runs
                const { data: runs } = await octokit.rest.actions.listWorkflowRuns({
                    owner: CONFIG.owner,
                    repo: CONFIG.repo,
                    per_page: 10
                });

                this.log(`📊 Found ${runs.total_count} recent workflow runs`);
                
                for (const run of runs.workflow_runs.slice(0, 5)) {
                    if (run.name.includes('Claude') || run.name.includes('Approval')) {
                        this.log(`  🔍 ${run.name} - ${run.status} - ${run.created_at}`);
                        
                        if (run.status === 'completed' && run.conclusion === 'skipped') {
                            this.log(`⚠️ FOUND SKIPPED WORKFLOW: ${run.name} (${run.id})`, 'WARNING');
                            await this.analyzeWorkflowRun(run.id);
                        }
                    }
                }
                
            } catch (error) {
                this.log(`❌ Error in monitoring iteration: ${error.message}`, 'ERROR');
            }
            
            // Wait before next iteration
            await new Promise(resolve => setTimeout(resolve, CONFIG.monitoringInterval));
        }
        
        this.log(`⏰ Monitoring completed after ${CONFIG.maxMonitoringTime / 1000} seconds`);
    }

    async runComprehensiveTest() {
        try {
            this.log(`🚀 Starting comprehensive auto-approval issue test...`, 'SUCCESS');
            
            // Step 1: Analyze the specific problematic workflow run
            this.log(`📋 Step 1: Analyzing problematic workflow run...`);
            await this.analyzeWorkflowRun(CONFIG.targetWorkflowRun);
            
            // Step 2: Check the related PR status
            this.log(`📋 Step 2: Checking related PR status...`);
            await this.checkPRStatus(394); // PR #394 from the workflow run
            
            // Step 3: Start continuous monitoring
            this.log(`📋 Step 3: Starting continuous monitoring...`);
            await this.monitorWorkflows();
            
            this.log(`✅ Comprehensive test completed!`, 'SUCCESS');
            
        } catch (error) {
            this.log(`❌ Comprehensive test failed: ${error.message}`, 'CRITICAL');
            throw error;
        }
    }
}

// Main execution
async function main() {
    console.log('🔧 AUTO-APPROVAL ISSUE TESTING AND MONITORING');
    console.log('===========================================');
    
    const tester = new AutoApprovalTester();
    
    try {
        await tester.runComprehensiveTest();
    } catch (error) {
        console.error('❌ Test failed:', error.message);
        process.exit(1);
    }
}

// Run if called directly
if (require.main === module) {
    main();
}

module.exports = AutoApprovalTester;
