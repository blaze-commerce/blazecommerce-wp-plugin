#!/usr/bin/env node

/**
 * Comprehensive tests for revert commit handling functionality
 * Tests the enhanced version bump system with smart revert cancellation
 */

const {
  parseConventionalCommit,
  parseRevertTarget,
  analyzeCommitsWithReverts,
  determineBumpType
} = require('./semver-utils');

let testsPassed = 0;
let testsFailed = 0;

function runTest(testName, testFn) {
  try {
    console.log(`🧪 Testing: ${testName}`);
    testFn();
    console.log(`✅ ${testName} - PASSED`);
    testsPassed++;
  } catch (error) {
    console.error(`❌ ${testName} - FAILED: ${error.message}`);
    testsFailed++;
  }
}

function assertEquals(actual, expected, message = '') {
  if (JSON.stringify(actual) !== JSON.stringify(expected)) {
    throw new Error(`Expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}. ${message}`);
  }
}

function assertNotNull(value, message = '') {
  if (value === null || value === undefined) {
    throw new Error(`Expected non-null value. ${message}`);
  }
}

console.log('🔄 Running Revert Commit Handling Tests...\n');

// Test 1: Basic revert commit recognition
runTest('Basic revert commit recognition', () => {
  const revertCommit = 'revert: feat: add new dashboard';
  const parsed = parseConventionalCommit(revertCommit);
  
  assertNotNull(parsed, 'Revert commit should be recognized');
  assertEquals(parsed.type, 'revert', 'Should identify as revert type');
  assertEquals(parsed.description, 'feat: add new dashboard', 'Should preserve target description');
});

// Test 2: Parse revert targets
runTest('Parse revert targets', () => {
  const testCases = [
    {
      input: 'feat: add new dashboard',
      expected: { type: 'feat', scope: null, breaking: false, description: 'add new dashboard' }
    },
    {
      input: 'revert: feat: add new dashboard',
      expected: { type: 'feat', scope: null, breaking: false, description: 'add new dashboard' }
    },
    {
      input: 'Revert "feat: add new dashboard"',
      expected: { type: 'feat', scope: null, breaking: false, description: 'add new dashboard' }
    },
    {
      input: 'fix(auth): resolve login issue',
      expected: { type: 'fix', scope: 'auth', breaking: false, description: 'resolve login issue' }
    },
    {
      input: 'feat!: breaking change',
      expected: { type: 'feat', scope: null, breaking: true, description: 'breaking change' }
    }
  ];
  
  testCases.forEach(({ input, expected }) => {
    const result = parseRevertTarget(input);
    assertNotNull(result, `Should parse: ${input}`);
    assertEquals(result.type, expected.type, `Type mismatch for: ${input}`);
    assertEquals(result.scope, expected.scope, `Scope mismatch for: ${input}`);
    assertEquals(result.breaking, expected.breaking, `Breaking mismatch for: ${input}`);
    assertEquals(result.description, expected.description, `Description mismatch for: ${input}`);
  });
});

// Test 3: Simple revert cancellation
runTest('Simple revert cancellation', () => {
  const commits = [
    'feat: add new dashboard',
    'revert: feat: add new dashboard'
  ];
  
  const analysis = analyzeCommitsWithReverts(commits);
  
  assertEquals(analysis.originalCommits.length, 2, 'Should have 2 original commits');
  assertEquals(analysis.netCommits.length, 0, 'Should have 0 net commits after cancellation');
  assertEquals(analysis.revertMatches.length, 1, 'Should have 1 revert match');
  assertEquals(analysis.revertMatches[0].cancelledType, 'feat', 'Should cancel feat commit');
});

// Test 4: Partial revert scenario
runTest('Partial revert scenario', () => {
  const commits = [
    'feat: add feature A',
    'feat: add feature B', 
    'revert: feat: add feature A'
  ];
  
  const analysis = analyzeCommitsWithReverts(commits);
  
  assertEquals(analysis.netCommits.length, 1, 'Should have 1 net commit');
  assertEquals(analysis.revertMatches.length, 1, 'Should have 1 revert match');
  
  // The remaining commit should be feature B
  const remainingCommit = analysis.netCommits[0];
  assertEquals(remainingCommit.commit, 'feat: add feature B', 'Should keep non-reverted feature');
});

// Test 5: Version bump with reverts
runTest('Version bump with reverts', () => {
  const scenarios = [
    {
      name: 'Complete cancellation',
      commits: ['feat: add feature', 'revert: feat: add feature'],
      expectedBump: 'none',
      expectedReason: 'No conventional commits found'
    },
    {
      name: 'Partial cancellation',
      commits: ['feat: feature A', 'feat: feature B', 'revert: feat: feature A'],
      expectedBump: 'minor',
      expectedReason: 'Found 1 new feature(s)'
    },
    {
      name: 'Fix with revert',
      commits: ['fix: bug fix', 'revert: fix: bug fix'],
      expectedBump: 'none',
      expectedReason: 'No conventional commits found'
    },
    {
      name: 'Breaking change with revert',
      commits: ['feat!: breaking change', 'revert: feat!: breaking change'],
      expectedBump: 'none',
      expectedReason: 'No conventional commits found'
    },
    {
      name: 'Mixed scenario',
      commits: ['feat: feature A', 'fix: bug fix', 'revert: feat: feature A'],
      expectedBump: 'patch',
      expectedReason: 'Found 1 fix(es)'
    }
  ];
  
  scenarios.forEach(scenario => {
    const result = determineBumpType(scenario.commits, { enableRevertHandling: true });
    assertEquals(result.bumpType, scenario.expectedBump, 
      `${scenario.name}: Expected ${scenario.expectedBump}, got ${result.bumpType}`);
    
    const hasExpectedReason = result.reasoning.some(reason => 
      reason.includes(scenario.expectedReason.split(' ')[scenario.expectedReason.split(' ').length - 1])
    );
    if (!hasExpectedReason && scenario.expectedBump !== 'none') {
      console.warn(`Warning: ${scenario.name} - Expected reasoning not found. Got: ${result.reasoning.join('; ')}`);
    }
  });
});

// Test 6: Unmatched reverts
runTest('Unmatched reverts', () => {
  const commits = [
    'revert: feat: some external feature',
    'feat: add new feature'
  ];
  
  const analysis = analyzeCommitsWithReverts(commits);
  
  assertEquals(analysis.netCommits.length, 2, 'Should keep both commits');
  assertEquals(analysis.revertMatches.length, 0, 'Should have no matches');
  
  // The revert should be treated as a regular commit since it doesn't match anything
  const result = determineBumpType(commits, { enableRevertHandling: true });
  assertEquals(result.bumpType, 'minor', 'Should bump for the feature, ignore unmatched revert');
});

// Test 7: GitHub-style revert format
runTest('GitHub-style revert format', () => {
  const commits = [
    'feat: add new dashboard',
    'Revert "feat: add new dashboard"'
  ];
  
  const analysis = analyzeCommitsWithReverts(commits);
  
  assertEquals(analysis.netCommits.length, 0, 'Should cancel GitHub-style revert');
  assertEquals(analysis.revertMatches.length, 1, 'Should match GitHub-style revert');
  
  const result = determineBumpType(commits, { enableRevertHandling: true });
  assertEquals(result.bumpType, 'none', 'Should result in no version bump');
});

// Test 8: Complex scenario with scopes
runTest('Complex scenario with scopes', () => {
  const commits = [
    'feat(api): add new endpoint',
    'feat(ui): add new component',
    'fix(auth): resolve login issue',
    'revert: feat(api): add new endpoint'
  ];
  
  const analysis = analyzeCommitsWithReverts(commits);
  
  assertEquals(analysis.netCommits.length, 2, 'Should have 2 net commits');
  assertEquals(analysis.revertMatches.length, 1, 'Should match scoped revert');
  
  const result = determineBumpType(commits, { enableRevertHandling: true });
  assertEquals(result.bumpType, 'minor', 'Should bump minor for remaining feature');
});

// Test 9: Revert handling disabled
runTest('Revert handling disabled', () => {
  const commits = [
    'feat: add feature',
    'revert: feat: add feature'
  ];
  
  const result = determineBumpType(commits, { enableRevertHandling: false });
  assertEquals(result.bumpType, 'minor', 'Should count both commits when revert handling disabled');
});

// Test 10: Edge cases
runTest('Edge cases', () => {
  // Empty commits
  let result = determineBumpType([], { enableRevertHandling: true });
  assertEquals(result.bumpType, 'none', 'Empty commits should result in none');
  
  // Only reverts
  result = determineBumpType(['revert: feat: some feature'], { enableRevertHandling: true });
  assertEquals(result.bumpType, 'none', 'Only unmatched reverts should result in none');
  
  // Invalid revert format
  result = determineBumpType(['revert: invalid format'], { enableRevertHandling: true });
  assertEquals(result.bumpType, 'none', 'Invalid revert format should be ignored');
});

console.log('\n📊 Revert Commit Handling Test Results:');
console.log(`   ✅ Passed: ${testsPassed}`);
console.log(`   ❌ Failed: ${testsFailed}`);
console.log(`   📈 Success Rate: ${((testsPassed / (testsPassed + testsFailed)) * 100).toFixed(1)}%`);

if (testsFailed === 0) {
  console.log('\n🎉 All revert commit handling tests passed!');
  process.exit(0);
} else {
  console.log('\n⚠️  Some tests failed. Please review the implementation.');
  process.exit(1);
}
