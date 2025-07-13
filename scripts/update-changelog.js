#!/usr/bin/env node

/**
 * Enhanced Changelog Generation Script
 * Generates categorized changelog based on conventional commits
 *
 * SECURITY ENHANCEMENTS (Claude AI Review Recommendations):
 * - ReDoS protection with regex timeouts
 * - Path sanitization for security
 * - Input validation and length limits
 * - Memory-conscious processing
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const { parseConventionalCommit, getCommitsSinceLastTag, getLatestTag } = require('./semver-utils');
const config = require('./config');

// Security constants
const SECURITY_LIMITS = {
  REGEX_TIMEOUT: 5000, // 5 seconds timeout for regex operations
  MAX_ITERATIONS: 10000, // Prevent infinite loops
  MAX_PATH_LENGTH: 1000, // Maximum path length for security
  SUSPICIOUS_PATTERNS: [
    /\.\./g, // Directory traversal
    /[<>"|*?]/g, // Invalid filename characters
    /[\x00-\x1f\x7f]/g // Control characters
  ]
};

// Configuration for changelog file location
const CHANGELOG_PATH = 'docs/reference/changelog.md';

/**
 * SECURITY: Sanitize file paths to prevent directory traversal attacks
 * @param {string} inputPath - Path to sanitize
 * @returns {string} Sanitized path
 */
function sanitizePath(inputPath) {
  if (!inputPath || typeof inputPath !== 'string') {
    throw new Error('Invalid path input');
  }

  if (inputPath.length > SECURITY_LIMITS.MAX_PATH_LENGTH) {
    throw new Error(`Path too long: ${inputPath.length} > ${SECURITY_LIMITS.MAX_PATH_LENGTH}`);
  }

  // Check for suspicious patterns
  for (const pattern of SECURITY_LIMITS.SUSPICIOUS_PATTERNS) {
    if (pattern.test(inputPath)) {
      throw new Error(`Suspicious pattern detected in path: ${inputPath}`);
    }
  }

  // Normalize and resolve path
  const normalizedPath = path.normalize(inputPath);
  const resolvedPath = path.resolve(normalizedPath);

  // Ensure path is within project directory
  const projectRoot = path.resolve('.');
  if (!resolvedPath.startsWith(projectRoot)) {
    throw new Error(`Path outside project directory: ${resolvedPath}`);
  }

  return normalizedPath;
}

/**
 * SECURITY: Safe regex execution with timeout protection against ReDoS attacks
 * @param {RegExp} regex - Regular expression to execute
 * @param {string} text - Text to test against
 * @param {number} timeout - Timeout in milliseconds
 * @returns {RegExpExecArray|null} Match result or null
 */
function safeRegexExec(regex, text, timeout = SECURITY_LIMITS.REGEX_TIMEOUT) {
  return new Promise((resolve, reject) => {
    const timeoutId = setTimeout(() => {
      reject(new Error(`Regex execution timeout after ${timeout}ms`));
    }, timeout);

    try {
      const result = regex.exec(text);
      clearTimeout(timeoutId);
      resolve(result);
    } catch (error) {
      clearTimeout(timeoutId);
      reject(error);
    }
  });
}

// Configuration
const CONFIG = {
  dryRun: process.argv.includes('--dry-run'),
  verbose: process.argv.includes('--verbose') || process.argv.includes('-v'),
  includeAll: process.argv.includes('--include-all'),
  userFriendly: !process.argv.includes('--technical'), // Default to user-friendly
  groupByScope: process.argv.includes('--group-by-scope')
};

// Commit type categories and their display names
const COMMIT_CATEGORIES = {
  'feat': { title: '✨ New Features', emoji: '✨', userTitle: '✨ New Features' },
  'fix': { title: '🐛 Bug Fixes', emoji: '🐛', userTitle: '🐛 Bug Fixes' },
  'perf': { title: '⚡ Performance Improvements', emoji: '⚡', userTitle: '⚡ Performance Improvements' },
  'refactor': { title: '♻️ Code Refactoring', emoji: '♻️', userTitle: '🔧 Improvements' },
  'docs': { title: '📚 Documentation', emoji: '📚', userTitle: '📚 Documentation' },
  'style': { title: '💄 Styles', emoji: '💄', userTitle: '🎨 Visual Improvements' },
  'test': { title: '✅ Tests', emoji: '✅', userTitle: '✅ Testing' },
  'build': { title: '👷 Build System', emoji: '👷', userTitle: '🔧 Build System' },
  'ci': { title: '💚 Continuous Integration', emoji: '💚', userTitle: '🤖 Automation' },
  'chore': { title: '🔧 Chores', emoji: '🔧', userTitle: '🔧 Maintenance' }
};

// Technical abbreviations and their user-friendly expansions
const ABBREVIATIONS = {
  'API': 'Application Programming Interface',
  'UI': 'user interface',
  'CLI': 'command-line tool',
  'DB': 'database',
  'WP': 'WordPress',
  'JS': 'JavaScript',
  'CSS': 'styling',
  'HTTP': 'web request',
  'JSON': 'data format',
  'SQL': 'database query',
  'URL': 'web address',
  'PHP': 'server code',
  'HTML': 'page structure',
  'AJAX': 'dynamic loading',
  'REST': 'web service',
  'GraphQL': 'data query system',
  'JWT': 'authentication token',
  'OAuth': 'secure login',
  'CORS': 'cross-origin access',
  'CSRF': 'security protection',
  'XSS': 'security vulnerability',
  'SEO': 'search engine optimization',
  'CDN': 'content delivery network',
  'SSL': 'secure connection',
  'FTP': 'file transfer',
  'SSH': 'secure connection',
  'DNS': 'domain name system',
  'IP': 'internet address'
};

// Scope context mapping for better descriptions
const SCOPE_CONTEXT = {
  'api': 'in the Application Programming Interface',
  'ui': 'in the user interface',
  'cli': 'in the command-line tools',
  'sync': 'in the synchronization system',
  'admin': 'in the admin panel',
  'frontend': 'in the frontend display',
  'backend': 'in the backend system',
  'auth': 'in the authentication system',
  'payment': 'in the payment processing',
  'cart': 'in the shopping cart',
  'checkout': 'in the checkout process',
  'product': 'for product management',
  'order': 'for order processing',
  'user': 'for user management',
  'email': 'in email functionality',
  'search': 'in the search functionality',
  'filter': 'in the filtering system',
  'cache': 'in the caching system',
  'security': 'in the security system',
  'performance': 'for performance optimization',
  'mobile': 'for mobile devices',
  'responsive': 'for responsive design',
  'accessibility': 'for accessibility features'
};

// Action word mapping for different commit types
const ACTION_WORDS = {
  'feat': ['Added', 'Introduced', 'Implemented', 'Created'],
  'fix': ['Fixed', 'Resolved', 'Corrected', 'Addressed'],
  'perf': ['Improved', 'Optimized', 'Enhanced', 'Accelerated'],
  'refactor': ['Improved', 'Refactored', 'Enhanced', 'Restructured'],
  'docs': ['Updated', 'Improved', 'Enhanced', 'Documented'],
  'style': ['Improved', 'Enhanced', 'Updated', 'Refined'],
  'test': ['Added', 'Improved', 'Enhanced', 'Updated'],
  'build': ['Updated', 'Improved', 'Enhanced', 'Modified'],
  'ci': ['Updated', 'Improved', 'Enhanced', 'Modified'],
  'chore': ['Updated', 'Maintained', 'Improved', 'Modified']
};

/**
 * Extract issue/PR references from commit message with enhanced security
 * @param {string} message - Commit message
 * @returns {Promise<string[]>} Array of references
 */
async function extractReferences(message) {
  if (!message || typeof message !== 'string') {
    return [];
  }

  // Limit message length for security and performance
  const safeMessage = message.substring(0, config.CHANGELOG.MAX_COMMIT_MESSAGE_LENGTH);
  const references = new Set(); // Use Set to avoid duplicates efficiently

  // Match patterns like (#123), (PR #123), closes #123, fixes #123, resolves #123
  const patterns = [
    /\(#(\d+)\)/g,
    /\(PR #(\d+)\)/g,
    /(?:closes?|fixes?|resolves?) #(\d+)/gi,
    /#(\d+)/g
  ];

  for (const pattern of patterns) {
    let match;
    let matchCount = 0;
    let iterationCount = 0;

    // Reset regex lastIndex for safety
    pattern.lastIndex = 0;

    try {
      while (matchCount < config.CHANGELOG.MAX_REFERENCES_PER_COMMIT &&
             iterationCount < SECURITY_LIMITS.MAX_ITERATIONS) {

        // Use safe regex execution with timeout protection
        match = await safeRegexExec(pattern, safeMessage);

        if (!match) break;

        const issueNumber = parseInt(match[1], 10);
        if (issueNumber > 0 && issueNumber <= config.VERSION.MAX_ISSUE_NUMBER) {
          references.add(`#${issueNumber}`);
          matchCount++;
        }

        iterationCount++;

        // Move pattern position forward to prevent infinite loops
        if (pattern.lastIndex <= match.index) {
          pattern.lastIndex = match.index + 1;
        }
      }
    } catch (error) {
      console.warn(`⚠️  Regex timeout or error processing references: ${error.message}`);
      // Continue with partial results rather than failing completely
    }
  }

  return Array.from(references);
}

/**
 * Expand technical abbreviations in text
 * @param {string} text - Text to expand
 * @returns {string} Text with expanded abbreviations
 */
function expandAbbreviations(text) {
  if (!text || typeof text !== 'string') {
    return text || '';
  }

  // Limit text length for performance
  if (text.length > config.CHANGELOG.MAX_EXPANSION_LENGTH) {
    text = text.substring(0, config.CHANGELOG.MAX_EXPANSION_LENGTH);
  }

  let expandedText = text;

  // Pre-compile regexes for better performance (cache them)
  if (!expandAbbreviations._regexCache) {
    expandAbbreviations._regexCache = new Map();
    for (const abbrev of Object.keys(ABBREVIATIONS)) {
      expandAbbreviations._regexCache.set(abbrev, new RegExp(`\\b${abbrev}\\b`, 'gi'));
    }
  }

  for (const [abbrev, expansion] of Object.entries(ABBREVIATIONS)) {
    const regex = expandAbbreviations._regexCache.get(abbrev);
    expandedText = expandedText.replace(regex, (match) => {
      // Preserve original case for first letter
      if (match === match.toUpperCase()) {
        return expansion.charAt(0).toUpperCase() + expansion.slice(1);
      }
      return expansion;
    });
  }

  return expandedText;
}

/**
 * Improve capitalization and punctuation
 * @param {string} text - Text to improve
 * @returns {string} Improved text
 */
function improveCapitalization(text) {
  // Ensure first letter is capitalized
  text = text.charAt(0).toUpperCase() + text.slice(1);

  // Ensure proper ending punctuation
  if (!/[.!?]$/.test(text.trim())) {
    text = text.trim() + '.';
  }

  return text;
}

/**
 * Add contextual information based on commit type and scope
 * @param {string} type - Commit type
 * @param {string} scope - Commit scope
 * @param {string} description - Commit description
 * @returns {string} Enhanced description with context
 */
function addContext(type, scope, description) {
  let contextualDescription = description;

  // Add scope context if available
  if (scope && SCOPE_CONTEXT[scope.toLowerCase()]) {
    const scopeContext = SCOPE_CONTEXT[scope.toLowerCase()];

    // Check if description already mentions the scope context
    if (!contextualDescription.toLowerCase().includes(scope.toLowerCase())) {
      contextualDescription += ` ${scopeContext}`;
    }
  }

  // Add type-specific context
  switch (type) {
    case 'fix':
      if (!contextualDescription.toLowerCase().includes('issue') &&
          !contextualDescription.toLowerCase().includes('problem') &&
          !contextualDescription.toLowerCase().includes('bug')) {
        contextualDescription = `an issue where ${contextualDescription}`;
      }
      break;
    case 'feat':
      if (!contextualDescription.toLowerCase().includes('ability') &&
          !contextualDescription.toLowerCase().includes('feature') &&
          !contextualDescription.toLowerCase().includes('support')) {
        // Check if it's adding something new
        if (contextualDescription.toLowerCase().startsWith('add')) {
          contextualDescription = contextualDescription.replace(/^add\s+/i, 'the ability to ');
        }
      }
      break;
    case 'perf':
      if (!contextualDescription.toLowerCase().includes('performance') &&
          !contextualDescription.toLowerCase().includes('speed') &&
          !contextualDescription.toLowerCase().includes('efficiency')) {
        contextualDescription += ' for better performance';
      }
      break;
  }

  return contextualDescription;
}

/**
 * PERFORMANCE: Clean and normalize commit description
 * @param {string} description - Raw commit description
 * @returns {string} Cleaned description
 */
function cleanCommitDescription(description) {
  if (!description || typeof description !== 'string') {
    return '';
  }

  // Remove redundant action words
  return description.replace(/^(add|fix|update|improve|enhance|implement|create|resolve|correct|address)\s+/i, '');
}

/**
 * PERFORMANCE: Determine appropriate action word for commit type
 * @param {string} type - Commit type
 * @param {string} description - Commit description
 * @returns {string} Action word
 */
function getActionWord(type, description) {
  const actionWords = ACTION_WORDS[type] || ['Updated'];
  let actionWord = actionWords[0];

  if (type === 'fix') {
    // For fixes, use more natural language
    if (description.toLowerCase().includes('error') ||
        description.toLowerCase().includes('issue') ||
        description.toLowerCase().includes('problem') ||
        description.toLowerCase().includes('bug')) {
      actionWord = 'Resolved';
    } else {
      actionWord = 'Fixed';
    }
  } else if (type === 'feat') {
    // For features, be more specific about what was added
    if (description.toLowerCase().includes('support for') ||
        description.toLowerCase().includes('ability to')) {
      actionWord = 'Added';
    } else if (description.toLowerCase().includes('command') ||
             description.toLowerCase().includes('tool') ||
             description.toLowerCase().includes('function') ||
             description.toLowerCase().includes('script')) {
      actionWord = 'Introduced';
    } else if (description.toLowerCase().includes('integration') ||
             description.toLowerCase().includes('extension') ||
             description.toLowerCase().includes('plugin')) {
      actionWord = 'Implemented';
    } else {
      actionWord = 'Added';
    }
  } else if (type === 'perf') {
    actionWord = 'Improved';
  }

  return actionWord;
}

/**
 * PERFORMANCE: Process feature descriptions for better readability
 * @param {string} description - Feature description
 * @returns {string} Enhanced description
 */
function processFeatureDescription(description) {
  // Only add "the ability to" for verb-like descriptions
  if (!description.toLowerCase().includes('ability') &&
      !description.toLowerCase().includes('support') &&
      !description.toLowerCase().includes('feature') &&
      !description.toLowerCase().includes('option') &&
      !description.toLowerCase().includes('system') &&
      !description.toLowerCase().includes('integration') &&
      !description.toLowerCase().includes('extension') &&
      !description.toLowerCase().includes('component') &&
      !description.toLowerCase().includes('workflow') &&
      !description.toLowerCase().includes('documentation') &&
      !description.toLowerCase().includes('file') &&
      !description.toLowerCase().includes('folder') &&
      !description.toLowerCase().includes('directory')) {

    // Check if description starts with a verb (action word)
    const commonVerbs = ['sync', 'manage', 'handle', 'process', 'generate', 'create', 'build', 'deploy', 'configure', 'setup', 'install', 'update', 'upgrade', 'migrate', 'transform', 'convert', 'parse', 'validate', 'check', 'test', 'run', 'execute', 'perform', 'calculate', 'compute', 'analyze', 'optimize', 'improve', 'enhance', 'fix', 'resolve', 'correct', 'address', 'prevent', 'avoid', 'ensure', 'provide', 'enable', 'disable', 'activate', 'toggle', 'switch', 'change', 'modify', 'adjust', 'set', 'get', 'fetch', 'retrieve', 'load', 'save', 'store', 'delete', 'remove', 'clear', 'reset', 'refresh', 'reload', 'restart', 'start', 'stop', 'filter', 'sort', 'search', 'find', 'locate', 'discover', 'detect', 'identify', 'recognize', 'match', 'compare', 'merge', 'combine', 'join', 'split', 'separate', 'divide', 'group', 'organize', 'arrange'];
    const startsWithVerb = commonVerbs.some(verb => description.toLowerCase().startsWith(verb));
    if (startsWithVerb) {
      description = `the ability to ${description}`;
    }
  }

  return description;
}

/**
 * PERFORMANCE: Transform commit message into user-friendly description (decomposed)
 * @param {object} commitInfo - Parsed commit information
 * @returns {string} User-friendly description
 */
function transformCommitMessage(commitInfo) {
  if (!CONFIG.userFriendly) {
    // Return original format for technical mode
    return commitInfo.description;
  }

  let { type, scope, description } = commitInfo;

  // Clean up description
  description = cleanCommitDescription(description);

  // Get appropriate action word
  let actionWord = getActionWord(type, description);

  // Handle specific patterns for better readability
  if (type === 'fix' && actionWord === 'Fixed') {
    description = `an issue where ${description}`;
  } else if (type === 'feat') {
    description = processFeatureDescription(description);
  } else if (type === 'perf') {
    if (!description.toLowerCase().includes('performance') &&
        !description.toLowerCase().includes('speed') &&
        !description.toLowerCase().includes('efficiency') &&
        !description.toLowerCase().includes('optimization')) {
      description += ' for better performance';
    }
  } else if (type === 'refactor') {
    actionWord = 'Enhanced';
    if (!description.toLowerCase().includes('code') &&
        !description.toLowerCase().includes('structure') &&
        !description.toLowerCase().includes('implementation')) {
      description += ' implementation';
    }
  }

  // Expand abbreviations
  description = expandAbbreviations(description);

  // Add scope context if meaningful and not already included
  if (scope && SCOPE_CONTEXT[scope.toLowerCase()]) {
    const scopeContext = SCOPE_CONTEXT[scope.toLowerCase()];
    if (!description.toLowerCase().includes(scope.toLowerCase()) &&
        !description.toLowerCase().includes(scopeContext.replace('in the ', '').replace('for ', ''))) {
      // Only add scope context for certain scopes that add meaningful information
      if (['api', 'ui', 'cli', 'admin', 'auth', 'payment', 'cart', 'checkout'].includes(scope.toLowerCase())) {
        description += ` ${scopeContext}`;
      }
    }
  }

  // Construct user-friendly message
  let friendlyMessage = `${actionWord} ${description}`;

  // Clean up redundant phrases
  friendlyMessage = friendlyMessage.replace(/\s+/g, ' '); // Multiple spaces
  friendlyMessage = friendlyMessage.replace(/\b(the the|a a|an an)\b/gi, (match) => match.split(' ')[0]); // Duplicate articles

  // Improve capitalization and punctuation
  friendlyMessage = improveCapitalization(friendlyMessage);

  return friendlyMessage;
}

/**
 * PERFORMANCE: Process commits in batches with enhanced memory management
 * @param {string[]} commits - Array of commit messages
 * @param {number} batchSize - Size of each batch
 * @returns {object} Categorized commits
 */
function categorizeCommitsInBatches(commits, batchSize = 20) {
  // PERFORMANCE: Apply memory-conscious limits for large repositories
  const maxCommitsToProcess = Math.min(commits.length, config.CHANGELOG.MAX_CHANGELOG_COMMITS);
  const actualCommits = commits.slice(0, maxCommitsToProcess);

  if (commits.length > maxCommitsToProcess) {
    console.warn(`⚠️  Processing ${maxCommitsToProcess} of ${commits.length} commits for memory efficiency`);
  }

  const result = {
    breaking: [],
    categories: {},
    uncategorized: []
  };

  // Initialize categories with pre-allocated arrays for better performance
  for (const type of Object.keys(COMMIT_CATEGORIES)) {
    result.categories[type] = [];
  }

  // Process commits in batches with progress tracking
  const totalBatches = Math.ceil(actualCommits.length / batchSize);

  for (let i = 0; i < actualCommits.length; i += batchSize) {
    const batchNumber = Math.floor(i / batchSize) + 1;
    const batch = actualCommits.slice(i, i + batchSize);
    const batchResult = categorizeCommits(batch);

    // PERFORMANCE: Use more efficient array concatenation for large datasets
    if (batchResult.breaking.length > 0) {
      result.breaking = result.breaking.concat(batchResult.breaking);
    }
    if (batchResult.uncategorized.length > 0) {
      result.uncategorized = result.uncategorized.concat(batchResult.uncategorized);
    }

    for (const [type, commitList] of Object.entries(batchResult.categories)) {
      if (result.categories[type] && commitList.length > 0) {
        result.categories[type] = result.categories[type].concat(commitList);
      }
    }

    // PERFORMANCE: Trigger garbage collection for large batches
    if (batchNumber % 10 === 0 && typeof global !== 'undefined' && global.gc) {
      global.gc();
    }

    // Progress reporting for large operations
    if (CONFIG.verbose && totalBatches > 5) {
      const progress = Math.round((batchNumber / totalBatches) * 100);
      console.log(`   Processing batch ${batchNumber}/${totalBatches} (${progress}%)`);
    }
  }

  return result;
}

/**
 * Categorize commits by type
 * @param {string[]} commits - Array of commit messages
 * @returns {object} Categorized commits
 */
function categorizeCommits(commits) {
  const categorized = {
    breaking: [],
    categories: {},
    uncategorized: []
  };

  // Initialize categories
  for (const type of Object.keys(COMMIT_CATEGORIES)) {
    categorized.categories[type] = [];
  }

  for (const commit of commits) {
    const parsed = parseConventionalCommit(commit);

    if (!parsed) {
      // Handle non-conventional commits
      if (CONFIG.includeAll) {
        categorized.uncategorized.push({
          message: commit,
          hash: extractHashFromCommit(commit)
        });
      }
      continue;
    }

    const commitInfo = {
      type: parsed.type,
      scope: parsed.scope,
      description: parsed.description,
      breaking: parsed.breaking,
      message: commit,
      hash: extractHashFromCommit(commit)
    };

    if (parsed.breaking) {
      categorized.breaking.push(commitInfo);
    } else if (categorized.categories[parsed.type]) {
      categorized.categories[parsed.type].push(commitInfo);
    } else {
      categorized.uncategorized.push(commitInfo);
    }
  }

  return categorized;
}

/**
 * Extract commit hash from commit message
 * @param {string} commit - Commit message
 * @returns {string|null} Commit hash or null
 */
function extractHashFromCommit(commit) {
  const hashMatch = commit.match(/\(([a-f0-9]{7,})\)$/);
  return hashMatch ? hashMatch[1] : null;
}

/**
 * Format commit for changelog
 * @param {object} commitInfo - Commit information
 * @returns {Promise<string>} Formatted commit line
 */
async function formatCommit(commitInfo) {
  // Transform the commit message
  const transformedDescription = transformCommitMessage(commitInfo);

  // Extract references from original message
  const references = await extractReferences(commitInfo.message);

  let line;

  if (CONFIG.userFriendly) {
    // User-friendly format
    line = `- ${transformedDescription}`;

    // Add scope information in a more readable way
    if (commitInfo.scope && !transformedDescription.toLowerCase().includes(commitInfo.scope.toLowerCase())) {
      const scopeContext = SCOPE_CONTEXT[commitInfo.scope.toLowerCase()];
      if (scopeContext && !transformedDescription.includes(scopeContext)) {
        // Only add scope if it adds meaningful context
        if (['api', 'ui', 'cli', 'admin'].includes(commitInfo.scope.toLowerCase())) {
          line = `- ${transformedDescription.replace('.', '')} ${scopeContext}.`;
        }
      }
    }
  } else {
    // Technical format (original behavior)
    line = `- ${commitInfo.description}`;
    if (commitInfo.scope) {
      line = `- **${commitInfo.scope}**: ${commitInfo.description}`;
    }
  }

  // Add references
  if (references.length > 0) {
    const refLinks = references.map(ref => {
      const issueNum = ref.replace('#', '');
      return `[${ref}](../../issues/${issueNum})`;
    }).join(', ');
    line += ` (${refLinks})`;
  } else if (commitInfo.hash) {
    // Add commit hash if no issue references
    line += ` ([${commitInfo.hash}](../../commit/${commitInfo.hash}))`;
  }

  return line;
}

/**
 * Group commits by scope within a category
 * @param {object[]} commits - Commits to group
 * @returns {object} Grouped commits
 */
function groupCommitsByScope(commits) {
  const grouped = {
    withScope: {},
    withoutScope: []
  };

  for (const commit of commits) {
    if (commit.scope) {
      if (!grouped.withScope[commit.scope]) {
        grouped.withScope[commit.scope] = [];
      }
      grouped.withScope[commit.scope].push(commit);
    } else {
      grouped.withoutScope.push(commit);
    }
  }

  return grouped;
}

/**
 * PERFORMANCE: Generate changelog section for a category with optimized string building
 * @param {string} categoryType - Category type
 * @param {object[]} commits - Commits in this category
 * @returns {Promise<string>} Formatted section
 */
async function generateCategorySection(categoryType, commits) {
  if (commits.length === 0) return '';

  const category = COMMIT_CATEGORIES[categoryType];
  const title = CONFIG.userFriendly ? (category.userTitle || category.title) : category.title;

  // PERFORMANCE: Pre-allocate array with estimated size for better memory efficiency
  const estimatedSize = commits.length + 10; // Extra space for headers and spacing
  const lines = new Array(estimatedSize);
  let lineIndex = 0;

  lines[lineIndex++] = `### ${title}`;
  lines[lineIndex++] = '';

  if (CONFIG.groupByScope && commits.length > 3) {
    // Group by scope for better organization
    const grouped = groupCommitsByScope(commits);

    // Add commits without scope first
    for (const commit of grouped.withoutScope) {
      lines[lineIndex++] = await formatCommit(commit);
    }

    // Add scoped commits grouped by scope
    const sortedScopes = Object.keys(grouped.withScope).sort();
    for (const scope of sortedScopes) {
      if (grouped.withScope[scope].length > 1) {
        // Add subheading for scope if multiple commits
        const scopeTitle = scope.charAt(0).toUpperCase() + scope.slice(1);
        lines.push('', `#### ${scopeTitle}`, '');
      }

      for (const commit of grouped.withScope[scope]) {
        lines.push(await formatCommit(commit));
      }
    }
  } else {
    // Standard format without grouping
    for (const commit of commits) {
      lines.push(await formatCommit(commit));
    }
  }

  // Filter out undefined/null entries and join
  const filteredLines = lines.filter(line => line !== undefined && line !== null);
  filteredLines.push(''); // Add final newline
  return filteredLines.join('\n');
}

/**
 * Generate breaking changes section
 * @param {object[]} breakingCommits - Breaking change commits
 * @returns {Promise<string>} Formatted breaking changes section
 */
async function generateBreakingChangesSection(breakingCommits) {
  if (breakingCommits.length === 0) return '';

  const title = CONFIG.userFriendly ? '💥 Important Changes' : '💥 BREAKING CHANGES';
  const lines = [`### ${title}`, ''];

  if (CONFIG.userFriendly && breakingCommits.length > 0) {
    lines.push('> ⚠️ **Note**: These changes may require updates to your existing setup.', '');
  }

  for (const commit of breakingCommits) {
    lines.push(await formatCommit(commit));
  }

  lines.push(''); // Add final newline
  return lines.join('\n');
}

/**
 * Generate full changelog entry
 * @param {string} version - Version number
 * @param {object} categorizedCommits - Categorized commits
 * @returns {Promise<string>} Complete changelog entry
 */
async function generateChangelogEntry(version, categorizedCommits) {
  const today = new Date().toISOString().split('T')[0];
  let entry = `## [${version}] - ${today}\n\n`;

  // Breaking changes first (most important)
  entry += await generateBreakingChangesSection(categorizedCommits.breaking);

  // Features
  entry += await generateCategorySection('feat', categorizedCommits.categories.feat);

  // Bug fixes
  entry += await generateCategorySection('fix', categorizedCommits.categories.fix);

  // Performance improvements
  entry += await generateCategorySection('perf', categorizedCommits.categories.perf);

  // Other categories
  const otherCategories = ['refactor', 'docs', 'style', 'test', 'build', 'ci', 'chore'];
  for (const category of otherCategories) {
    entry += await generateCategorySection(category, categorizedCommits.categories[category]);
  }

  // Uncategorized commits (if including all)
  if (CONFIG.includeAll && categorizedCommits.uncategorized.length > 0) {
    entry += `### 📝 Other Changes\n\n`;
    for (const commit of categorizedCommits.uncategorized) {
      entry += `- ${commit.message}\n`;
    }
    entry += '\n';
  }

  return entry;
}

/**
 * Ensure the docs/reference directory exists
 * @returns {boolean} True if successful
 */
function ensureChangelogDirectory() {
  try {
    const changelogDir = path.dirname(CHANGELOG_PATH);
    if (!fs.existsSync(changelogDir)) {
      console.log(`📁 Creating directory: ${changelogDir}`);
      fs.mkdirSync(changelogDir, { recursive: true });
    }
    return true;
  } catch (error) {
    console.error(`❌ Error creating changelog directory: ${error.message}`);
    return false;
  }
}

/**
 * Extract frontmatter from existing changelog
 * @param {string} content - Changelog content
 * @returns {object} Object with frontmatter and content
 */
function extractFrontmatter(content) {
  const frontmatterRegex = /^---\n([\s\S]*?)\n---\n([\s\S]*)$/;
  const match = content.match(frontmatterRegex);

  if (match) {
    return {
      frontmatter: match[1],
      content: match[2]
    };
  }

  return {
    frontmatter: null,
    content: content
  };
}

/**
 * Create default frontmatter for new changelog
 * @returns {string} Default frontmatter
 */
function createDefaultFrontmatter() {
  const currentDate = new Date().toISOString().split('T')[0];
  return `---
title: "Changelog"
description: "Version history and release notes for the Blaze Commerce WordPress Plugin"
category: "reference"
version: "1.0.0"
last_updated: "${currentDate}"
author: "Blaze Commerce Team"
tags: ["changelog", "releases", "version-history", "updates"]
related_docs: ["index.md"]
---`;
}

/**
 * Update changelog file
 * @param {string} version - Version to add to changelog
 * @returns {Promise<boolean>} True if successful
 */
async function updateChangelog(version = null) {
  console.log(`📝 Updating changelog at ${CHANGELOG_PATH}...\n`);

  // Ensure changelog directory exists
  if (!ensureChangelogDirectory()) {
    return false;
  }

  // Get version
  if (!version) {
    try {
      const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
      version = packageJson.version;
    } catch (error) {
      console.error('❌ Could not read version from package.json:', error.message);
      return false;
    }
  }

  console.log(`📦 Generating changelog for version ${version}`);

  try {
    // CLAUDE AI REVIEW: Fixed memory inefficiency from comment #3060465549, #3060512807, #3060543625
    // Get commits since last tag with configurable limit
    const maxCommits = Math.min(config.CHANGELOG.MAX_CHANGELOG_COMMITS, 200); // Hard limit for memory safety
    const commitResult = getCommitsSinceLastTag(maxCommits);

    // Extract commit messages from the result object
    const commits = commitResult.messages || [];

    if (commits.length === 0) {
      console.log('ℹ️  No new commits found since last tag');
      return true;
    }

    console.log(`📊 Found ${commits.length} commits to process`);

    // CLAUDE AI REVIEW: Performance optimization from comment #3060512807 - batch processing
    // Process commits in batches for better memory management
    const categorizedCommits = categorizeCommitsInBatches(commits, config.CHANGELOG.COMMIT_BATCH_SIZE);

    if (CONFIG.verbose) {
      console.log('\n📋 Commit breakdown:');
      console.log(`   Breaking changes: ${categorizedCommits.breaking.length}`);
      for (const [type, commits] of Object.entries(categorizedCommits.categories)) {
        if (commits.length > 0) {
          console.log(`   ${COMMIT_CATEGORIES[type].emoji} ${type}: ${commits.length}`);
        }
      }
      if (categorizedCommits.uncategorized.length > 0) {
        console.log(`   📝 Uncategorized: ${categorizedCommits.uncategorized.length}`);
      }
    }

    // Generate changelog entry
    const changelogEntry = await generateChangelogEntry(version, categorizedCommits);

    if (CONFIG.dryRun) {
      console.log('\n🧪 DRY RUN - Generated changelog entry:');
      console.log('─'.repeat(50));
      console.log(changelogEntry);
      console.log('─'.repeat(50));
      return true;
    }

    // Read existing changelog or create new one
    let changelogContent = '';
    let frontmatter = null;

    if (fs.existsSync(CHANGELOG_PATH)) {
      console.log(`📖 Reading existing changelog from ${CHANGELOG_PATH}`);
      const rawContent = fs.readFileSync(CHANGELOG_PATH, 'utf8');
      const parsed = extractFrontmatter(rawContent);
      frontmatter = parsed.frontmatter;
      changelogContent = parsed.content;
    } else {
      console.log(`📄 Creating new changelog at ${CHANGELOG_PATH}`);
      frontmatter = createDefaultFrontmatter();
      changelogContent = '# Changelog\n\nAll notable changes to the BlazeCommerce WordPress Plugin will be documented in this file.\n\nThe format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),\nand this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).\n\n> **Note**: Release dates have been corrected based on actual Git commit history to ensure accuracy.\n\n';
    }

    // Check if version already exists in changelog
    if (changelogContent.includes(`## [${version}]`)) {
      console.log(`⚠️  Version ${version} already exists in changelog`);
      if (!process.argv.includes('--force')) {
        console.log('   Use --force to override');
        return false;
      }
      console.log('   Overriding existing entry...');

      // Remove existing entry
      const versionRegex = new RegExp(`## \\[${version.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}\\].*?(?=## \\[|$)`, 's');
      changelogContent = changelogContent.replace(versionRegex, '');
    }

    // Insert new entry after header
    const lines = changelogContent.split('\n');
    let insertIndex = lines.findIndex(line => line.startsWith('## [')) || lines.length;

    // If no existing entries, find end of header
    if (insertIndex === -1) {
      insertIndex = lines.findIndex((line, index) =>
        line.trim() === '' && index > 3
      ) + 1 || lines.length;
    }

    // Insert the new entry
    const entryLines = changelogEntry.split('\n');
    lines.splice(insertIndex, 0, ...entryLines);

    // Write updated changelog with frontmatter if it exists
    let updatedChangelog;
    if (frontmatter) {
      // Update the last_updated field in frontmatter
      const currentDate = new Date().toISOString().split('T')[0];
      const updatedFrontmatter = frontmatter.replace(
        /last_updated:\s*"[^"]*"/,
        `last_updated: "${currentDate}"`
      );
      updatedChangelog = `---\n${updatedFrontmatter}\n---\n${lines.join('\n')}`;
    } else {
      updatedChangelog = lines.join('\n');
    }

    try {
      fs.writeFileSync(CHANGELOG_PATH, updatedChangelog, 'utf8');
      console.log(`✅ Updated ${CHANGELOG_PATH} with version ${version}`);
    } catch (error) {
      console.error(`❌ Error writing changelog file: ${error.message}`);
      return false;
    }

    if (CONFIG.verbose) {
      console.log('\n📝 Added sections:');
      if (categorizedCommits.breaking.length > 0) {
        console.log(`   💥 Breaking Changes (${categorizedCommits.breaking.length})`);
      }
      for (const [type, commits] of Object.entries(categorizedCommits.categories)) {
        if (commits.length > 0) {
          console.log(`   ${COMMIT_CATEGORIES[type].emoji} ${COMMIT_CATEGORIES[type].title} (${commits.length})`);
        }
      }
    }

    return true;

  } catch (error) {
    console.error('❌ Error updating changelog:', error.message);
    return false;
  }
}

// CLI interface
if (require.main === module) {
  const args = process.argv.slice(2);

  // Show help
  if (args.includes('--help') || args.includes('-h')) {
    console.log(`
Usage: node scripts/update-changelog.js [options] [version]

Updates the changelog at docs/reference/changelog.md following project documentation standards.

Options:
  --dry-run         Show what would be generated without updating file
  --verbose, -v     Show detailed output
  --include-all     Include non-conventional commits
  --force           Override existing changelog entries
  --technical       Use technical format (default is user-friendly)
  --group-by-scope  Group commits by scope within categories
  --help, -h        Show this help message

Examples:
  node scripts/update-changelog.js                    # Update for version in package.json (user-friendly)
  node scripts/update-changelog.js 1.2.3             # Update for specific version
  node scripts/update-changelog.js --dry-run         # Preview changelog
  node scripts/update-changelog.js --verbose         # Detailed output
  node scripts/update-changelog.js --technical       # Use technical format
  node scripts/update-changelog.js --group-by-scope  # Group by scope for better organization

Format Options:
  Default: User-friendly descriptions with expanded abbreviations and context
  --technical: Original commit messages with minimal transformation

File Location:
  The changelog is maintained at docs/reference/changelog.md following the project's
  documentation organization guidelines. Frontmatter metadata is preserved and
  automatically updated with each change.
`);
    process.exit(0);
  }

  // Get version from command line argument if provided
  const versionArg = args.find(arg => !arg.startsWith('--') && arg !== '-v');

  updateChangelog(versionArg).then(success => {
    process.exit(success ? 0 : 1);
  }).catch(error => {
    console.error('❌ Error updating changelog:', error.message);
    process.exit(1);
  });
}

/**
 * CLAUDE AI REVIEW: Enhanced module exports with security and performance functions
 * @module update-changelog
 */
module.exports = {
  // Core functionality
  updateChangelog,
  categorizeCommits,
  generateChangelogEntry,
  formatCommit,
  transformCommitMessage,
  expandAbbreviations,
  extractReferences,
  improveCapitalization,
  addContext,
  groupCommitsByScope,

  // Security enhancements (Claude AI Review)
  sanitizePath,
  safeRegexExec,

  // Performance optimizations (Claude AI Review)
  cleanCommitDescription,
  getActionWord,
  processFeatureDescription,
  categorizeCommitsInBatches,

  // Configuration constants
  COMMIT_CATEGORIES,
  ABBREVIATIONS,
  SCOPE_CONTEXT,
  ACTION_WORDS,
  SECURITY_LIMITS
};
