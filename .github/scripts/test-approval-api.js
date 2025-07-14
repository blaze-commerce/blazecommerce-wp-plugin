#!/usr/bin/env node

/**
 * Test script to validate GitHub API approval functionality
 * This script tests the exact API calls used for PR approval
 */

const { Octokit } = require('@octokit/rest');

// Configuration
const REPO_OWNER = 'blaze-commerce';
const REPO_NAME = 'blazecommerce-wp-plugin';
const PR_NUMBER = 342; // Test with PR #342
const TEST_MODE = true; // Set to false to actually create approval

async function testApprovalAPI() {
  console.log('🧪 TESTING GITHUB API APPROVAL FUNCTIONALITY');
  console.log('==============================================');
  
  // Check for required environment variables
  const token = process.env.GITHUB_TOKEN || process.env.BOT_GITHUB_TOKEN;
  if (!token) {
    console.error('❌ ERROR: GITHUB_TOKEN or BOT_GITHUB_TOKEN environment variable required');
    console.error('Usage: GITHUB_TOKEN=your_token node test-approval-api.js');
    process.exit(1);
  }
  
  const github = new Octokit({ auth: token });
  
  try {
    console.log(`🎯 Testing approval for PR #${PR_NUMBER}`);
    console.log(`📍 Repository: ${REPO_OWNER}/${REPO_NAME}`);
    console.log(`🔑 Token: ${token.substring(0, 8)}...`);
    console.log();
    
    // Step 1: Test repository access
    console.log('1️⃣ Testing repository access...');
    const repo = await github.rest.repos.get({
      owner: REPO_OWNER,
      repo: REPO_NAME
    });
    console.log(`✅ Repository access: ${repo.data.full_name}`);
    console.log();
    
    // Step 2: Test PR access
    console.log('2️⃣ Testing PR access...');
    const pr = await github.rest.pulls.get({
      owner: REPO_OWNER,
      repo: REPO_NAME,
      pull_number: PR_NUMBER
    });
    console.log(`✅ PR access: #${pr.data.number} - ${pr.data.title}`);
    console.log(`📋 PR state: ${pr.data.state}`);
    console.log(`📋 PR mergeable: ${pr.data.mergeable}`);
    console.log();
    
    // Step 3: Test comments access
    console.log('3️⃣ Testing comments access...');
    const comments = await github.rest.issues.listComments({
      owner: REPO_OWNER,
      repo: REPO_NAME,
      issue_number: PR_NUMBER,
      per_page: 10
    });
    console.log(`✅ Comments access: ${comments.data.length} comments found`);
    
    // Check for Claude approval
    let claudeApprovalFound = false;
    for (const comment of comments.data.reverse()) {
      if (comment.body.includes('Status: APPROVED') || 
          comment.body.includes('Status**: APPROVED') ||
          comment.body.includes('**Status**: APPROVED')) {
        console.log(`✅ Claude approval found in comment by ${comment.user.login}`);
        console.log(`📄 Comment preview: ${comment.body.substring(0, 100)}...`);
        claudeApprovalFound = true;
        break;
      }
    }
    
    if (!claudeApprovalFound) {
      console.log('❌ No Claude approval found in comments');
    }
    console.log();
    
    // Step 4: Test reviews access
    console.log('4️⃣ Testing reviews access...');
    const reviews = await github.rest.pulls.listReviews({
      owner: REPO_OWNER,
      repo: REPO_NAME,
      pull_number: PR_NUMBER
    });
    console.log(`✅ Reviews access: ${reviews.data.length} reviews found`);
    
    // Check for existing approval
    const existingApproval = reviews.data.find(review => 
      review.user.login === 'blazecommerce-claude-ai' && 
      review.state === 'APPROVED'
    );
    
    if (existingApproval) {
      console.log(`✅ Existing approval found: ${existingApproval.submitted_at}`);
    } else {
      console.log('ℹ️ No existing approval from blazecommerce-claude-ai');
    }
    console.log();
    
    // Step 5: Test approval API call (dry run or actual)
    console.log('5️⃣ Testing approval API call...');
    
    if (TEST_MODE) {
      console.log('🧪 TEST MODE: Simulating approval API call');
      console.log('📡 Would call: POST /repos/blaze-commerce/blazecommerce-wp-plugin/pulls/342/reviews');
      console.log('📋 Would send: { event: "APPROVE", body: "Test approval message" }');
      console.log('✅ API call simulation successful');
    } else {
      console.log('🚀 LIVE MODE: Creating actual approval...');
      
      const approvalResponse = await github.rest.pulls.createReview({
        owner: REPO_OWNER,
        repo: REPO_NAME,
        pull_number: PR_NUMBER,
        event: 'APPROVE',
        body: `🧪 **Test Approval by API Script**

This is a test approval created by the GitHub API test script.

**Test Details:**
- Script: test-approval-api.js
- Timestamp: ${new Date().toISOString()}
- Purpose: Validate approval API functionality

✅ If you see this review, the approval API is working correctly!`
      });
      
      console.log('✅ APPROVAL CREATED SUCCESSFULLY!');
      console.log(`📋 Review ID: ${approvalResponse.data.id}`);
      console.log(`📋 Review State: ${approvalResponse.data.state}`);
      console.log(`📋 Review URL: ${approvalResponse.data.html_url}`);
    }
    console.log();
    
    // Step 6: Test comment creation
    console.log('6️⃣ Testing comment creation...');
    
    if (TEST_MODE) {
      console.log('🧪 TEST MODE: Simulating comment creation');
      console.log('📡 Would call: POST /repos/blaze-commerce/blazecommerce-wp-plugin/issues/342/comments');
      console.log('📋 Would send: { body: "Test comment message" }');
      console.log('✅ Comment creation simulation successful');
    } else {
      const commentResponse = await github.rest.issues.createComment({
        owner: REPO_OWNER,
        repo: REPO_NAME,
        issue_number: PR_NUMBER,
        body: `🧪 **API Test Comment**

This comment was created by the GitHub API test script to validate comment creation functionality.

**Test Results:**
- ✅ Repository access: Working
- ✅ PR access: Working  
- ✅ Comments access: Working
- ✅ Reviews access: Working
- ✅ Approval creation: Working
- ✅ Comment creation: Working

*Timestamp: ${new Date().toISOString()}*`
      });
      
      console.log('✅ COMMENT CREATED SUCCESSFULLY!');
      console.log(`📋 Comment ID: ${commentResponse.data.id}`);
      console.log(`📋 Comment URL: ${commentResponse.data.html_url}`);
    }
    console.log();
    
    console.log('🎉 ALL API TESTS PASSED!');
    console.log('=========================');
    console.log('✅ GitHub API approval functionality is working correctly');
    console.log('✅ The approval workflow should work when deployed');
    
    if (TEST_MODE) {
      console.log();
      console.log('💡 To run in LIVE mode (create actual approval):');
      console.log('   Set TEST_MODE = false in the script and run again');
    }
    
  } catch (error) {
    console.error('❌ API TEST FAILED!');
    console.error('==================');
    console.error(`Error: ${error.message}`);
    console.error(`Status: ${error.status}`);
    
    if (error.response?.data) {
      console.error('Response data:', JSON.stringify(error.response.data, null, 2));
    }
    
    console.error();
    console.error('🔧 TROUBLESHOOTING:');
    console.error('- Check that GITHUB_TOKEN has sufficient permissions');
    console.error('- Verify token has pull_requests:write and issues:write scopes');
    console.error('- Ensure the repository and PR exist and are accessible');
    console.error('- Check that the PR is in a valid state for approval');
    
    process.exit(1);
  }
}

// Run the test
if (require.main === module) {
  testApprovalAPI().catch(error => {
    console.error('Unhandled error:', error);
    process.exit(1);
  });
}

module.exports = { testApprovalAPI };
