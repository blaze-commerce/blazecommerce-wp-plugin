#!/usr/bin/env node

/**
 * Test Script for Intelligent Commit Analysis
 * Tests the new intelligent commit scanning and gap detection functionality
 * 
 * @author BlazeCommerce Workflow Enhancement
 * @version 1.0.0
 */

const {
  analyzeCommitHistoryWithGapDetection,
  detectVersionGaps,
  getVersionHistory,
  analyzeCommitsBetweenTags,
  calculateActualBump
} = require('./semver-utils');

/**
 * Test intelligent commit analysis
 */
async function testIntelligentAnalysis() {
  console.log('🧪 Testing Intelligent Commit Analysis\n');
  
  try {
    // Test 1: Version History
    console.log('📋 Test 1: Version History Analysis');
    console.log('=====================================');
    const versionHistory = getVersionHistory({ verbose: true, limit: 5 });
    console.log('Version History Result:', JSON.stringify(versionHistory, null, 2));
    console.log('');
    
    // Test 2: Gap Detection
    console.log('🔍 Test 2: Gap Detection Analysis');
    console.log('==================================');
    const gapAnalysis = detectVersionGaps({ verbose: true, maxTagsToAnalyze: 5 });
    console.log('Gap Detection Result:', JSON.stringify(gapAnalysis, null, 2));
    console.log('');
    
    // Test 3: Intelligent Commit Analysis
    console.log('🔍 Test 3: Intelligent Commit Analysis');
    console.log('=======================================');
    const intelligentAnalysis = analyzeCommitHistoryWithGapDetection({
      verbose: true,
      includeGapDetection: true,
      maxCommitsToAnalyze: 50,
      maxTagsToAnalyze: 5,
      enableCumulativeAnalysis: true
    });
    console.log('Intelligent Analysis Result:', JSON.stringify(intelligentAnalysis, null, 2));
    console.log('');
    
    // Test 4: Commits Between Tags (if tags exist)
    if (versionHistory.hasHistory && versionHistory.history.length >= 2) {
      console.log('📊 Test 4: Commits Between Tags Analysis');
      console.log('=========================================');
      const newerTag = versionHistory.history[0].tag;
      const olderTag = versionHistory.history[1].tag;
      
      const betweenTagsAnalysis = analyzeCommitsBetweenTags(olderTag, newerTag, {
        verbose: true,
        includeDetails: true
      });
      console.log(`Analysis between ${olderTag} and ${newerTag}:`);
      console.log(JSON.stringify(betweenTagsAnalysis, null, 2));
      console.log('');
    }
    
    // Test 5: Actual Bump Calculation
    console.log('🔢 Test 5: Actual Bump Calculation');
    console.log('===================================');
    const testVersions = [
      ['1.0.0', '1.0.1'],
      ['1.0.0', '1.1.0'],
      ['1.0.0', '2.0.0'],
      ['1.5.3', '1.5.4'],
      ['2.1.0', '3.0.0']
    ];
    
    testVersions.forEach(([from, to]) => {
      const bumpType = calculateActualBump(from, to);
      console.log(`${from} → ${to}: ${bumpType} bump`);
    });
    console.log('');
    
    // Summary
    console.log('✅ Test Summary');
    console.log('===============');
    console.log(`Version history available: ${versionHistory.hasHistory}`);
    console.log(`Gap detection enabled: ${gapAnalysis.hasGaps !== undefined}`);
    console.log(`Gaps detected: ${gapAnalysis.hasGaps}`);
    console.log(`Intelligent analysis completed: ${intelligentAnalysis.finalBumpType !== undefined}`);
    console.log(`Final bump recommendation: ${intelligentAnalysis.finalBumpType}`);
    console.log(`Analysis confidence: ${intelligentAnalysis.confidence}`);
    console.log(`Recommendations count: ${intelligentAnalysis.recommendations.length}`);
    
    if (intelligentAnalysis.recommendations.length > 0) {
      console.log('\n💡 Recommendations:');
      intelligentAnalysis.recommendations.forEach((rec, i) => {
        console.log(`   ${i + 1}. ${rec}`);
      });
    }
    
    console.log('\n🎉 All tests completed successfully!');
    
  } catch (error) {
    console.error('❌ Test failed:', error.message);
    console.error('Stack trace:', error.stack);
    process.exit(1);
  }
}

/**
 * Test the BumpTypeAnalyzer integration
 */
async function testBumpTypeAnalyzerIntegration() {
  console.log('\n🔧 Testing BumpTypeAnalyzer Integration\n');
  
  try {
    const { BumpTypeAnalyzer } = require('../.github/scripts/bump-type-analyzer');
    const analyzer = new BumpTypeAnalyzer();
    
    console.log('📊 Traditional Analysis:');
    const traditionalResult = analyzer.analyze(false, 'none');
    console.log(JSON.stringify(traditionalResult, null, 2));
    
    console.log('\n🔍 Intelligent Analysis:');
    const intelligentResult = analyzer.analyzeIntelligent(false, 'none', {
      enableGapDetection: true,
      enableCumulativeAnalysis: true,
      verbose: true
    });
    console.log(JSON.stringify(intelligentResult, null, 2));
    
    console.log('\n✅ BumpTypeAnalyzer integration test completed!');
    
  } catch (error) {
    console.error('❌ BumpTypeAnalyzer integration test failed:', error.message);
    console.error('Stack trace:', error.stack);
  }
}

// Main execution
if (require.main === module) {
  console.log('🚀 Starting Intelligent Commit Analysis Tests\n');
  
  testIntelligentAnalysis()
    .then(() => testBumpTypeAnalyzerIntegration())
    .then(() => {
      console.log('\n🎉 All tests completed!');
      process.exit(0);
    })
    .catch((error) => {
      console.error('❌ Test suite failed:', error.message);
      process.exit(1);
    });
}

module.exports = {
  testIntelligentAnalysis,
  testBumpTypeAnalyzerIntegration
};
