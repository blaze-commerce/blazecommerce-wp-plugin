#!/usr/bin/env node

/**
 * Emergency GitHub Actions Output Fix Script
 * 
 * This script can be run to quickly fix GitHub Actions output formatting issues
 * similar to the "Invalid format" error encountered in PR #337.
 * 
 * Usage:
 *   node .github/scripts/emergency-output-fix.js [--dry-run] [--file=path]
 * 
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');

class EmergencyOutputFixer {
  constructor(options = {}) {
    this.dryRun = options.dryRun || false;
    this.targetFile = options.file || null;
    this.fixes = [];
  }

  /**
   * Scan for problematic console.log patterns that could interfere with GitHub Actions output
   */
  scanForProblematicPatterns(filePath) {
    const content = fs.readFileSync(filePath, 'utf8');
    const lines = content.split('\n');
    const issues = [];

    lines.forEach((line, index) => {
      const lineNumber = index + 1;
      
      // Pattern 1: console.log in outputForGitHubActions methods
      if (line.includes('outputForGitHubActions') || 
          (line.includes('console.log') && line.includes('${'))) {
        issues.push({
          type: 'console-log-in-output',
          line: lineNumber,
          content: line.trim(),
          severity: 'high'
        });
      }
      
      // Pattern 2: Workflow redirection to $GITHUB_OUTPUT
      if (line.includes('>> $GITHUB_OUTPUT') && line.includes('node')) {
        issues.push({
          type: 'output-redirection',
          line: lineNumber,
          content: line.trim(),
          severity: 'high'
        });
      }
      
      // Pattern 3: Non-ASCII characters in output
      if ((line.includes('console.log') || line.includes('echo')) && 
          /[^\x00-\x7F]/.test(line)) {
        issues.push({
          type: 'non-ascii-output',
          line: lineNumber,
          content: line.trim(),
          severity: 'medium'
        });
      }
    });

    return issues;
  }

  /**
   * Generate a fixed version of outputForGitHubActions method
   */
  generateFixedOutputMethod() {
    return `  /**
   * Output results in GitHub Actions format (enhanced)
   * @param {Object} result - Processing result
   */
  outputForGitHubActions(result) {
    try {
      const fs = require('fs');
      
      // Prepare output data
      const outputData = [];
      
      // Handle multiline comment using GitHub Actions multiline format
      let enhancedComment = result.enhancedComment || '';
      
      // GitHub Actions multiline output format using EOF delimiter
      const delimiter = \`EOF_\${Date.now()}_\${Math.random().toString(36).substr(2, 9)}\`;
      outputData.push(\`enhanced_comment<<\${delimiter}\`);
      outputData.push(enhancedComment);
      outputData.push(delimiter);
      
      // Add other outputs as simple key=value pairs
      outputData.push(\`has_blocking_issues=\${result.hasBlockingIssues || false}\`);
      outputData.push(\`processing_success=\${result.success || false}\`);
      outputData.push(\`progress_made=\${result.progressMade || false}\`);
      outputData.push(\`review_version=\${result.reviewVersion || 1}\`);

      // Add counts with defaults
      if (result.recommendations) {
        outputData.push(\`required_count=\${result.recommendations.required.length}\`);
        outputData.push(\`important_count=\${result.recommendations.important.length}\`);
        outputData.push(\`suggestions_count=\${result.recommendations.suggestions.length}\`);
      } else {
        outputData.push(\`required_count=0\`);
        outputData.push(\`important_count=0\`);
        outputData.push(\`suggestions_count=0\`);
      }

      if (result.resolvedCount) {
        outputData.push(\`resolved_required=\${result.resolvedCount.required}\`);
        outputData.push(\`resolved_important=\${result.resolvedCount.important}\`);
        outputData.push(\`total_resolved=\${result.resolvedCount.required + result.resolvedCount.important}\`);
      } else {
        outputData.push(\`resolved_required=0\`);
        outputData.push(\`resolved_important=0\`);
        outputData.push(\`total_resolved=0\`);
      }
      
      // Write to GitHub Actions output file if available, otherwise use console
      const githubOutput = process.env.GITHUB_OUTPUT;
      if (githubOutput) {
        // Write directly to GitHub Actions output file
        fs.appendFileSync(githubOutput, outputData.join('\\n') + '\\n');
        
        // Log success to stderr (won't interfere with output)
        console.error('SUCCESS: GitHub Actions output written successfully');
      } else {
        // Fallback: output to console for local testing
        console.log('# GitHub Actions Output (local testing mode):');
        outputData.forEach(line => console.log(line));
      }
      
    } catch (error) {
      // Log error to stderr (won't interfere with GitHub Actions output)
      console.error(\`ERROR: Failed to write GitHub Actions output: \${error.message}\`);
      
      // Fallback: output basic information to console
      console.log(\`processing_success=false\`);
      console.log(\`has_blocking_issues=true\`);
      console.log(\`error_message=\${error.message.replace(/\\n/g, ' ')}\`);
    }
  }`;
  }

  /**
   * Apply emergency fixes to a file
   */
  applyFixes(filePath, issues) {
    if (this.dryRun) {
      console.log(`DRY RUN: Would fix ${issues.length} issues in ${filePath}`);
      return;
    }

    let content = fs.readFileSync(filePath, 'utf8');
    let modified = false;

    // Fix console.log in outputForGitHubActions methods
    const outputMethodRegex = /outputForGitHubActions\([\s\S]*?\n  \}/g;
    if (outputMethodRegex.test(content)) {
      content = content.replace(outputMethodRegex, this.generateFixedOutputMethod());
      modified = true;
      this.fixes.push(`Fixed outputForGitHubActions method in ${filePath}`);
    }

    // Fix workflow redirection
    if (filePath.endsWith('.yml') || filePath.endsWith('.yaml')) {
      content = content.replace(
        /node\s+([^\s]+)\s+>>\s+\$GITHUB_OUTPUT/g,
        'node $1'
      );
      if (content !== fs.readFileSync(filePath, 'utf8')) {
        modified = true;
        this.fixes.push(`Removed output redirection in ${filePath}`);
      }
    }

    if (modified) {
      fs.writeFileSync(filePath, content);
      console.log(`SUCCESS: Applied fixes to ${filePath}`);
    }
  }

  /**
   * Run the emergency fix process
   */
  run() {
    console.log('INFO: Starting emergency GitHub Actions output fix...');
    
    const filesToCheck = this.targetFile ? [this.targetFile] : [
      '.github/scripts/claude-review-enhancer.js',
      '.github/workflows/workflow-preflight-check.yml',
      '.github/workflows/claude-code-review.yml',
      '.github/workflows/claude-approval-gate.yml'
    ];

    let totalIssues = 0;

    for (const filePath of filesToCheck) {
      if (!fs.existsSync(filePath)) {
        console.log(`WARNING: File not found: ${filePath}`);
        continue;
      }

      console.log(`INFO: Scanning ${filePath}...`);
      const issues = this.scanForProblematicPatterns(filePath);
      
      if (issues.length > 0) {
        console.log(`WARNING: Found ${issues.length} issue(s) in ${filePath}:`);
        issues.forEach((issue, index) => {
          console.log(`  ${index + 1}. ${issue.type} (line ${issue.line}) - ${issue.severity}`);
          console.log(`     ${issue.content}`);
        });

        this.applyFixes(filePath, issues);
        totalIssues += issues.length;
      } else {
        console.log(`SUCCESS: No issues found in ${filePath}`);
      }
    }

    // Summary
    console.log('\nINFO: Emergency fix summary:');
    console.log(`- Files scanned: ${filesToCheck.length}`);
    console.log(`- Issues found: ${totalIssues}`);
    console.log(`- Fixes applied: ${this.fixes.length}`);
    
    if (this.fixes.length > 0) {
      console.log('\nSUCCESS: Applied fixes:');
      this.fixes.forEach((fix, index) => {
        console.log(`  ${index + 1}. ${fix}`);
      });
      
      console.log('\nINFO: Next steps:');
      console.log('1. Test the fixes: node .github/scripts/test-github-actions-output.js');
      console.log('2. Commit the changes');
      console.log('3. Test with a real PR');
    } else {
      console.log('\nINFO: No fixes were needed.');
    }

    return totalIssues === 0;
  }
}

// Command line interface
if (require.main === module) {
  const args = process.argv.slice(2);
  const options = {
    dryRun: args.includes('--dry-run'),
    file: args.find(arg => arg.startsWith('--file='))?.split('=')[1]
  };

  if (args.includes('--help')) {
    console.log(`
Emergency GitHub Actions Output Fix Script

Usage:
  node .github/scripts/emergency-output-fix.js [options]

Options:
  --dry-run     Show what would be fixed without making changes
  --file=path   Fix only the specified file
  --help        Show this help message

Examples:
  node .github/scripts/emergency-output-fix.js
  node .github/scripts/emergency-output-fix.js --dry-run
  node .github/scripts/emergency-output-fix.js --file=.github/scripts/claude-review-enhancer.js
`);
    process.exit(0);
  }

  const fixer = new EmergencyOutputFixer(options);
  const success = fixer.run();
  
  process.exit(success ? 0 : 1);
}

module.exports = { EmergencyOutputFixer };
