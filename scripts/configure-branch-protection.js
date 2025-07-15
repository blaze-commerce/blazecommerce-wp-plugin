#!/usr/bin/env node

/**
 * Branch Protection Configuration Script
 * 
 * This script helps configure branch protection rules to allow
 * the GitHub App to bypass restrictions for automated version bumps.
 */

const { Octokit } = require('@octokit/rest');

async function configureBranchProtection() {
  console.log('🛡️  Branch Protection Configuration');
  console.log('===================================\n');

  const token = process.env.GITHUB_TOKEN || process.env.BC_GITHUB_TOKEN;
  const owner = 'blaze-commerce';
  const repo = 'blazecommerce-wp-plugin';
  const branch = 'main';

  if (!token) {
    console.error('❌ Missing GITHUB_TOKEN or BC_GITHUB_TOKEN environment variable');
    console.error('   Please set one of these tokens with admin permissions');
    process.exit(1);
  }

  const octokit = new Octokit({ auth: token });

  try {
    // Get current branch protection
    let currentProtection = null;
    try {
      const { data } = await octokit.rest.repos.getBranchProtection({
        owner,
        repo,
        branch,
      });
      currentProtection = data;
      console.log('✅ Found existing branch protection rules\n');
    } catch (error) {
      if (error.status === 404) {
        console.log('ℹ️  No existing branch protection rules found\n');
      } else {
        throw error;
      }
    }

    // Get GitHub App information
    const appSlug = 'blazecommerce-automation-bot'; // Update this to match your app's slug
    
    console.log('🔧 Configuring branch protection with GitHub App bypass...\n');

    // Configure branch protection with bypass allowances
    const protectionConfig = {
      owner,
      repo,
      branch,
      required_status_checks: currentProtection?.required_status_checks || {
        strict: true,
        contexts: [
          'Priority 1: Claude AI Code Review',
          'Priority 3: Claude AI Approval Gate'
        ]
      },
      enforce_admins: false, // Allow admins to bypass
      required_pull_request_reviews: {
        required_approving_review_count: 1,
        dismiss_stale_reviews: true,
        require_code_owner_reviews: false,
        bypass_pull_request_allowances: {
          apps: [appSlug], // Allow GitHub App to bypass
          users: [], // Add specific users if needed
          teams: [] // Add specific teams if needed
        }
      },
      restrictions: null, // No push restrictions
      allow_force_pushes: false,
      allow_deletions: false,
      block_creations: false
    };

    await octokit.rest.repos.updateBranchProtection(protectionConfig);

    console.log('✅ Branch protection configured successfully!');
    console.log('\n📋 Configuration summary:');
    console.log(`   - Branch: ${branch}`);
    console.log(`   - Required status checks: ${protectionConfig.required_status_checks.contexts.length} checks`);
    console.log(`   - Pull request reviews required: ${protectionConfig.required_pull_request_reviews.required_approving_review_count}`);
    console.log(`   - GitHub App bypass: ${appSlug}`);
    console.log(`   - Enforce admins: ${protectionConfig.enforce_admins}`);

    // Verify the configuration
    console.log('\n🔍 Verifying configuration...');
    const { data: updatedProtection } = await octokit.rest.repos.getBranchProtection({
      owner,
      repo,
      branch,
    });

    const bypasses = updatedProtection.required_pull_request_reviews?.bypass_pull_request_allowances;
    if (bypasses?.apps?.some(app => app.slug === appSlug)) {
      console.log('✅ GitHub App bypass verified');
    } else {
      console.log('⚠️  GitHub App bypass not found - may need manual configuration');
    }

    console.log('\n🎉 Configuration complete!');
    console.log('\n💡 Next steps:');
    console.log('   1. Verify your GitHub App has the required permissions');
    console.log('   2. Test the auto-version workflow');
    console.log('   3. Monitor the workflow logs for successful bypass');

  } catch (error) {
    console.error('❌ Configuration failed:', error.message);
    
    if (error.status === 403) {
      console.error('\n💡 This error usually means:');
      console.error('   - The token doesn\'t have admin permissions');
      console.error('   - The repository has additional restrictions');
      console.error('   - Organization policies prevent this configuration');
    }
    
    process.exit(1);
  }
}

async function listCurrentProtection() {
  const token = process.env.GITHUB_TOKEN || process.env.BC_GITHUB_TOKEN;
  const owner = 'blaze-commerce';
  const repo = 'blazecommerce-wp-plugin';
  const branch = 'main';

  if (!token) {
    console.error('❌ Missing GITHUB_TOKEN or BC_GITHUB_TOKEN environment variable');
    process.exit(1);
  }

  const octokit = new Octokit({ auth: token });

  try {
    const { data: protection } = await octokit.rest.repos.getBranchProtection({
      owner,
      repo,
      branch,
    });

    console.log('📋 Current Branch Protection Configuration');
    console.log('=========================================\n');
    console.log(JSON.stringify(protection, null, 2));

  } catch (error) {
    if (error.status === 404) {
      console.log('ℹ️  No branch protection rules found');
    } else {
      console.error('❌ Failed to get branch protection:', error.message);
    }
  }
}

// Command line interface
const command = process.argv[2];

if (command === 'configure') {
  configureBranchProtection();
} else if (command === 'list') {
  listCurrentProtection();
} else {
  console.log('Usage:');
  console.log('  node configure-branch-protection.js configure  # Configure branch protection');
  console.log('  node configure-branch-protection.js list       # List current protection');
  console.log('\nEnvironment variables required:');
  console.log('  GITHUB_TOKEN or BC_GITHUB_TOKEN (with admin permissions)');
}
