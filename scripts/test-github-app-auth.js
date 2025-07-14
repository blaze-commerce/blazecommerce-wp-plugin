#!/usr/bin/env node

/**
 * GitHub App Authentication Test Script
 * 
 * This script tests the GitHub App authentication setup for the auto-version workflow.
 * Run this locally to verify your GitHub App configuration before deploying.
 */

const { createAppAuth } = require('@octokit/auth-app');
const { Octokit } = require('@octokit/rest');

async function testGitHubAppAuth() {
    console.log('🔍 Testing GitHub App Authentication...\n');

    // Check environment variables
    const appId = process.env.BC_GITHUB_APP_ID;
    const privateKey = process.env.BC_GITHUB_APP_PRIVATE_KEY;
    const owner = 'blaze-commerce';
    const repo = 'blazecommerce-wp-plugin';

    if (!appId) {
        console.error('❌ BC_GITHUB_APP_ID environment variable not set');
        process.exit(1);
    }

    if (!privateKey) {
        console.error('❌ BC_GITHUB_APP_PRIVATE_KEY environment variable not set');
        process.exit(1);
    }

    console.log(`✅ App ID: ${appId}`);
    console.log(`✅ Private Key: ${privateKey.length} characters\n`);

    try {
        // Create authentication
        const auth = createAppAuth({
            appId: appId,
            privateKey: privateKey,
        });

        // Get installation access token
        console.log('🔑 Getting installation access token...');
        const installationAuth = await auth({
            type: 'installation',
            installationId: undefined, // Will be auto-detected
            repositoryNames: [repo],
        });

        console.log('✅ Installation token obtained\n');

        // Create Octokit instance
        const octokit = new Octokit({
            auth: installationAuth.token,
        });

        // Test repository access
        console.log('📂 Testing repository access...');
        const { data: repository } = await octokit.rest.repos.get({
            owner,
            repo,
        });

        console.log(`✅ Repository access successful: ${repository.full_name}`);
        console.log(`   - Default branch: ${repository.default_branch}`);
        console.log(`   - Permissions: ${JSON.stringify(repository.permissions)}\n`);

        // Test contents permission
        console.log('📝 Testing contents permission...');
        const { data: contents } = await octokit.rest.repos.getContent({
            owner,
            repo,
            path: 'package.json',
        });

        console.log('✅ Contents read permission working');
        console.log(`   - File: package.json (${contents.size} bytes)\n`);

        // Test pull requests permission
        console.log('🔍 Testing pull requests permission...');
        const { data: pulls } = await octokit.rest.pulls.list({
            owner,
            repo,
            state: 'open',
            per_page: 1,
        });

        console.log(`✅ Pull requests read permission working`);
        console.log(`   - Open PRs: ${pulls.length}\n`);

        // Test actions permission
        console.log('⚡ Testing actions permission...');
        const { data: workflows } = await octokit.rest.actions.listRepoWorkflows({
            owner,
            repo,
        });

        console.log(`✅ Actions read permission working`);
        console.log(`   - Workflows: ${workflows.total_count}\n`);

        console.log('🎉 All tests passed! GitHub App authentication is properly configured.');
        console.log('\n📋 Summary:');
        console.log('   ✅ App authentication successful');
        console.log('   ✅ Repository access confirmed');
        console.log('   ✅ Contents permission verified');
        console.log('   ✅ Pull requests permission verified');
        console.log('   ✅ Actions permission verified');
        console.log('\n🚀 Your auto-version workflow should work correctly with this GitHub App setup.');

    } catch (error) {
        console.error('❌ GitHub App authentication test failed:');
        console.error(`   Error: ${error.message}`);
        
        if (error.status === 401) {
            console.error('\n💡 Troubleshooting tips:');
            console.error('   - Verify your App ID is correct');
            console.error('   - Check that your private key is complete and properly formatted');
            console.error('   - Ensure the GitHub App is installed in the blaze-commerce organization');
        } else if (error.status === 403) {
            console.error('\n💡 Troubleshooting tips:');
            console.error('   - Check that the GitHub App has the required permissions');
            console.error('   - Verify the app is installed on the correct repository');
            console.error('   - Ensure the app has Contents: Write, Pull requests: Read, Actions: Read permissions');
        }
        
        process.exit(1);
    }
}

// Run the test
if (require.main === module) {
    testGitHubAppAuth();
}

module.exports = { testGitHubAppAuth };
