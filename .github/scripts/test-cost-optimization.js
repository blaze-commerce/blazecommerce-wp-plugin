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
    name: "Documentation changes - should use Sonnet",
    files: ['README.md', 'docs/installation.md', 'CHANGELOG.md'],
    additions: 20,
    deletions: 5,
    expected: 'claude-3-5-sonnet-20240620',
    reason: 'Using Claude Sonnet for all reviews'
  },
  {
    name: "Small config changes - should use Sonnet",
    files: ['package.json', 'composer.json'],
    additions: 5,
    deletions: 2,
    expected: 'claude-3-5-sonnet-20240620',
    reason: 'Using Claude Sonnet for all reviews'
  },
  {
    name: "Mixed small changes - should use Sonnet",
    files: ['src/utils.php', 'assets/style.css'],
    additions: 15,
    deletions: 8,
    expected: 'claude-3-5-sonnet-20240620',
    reason: 'Using Claude Sonnet for all reviews'
  }
];

// Model selection logic (always returns Sonnet now)
function selectModel(files, additions, deletions, forceSonnet = false) {
  // Always use Claude Sonnet model
  return {
    model: 'claude-3-5-sonnet-20240620',
    reason: 'Using Claude Sonnet for all reviews'
  };

}

// Cost calculation
function calculateCostSavings() {
  const sonnetCostPer1K = 15.00; // $15 per 1K tokens
  const avgTokensPerReview = 2000; // Average tokens per review
  const reviewsPerDay = 10; // Estimated reviews per day

  // Using only Sonnet now
  const costPerReview = (avgTokensPerReview / 1000) * sonnetCostPer1K;
  const dailyCost = costPerReview * reviewsPerDay;

  return {
    dailyCost: dailyCost.toFixed(2),
    costPerReview: costPerReview.toFixed(2),
    // No savings since we're using only Sonnet
    savingsPercentage: "0.0"
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
  console.log('\n💰 Cost Analysis');
  console.log('='.repeat(30));

  const costs = calculateCostSavings();

  console.log(`Daily cost (100% Sonnet): $${costs.dailyCost}`);
  console.log(`Cost per review: $${costs.costPerReview}`);
  console.log(`Monthly cost: $${(parseFloat(costs.dailyCost) * 30).toFixed(2)}`);
  console.log(`Annual cost: $${(parseFloat(costs.dailyCost) * 365).toFixed(2)}`);
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
    console.log(`   ${file} → ${status} (All use Sonnet now)`);
  });
}

// Main execution
if (require.main === module) {
  runTests();
  displayCostAnalysis();
  testFilePatterns();

  console.log('\n🚀 Claude Sonnet model testing completed successfully!');
  console.log('All reviews will now use Claude Sonnet for consistent quality.');
}
