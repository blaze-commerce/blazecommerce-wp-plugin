#!/usr/bin/env node

/**
 * GitHub App Configuration Verification Script
 * 
 * This script helps verify that your GitHub App is properly configured
 * for the auto-version workflow to bypass branch protection rules.
 */

const { Octokit } = require('@octokit/rest');
const { createAppAuth } = require('@octokit/auth-app');

async function verifyGitHubAppConfig() {
  console.log('🔍 GitHub App Configuration Verification');
  console.log('==========================================\n');

  // Check environment variables
  const appId = process.env.BC_GITHUB_APP_ID;
  const privateKey = process.env.BC_GITHUB_APP_PRIVATE_KEY;
  const owner = 'blaze-commerce';
  const repo = 'blazecommerce-wp-plugin';

  if (!appId || !privateKey) {
    console.error('❌ Missing required environment variables:');
    console.error('   - BC_GITHUB_APP_ID');
    console.error('   - BC_GITHUB_APP_PRIVATE_KEY');
    console.error('\nPlease set these environment variables and try again.');
    process.exit(1);
  }

  try {
    // Create GitHub App authentication
    const auth = createAppAuth({
      appId: appId,
      privateKey: privateKey,
    });

    // Get installation access token
    const installationAuth = await auth({
      type: 'installation',
      installationId: await getInstallationId(appId, privateKey, owner),
    });

    const octokit = new Octokit({
      auth: installationAuth.token,
    });

    console.log('✅ GitHub App authentication successful\n');

    // Check app permissions
    await checkAppPermissions(octokit, owner, repo);

    // Check branch protection rules
    await checkBranchProtection(octokit, owner, repo);

    // Check repository access
    await checkRepositoryAccess(octokit, owner, repo);

    console.log('\n🎉 Verification complete!');

  } catch (error) {
    console.error('❌ Verification failed:', error.message);
    process.exit(1);
  }
}

async function getInstallationId(appId, privateKey, owner) {
  const appOctokit = new Octokit({
    authStrategy: createAppAuth,
    auth: {
      appId: appId,
      privateKey: privateKey,
    },
  });

  const { data: installations } = await appOctokit.rest.apps.listInstallations();
  const installation = installations.find(inst => 
    inst.account.login === owner
  );

  if (!installation) {
    throw new Error(`GitHub App not installed for organization: ${owner}`);
  }

  return installation.id;
}

async function checkAppPermissions(octokit, owner, repo) {
  console.log('📋 Checking GitHub App permissions...');

  try {
    const { data: installation } = await octokit.rest.apps.getRepoInstallation({
      owner,
      repo,
    });

    const permissions = installation.permissions;
    const requiredPermissions = {
      contents: 'write',
      metadata: 'read',
      pull_requests: 'write',
      actions: 'read',
    };

    console.log('   Current permissions:');
    for (const [perm, level] of Object.entries(permissions)) {
      const required = requiredPermissions[perm];
      const status = required && level === required ? '✅' : 
                    required ? '❌' : '📝';
      console.log(`   ${status} ${perm}: ${level}${required ? ` (required: ${required})` : ''}`);
    }

    // Check if all required permissions are met
    const missingPerms = Object.entries(requiredPermissions)
      .filter(([perm, level]) => permissions[perm] !== level);

    if (missingPerms.length > 0) {
      console.log('\n❌ Missing required permissions:');
      missingPerms.forEach(([perm, level]) => {
        console.log(`   - ${perm}: ${level}`);
      });
      return false;
    } else {
      console.log('✅ All required permissions are granted\n');
      return true;
    }

  } catch (error) {
    console.error('❌ Failed to check permissions:', error.message);
    return false;
  }
}

async function checkBranchProtection(octokit, owner, repo) {
  console.log('🛡️  Checking branch protection rules...');

  try {
    const { data: protection } = await octokit.rest.repos.getBranchProtection({
      owner,
      repo,
      branch: 'main',
    });

    console.log('   Branch protection is enabled');

    // Check required status checks
    if (protection.required_status_checks) {
      console.log(`   📊 Required status checks: ${protection.required_status_checks.contexts.length} checks`);
      protection.required_status_checks.contexts.forEach(check => {
        console.log(`      - ${check}`);
      });
    }

    // Check pull request requirements
    if (protection.required_pull_request_reviews) {
      console.log('   🔍 Pull request reviews required');
      
      if (protection.required_pull_request_reviews.bypass_pull_request_allowances) {
        const bypasses = protection.required_pull_request_reviews.bypass_pull_request_allowances;
        console.log('   ✅ Bypass allowances configured:');
        
        if (bypasses.apps && bypasses.apps.length > 0) {
          bypasses.apps.forEach(app => {
            console.log(`      - App: ${app.name} (${app.slug})`);
          });
        }
        
        if (bypasses.users && bypasses.users.length > 0) {
          bypasses.users.forEach(user => {
            console.log(`      - User: ${user.login}`);
          });
        }
      } else {
        console.log('   ⚠️  No bypass allowances configured');
      }
    }

    console.log('✅ Branch protection check complete\n');
    return true;

  } catch (error) {
    if (error.status === 404) {
      console.log('   ℹ️  No branch protection rules found');
      return true;
    } else {
      console.error('❌ Failed to check branch protection:', error.message);
      return false;
    }
  }
}

async function checkRepositoryAccess(octokit, owner, repo) {
  console.log('🔐 Checking repository access...');

  try {
    const { data: repository } = await octokit.rest.repos.get({
      owner,
      repo,
    });

    console.log(`   ✅ Repository access: ${repository.name}`);
    console.log(`   📝 Permissions: ${repository.permissions ? Object.keys(repository.permissions).join(', ') : 'Unknown'}`);
    
    return true;

  } catch (error) {
    console.error('❌ Failed to access repository:', error.message);
    return false;
  }
}

// Run the verification
if (require.main === module) {
  verifyGitHubAppConfig().catch(error => {
    console.error('💥 Unexpected error:', error);
    process.exit(1);
  });
}

module.exports = { verifyGitHubAppConfig };
