#!/usr/bin/env node

/**
 * Commit Parser Script
 * Analyzes commit messages and determines appropriate actions
 * Supports conventional commits and custom patterns with enhanced error handling
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 2.0.0
 */

const fs = require('fs');
const { Logger, handleError, handleWarning, ErrorCategory, ErrorSeverity } = require('./error-handler');

/**
 * Commit Parser Class
 * Provides comprehensive commit message analysis with standardized error handling
 */
class CommitParser {
  /**
   * Initialize CommitParser with environment variables
   */
  constructor() {
    try {
      this.commitMessages = process.env.COMMIT_MESSAGES || '';
      this.prTitle = process.env.PR_TITLE || '';
      this.prBody = process.env.PR_BODY || '';

      Logger.info('CommitParser initialized successfully');
      Logger.debug(`Commit messages length: ${this.commitMessages.length}`);
      Logger.debug(`PR title: ${this.prTitle}`);
    } catch (error) {
      handleError('Failed to initialize CommitParser', ErrorCategory.CONFIGURATION, ErrorSeverity.CRITICAL, error);
      throw error;
    }
  }

  /**
   * Parse conventional commit format with comprehensive validation
   * @param {string} message - Commit message to parse
   * @returns {Object} Parsed commit information with validation status
   * @throws {Error} When message parsing fails critically
   */
  parseConventionalCommit(message) {
    try {
      if (!message || typeof message !== 'string') {
        handleWarning('Invalid commit message provided', { message });
        return { isValid: false, type: 'unknown', description: message || '' };
      }

      // Enhanced conventional commit pattern with breaking change support
      const conventionalPattern = /^(\w+)(\([^)]+\))?\s*(!?):\s*(.+)$/;
      const match = message.trim().match(conventionalPattern);

      if (match) {
        const result = {
          isValid: true,
          type: match[1].toLowerCase(),
        scope: match[2] ? match[2].slice(1, -1) : null,
        description: match[3],
        isConventional: true
      };
    }
    
    return {
      type: 'unknown',
      scope: null,
      description: message,
      isConventional: false
    };
  }

  /**
   * Analyze commit messages for patterns
   * @returns {object} Analysis results
   */
  analyzeCommits() {
    const messages = this.commitMessages.split('\n').filter(msg => msg.trim());
    const analysis = {
      totalCommits: messages.length,
      conventionalCommits: 0,
      types: {},
      hasBreakingChanges: false,
      hasSecurityFixes: false,
      hasFeatures: false,
      hasFixes: false,
      hasDocumentation: false,
      hasTests: false,
      skipCI: false,
      skipVersion: false,
      releaseType: 'patch'
    };

    for (const message of messages) {
      const parsed = this.parseConventionalCommit(message);
      
      if (parsed.isConventional) {
        analysis.conventionalCommits++;
      }
      
      // Count commit types
      analysis.types[parsed.type] = (analysis.types[parsed.type] || 0) + 1;
      
      // Check for specific patterns
      const lowerMessage = message.toLowerCase();
      
      // Breaking changes
      if (lowerMessage.includes('breaking change') || 
          lowerMessage.includes('breaking:') ||
          message.includes('!:') ||
          parsed.type === 'breaking') {
        analysis.hasBreakingChanges = true;
        analysis.releaseType = 'major';
      }
      
      // Security fixes
      if (parsed.type === 'security' || 
          lowerMessage.includes('security') ||
          lowerMessage.includes('vulnerability') ||
          lowerMessage.includes('cve-')) {
        analysis.hasSecurityFixes = true;
        if (analysis.releaseType === 'patch') {
          analysis.releaseType = 'patch'; // Security fixes are typically patches
        }
      }
      
      // Features
      if (parsed.type === 'feat' || 
          parsed.type === 'feature' ||
          lowerMessage.includes('add:') ||
          lowerMessage.includes('new:')) {
        analysis.hasFeatures = true;
        if (analysis.releaseType !== 'major') {
          analysis.releaseType = 'minor';
        }
      }
      
      // Fixes
      if (parsed.type === 'fix' || 
          parsed.type === 'bug' ||
          lowerMessage.includes('fix:') ||
          lowerMessage.includes('bug:')) {
        analysis.hasFixes = true;
      }
      
      // Documentation
      if (parsed.type === 'docs' || 
          lowerMessage.includes('docs:') ||
          lowerMessage.includes('documentation')) {
        analysis.hasDocumentation = true;
      }
      
      // Tests
      if (parsed.type === 'test' || 
          lowerMessage.includes('test:') ||
          lowerMessage.includes('testing')) {
        analysis.hasTests = true;
      }
      
      // CI/CD control flags
      if (lowerMessage.includes('[skip ci]') || 
          lowerMessage.includes('[ci skip]') ||
          lowerMessage.includes('skip ci')) {
        analysis.skipCI = true;
      }
      
      if (lowerMessage.includes('[skip version]') || 
          lowerMessage.includes('[no version]') ||
          lowerMessage.includes('skip version')) {
        analysis.skipVersion = true;
      }
    }

    return analysis;
  }

  /**
   * Generate changelog entry from commits
   * @returns {string} Formatted changelog entry
   */
  generateChangelog() {
    const analysis = this.analyzeCommits();
    const messages = this.commitMessages.split('\n').filter(msg => msg.trim());
    
    let changelog = '';
    const sections = {
      'Breaking Changes': [],
      'Features': [],
      'Bug Fixes': [],
      'Security': [],
      'Documentation': [],
      'Tests': [],
      'Other': []
    };

    for (const message of messages) {
      const parsed = this.parseConventionalCommit(message);
      const cleanMessage = parsed.description || message;
      
      if (message.toLowerCase().includes('breaking change') || parsed.type === 'breaking') {
        sections['Breaking Changes'].push(`- ${cleanMessage}`);
      } else if (parsed.type === 'feat' || parsed.type === 'feature') {
        sections['Features'].push(`- ${cleanMessage}`);
      } else if (parsed.type === 'fix' || parsed.type === 'bug') {
        sections['Bug Fixes'].push(`- ${cleanMessage}`);
      } else if (parsed.type === 'security') {
        sections['Security'].push(`- ${cleanMessage}`);
      } else if (parsed.type === 'docs') {
        sections['Documentation'].push(`- ${cleanMessage}`);
      } else if (parsed.type === 'test') {
        sections['Tests'].push(`- ${cleanMessage}`);
      } else if (parsed.type !== 'chore' && parsed.type !== 'style' && parsed.type !== 'refactor') {
        sections['Other'].push(`- ${cleanMessage}`);
      }
    }

    // Build changelog
    for (const [section, items] of Object.entries(sections)) {
      if (items.length > 0) {
        changelog += `### ${section}\n${items.join('\n')}\n\n`;
      }
    }

    return changelog.trim();
  }

  /**
   * Determine if version bump should be skipped
   * @returns {boolean} True if version bump should be skipped
   */
  shouldSkipVersionBump() {
    const analysis = this.analyzeCommits();
    
    // Skip if explicitly requested
    if (analysis.skipVersion) {
      return true;
    }
    
    // Skip if only documentation or test changes
    if (analysis.hasDocumentation && 
        !analysis.hasFeatures && 
        !analysis.hasFixes && 
        !analysis.hasBreakingChanges &&
        !analysis.hasSecurityFixes) {
      return true;
    }
    
    return false;
  }

  /**
   * Main execution function
   */
  run() {
    console.log('🔍 Starting commit analysis...');
    
    const analysis = this.analyzeCommits();
    const changelog = this.generateChangelog();
    const shouldSkip = this.shouldSkipVersionBump();
    
    console.log('📊 Commit Analysis Results:');
    console.log(`  Total Commits: ${analysis.totalCommits}`);
    console.log(`  Conventional Commits: ${analysis.conventionalCommits}`);
    console.log(`  Release Type: ${analysis.releaseType}`);
    console.log(`  Skip Version: ${shouldSkip}`);
    console.log(`  Skip CI: ${analysis.skipCI}`);
    
    // Output for GitHub Actions
    if (process.env.GITHUB_OUTPUT) {
      const output = [
        `RELEASE_TYPE=${analysis.releaseType}`,
        `SKIP_VERSION=${shouldSkip}`,
        `SKIP_CI=${analysis.skipCI}`,
        `HAS_BREAKING_CHANGES=${analysis.hasBreakingChanges}`,
        `HAS_SECURITY_FIXES=${analysis.hasSecurityFixes}`,
        `HAS_FEATURES=${analysis.hasFeatures}`,
        `HAS_FIXES=${analysis.hasFixes}`,
        `CONVENTIONAL_COMMITS=${analysis.conventionalCommits}`,
        `TOTAL_COMMITS=${analysis.totalCommits}`
      ].join('\n');
      
      fs.appendFileSync(process.env.GITHUB_OUTPUT, output + '\n');
    }
    
    // Write changelog to file
    if (changelog && process.env.GITHUB_WORKSPACE) {
      const changelogPath = `${process.env.GITHUB_WORKSPACE}/CHANGELOG_ENTRY.md`;
      fs.writeFileSync(changelogPath, changelog);
      console.log(`📝 Changelog entry written to ${changelogPath}`);
    }
    
    console.log('✅ Commit analysis completed successfully');
  }
}

// Execute if run directly
if (require.main === module) {
  const parser = new CommitParser();
  parser.run();
}

module.exports = CommitParser;
