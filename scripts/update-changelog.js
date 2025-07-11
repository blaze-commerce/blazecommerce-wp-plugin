#!/usr/bin/env node

/**
 * Enhanced Changelog Generation Script
 * Generates categorized changelog based on conventional commits
 */

const fs = require('fs');
const { execSync } = require('child_process');
const { parseConventionalCommit, getCommitsSinceLastTag, getLatestTag } = require('./semver-utils');
const config = require('./config');

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
 * Extract issue/PR references from commit message
 * @param {string} message - Commit message
 * @returns {string[]} Array of references
 */
function extractReferences(message) {
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
    while ((match = pattern.exec(safeMessage)) !== null && matchCount < config.CHANGELOG.MAX_REFERENCES_PER_COMMIT) {
      const issueNumber = parseInt(match[1], 10);
      if (issueNumber > 0 && issueNumber < 1000000) { // Reasonable issue number range
        references.add(`#${issueNumber}`);
        matchCount++;
      }
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
 * Transform commit message into user-friendly description
 * @param {object} commitInfo - Parsed commit information
 * @returns {string} User-friendly description
 */
function transformCommitMessage(commitInfo) {
  if (!CONFIG.userFriendly) {
    // Return original format for technical mode
    return commitInfo.description;
  }

  let { type, scope, description } = commitInfo;

  // Get appropriate action word
  const actionWords = ACTION_WORDS[type] || ['Updated'];
  let actionWord = actionWords[0];

  // Clean up description - remove redundant action words
  description = description.replace(/^(add|fix|update|improve|enhance|implement|create|resolve|correct|address)\s+/i, '');

  // Handle specific patterns for better readability
  if (type === 'fix') {
    // For fixes, use more natural language
    if (description.toLowerCase().includes('error') ||
        description.toLowerCase().includes('issue') ||
        description.toLowerCase().includes('problem') ||
        description.toLowerCase().includes('bug')) {
      actionWord = 'Resolved';
    } else {
      actionWord = 'Fixed';
      description = `an issue where ${description}`;
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
    }
  } else if (type === 'perf') {
    actionWord = 'Improved';
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
 * @returns {string} Formatted commit line
 */
function formatCommit(commitInfo) {
  // Transform the commit message
  const transformedDescription = transformCommitMessage(commitInfo);

  // Extract references from original message
  const references = extractReferences(commitInfo.message);

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
 * Generate changelog section for a category
 * @param {string} categoryType - Category type
 * @param {object[]} commits - Commits in this category
 * @returns {string} Formatted section
 */
function generateCategorySection(categoryType, commits) {
  if (commits.length === 0) return '';

  const category = COMMIT_CATEGORIES[categoryType];
  const title = CONFIG.userFriendly ? (category.userTitle || category.title) : category.title;
  let section = `### ${title}\n\n`;

  if (CONFIG.groupByScope && commits.length > 3) {
    // Group by scope for better organization
    const grouped = groupCommitsByScope(commits);

    // Add commits without scope first
    for (const commit of grouped.withoutScope) {
      section += formatCommit(commit) + '\n';
    }

    // Add scoped commits grouped by scope
    const sortedScopes = Object.keys(grouped.withScope).sort();
    for (const scope of sortedScopes) {
      if (grouped.withScope[scope].length > 1) {
        // Add subheading for scope if multiple commits
        const scopeTitle = scope.charAt(0).toUpperCase() + scope.slice(1);
        section += `\n#### ${scopeTitle}\n\n`;
      }

      for (const commit of grouped.withScope[scope]) {
        section += formatCommit(commit) + '\n';
      }
    }
  } else {
    // Standard format without grouping
    for (const commit of commits) {
      section += formatCommit(commit) + '\n';
    }
  }

  return section + '\n';
}

/**
 * Generate breaking changes section
 * @param {object[]} breakingCommits - Breaking change commits
 * @returns {string} Formatted breaking changes section
 */
function generateBreakingChangesSection(breakingCommits) {
  if (breakingCommits.length === 0) return '';

  const title = CONFIG.userFriendly ? '💥 Important Changes' : '💥 BREAKING CHANGES';
  let section = `### ${title}\n\n`;

  if (CONFIG.userFriendly && breakingCommits.length > 0) {
    section += '> ⚠️ **Note**: These changes may require updates to your existing setup.\n\n';
  }

  for (const commit of breakingCommits) {
    section += formatCommit(commit) + '\n';
  }

  return section + '\n';
}

/**
 * Generate full changelog entry
 * @param {string} version - Version number
 * @param {object} categorizedCommits - Categorized commits
 * @returns {string} Complete changelog entry
 */
function generateChangelogEntry(version, categorizedCommits) {
  const today = new Date().toISOString().split('T')[0];
  let entry = `## [${version}] - ${today}\n\n`;

  // Breaking changes first (most important)
  entry += generateBreakingChangesSection(categorizedCommits.breaking);

  // Features
  entry += generateCategorySection('feat', categorizedCommits.categories.feat);

  // Bug fixes
  entry += generateCategorySection('fix', categorizedCommits.categories.fix);

  // Performance improvements
  entry += generateCategorySection('perf', categorizedCommits.categories.perf);

  // Other categories
  const otherCategories = ['refactor', 'docs', 'style', 'test', 'build', 'ci', 'chore'];
  for (const category of otherCategories) {
    entry += generateCategorySection(category, categorizedCommits.categories[category]);
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
 * Update changelog file
 * @param {string} version - Version to add to changelog
 * @returns {boolean} True if successful
 */
function updateChangelog(version = null) {
  console.log('📝 Updating CHANGELOG.md...\n');

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
    // Get commits since last tag with configurable limit
    const commits = getCommitsSinceLastTag(config.CHANGELOG.MAX_CHANGELOG_COMMITS);

    if (commits.length === 0) {
      console.log('ℹ️  No new commits found since last tag');
      return true;
    }

    console.log(`📊 Found ${commits.length} commits to process`);

    // Categorize commits
    const categorizedCommits = categorizeCommits(commits);

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
    const changelogEntry = generateChangelogEntry(version, categorizedCommits);

    if (CONFIG.dryRun) {
      console.log('\n🧪 DRY RUN - Generated changelog entry:');
      console.log('─'.repeat(50));
      console.log(changelogEntry);
      console.log('─'.repeat(50));
      return true;
    }

    // Read existing changelog or create new one
    let changelogContent = '';
    if (fs.existsSync('CHANGELOG.md')) {
      changelogContent = fs.readFileSync('CHANGELOG.md', 'utf8');
    } else {
      changelogContent = '# Changelog\n\nAll notable changes to this project will be documented in this file.\n\nThe format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),\nand this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).\n\n';
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

    // Write updated changelog
    const updatedChangelog = lines.join('\n');
    fs.writeFileSync('CHANGELOG.md', updatedChangelog);

    console.log(`✅ Updated CHANGELOG.md with version ${version}`);

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
`);
    process.exit(0);
  }

  // Get version from command line argument if provided
  const versionArg = args.find(arg => !arg.startsWith('--') && arg !== '-v');

  const success = updateChangelog(versionArg);
  process.exit(success ? 0 : 1);
}

module.exports = {
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
  COMMIT_CATEGORIES,
  ABBREVIATIONS,
  SCOPE_CONTEXT,
  ACTION_WORDS
};
