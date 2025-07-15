#!/usr/bin/env node

/**
 * Test script for Claude AI cost optimization features
 * Validates model selection logic and cost calculations
 */

const fs = require('fs');
const path = require('path');

// Test scenarios for model selection
const testScenarios = [
  {
    name: "Security files - should use Sonnet",
    files: ['src/auth/login.php', 'includes/security.php'],
    additions: 50,
    deletions: 10,
    expected: 'claude-3-5-sonnet-20241022',
    reason: 'Critical security/auth/payment files detected'
  },
  {
    name: "Payment files - should use Sonnet",
    files: ['woocommerce/payment.php', 'includes/payment-gateway.php'],
    additions: 30,
    deletions: 5,
    expected: 'claude-3-5-sonnet-20241022',
    reason: 'Critical security/auth/payment files detected'
  },
  {
    name: "Large changeset - should use Sonnet",
    files: ['src/utils.php', 'includes/helpers.php'],
    additions: 150,
    deletions: 80,
    expected: 'claude-3-5-sonnet-20241022', 
    reason: 'Large changeset (230 changes > 200 threshold)'
  },
  {
    name: "Medium PHP changes - should use Sonnet",
    files: ['src/product.php', 'includes/cart.php'],
    additions: 80,
    deletions: 30,
    expected: 'claude-3-5-sonnet-20241022',
    reason: 'Medium PHP changeset (110 changes)'
  },
  {
    name: "Documentation changes - should use Haiku",
    files: ['README.md', 'docs/installation.md', 'CHANGELOG.md'],
    additions: 20,
    deletions: 5,
    expected: 'claude-3-haiku-20240307',
    reason: 'Simple changes (25 changes, no critical files)'
  },
  {
    name: "Small config changes - should use Haiku",
    files: ['package.json', 'composer.json'],
    additions: 5,
    deletions: 2,
    expected: 'claude-3-haiku-20240307',
    reason: 'Simple changes (7 changes, no critical files)'
  },
  {
    name: "Mixed small changes - should use Haiku",
    files: ['src/utils.php', 'assets/style.css'],
    additions: 15,
    deletions: 8,
    expected: 'claude-3-haiku-20240307',
    reason: 'Simple changes (23 changes, no critical files)'
  }
];

// Model selection logic (extracted from workflow)
function selectModel(files, additions, deletions, forceSonnet = false) {
  if (forceSonnet) {
    return {
      model: 'claude-3-5-sonnet-20241022',
      reason: 'Manual override via workflow_dispatch'
    };
  }

  const totalChanges = additions + deletions;
  
  // Critical file patterns (more specific to avoid false positives)
  const criticalPatterns = [
    /\/security\//i, /\/auth\//i, /\/payment\//i, /\/admin\//i, /\/core\//i, /\/api\//i,
    /security\./i, /auth\./i, /payment\./i, /admin\./i, /core\./i, /api\./i,
    /login\./i, /user\./i, /permission\./i, /role\./i, /session\./i, /token\./i,
    /-security/i, /-auth/i, /-payment/i, /-admin/i, /-core/i, /-api/i,
    /crypto/i, /encrypt/i, /password/i, /oauth/i, /jwt/i, /sql/i,
    /database/i, /migration/i, /schema/i
  ];

  // Check for critical files
  const hasCriticalFiles = files.some(file => 
    criticalPatterns.some(pattern => pattern.test(file))
  );

  // Decision logic
  if (hasCriticalFiles) {
    return {
      model: 'claude-3-5-sonnet-20241022',
      reason: 'Critical security/auth/payment files detected'
    };
  } else if (totalChanges > 200) {
    return {
      model: 'claude-3-5-sonnet-20241022',
      reason: `Large changeset (${totalChanges} changes > 200 threshold)`
    };
  } else if (totalChanges > 100 && files.some(f => f.endsWith('.php'))) {
    return {
      model: 'claude-3-5-sonnet-20241022',
      reason: `Medium PHP changeset (${totalChanges} changes)`
    };
  } else {
    return {
      model: 'claude-3-haiku-20240307',
      reason: `Simple changes (${totalChanges} changes, no critical files)`
    };
  }
}

// Cost calculation
function calculateCostSavings() {
  const sonnetCostPer1K = 15.00; // $15 per 1K tokens
  const haikuCostPer1K = 0.25;   // $0.25 per 1K tokens
  const avgTokensPerReview = 2000; // Average tokens per review
  const reviewsPerDay = 10; // Estimated reviews per day

  const oldCostPerReview = (avgTokensPerReview / 1000) * sonnetCostPer1K;
  const oldDailyCost = oldCostPerReview * reviewsPerDay;

  // Assume 70% Haiku, 30% Sonnet after optimization
  const haikuPercentage = 0.7;
  const sonnetPercentage = 0.3;
  
  const newCostPerReview = 
    (haikuPercentage * (avgTokensPerReview / 1000) * haikuCostPer1K) +
    (sonnetPercentage * (avgTokensPerReview / 1000) * sonnetCostPer1K);
  const newDailyCost = newCostPerReview * reviewsPerDay;

  const savings = oldDailyCost - newDailyCost;
  const savingsPercentage = (savings / oldDailyCost) * 100;

  return {
    oldDailyCost: oldDailyCost.toFixed(2),
    newDailyCost: newDailyCost.toFixed(2),
    dailySavings: savings.toFixed(2),
    savingsPercentage: savingsPercentage.toFixed(1)
  };
}

// Run tests
function runTests() {
  console.log('🧪 Testing Claude AI Cost Optimization Logic');
  console.log('='.repeat(50));

  let passed = 0;
  let failed = 0;

  testScenarios.forEach((scenario, index) => {
    const result = selectModel(scenario.files, scenario.additions, scenario.deletions);
    
    const success = result.model === scenario.expected;
    const status = success ? '✅ PASS' : '❌ FAIL';
    
    console.log(`\n${index + 1}. ${scenario.name}`);
    console.log(`   Files: ${scenario.files.join(', ')}`);
    console.log(`   Changes: +${scenario.additions} -${scenario.deletions}`);
    console.log(`   Expected: ${scenario.expected}`);
    console.log(`   Got: ${result.model}`);
    console.log(`   Reason: ${result.reason}`);
    console.log(`   ${status}`);

    if (success) {
      passed++;
    } else {
      failed++;
    }
  });

  console.log('\n' + '='.repeat(50));
  console.log(`📊 Test Results: ${passed} passed, ${failed} failed`);
  
  if (failed === 0) {
    console.log('🎉 All tests passed! Model selection logic is working correctly.');
  } else {
    console.log('⚠️  Some tests failed. Please review the model selection logic.');
    process.exit(1);
  }
}

// Calculate and display cost savings
function displayCostAnalysis() {
  console.log('\n💰 Cost Savings Analysis');
  console.log('='.repeat(30));
  
  const costs = calculateCostSavings();
  
  console.log(`Old daily cost (100% Sonnet): $${costs.oldDailyCost}`);
  console.log(`New daily cost (70% Haiku): $${costs.newDailyCost}`);
  console.log(`Daily savings: $${costs.dailySavings}`);
  console.log(`Savings percentage: ${costs.savingsPercentage}%`);
  console.log(`Monthly savings: $${(costs.dailySavings * 30).toFixed(2)}`);
  console.log(`Annual savings: $${(costs.dailySavings * 365).toFixed(2)}`);
}

// Test file pattern matching
function testFilePatterns() {
  console.log('\n🔍 Testing File Pattern Matching');
  console.log('='.repeat(35));

  const criticalPatterns = [
    /\/security\//i, /\/auth\//i, /\/payment\//i, /\/admin\//i, /\/core\//i, /\/api\//i,
    /security\./i, /auth\./i, /payment\./i, /admin\./i, /core\./i, /api\./i,
    /login\./i, /user\./i, /permission\./i, /role\./i, /session\./i, /token\./i,
    /-security/i, /-auth/i, /-payment/i, /-admin/i, /-core/i, /-api/i,
    /crypto/i, /encrypt/i, /password/i, /oauth/i, /jwt/i, /sql/i,
    /database/i, /migration/i, /schema/i
  ];

  const testFiles = [
    'src/security/auth.php',
    'includes/payment-gateway.php', 
    'admin/user-management.php',
    'api/endpoints.php',
    'src/utils.php',
    'README.md',
    'docs/installation.md',
    'tests/unit-tests.php'
  ];

  testFiles.forEach(file => {
    const isCritical = criticalPatterns.some(pattern => pattern.test(file));
    const status = isCritical ? '🔴 Critical' : '🟢 Safe';
    console.log(`   ${file} → ${status}`);
  });
}

// Main execution
if (require.main === module) {
  runTests();
  displayCostAnalysis();
  testFilePatterns();
  
  console.log('\n🚀 Cost optimization testing completed successfully!');
  console.log('You can now deploy the optimized workflow with confidence.');
}
