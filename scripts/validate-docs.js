#!/usr/bin/env node

/**
 * Documentation Validation Script
 * 
 * This script validates that documentation follows the established standards:
 * - Proper directory structure
 * - File naming conventions
 * - Required frontmatter metadata
 * - Internal link integrity
 * 
 * Usage: node scripts/validate-docs.js
 */

const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');

// Configuration
const DOCS_DIR = path.join(__dirname, '..', 'docs');
const VALID_CATEGORIES = ['features', 'api', 'development', 'setup', 'reference', 'troubleshooting'];
const REQUIRED_FRONTMATTER = ['title', 'description', 'category', 'version', 'last_updated'];

// Validation results
let errors = [];
let warnings = [];

/**
 * Main validation function
 */
function validateDocumentation() {
    console.log('🔍 Validating documentation structure and standards...\n');
    
    // Check if docs directory exists
    if (!fs.existsSync(DOCS_DIR)) {
        errors.push('Documentation directory does not exist: ' + DOCS_DIR);
        return;
    }
    
    // Validate directory structure
    validateDirectoryStructure();
    
    // Validate all markdown files
    validateMarkdownFiles();
    
    // Report results
    reportResults();
}

/**
 * Validate that required directories exist
 */
function validateDirectoryStructure() {
    console.log('📁 Validating directory structure...');
    
    VALID_CATEGORIES.forEach(category => {
        const categoryDir = path.join(DOCS_DIR, category);
        if (!fs.existsSync(categoryDir)) {
            warnings.push(`Missing category directory: docs/${category}/`);
        }
    });
    
    // Check for DOCUMENTATION_STANDARDS.md
    const standardsFile = path.join(DOCS_DIR, 'DOCUMENTATION_STANDARDS.md');
    if (!fs.existsSync(standardsFile)) {
        errors.push('Missing DOCUMENTATION_STANDARDS.md in docs/ directory');
    }
}

/**
 * Validate all markdown files in the docs directory
 */
function validateMarkdownFiles() {
    console.log('📝 Validating markdown files...');
    
    walkDirectory(DOCS_DIR, (filePath) => {
        if (path.extname(filePath) === '.md') {
            validateMarkdownFile(filePath);
        }
    });
}

/**
 * Validate a single markdown file
 */
function validateMarkdownFile(filePath) {
    const relativePath = path.relative(process.cwd(), filePath);
    const fileName = path.basename(filePath);
    const dirName = path.basename(path.dirname(filePath));
    
    // Skip root-level files (README.md, CONTRIBUTING.md, etc.)
    if (path.dirname(filePath) === DOCS_DIR) {
        return;
    }
    
    console.log(`  Checking: ${relativePath}`);
    
    // Validate file naming convention
    validateFileName(fileName, relativePath);
    
    // Validate directory placement
    validateDirectoryPlacement(dirName, relativePath);
    
    // Read and validate file content
    try {
        const content = fs.readFileSync(filePath, 'utf8');
        validateFileContent(content, relativePath);
    } catch (error) {
        errors.push(`Cannot read file: ${relativePath} - ${error.message}`);
    }
}

/**
 * Validate file naming conventions
 */
function validateFileName(fileName, relativePath) {
    // Check for lowercase and hyphens
    if (!/^[a-z0-9-]+\.md$/.test(fileName)) {
        errors.push(`Invalid file name format: ${relativePath} (use lowercase letters, numbers, and hyphens only)`);
    }
    
    // Check for descriptive names (at least 3 characters before .md)
    if (fileName.length < 6) { // 3 chars + .md = 6
        warnings.push(`File name may be too short: ${relativePath} (consider more descriptive names)`);
    }
}

/**
 * Validate directory placement
 */
function validateDirectoryPlacement(dirName, relativePath) {
    if (!VALID_CATEGORIES.includes(dirName)) {
        errors.push(`File in invalid directory: ${relativePath} (must be in one of: ${VALID_CATEGORIES.join(', ')})`);
    }
}

/**
 * Validate file content including frontmatter and links
 */
function validateFileContent(content, relativePath) {
    // Extract frontmatter
    const frontmatterMatch = content.match(/^---\n([\s\S]*?)\n---/);
    
    if (!frontmatterMatch) {
        errors.push(`Missing frontmatter: ${relativePath}`);
        return;
    }
    
    try {
        const frontmatter = yaml.load(frontmatterMatch[1]);
        validateFrontmatter(frontmatter, relativePath);
    } catch (error) {
        errors.push(`Invalid frontmatter YAML: ${relativePath} - ${error.message}`);
    }
    
    // Validate internal links
    validateInternalLinks(content, relativePath);
}

/**
 * Validate frontmatter metadata
 */
function validateFrontmatter(frontmatter, relativePath) {
    // Check required fields
    REQUIRED_FRONTMATTER.forEach(field => {
        if (!frontmatter[field]) {
            errors.push(`Missing required frontmatter field '${field}': ${relativePath}`);
        }
    });
    
    // Validate category
    if (frontmatter.category && !VALID_CATEGORIES.includes(frontmatter.category)) {
        errors.push(`Invalid category '${frontmatter.category}': ${relativePath} (must be one of: ${VALID_CATEGORIES.join(', ')})`);
    }
    
    // Validate version format (semantic versioning)
    if (frontmatter.version && !/^\d+\.\d+\.\d+$/.test(frontmatter.version)) {
        warnings.push(`Version should follow semantic versioning (x.y.z): ${relativePath}`);
    }
    
    // Validate date format
    if (frontmatter.last_updated && !/^\d{4}-\d{2}-\d{2}$/.test(frontmatter.last_updated)) {
        errors.push(`Invalid date format for last_updated (use YYYY-MM-DD): ${relativePath}`);
    }
}

/**
 * Validate internal links
 */
function validateInternalLinks(content, relativePath) {
    // Find markdown links
    const linkRegex = /\[([^\]]+)\]\(([^)]+)\)/g;
    let match;
    
    while ((match = linkRegex.exec(content)) !== null) {
        const linkText = match[1];
        const linkUrl = match[2];
        
        // Skip external links
        if (linkUrl.startsWith('http://') || linkUrl.startsWith('https://')) {
            continue;
        }
        
        // Skip anchors
        if (linkUrl.startsWith('#')) {
            continue;
        }
        
        // Validate internal links
        validateInternalLink(linkUrl, relativePath);
    }
}

/**
 * Validate a single internal link
 */
function validateInternalLink(linkUrl, relativePath) {
    const currentDir = path.dirname(relativePath);
    const targetPath = path.resolve(currentDir, linkUrl);
    
    if (!fs.existsSync(targetPath)) {
        errors.push(`Broken internal link: ${relativePath} -> ${linkUrl}`);
    }
}

/**
 * Recursively walk directory
 */
function walkDirectory(dir, callback) {
    const files = fs.readdirSync(dir);
    
    files.forEach(file => {
        const filePath = path.join(dir, file);
        const stat = fs.statSync(filePath);
        
        if (stat.isDirectory()) {
            walkDirectory(filePath, callback);
        } else {
            callback(filePath);
        }
    });
}

/**
 * Report validation results
 */
function reportResults() {
    console.log('\n📊 Validation Results:');
    console.log('='.repeat(50));
    
    if (errors.length === 0 && warnings.length === 0) {
        console.log('✅ All documentation validation checks passed!');
        process.exit(0);
    }
    
    if (errors.length > 0) {
        console.log(`\n❌ Errors (${errors.length}):`);
        errors.forEach(error => console.log(`  • ${error}`));
    }
    
    if (warnings.length > 0) {
        console.log(`\n⚠️  Warnings (${warnings.length}):`);
        warnings.forEach(warning => console.log(`  • ${warning}`));
    }
    
    console.log('\n📖 See docs/DOCUMENTATION_STANDARDS.md for guidelines.');
    
    // Exit with error code if there are errors
    process.exit(errors.length > 0 ? 1 : 0);
}

// Run validation if this script is executed directly
if (require.main === module) {
    validateDocumentation();
}

module.exports = {
    validateDocumentation,
    VALID_CATEGORIES,
    REQUIRED_FRONTMATTER
};
