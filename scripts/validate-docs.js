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

// File size threshold (1MB)
const MAX_FILE_SIZE = 1024 * 1024;

// Configuration
const DOCS_DIR = path.join(__dirname, '..', 'docs');
const VALID_CATEGORIES = ['features', 'api', 'development', 'setup', 'reference', 'troubleshooting'];
const REQUIRED_FRONTMATTER = ['title', 'description', 'category', 'version', 'last_updated'];

// Validation results
let errors = [];
let warnings = [];

// Performance tracking
let performanceMetrics = {
    startTime: 0,
    endTime: 0,
    filesProcessed: 0,
    totalFileSize: 0
};

/**
 * Main validation function
 */
function validateDocumentation() {
    performanceMetrics.startTime = Date.now();
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
    
    performanceMetrics.endTime = Date.now();
    
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
    
    // Update performance metrics
    performanceMetrics.filesProcessed++;
    
    // Validate file size
    try {
        const stats = fs.statSync(filePath);
        performanceMetrics.totalFileSize += stats.size;
        
        if (stats.size > MAX_FILE_SIZE) {
            warnings.push(`File size too large: ${relativePath} (${Math.round(stats.size/1024)}KB > ${Math.round(MAX_FILE_SIZE/1024)}KB)`);
        }
    } catch (error) {
        errors.push(`Cannot stat file: ${relativePath} - ${error.message}`);
        return;
    }
    
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
    
    // Validate date format and validity
    if (frontmatter.last_updated) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(frontmatter.last_updated)) {
            errors.push(`Invalid date format for last_updated (use YYYY-MM-DD): ${relativePath}`);
        } else {
            // Check if date is actually valid
            const date = new Date(frontmatter.last_updated);
            if (isNaN(date.getTime()) || date.toISOString().split('T')[0] !== frontmatter.last_updated) {
                errors.push(`Invalid date value for last_updated: ${relativePath}`);
            }
        }
    }
    
    // Validate related documents exist
    if (frontmatter.related_docs && Array.isArray(frontmatter.related_docs)) {
        frontmatter.related_docs.forEach(relatedDoc => {
            if (typeof relatedDoc === 'string') {
                validateRelatedDocument(relatedDoc, relativePath);
            }
        });
    }
}

/**
 * Validate related document exists
 */
function validateRelatedDocument(relatedDoc, relativePath) {
    // Check if it's a relative path
    if (relatedDoc.startsWith('./') || relatedDoc.startsWith('../')) {
        const currentDir = path.dirname(relativePath);
        const targetPath = path.resolve(currentDir, relatedDoc);
        
        if (!fs.existsSync(targetPath)) {
            errors.push(`Related document not found: ${relativePath} -> ${relatedDoc}`);
        }
    } else {
        // Check if it's a docs-relative path
        const docsPath = path.join(DOCS_DIR, relatedDoc);
        if (!fs.existsSync(docsPath)) {
            errors.push(`Related document not found: ${relativePath} -> ${relatedDoc}`);
        }
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
    try {
        // Basic URL validation - check for malformed URLs
        if (!linkUrl || linkUrl.trim() === '') {
            errors.push(`Empty link URL: ${relativePath}`);
            return;
        }
        
        // Prevent directory traversal attacks
        if (linkUrl.includes('..') && linkUrl.includes('..')) {
            const normalizedPath = path.normalize(linkUrl);
            if (normalizedPath.startsWith('..')) {
                warnings.push(`Potentially unsafe link path: ${relativePath} -> ${linkUrl}`);
            }
        }
        
        const currentDir = path.dirname(relativePath);
        const targetPath = path.resolve(currentDir, linkUrl);
        
        // Security check: ensure resolved path is within the project directory
        const projectRoot = path.resolve(__dirname, '..');
        if (!targetPath.startsWith(projectRoot)) {
            errors.push(`Link points outside project directory: ${relativePath} -> ${linkUrl}`);
            return;
        }
        
        if (!fs.existsSync(targetPath)) {
            errors.push(`Broken internal link: ${relativePath} -> ${linkUrl}`);
        }
    } catch (error) {
        errors.push(`Error validating link: ${relativePath} -> ${linkUrl} - ${error.message}`);
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
    
    // Performance metrics
    const duration = performanceMetrics.endTime - performanceMetrics.startTime;
    const avgFileSize = performanceMetrics.filesProcessed > 0 ? 
        Math.round(performanceMetrics.totalFileSize / performanceMetrics.filesProcessed) : 0;
    
    console.log(`\n⚡ Performance Metrics:`);
    console.log(`  • Files processed: ${performanceMetrics.filesProcessed}`);
    console.log(`  • Total file size: ${Math.round(performanceMetrics.totalFileSize / 1024)}KB`);
    console.log(`  • Average file size: ${Math.round(avgFileSize / 1024)}KB`);
    console.log(`  • Validation time: ${duration}ms`);
    
    if (errors.length === 0 && warnings.length === 0) {
        console.log('\n✅ All documentation validation checks passed!');
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
    validateMarkdownFile,
    validateFrontmatter,
    validateRelatedDocument,
    validateInternalLink,
    VALID_CATEGORIES,
    REQUIRED_FRONTMATTER,
    MAX_FILE_SIZE
};
