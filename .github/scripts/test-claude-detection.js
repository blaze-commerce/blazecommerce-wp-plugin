#!/usr/bin/env node

/**
 * Test script to validate Claude approval detection patterns
 * This script tests our regex patterns against actual Claude comment formats
 */

// Actual Claude comment format from PR #342 comment #3067308966
const actualClaudeComment = `## Claude AI Code Review

I'll review this pull request focusing on the Claude AI workflow improvements and security fixes.

### CRITICAL ISSUES
None found. All previous security vulnerabilities have been properly addressed.

### STRENGTHS
1. **Security Fixes**: Excellent resolution of script injection vulnerability by using environment variables
2. **Token Security**: Proper implementation of required BOT_GITHUB_TOKEN validation
3. **Detection Logic**: Robust multi-tier detection system with comprehensive fallbacks
4. **Error Handling**: Well-structured error handling with specific error types
5. **Documentation**: Thorough documentation of all changes and improvements
6. **Testing**: Good addition of test framework for validation

### AREAS FOR IMPROVEMENT
1. **Performance**: Consider adding caching for API responses to reduce GitHub API calls
2. **Monitoring**: Could benefit from metrics collection for workflow performance tracking

### PERFORMANCE CONSIDERATIONS
The workflow changes should improve reliability without significant performance impact. The enhanced logging may slightly increase execution time but provides valuable debugging information.

### SECURITY ASSESSMENT
✅ **EXCELLENT**: All critical security issues have been resolved:
- Script injection vulnerability eliminated
- Token fallback security risk removed
- Proper input validation implemented
- Secure environment variable usage

### FINAL VERDICT
**Status**: APPROVED
**Merge Readiness**: READY TO MERGE
**Recommendation**: This PR successfully restores the Claude AI workflow with significant architectural improvements, comprehensive documentation, and proper security practices. All critical issues from previous reviews have been addressed, and the implementation is ready for production use.`;

// Test patterns
const patterns = [
  {
    name: 'Bracketed Format',
    regex: /### FINAL VERDICT[\s\S]*?\*\*Status\*\*:\s*\[([^\]]+)\]/i,
    description: 'Looks for [APPROVED] format'
  },
  {
    name: 'Plain Text Format',
    regex: /### FINAL VERDICT[\s\S]*?\*\*Status\*\*:\s*([A-Z\s]+)(?:\*\*|\n)/i,
    description: 'Looks for APPROVED format'
  },
  {
    name: 'Loose Match',
    regex: /\*\*Status\*\*:\s*([A-Z\s]+?)(?:\s*\*\*|\s*\n)/i,
    description: 'Looks for any Status: VALUE'
  },
  {
    name: 'Alternative Pattern 1',
    regex: /\*\*Status\*\*:\s*([A-Z\s]+)/i,
    description: 'Simple Status match'
  },
  {
    name: 'Alternative Pattern 2',
    regex: /Status\*\*:\s*([A-Z\s]+)/i,
    description: 'Status without leading **'
  }
];

console.log('🧪 TESTING CLAUDE DETECTION PATTERNS');
console.log('=====================================');
console.log();

console.log('📄 COMMENT ANALYSIS:');
console.log(`- Length: ${actualClaudeComment.length} characters`);
console.log(`- Contains "FINAL VERDICT": ${actualClaudeComment.includes('FINAL VERDICT')}`);
console.log(`- Contains "Status": ${actualClaudeComment.includes('Status')}`);
console.log(`- Contains "APPROVED": ${actualClaudeComment.includes('APPROVED')}`);
console.log();

// Show the FINAL VERDICT section
const finalVerdictIndex = actualClaudeComment.indexOf('FINAL VERDICT');
if (finalVerdictIndex !== -1) {
  const relevantSection = actualClaudeComment.substring(finalVerdictIndex, finalVerdictIndex + 400);
  console.log('📄 FINAL VERDICT SECTION:');
  console.log(relevantSection);
  console.log();
}

console.log('🔍 PATTERN TESTING RESULTS:');
console.log('============================');

patterns.forEach((pattern, index) => {
  console.log(`\n${index + 1}. ${pattern.name}`);
  console.log(`   Description: ${pattern.description}`);
  console.log(`   Regex: ${pattern.regex}`);
  
  const match = actualClaudeComment.match(pattern.regex);
  
  if (match) {
    console.log(`   ✅ MATCH FOUND: "${match[1].trim()}"`);
    console.log(`   Full match: "${match[0].substring(0, 100)}..."`);
  } else {
    console.log(`   ❌ NO MATCH`);
  }
});

console.log('\n🎯 RECOMMENDED PATTERN:');
console.log('=======================');

// Find the best working pattern
const workingPattern = patterns.find(pattern => {
  const match = actualClaudeComment.match(pattern.regex);
  return match && match[1].trim() === 'APPROVED';
});

if (workingPattern) {
  console.log(`✅ Use: ${workingPattern.name}`);
  console.log(`Regex: ${workingPattern.regex}`);
  console.log(`Result: "${actualClaudeComment.match(workingPattern.regex)[1].trim()}"`);
} else {
  console.log('❌ No pattern successfully detected APPROVED status');
}

console.log('\n🔧 STATUS VALIDATION:');
console.log('=====================');

// Test status validation logic
const testStatuses = ['APPROVED', 'BLOCKED', 'CONDITIONAL APPROVAL'];

testStatuses.forEach(status => {
  console.log(`\nTesting status: "${status}"`);
  
  if (status === 'APPROVED' || (status.includes('APPROVED') && !status.includes('CONDITIONAL'))) {
    console.log('  ✅ Would be detected as APPROVED');
  } else if (status === 'BLOCKED' || status.includes('BLOCKED')) {
    console.log('  ❌ Would be detected as BLOCKED');
  } else if (status === 'CONDITIONAL APPROVAL' || status.includes('CONDITIONAL')) {
    console.log('  ⚠️ Would be detected as CONDITIONAL');
  } else {
    console.log('  ❓ Would be detected as UNKNOWN');
  }
});

console.log('\n🚀 CONCLUSION:');
console.log('==============');

if (workingPattern) {
  console.log('✅ Detection should work with current patterns');
  console.log('✅ Auto-approval should trigger for this comment');
} else {
  console.log('❌ Detection patterns need adjustment');
  console.log('❌ Auto-approval will NOT trigger for this comment');
}

console.log('\nTest completed! 🎉');
