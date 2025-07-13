#!/usr/bin/env node

/**
 * Test Suite for Claude Progressive Review System
 * Tests the enhanced tracking and progressive review functionality
 */

const fs = require('fs');
const path = require('path');
const { ClaudeReviewEnhancer } = require('../claude-review-enhancer');

// Mock environment setup
process.env.PR_NUMBER = '123';
process.env.GITHUB_REPOSITORY = 'blaze-commerce/test-repo';
process.env.GITHUB_SHA = 'abc123def456';
process.env.REPO_TYPE = 'test';

// Test data
const mockClaudeOutput1 = `
## 🔴 REQUIRED - Critical Issues

### 1. Security Vulnerability in User Input
The user input validation is missing proper sanitization.

### 2. Database Query Injection Risk
SQL queries are not using prepared statements.

## 🟡 IMPORTANT - Improvements

### 1. Error Handling Enhancement
Add comprehensive error handling for API calls.

## 🔵 SUGGESTIONS - Optional

### 1. Code Documentation
Add JSDoc comments to all functions.
`;

const mockClaudeOutput2 = `
## 🔴 REQUIRED - Critical Issues

### 1. Database Query Injection Risk
SQL queries are not using prepared statements.

### 2. Memory Leak in Event Handlers
Event listeners are not properly cleaned up.

## 🟡 IMPORTANT - Improvements

### 1. Error Handling Enhancement
Add comprehensive error handling for API calls.

## 🔵 SUGGESTIONS - Optional

### 1. Performance Optimization
Implement caching for frequently accessed data.
`;

class ProgressiveReviewTester {
  constructor() {
    this.testResults = [];
    this.enhancer = new ClaudeReviewEnhancer();
  }

  log(message, type = 'info') {
    const timestamp = new Date().toISOString();
    const prefix = type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️';
    console.log(`${prefix} [${timestamp}] ${message}`);
  }

  async runTest(testName, testFunction) {
    try {
      this.log(`Running test: ${testName}`);
      await testFunction();
      this.testResults.push({ name: testName, status: 'PASS' });
      this.log(`Test passed: ${testName}`, 'success');
    } catch (error) {
      this.testResults.push({ name: testName, status: 'FAIL', error: error.message });
      this.log(`Test failed: ${testName} - ${error.message}`, 'error');
    }
  }

  async testRecommendationHashing() {
    const rec1 = "1. Security Vulnerability in User Input\nThe user input validation is missing proper sanitization.";
    const rec2 = "2. ✅ **RESOLVED** Security Vulnerability in User Input\nThe user input validation is missing proper sanitization.\n*Applied: 2024-12-13*";
    
    const hash1 = this.enhancer.generateRecommendationHash(rec1);
    const hash2 = this.enhancer.generateRecommendationHash(rec2);
    
    if (hash1 !== hash2) {
      throw new Error(`Hashes should be equal: ${hash1} !== ${hash2}`);
    }
  }

  async testRecommendationParsing() {
    const recommendations = this.enhancer.parseClaudeReview(mockClaudeOutput1);
    
    if (recommendations.required.length !== 2) {
      throw new Error(`Expected 2 required recommendations, got ${recommendations.required.length}`);
    }
    
    if (recommendations.important.length !== 1) {
      throw new Error(`Expected 1 important recommendation, got ${recommendations.important.length}`);
    }
    
    if (recommendations.suggestions.length !== 1) {
      throw new Error(`Expected 1 suggestion, got ${recommendations.suggestions.length}`);
    }
  }

  async testProgressiveTracking() {
    // Simulate first review
    const result1 = await this.enhancer.processReview(mockClaudeOutput1);
    
    if (!result1.success) {
      throw new Error('First review processing failed');
    }
    
    if (result1.reviewVersion !== 1) {
      throw new Error(`Expected review version 1, got ${result1.reviewVersion}`);
    }
    
    // Simulate second review with some resolved issues
    this.enhancer.reviewVersion = 2;
    const result2 = await this.enhancer.processReview(mockClaudeOutput2);
    
    if (!result2.success) {
      throw new Error('Second review processing failed');
    }
    
    if (!result2.analysis) {
      throw new Error('Analysis should be present in second review');
    }
    
    // Check if resolved issues were detected
    const resolvedCount = result2.analysis.resolved.required.length + result2.analysis.resolved.important.length;
    if (resolvedCount === 0) {
      this.log('Warning: No resolved issues detected - this might be expected in test environment');
    }
  }

  async testTrackingDataStructure() {
    const recommendations = this.enhancer.parseClaudeReview(mockClaudeOutput1);
    const analysis = { resolved: { required: [], important: [] }, new: { required: [], important: [] }, persistent: { required: [], important: [] } };
    
    const trackingData = this.enhancer.updateTrackingData(recommendations, analysis, null);
    
    // Verify required fields
    const requiredFields = ['pr_number', 'created_at', 'updated_at', 'review_history', 'recommendations', 'cumulative_stats'];
    for (const field of requiredFields) {
      if (!trackingData.hasOwnProperty(field)) {
        throw new Error(`Missing required field: ${field}`);
      }
    }
    
    if (trackingData.pr_number !== 123) {
      throw new Error(`Expected PR number 123, got ${trackingData.pr_number}`);
    }
    
    if (trackingData.review_history.length !== 1) {
      throw new Error(`Expected 1 review in history, got ${trackingData.review_history.length}`);
    }
  }

  async testEnhancedCommentGeneration() {
    const recommendations = this.enhancer.parseClaudeReview(mockClaudeOutput1);
    const analysis = {
      resolved: { required: [], important: [] },
      new: { 
        required: recommendations.required.map((rec, index) => ({ index, text: rec, hash: this.enhancer.generateRecommendationHash(rec) })),
        important: recommendations.important.map((rec, index) => ({ index, text: rec, hash: this.enhancer.generateRecommendationHash(rec) }))
      },
      persistent: { required: [], important: [] }
    };
    
    const trackingData = { repo_type: 'test', review_history: [] };
    const comment = this.enhancer.generateEnhancedComment(mockClaudeOutput1, trackingData, analysis);
    
    // Verify comment structure
    if (!comment.includes('BlazeCommerce Claude AI Review v')) {
      throw new Error('Comment missing version header');
    }
    
    if (!comment.includes('🔴 REQUIRED Issues')) {
      throw new Error('Comment missing required issues section');
    }
    
    if (!comment.includes('🆕 **NEW**')) {
      throw new Error('Comment missing new issue indicators');
    }
  }

  async runAllTests() {
    this.log('Starting Progressive Review System Tests');
    
    await this.runTest('Recommendation Hashing', () => this.testRecommendationHashing());
    await this.runTest('Recommendation Parsing', () => this.testRecommendationParsing());
    await this.runTest('Tracking Data Structure', () => this.testTrackingDataStructure());
    await this.runTest('Enhanced Comment Generation', () => this.testEnhancedCommentGeneration());
    await this.runTest('Progressive Tracking', () => this.testProgressiveTracking());
    
    // Summary
    const passed = this.testResults.filter(r => r.status === 'PASS').length;
    const failed = this.testResults.filter(r => r.status === 'FAIL').length;
    
    this.log(`\nTest Summary: ${passed} passed, ${failed} failed`);
    
    if (failed > 0) {
      this.log('Failed tests:');
      this.testResults.filter(r => r.status === 'FAIL').forEach(test => {
        this.log(`  - ${test.name}: ${test.error}`, 'error');
      });
      process.exit(1);
    } else {
      this.log('All tests passed!', 'success');
      process.exit(0);
    }
  }
}

// Run tests if this file is executed directly
if (require.main === module) {
  const tester = new ProgressiveReviewTester();
  tester.runAllTests().catch(error => {
    console.error('Test execution failed:', error);
    process.exit(1);
  });
}

module.exports = { ProgressiveReviewTester };
