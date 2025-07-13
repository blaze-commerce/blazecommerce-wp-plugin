#!/usr/bin/env node

/**
 * Priority Dependency Checker
 * Checks if prerequisite workflows have completed before proceeding
 * Supports configurable timeouts and detailed status reporting
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const { Logger } = require('./file-change-analyzer');

/**
 * Priority Dependency Checker Class
 */
class PriorityDependencyChecker {
  constructor() {
    this.github = this.initializeGitHub();
    this.context = this.getContext();
    this.timeout = parseInt(process.env.DEPENDENCY_CHECK_TIMEOUT) || 300; // 5 minutes default
    this.pollInterval = parseInt(process.env.POLL_INTERVAL) || 30; // 30 seconds default
  }

  /**
   * Initialize GitHub context (mock for standalone usage)
   * In actual GitHub Actions, this would use @actions/github
   */
  initializeGitHub() {
    // This is a placeholder - in actual usage, this would be:
    // const github = require('@actions/github');
    // return github.getOctokit(process.env.GITHUB_TOKEN);
    
    return {
      rest: {
        actions: {
          listWorkflowRunsForRepo: async (params) => {
            Logger.debug(`Mock API call: listWorkflowRunsForRepo with params: ${JSON.stringify(params)}`);
            return { data: { workflow_runs: [] } };
          }
        }
      }
    };
  }

  /**
   * Get GitHub context
   */
  getContext() {
    return {
      repo: {
        owner: process.env.GITHUB_REPOSITORY_OWNER || 'blaze-commerce',
        repo: process.env.GITHUB_REPOSITORY?.split('/')[1] || 'test-repo'
      },
      sha: process.env.GITHUB_SHA || 'test-sha',
      payload: {
        pull_request: {
          head: {
            sha: process.env.GITHUB_SHA || 'test-sha'
          },
          number: parseInt(process.env.PR_NUMBER) || 1
        }
      }
    };
  }

  /**
   * Check if a specific workflow has completed
   * @param {string} workflowName - Name of the workflow to check
   * @param {string} targetSha - SHA to check for
   * @returns {Promise<Object>} Completion status and details
   */
  async checkWorkflowCompletion(workflowName, targetSha) {
    try {
      Logger.info(`Checking completion status for workflow: ${workflowName}`);
      Logger.debug(`Target SHA: ${targetSha}`);

      const { data: workflowRuns } = await this.github.rest.actions.listWorkflowRunsForRepo({
        owner: this.context.repo.owner,
        repo: this.context.repo.repo,
        head_sha: targetSha,
        status: 'completed',
        per_page: 50
      });

      Logger.debug(`Found ${workflowRuns.workflow_runs.length} completed workflow runs`);

      // Look for the specific workflow
      const targetWorkflow = workflowRuns.workflow_runs.find(run =>
        run.name === workflowName && run.head_sha === targetSha
      );

      if (targetWorkflow) {
        Logger.success(`Workflow '${workflowName}' completed with status: ${targetWorkflow.conclusion}`);
        return {
          completed: true,
          conclusion: targetWorkflow.conclusion,
          workflowId: targetWorkflow.id,
          htmlUrl: targetWorkflow.html_url,
          createdAt: targetWorkflow.created_at,
          updatedAt: targetWorkflow.updated_at
        };
      } else {
        Logger.debug(`Workflow '${workflowName}' not yet completed`);
        return {
          completed: false,
          reason: 'Workflow not found in completed runs'
        };
      }

    } catch (error) {
      Logger.error(`Failed to check workflow completion: ${error.message}`);
      throw error;
    }
  }

  /**
   * Wait for workflow completion with timeout
   * @param {string} workflowName - Name of the workflow to wait for
   * @param {string} targetSha - SHA to check for
   * @returns {Promise<Object>} Final completion status
   */
  async waitForWorkflowCompletion(workflowName, targetSha) {
    const startTime = Date.now();
    const timeoutMs = this.timeout * 1000;

    Logger.info(`Waiting for workflow '${workflowName}' to complete...`);
    Logger.info(`Timeout: ${this.timeout} seconds, Poll interval: ${this.pollInterval} seconds`);

    while (Date.now() - startTime < timeoutMs) {
      const status = await this.checkWorkflowCompletion(workflowName, targetSha);
      
      if (status.completed) {
        const elapsedSeconds = Math.round((Date.now() - startTime) / 1000);
        Logger.success(`Workflow completed after ${elapsedSeconds} seconds`);
        return status;
      }

      const remainingSeconds = Math.round((timeoutMs - (Date.now() - startTime)) / 1000);
      Logger.info(`Workflow not yet completed. Retrying in ${this.pollInterval}s (${remainingSeconds}s remaining)`);
      
      await this.sleep(this.pollInterval * 1000);
    }

    // Timeout reached
    const elapsedSeconds = Math.round((Date.now() - startTime) / 1000);
    Logger.warning(`Timeout reached after ${elapsedSeconds} seconds`);
    
    return {
      completed: false,
      reason: 'Timeout reached',
      elapsedSeconds
    };
  }

  /**
   * Check multiple workflow dependencies
   * @param {Array<string>} workflowNames - Array of workflow names to check
   * @param {string} targetSha - SHA to check for
   * @returns {Promise<Object>} Overall dependency status
   */
  async checkMultipleDependencies(workflowNames, targetSha) {
    Logger.info(`Checking dependencies for ${workflowNames.length} workflows`);
    
    const results = {};
    let allCompleted = true;
    
    for (const workflowName of workflowNames) {
      const status = await this.checkWorkflowCompletion(workflowName, targetSha);
      results[workflowName] = status;
      
      if (!status.completed) {
        allCompleted = false;
      }
    }

    return {
      allCompleted,
      results,
      summary: {
        total: workflowNames.length,
        completed: Object.values(results).filter(r => r.completed).length,
        pending: Object.values(results).filter(r => !r.completed).length
      }
    };
  }

  /**
   * Sleep utility
   * @param {number} ms - Milliseconds to sleep
   */
  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Output results in GitHub Actions format
   * @param {Object} result - Dependency check result
   */
  outputForGitHubActions(result) {
    if (result.allCompleted !== undefined) {
      // Multiple dependencies result
      console.log(`all_dependencies_completed=${result.allCompleted}`);
      console.log(`completed_count=${result.summary.completed}`);
      console.log(`pending_count=${result.summary.pending}`);
      console.log(`total_count=${result.summary.total}`);
    } else {
      // Single dependency result
      console.log(`dependency_completed=${result.completed}`);
      if (result.completed) {
        console.log(`dependency_conclusion=${result.conclusion}`);
        console.log(`dependency_workflow_id=${result.workflowId}`);
      } else {
        console.log(`dependency_reason=${result.reason}`);
      }
    }
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  async function main() {
    try {
      const checker = new PriorityDependencyChecker();
      
      // Get parameters from command line arguments
      const workflowName = process.argv[2];
      const targetSha = process.argv[3] || checker.context.sha;
      const shouldWait = process.argv[4] === 'true';

      if (!workflowName) {
        Logger.error('Workflow name is required as first argument');
        process.exit(1);
      }

      let result;
      if (shouldWait) {
        result = await checker.waitForWorkflowCompletion(workflowName, targetSha);
      } else {
        result = await checker.checkWorkflowCompletion(workflowName, targetSha);
      }

      checker.outputForGitHubActions(result);
      
      // Exit with error if dependency not completed (for workflow gating)
      process.exit(result.completed ? 0 : 1);
    } catch (error) {
      Logger.error(`Script execution failed: ${error.message}`);
      process.exit(1);
    }
  }

  main();
}

module.exports = { PriorityDependencyChecker };
