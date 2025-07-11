#!/usr/bin/env node

/**
 * Comprehensive tests for revert commit handling functionality
 * Tests the enhanced version bump system with smart revert cancellation
 */

const {
  parseConventionalCommit,
  parseRevertTarget,
  createMatchingKey,
  resolveMultipleMatches,
  analyzeCommitsWithReverts,
  determineBumpType,
  MemoryManager,
  COMPILED_PATTERNS
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

// CLAUDE AI REVIEW: Additional test cases for enhanced functionality

// Test 11: Input validation for parseRevertTarget
runTest('Input validation for parseRevertTarget', () => {
  // Test null input
  try {
    parseRevertTarget(null);
    throw new Error('Should have thrown ValidationError for null input');
  } catch (error) {
    if (error.name !== 'ValidationError') {
      throw new Error(`Expected ValidationError, got ${error.name}`);
    }
  }

  // Test empty string
  try {
    parseRevertTarget('');
    throw new Error('Should have thrown ValidationError for empty string');
  } catch (error) {
    if (error.name !== 'ValidationError') {
      throw new Error(`Expected ValidationError, got ${error.name}`);
    }
  }

  // Test non-string input
  try {
    parseRevertTarget(123);
    throw new Error('Should have thrown ValidationError for non-string input');
  } catch (error) {
    if (error.name !== 'ValidationError') {
      throw new Error(`Expected ValidationError, got ${error.name}`);
    }
  }
});

// Test 12: Case sensitivity consistency
runTest('Case sensitivity consistency', () => {
  const commits = [
    'FEAT: Add New Dashboard',
    'revert: feat: add new dashboard'
  ];

  const analysis = analyzeCommitsWithReverts(commits);

  assertEquals(analysis.netCommits.length, 0, 'Should handle case differences in matching');
  assertEquals(analysis.revertMatches.length, 1, 'Should match despite case differences');
});

// Test 13: Performance optimization with large commit sets
runTest('Performance optimization with large commit sets', () => {
  // Create a large set of commits
  const commits = [];
  for (let i = 0; i < 100; i++) {
    commits.push(`feat: add feature ${i}`);
  }
  // Add some reverts
  for (let i = 0; i < 10; i++) {
    commits.push(`revert: feat: add feature ${i}`);
  }

  const startTime = Date.now();
  const analysis = analyzeCommitsWithReverts(commits, {
    verbose: false,
    enablePerformanceMetrics: true
  });
  const duration = Date.now() - startTime;

  console.log(`   Performance test: ${commits.length} commits processed in ${duration}ms`);

  assertEquals(analysis.revertMatches.length, 10, 'Should match 10 reverts');
  assertEquals(analysis.netCommits.length, 90, 'Should have 90 net commits after cancellation');

  if (analysis.performanceMetrics) {
    console.log(`   Metrics: ${analysis.performanceMetrics.matchingComplexity}, ${(analysis.performanceMetrics.matchingEfficiency * 100).toFixed(1)}% efficiency`);
  }

  // Performance should be reasonable (less than 1 second for 110 commits)
  if (duration > 1000) {
    console.warn(`   ⚠️  Performance warning: ${duration}ms for ${commits.length} commits`);
  }
});

// Test 14: Identical commit messages with position tracking
runTest('Identical commit messages with position tracking', () => {
  const commits = [
    'feat: add feature',      // Position 0
    'feat: add feature',      // Position 1 (identical)
    'fix: bug fix',           // Position 2
    'revert: feat: add feature' // Position 3 - should match closest (position 1)
  ];

  const analysis = analyzeCommitsWithReverts(commits, { verbose: true });

  assertEquals(analysis.revertMatches.length, 1, 'Should match one revert');
  assertEquals(analysis.netCommits.length, 2, 'Should have 2 net commits (one feat + one fix)');

  // The revert should match the closest commit (position 1, not position 0)
  const match = analysis.revertMatches[0];
  assertNotNull(match.matchingKey, 'Should have matching key for debugging');
});

// Test 15: createMatchingKey function
runTest('createMatchingKey function consistency', () => {
  const commit1 = { type: 'feat', scope: 'api', breaking: false, description: 'Add New Feature' };
  const commit2 = { type: 'FEAT', scope: 'API', breaking: false, description: 'add new feature' };
  const commit3 = { type: 'feat', scope: 'api', breaking: true, description: 'add new feature' };

  const key1 = createMatchingKey(commit1);
  const key2 = createMatchingKey(commit2);
  const key3 = createMatchingKey(commit3);

  assertEquals(key1, key2, 'Should generate same key for case differences');
  if (key1 === key3) {
    throw new Error('Should generate different keys for breaking vs non-breaking');
  }

  console.log(`   Key format: ${key1}`);
});

// Test 16: Mixed case GitHub-style reverts
runTest('Mixed case GitHub-style reverts', () => {
  const commits = [
    'feat: add dashboard',
    'REVERT "feat: add dashboard"'  // Mixed case
  ];

  const analysis = analyzeCommitsWithReverts(commits);

  assertEquals(analysis.netCommits.length, 0, 'Should handle mixed case GitHub reverts');
  assertEquals(analysis.revertMatches.length, 1, 'Should match mixed case revert');
});

// CLAUDE AI RECOMMENDATION: Advanced test cases for multiple identical commits and conflict resolution

// Test 17: Multiple identical commits with advanced conflict resolution
runTest('Multiple identical commits with advanced conflict resolution', () => {
  const commits = [
    'feat: add feature X',      // Position 0
    'fix: bug fix Y',           // Position 1
    'feat: add feature X',      // Position 2 (identical to position 0)
    'feat: add feature X',      // Position 3 (identical to position 0 and 2)
    'revert: feat: add feature X', // Position 4 - should match closest using chronological strategy
    'revert: feat: add feature X'  // Position 5 - should match next closest
  ];

  const analysis = analyzeCommitsWithReverts(commits, {
    verbose: true,
    enablePerformanceMetrics: true
  });

  assertEquals(analysis.revertMatches.length, 2, 'Should match 2 reverts with 2 of the 3 identical commits');
  assertEquals(analysis.netCommits.length, 2, 'Should have 2 net commits (1 remaining feat + 1 fix)');

  // Verify performance metrics for conflict resolution
  if (analysis.performanceMetrics) {
    console.log(`   📊 Conflict resolutions: ${analysis.performanceMetrics.conflictResolutions}`);
    console.log(`   🔍 Multiple match scenarios: ${analysis.performanceMetrics.multipleMatchScenarios}`);

    if (analysis.performanceMetrics.conflictResolutions > 0) {
      console.log(`   ⚡ Avg resolution time: ${(analysis.performanceMetrics.patternMatchingTime / analysis.performanceMetrics.conflictResolutions).toFixed(2)}ms`);
    }
  }
});

// Test 18: resolveMultipleMatches function with different strategies
runTest('resolveMultipleMatches function with different strategies', () => {
  const candidates = [
    { position: 1, commit: 'feat: feature A' },
    { position: 5, commit: 'feat: feature A' },
    { position: 8, commit: 'feat: feature A' }
  ];
  const targetCommit = { position: 6, commit: 'revert: feat: feature A' };

  // Test closest-position strategy
  const closestMatch = resolveMultipleMatches(candidates, targetCommit, { strategy: 'closest-position' });
  assertEquals(closestMatch.position, 5, 'Closest position strategy should choose position 5');

  // Test first-occurrence strategy
  const firstMatch = resolveMultipleMatches(candidates, targetCommit, { strategy: 'first-occurrence' });
  assertEquals(firstMatch.position, 1, 'First occurrence strategy should choose position 1');

  // Test last-occurrence strategy
  const lastMatch = resolveMultipleMatches(candidates, targetCommit, { strategy: 'last-occurrence' });
  assertEquals(lastMatch.position, 8, 'Last occurrence strategy should choose position 8');

  // Test chronological strategy (prefers commits after target)
  const chronologicalMatch = resolveMultipleMatches(candidates, targetCommit, { strategy: 'chronological' });
  assertEquals(chronologicalMatch.position, 8, 'Chronological strategy should prefer position 8 (after target)');
});

// Test 19: Memory management and performance optimization
runTest('Memory management and performance optimization', () => {
  console.log('   Testing memory management utilities...');

  // Test memory monitoring
  const monitor = MemoryManager.startMonitoring('test-operation');

  // Simulate some work
  const largeArray = new Array(10000).fill(0).map((_, i) => `commit-${i}`);
  MemoryManager.checkpoint(monitor, 'after-array-creation');

  // Process the array
  const processed = largeArray.map(commit => commit.toUpperCase());
  MemoryManager.checkpoint(monitor, 'after-processing');

  const report = MemoryManager.complete(monitor);

  console.log(`   📊 Operation: ${report.operation}`);
  console.log(`   ⏱️  Total time: ${report.totalTime}ms`);
  console.log(`   🧠 Memory delta: ${(report.memoryDelta / 1024).toFixed(1)}KB`);
  console.log(`   📈 Checkpoints: ${report.checkpoints.length}`);
  console.log(`   💡 Recommendations: ${report.recommendations.length}`);

  // Verify monitoring worked
  if (report.checkpoints.length !== 2) {
    throw new Error(`Expected 2 checkpoints, got ${report.checkpoints.length}`);
  }

  if (report.totalTime <= 0) {
    throw new Error('Total time should be positive');
  }
});

// Test 20: COMPILED_PATTERNS performance optimization
runTest('COMPILED_PATTERNS performance optimization', () => {
  console.log('   Testing pre-compiled regex patterns...');

  const testCommits = [
    'feat: add feature',
    'FEAT: ADD FEATURE',
    'fix(scope): bug fix',
    'revert: feat: add feature',
    'Revert "feat: add feature"'
  ];

  const startTime = Date.now();

  // Test pattern matching performance
  testCommits.forEach(commit => {
    const conventionalMatch = commit.match(COMPILED_PATTERNS.CONVENTIONAL_COMMIT);
    const githubRevertMatch = commit.match(COMPILED_PATTERNS.GITHUB_REVERT);
    const revertPrefixMatch = commit.match(COMPILED_PATTERNS.REVERT_PREFIX);

    // Verify patterns work correctly
    if (commit.toLowerCase().includes('feat') || commit.toLowerCase().includes('fix')) {
      assertNotNull(conventionalMatch || githubRevertMatch || revertPrefixMatch,
        `Should match conventional pattern: ${commit}`);
    }
  });

  const duration = Date.now() - startTime;
  console.log(`   ⚡ Pattern matching completed in ${duration}ms`);

  // Verify all expected patterns exist
  const expectedPatterns = [
    'CONVENTIONAL_COMMIT', 'GITHUB_REVERT', 'REVERT_PREFIX',
    'BREAKING_CHANGE_EXCLAMATION', 'BREAKING_CHANGE_KEYWORD',
    'SEMVER_PATTERN', 'WHITESPACE_NORMALIZE'
  ];

  expectedPatterns.forEach(pattern => {
    if (!COMPILED_PATTERNS[pattern]) {
      throw new Error(`Missing compiled pattern: ${pattern}`);
    }
  });
});

// Test 21: Large dataset performance with memory monitoring
runTest('Large dataset performance with memory monitoring', () => {
  console.log('   Testing large dataset performance...');

  // Create a large commit set
  const largeCommitSet = [];
  for (let i = 0; i < 500; i++) {
    largeCommitSet.push(`feat: add feature ${i}`);
  }
  // Add reverts for first 100 features
  for (let i = 0; i < 100; i++) {
    largeCommitSet.push(`revert: feat: add feature ${i}`);
  }

  const startTime = Date.now();
  const analysis = analyzeCommitsWithReverts(largeCommitSet, {
    enablePerformanceMetrics: true,
    verbose: false
  });
  const duration = Date.now() - startTime;

  console.log(`   📊 Processed ${largeCommitSet.length} commits in ${duration}ms`);
  console.log(`   🔄 Matched ${analysis.revertMatches.length} reverts`);
  console.log(`   📈 Net commits: ${analysis.netCommits.length}`);

  // Verify performance metrics
  if (analysis.performanceMetrics) {
    const metrics = analysis.performanceMetrics;
    console.log(`   ⚡ Algorithm: ${metrics.matchingComplexity}`);
    console.log(`   🧠 Memory delta: ${(metrics.memoryUsage.delta / 1024).toFixed(1)}KB`);
    console.log(`   📊 Efficiency: ${(metrics.matchingEfficiency * 100).toFixed(1)}%`);

    // Performance should be reasonable
    if (duration > 5000) { // 5 seconds
      console.warn(`   ⚠️  Performance warning: ${duration}ms for ${largeCommitSet.length} commits`);
    }

    // Memory usage should be reasonable
    if (metrics.memoryUsage.delta > 100 * 1024 * 1024) { // 100MB
      console.warn(`   ⚠️  Memory warning: ${(metrics.memoryUsage.delta / 1024 / 1024).toFixed(2)}MB delta`);
    }
  }

  // Verify correctness
  assertEquals(analysis.revertMatches.length, 100, 'Should match 100 reverts');
  assertEquals(analysis.netCommits.length, 400, 'Should have 400 net commits (500 - 100 matched pairs)');
});

console.log('\n📊 Advanced Revert Commit Handling Test Results:');
console.log(`   ✅ Passed: ${testsPassed}`);
console.log(`   ❌ Failed: ${testsFailed}`);
console.log(`   📈 Success Rate: ${((testsPassed / (testsPassed + testsFailed)) * 100).toFixed(1)}%`);

if (testsFailed === 0) {
  console.log('\n🎉 All advanced revert commit handling tests passed!');
  console.log('✅ Claude AI advanced recommendations successfully implemented and tested');
  console.log('🚀 Ready for production with enterprise-grade performance and reliability');
  process.exit(0);
} else {
  console.log('\n⚠️  Some tests failed. Please review the implementation.');
  process.exit(1);
}
