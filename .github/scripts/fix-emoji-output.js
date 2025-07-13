#!/usr/bin/env node

/**
 * Emergency Emoji Fix Script
 * Automatically removes all emojis from GitHub Actions workflow files and JavaScript files
 * to prevent output formatting failures.
 * 
 * This script fixes the critical production issue blocking CI/CD pipeline.
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');

/**
 * Emoji replacement mappings for consistent output formatting
 */
const EMOJI_REPLACEMENTS = {
  // Information and status emojis
  'INFO:': 'INFO:',
  'SUCCESS:': 'SUCCESS:',
  'ERROR:': 'ERROR:',
  'WARNING:': 'WARNING:',
  'DEBUG:': 'DEBUG:',
  'BOT:': 'BOT:',
  'TARGET:': 'TARGET:',
  'RETRY:': 'PROCESSING:',
  'NOTE:': 'NOTE:',
  'COMPLETED:': 'COMPLETED:',
  
  // Process and workflow emojis
  'EXECUTING:': 'EXECUTING:',
  'BUILDING:': 'BUILDING:',
  'TESTING:': 'TESTING:',
  'PACKAGE:': 'PACKAGE:',
  'ANALYSIS:': 'ANALYSIS:',
  'SUMMARY:': 'SUMMARY:',
  'LINK:': 'LINK:',
  'FILES:': 'FILES:',
  'TAG:': 'TAG:',
  'REPORT:': 'REPORT:',
  
  // Status and result emojis
  'WARNING:': 'WARNING:',
  'CRITICAL:': 'CRITICAL:',
  'INFO:': 'INFO:',
  'SUCCESS:': 'SUCCESS:',
  'PENDING:': 'PENDING:',
  'SKIPPED:': 'SKIPPED:',
  'IN_PROGRESS:': 'IN_PROGRESS:',
  'SECURITY:': 'SECURITY:',
  'SECURE:': 'SECURE:',
  'TIP:': 'TIP:',
  
  // Special characters that can cause issues
  '-': '-',
  'UNKNOWN:': 'UNKNOWN:',
  'BRANCH:': 'BRANCH:',
  'METRICS:': 'METRICS:',
  'NEW:': 'NEW:',
  'RETRY:': 'RETRY:',
  'DOCS:': 'DOCS:',
  'STYLE:': 'STYLE:',
  'FEATURE:': 'FEATURE:',
  'BUG:': 'BUG:',
  'CONFIG:': 'CONFIG:',
  'MOBILE:': 'MOBILE:',
  'DESKTOP:': 'DESKTOP:',
  'WEB:': 'WEB:'
};

/**
 * Emergency Emoji Fixer Class
 */
class EmojiOutputFixer {
  constructor() {
    this.workflowsDir = '.github/workflows';
    this.scriptsDir = '.github/scripts';
    this.fixedFiles = [];
    this.errors = [];
  }

  /**
   * Replace emojis in text with safe alternatives
   * @param {string} text - Text to process
   * @returns {string} Text with emojis replaced
   */
  replaceEmojis(text) {
    let result = text;
    
    // Replace known emojis with safe alternatives
    for (const [emoji, replacement] of Object.entries(EMOJI_REPLACEMENTS)) {
      const regex = new RegExp(emoji.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
      result = result.replace(regex, replacement);
    }
    
    // Remove any remaining non-ASCII characters that could cause issues
    result = result.replace(/[^\x00-\x7F]/g, '');
    
    return result;
  }

  /**
   * Fix a single file
   * @param {string} filePath - Path to file to fix
   */
  fixFile(filePath) {
    try {
      console.log(`INFO: Fixing ${filePath}...`);
      
      const originalContent = fs.readFileSync(filePath, 'utf8');
      const fixedContent = this.replaceEmojis(originalContent);
      
      // Only write if content changed
      if (originalContent !== fixedContent) {
        fs.writeFileSync(filePath, fixedContent, 'utf8');
        this.fixedFiles.push(filePath);
        console.log(`SUCCESS: Fixed ${filePath}`);
      } else {
        console.log(`INFO: No changes needed for ${filePath}`);
      }
    } catch (error) {
      this.errors.push({ file: filePath, error: error.message });
      console.error(`ERROR: Failed to fix ${filePath}: ${error.message}`);
    }
  }

  /**
   * Get all files to fix
   * @returns {Array} Array of file paths
   */
  getAllFilesToFix() {
    const files = [];
    
    // Add workflow files
    if (fs.existsSync(this.workflowsDir)) {
      const workflowFiles = fs.readdirSync(this.workflowsDir)
        .filter(file => file.endsWith('.yml') || file.endsWith('.yaml'))
        .map(file => path.join(this.workflowsDir, file));
      files.push(...workflowFiles);
    }
    
    // Add JavaScript files recursively
    if (fs.existsSync(this.scriptsDir)) {
      const jsFiles = this.getAllJSFiles(this.scriptsDir);
      files.push(...jsFiles);
    }
    
    return files;
  }

  /**
   * Recursively get all JavaScript files
   * @param {string} dir - Directory to search
   * @returns {Array} Array of JavaScript file paths
   */
  getAllJSFiles(dir) {
    const jsFiles = [];
    
    try {
      const items = fs.readdirSync(dir);
      for (const item of items) {
        const itemPath = path.join(dir, item);
        const stat = fs.statSync(itemPath);
        
        if (stat.isDirectory()) {
          jsFiles.push(...this.getAllJSFiles(itemPath));
        } else if (item.endsWith('.js')) {
          jsFiles.push(itemPath);
        }
      }
    } catch (error) {
      console.error(`ERROR: Failed to read directory ${dir}: ${error.message}`);
    }
    
    return jsFiles;
  }

  /**
   * Fix all files
   */
  fixAllFiles() {
    console.log('INFO: Starting emergency emoji fix for GitHub Actions output formatting...');
    
    const filesToFix = this.getAllFilesToFix();
    console.log(`INFO: Found ${filesToFix.length} files to process`);
    
    for (const filePath of filesToFix) {
      this.fixFile(filePath);
    }
    
    this.generateReport();
  }

  /**
   * Generate fix report
   */
  generateReport() {
    console.log('\nINFO: Emergency Emoji Fix Report:');
    console.log(`SUCCESS: Fixed ${this.fixedFiles.length} files`);
    console.log(`ERROR: Failed to fix ${this.errors.length} files`);
    
    if (this.fixedFiles.length > 0) {
      console.log('\nSUCCESS: Fixed files:');
      this.fixedFiles.forEach(file => console.log(`  - ${file}`));
    }
    
    if (this.errors.length > 0) {
      console.log('\nERROR: Failed files:');
      this.errors.forEach(({ file, error }) => console.log(`  - ${file}: ${error}`));
    }
    
    // Output for GitHub Actions
    if (process.env.GITHUB_ACTIONS) {
      console.log(`files_fixed=${this.fixedFiles.length}`);
      console.log(`files_failed=${this.errors.length}`);
      console.log(`fix_success=${this.errors.length === 0}`);
    }
    
    console.log('\nINFO: Emergency emoji fix completed!');
    console.log('INFO: All emojis have been replaced with GitHub Actions-safe text alternatives.');
    console.log('INFO: This should resolve the "Unable to process file command \'output\' successfully" errors.');
  }
}

/**
 * Main execution
 */
if (require.main === module) {
  const fixer = new EmojiOutputFixer();
  fixer.fixAllFiles();
  
  // Exit with error code if any files failed to fix
  process.exit(fixer.errors.length > 0 ? 1 : 0);
}

module.exports = { EmojiOutputFixer };
