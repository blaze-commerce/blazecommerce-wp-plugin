#!/usr/bin/env node

/**
 * Claude AI Security Fixes Validation Test
 * 
 * Validates all security improvements implemented from Claude AI review:
 * https://github.com/blaze-commerce/blazecommerce-wp-plugin/pull/352#issuecomment-3067786911
 */

const fs = require('fs');
const path = require('path');

console.log('🔍 CLAUDE AI SECURITY FIXES VALIDATION');
console.log('=' .repeat(60));

// Test results tracking
const testResults = {
  passed: 0,
  failed: 0,
  tests: []
};

function runTest(testName, testFunction) {
  try {
    const result = testFunction();
    if (result) {
      console.log(`✅ PASS: ${testName}`);
      testResults.passed++;
      testResults.tests.push({ name: testName, status: 'PASS', details: result });
    } else {
      console.log(`❌ FAIL: ${testName}`);
      testResults.failed++;
      testResults.tests.push({ name: testName, status: 'FAIL', details: 'Test returned false' });
    }
  } catch (error) {
    console.log(`❌ ERROR: ${testName} - ${error.message}`);
    testResults.failed++;
    testResults.tests.push({ name: testName, status: 'ERROR', details: error.message });
  }
}

// Load workflow files for testing
const claudeApprovalGate = fs.readFileSync('.github/workflows/claude-approval-gate.yml', 'utf8');
const claudeCodeReview = fs.readFileSync('.github/workflows/claude-code-review.yml', 'utf8');
const autoVersion = fs.readFileSync('.github/workflows/auto-version.yml', 'utf8');

console.log('\n🛡️ SECURITY VULNERABILITY FIXES:');

// Test 1: Script Injection Fix
runTest('Script Injection Prevention (claude-approval-gate.yml:603)', () => {
  // Should NOT contain direct injection pattern
  const hasDirectInjection = claudeApprovalGate.includes('const prNumber = ${{ needs.check-trigger.outputs.pr_number }};');
  
  // Should contain sanitized pattern
  const hasSanitizedInput = claudeApprovalGate.includes('const prNumberRaw = \'${{ needs.check-trigger.outputs.pr_number }}\';') &&
                           claudeApprovalGate.includes('const prNumber = parseInt(prNumberRaw, 10);');
  
  return !hasDirectInjection && hasSanitizedInput;
});

// Test 2: Third-Party Dependency Security
runTest('Third-Party Dependency Security (claude-code-review.yml:173)', () => {
  // Should NOT use unstable @beta tag
  const hasUnstableBeta = claudeCodeReview.includes('anthropics/claude-code-action@beta');
  
  // Should use specific version
  const hasSpecificVersion = claudeCodeReview.includes('anthropics/claude-code-action@v1.0.0');
  
  return !hasUnstableBeta && hasSpecificVersion;
});

// Test 3: Token Exposure Fix
runTest('Token Exposure Prevention (auto-version.yml:186)', () => {
  // Should NOT have direct export
  const hasDirectExport = autoVersion.includes('export GITHUB_EVENT_BEFORE="${{ github.event.before }}"');
  
  // Should have sanitization
  const hasSanitization = autoVersion.includes('GITHUB_EVENT_BEFORE_RAW="${{ github.event.before }}"') &&
                         autoVersion.includes('if [[ "$GITHUB_EVENT_BEFORE_RAW" =~ ^[a-f0-9]{40}$ ]];');
  
  return !hasDirectExport && hasSanitization;
});

console.log('\n🔒 ENHANCED AUTHENTICATION & VALIDATION:');

// Test 4: Enhanced Claude Comment Detection
runTest('Enhanced Claude Comment Authentication', () => {
  const hasEnhancedAuth = claudeApprovalGate.includes('comment.user.type === \'Bot\'') &&
                         claudeApprovalGate.includes('SECURITY FIX: Enhanced authentication verification');
  
  return hasEnhancedAuth;
});

// Test 5: Working Comment Filtering
runTest('Working Comment Filtering Implementation', () => {
  const hasWorkingFilter = claudeApprovalGate.includes('Claude is working') &&
                          claudeApprovalGate.includes('working…') &&
                          claudeApprovalGate.includes('Review in Progress') &&
                          claudeApprovalGate.includes('isWorkingComment');
  
  return hasWorkingFilter;
});

// Test 6: FINAL VERDICT Requirement
runTest('FINAL VERDICT Section Requirement', () => {
  const hasFinalVerdictReq = claudeApprovalGate.includes('CRITICAL: Must have FINAL VERDICT section') &&
                            claudeApprovalGate.includes('hasFinalVerdict') &&
                            claudeApprovalGate.includes('!isWorkingComment');
  
  return hasFinalVerdictReq;
});

console.log('\n🎯 AUTO-APPROVAL LOGIC IMPROVEMENTS:');

// Test 7: BLOCKED Status Priority
runTest('BLOCKED Status Takes Priority Over APPROVED', () => {
  const hasBlockedPriority = claudeApprovalGate.includes('PRIORITY 1: Check for explicit BLOCKED status first') &&
                            claudeApprovalGate.includes('status: blocked') &&
                            claudeApprovalGate.includes('hasRequiredIssues = true');
  
  return hasBlockedPriority;
});

// Test 8: Review Completion Validation
runTest('Review Completion Score Validation', () => {
  const hasCompletionValidation = claudeApprovalGate.includes('reviewCompletionScore') &&
                                 claudeApprovalGate.includes('minimumCompletionScore') &&
                                 claudeApprovalGate.includes('Review appears incomplete despite positive status');
  
  return hasCompletionValidation;
});

// Test 9: Comprehensive Pattern Detection
runTest('Comprehensive Pattern Detection System', () => {
  const hasPatternDetection = claudeApprovalGate.includes('completionIndicators') &&
                             claudeApprovalGate.includes('REVIEW COMPLETE') &&
                             claudeApprovalGate.includes('ANALYSIS COMPLETE') &&
                             claudeApprovalGate.includes('detectedPatterns');
  
  return hasPatternDetection;
});

console.log('\n📊 COMPREHENSIVE LOGGING SYSTEM:');

// Test 10: Enhanced Debug Logging
runTest('Comprehensive Debug Logging Implementation', () => {
  const hasEnhancedLogging = claudeApprovalGate.includes('COMPREHENSIVE DECISION ANALYSIS') &&
                            claudeApprovalGate.includes('reviewCompletionScore') &&
                            claudeApprovalGate.includes('detectedPatterns') &&
                            claudeApprovalGate.includes('SECURITY: Auto-approval based on strict FINAL VERDICT');
  
  return hasEnhancedLogging;
});

// Test 11: Security-Focused Logging
runTest('Security-Focused Logging Messages', () => {
  const hasSecurityLogging = claudeApprovalGate.includes('🛡️ PROTECTION: Working comments filtered') &&
                            claudeApprovalGate.includes('🔒 SECURITY:') &&
                            claudeApprovalGate.includes('input sanitized');
  
  return hasSecurityLogging;
});

console.log('\n🔧 INPUT SANITIZATION ACROSS ALL STEPS:');

// Test 12: All Steps Have Input Sanitization
runTest('Input Sanitization in All Approval Steps', () => {
  const evaluateStepSanitized = claudeApprovalGate.includes('SECURITY FIX: Proper input sanitization to prevent script injection');
  const approveStepSanitized = claudeApprovalGate.includes('SECURITY FIX: Sanitize all inputs to prevent injection attacks');
  const blockingStepSanitized = claudeApprovalGate.includes('SECURITY FIX: Sanitize PR number input');
  
  return evaluateStepSanitized && approveStepSanitized && blockingStepSanitized;
});

// Test 13: Disabled Problematic Triggers
runTest('Problematic Triggers Properly Disabled', () => {
  const issueCommentDisabled = claudeApprovalGate.includes('# issue_comment:  # DISABLED: Causing premature auto-approval');
  const prReviewDisabled = claudeApprovalGate.includes('# pull_request_review:  # DISABLED: Causing premature auto-approval');
  const prDisabled = claudeApprovalGate.includes('# pull_request:  # DISABLED: Causing auto-approval on every commit push');
  
  return issueCommentDisabled && prReviewDisabled && prDisabled;
});

// Final Results
console.log('\n' + '=' .repeat(60));
console.log('🎯 VALIDATION RESULTS SUMMARY:');
console.log(`✅ PASSED: ${testResults.passed} tests`);
console.log(`❌ FAILED: ${testResults.failed} tests`);
console.log(`📊 TOTAL: ${testResults.passed + testResults.failed} tests`);

if (testResults.failed === 0) {
  console.log('\n🎉 ALL SECURITY FIXES VALIDATED SUCCESSFULLY!');
  console.log('🛡️ All Claude AI review recommendations have been implemented.');
  process.exit(0);
} else {
  console.log('\n⚠️ SOME TESTS FAILED - REVIEW REQUIRED');
  console.log('\nFailed tests:');
  testResults.tests.filter(t => t.status !== 'PASS').forEach(test => {
    console.log(`  ❌ ${test.name}: ${test.details}`);
  });
  process.exit(1);
}
