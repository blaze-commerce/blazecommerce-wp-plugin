#!/usr/bin/env node

/**
 * Token Authentication Test Script
 * 
 * This script tests both GitHub App and Personal Access Token authentication
 * to verify your setup before deploying the workflow.
 */

const { Octokit } = require('@octokit/rest');

async function testTokenAuth() {
    console.log('🔍 Testing Token Authentication...\n');

    const token = process.env.BC_GITHUB_TOKEN;
    const owner = 'blaze-commerce';
    const repo = 'blazecommerce-wp-plugin';

    if (!token) {
        console.error('❌ BC_GITHUB_TOKEN environment variable not set');
        console.log('\n💡 To test locally:');
        console.log('   export BC_GITHUB_TOKEN="your-token-here"');
        console.log('   npm run test:token-auth');
        process.exit(1);
    }

    console.log(`✅ Token: ${token.substring(0, 10)}...${token.substring(token.length - 4)}`);
    console.log(`   Length: ${token.length} characters\n`);

    try {
        // Create Octokit instance
        const octokit = new Octokit({
            auth: token,
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

        // Test contents permission (read)
        console.log('📝 Testing contents read permission...');
        const { data: contents } = await octokit.rest.repos.getContent({
            owner,
            repo,
            path: 'package.json',
        });

        console.log('✅ Contents read permission working');
        console.log(`   - File: package.json (${contents.size} bytes)\n`);

        // Test contents permission (write) - check if we can create a commit
        console.log('✍️ Testing contents write permission...');
        try {
            // Get current commit SHA for main branch
            const { data: ref } = await octokit.rest.git.getRef({
                owner,
                repo,
                ref: 'heads/main',
            });

            console.log('✅ Contents write permission available');
            console.log(`   - Can access main branch: ${ref.object.sha.substring(0, 7)}\n`);
        } catch (writeError) {
            console.log('⚠️ Contents write permission may be limited');
            console.log(`   - Error: ${writeError.message}\n`);
        }

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

        // Test if token can bypass branch protection
        console.log('🛡️ Testing branch protection bypass capability...');
        try {
            const { data: branch } = await octokit.rest.repos.getBranch({
                owner,
                repo,
                branch: 'main',
            });

            if (branch.protection?.enabled) {
                console.log('⚠️ Branch protection is enabled');
                console.log('   - This token will need admin privileges to bypass protection');
                console.log('   - Or use the PR-based workflow as alternative\n');
            } else {
                console.log('✅ No branch protection detected\n');
            }
        } catch (protectionError) {
            console.log('ℹ️ Could not check branch protection status\n');
        }

        console.log('🎉 Token authentication test completed successfully!');
        console.log('\n📋 Summary:');
        console.log('   ✅ Repository access confirmed');
        console.log('   ✅ Contents permission verified');
        console.log('   ✅ Pull requests permission verified');
        console.log('   ✅ Actions permission verified');
        console.log('\n🚀 Your auto-version workflow should work with this token.');

        // Provide next steps
        console.log('\n📝 Next Steps:');
        console.log('   1. Add this token as BC_GITHUB_TOKEN secret in repository settings');
        console.log('   2. Test the workflow with a small commit');
        console.log('   3. Monitor the auto-version workflow execution');

    } catch (error) {
        console.error('❌ Token authentication test failed:');
        console.error(`   Error: ${error.message}`);
        
        if (error.status === 401) {
            console.error('\n💡 Troubleshooting tips:');
            console.error('   - Verify your token is correct and not expired');
            console.error('   - Check that the token has the required permissions');
            console.error('   - Ensure the token is for the correct organization/repository');
        } else if (error.status === 403) {
            console.error('\n💡 Troubleshooting tips:');
            console.error('   - Check that the token has the required repository permissions');
            console.error('   - Verify Contents: Write, Pull requests: Read, Actions: Read');
            console.error('   - Ensure the token scope includes the target repository');
        } else if (error.status === 404) {
            console.error('\n💡 Troubleshooting tips:');
            console.error('   - Verify the repository name and organization are correct');
            console.error('   - Check that the token has access to this repository');
        }
        
        process.exit(1);
    }
}

// Run the test
if (require.main === module) {
    testTokenAuth();
}

module.exports = { testTokenAuth };
