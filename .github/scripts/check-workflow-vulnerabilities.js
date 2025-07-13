#!/usr/bin/env node

/**
 * Workflow Vulnerability Scanner
 * Scans GitHub Actions workflows for potential JavaScript injection vulnerabilities
 * and unsafe template literal usage
 *
 * @author BlazeCommerce Workflow Optimization
 * @version 1.0.0
 */

const fs = require('fs');
const path = require('path');
const { EnhancedErrorHandler, ErrorSeverity, ErrorCategory } = require('./enhanced-error-handler');

class WorkflowVulnerabilityScanner {
  constructor() {
    this.errorHandler = new EnhancedErrorHandler({ enableSafetyChecks: true });
    this.workflowsDir = '.github/workflows';
    this.vulnerabilities = [];
    this.warnings = [];
  }

  /**
   * Scan all workflow files
   */
  async scanAllWorkflows() {
    console.log('🔍 Scanning GitHub Actions workflows for vulnerabilities...\n');

    if (!fs.existsSync(this.workflowsDir)) {
      console.error('ERROR: .github/workflows directory not found');
      return;
    }

    const workflowFiles = fs.readdirSync(this.workflowsDir)
      .filter(file => file.endsWith('.yml') || file.endsWith('.yaml'));

    for (const file of workflowFiles) {
      await this.scanWorkflowFile(file);
    }

    this.generateReport();
  }

  /**
   * Scan a single workflow file
   * @param {string} filename - Workflow filename
   */
  async scanWorkflowFile(filename) {
    const filePath = path.join(this.workflowsDir, filename);
    console.log(`Scanning: ${filename}`);

    try {
      const content = fs.readFileSync(filePath, 'utf8');
      const lines = content.split('\n');

      let inGitHubScript = false;
      let scriptStartLine = 0;
      let scriptContent = '';

      for (let i = 0; i < lines.length; i++) {
        const line = lines[i];
        const lineNumber = i + 1;

        // Detect start of github-script action
        if (line.includes('uses: actions/github-script@')) {
          inGitHubScript = true;
          scriptStartLine = lineNumber;
          scriptContent = '';
          continue;
        }

        // Collect script content
        if (inGitHubScript) {
          if (line.trim().startsWith('script: |')) {
            // Start collecting script content
            continue;
          } else if (line.trim() && !line.startsWith('  ') && !line.startsWith('\t')) {
            // End of script block
            if (scriptContent.trim()) {
              this.analyzeScriptContent(filename, scriptStartLine, scriptContent);
            }
            inGitHubScript = false;
            scriptContent = '';
          } else {
            // Add to script content
            scriptContent += line + '\n';
          }
        }

        // Check for direct template literal usage in script
        if (inGitHubScript && this.hasUnsafeTemplateUsage(line)) {
          this.addVulnerability(filename, lineNumber, 'Unsafe template literal usage', line.trim(), 'high');
        }

        // Check for other patterns
        this.checkLineForPatterns(filename, lineNumber, line);
      }

      // Handle case where script is at end of file
      if (inGitHubScript && scriptContent.trim()) {
        this.analyzeScriptContent(filename, scriptStartLine, scriptContent);
      }

    } catch (error) {
      console.error(`Error scanning ${filename}: ${error.message}`);
    }
  }

  /**
   * Analyze script content for vulnerabilities
   * @param {string} filename - Workflow filename
   * @param {number} startLine - Starting line number
   * @param {string} scriptContent - Script content
   */
  analyzeScriptContent(filename, startLine, scriptContent) {
    const validation = this.errorHandler.validateAndSanitizeContent(scriptContent);

    if (!validation.isValid) {
      validation.issues.forEach(issue => {
        this.addVulnerability(
          filename,
          startLine,
          issue.description,
          scriptContent.substring(0, 100) + '...',
          issue.risk
        );
      });
    }

    // Additional specific checks
    this.checkForSpecificPatterns(filename, startLine, scriptContent);
  }

  /**
   * Check for specific vulnerability patterns
   * @param {string} filename - Workflow filename
   * @param {number} startLine - Starting line number
   * @param {string} content - Content to check
   */
  checkForSpecificPatterns(filename, startLine, content) {
    const patterns = [
      {
        regex: /const\s+\w+\s*=\s*`\$\{\{[^}]+\}\}`/g,
        description: 'Direct template literal interpolation with GitHub expressions',
        risk: 'critical'
      },
      {
        regex: /`\$\{\{[^}]+\}\}`/g,
        description: 'GitHub expression in template literal',
        risk: 'high'
      },
      {
        regex: /\$\{\{[^}]*\.(outputs|env)\.[^}]*\}\}/g,
        description: 'Potentially unsafe output/env variable usage',
        risk: 'medium'
      }
    ];

    patterns.forEach(({ regex, description, risk }) => {
      const matches = content.match(regex);
      if (matches) {
        matches.forEach(match => {
          this.addVulnerability(filename, startLine, description, match, risk);
        });
      }
    });
  }

  /**
   * Check line for general patterns
   * @param {string} filename - Workflow filename
   * @param {number} lineNumber - Line number
   * @param {string} line - Line content
   */
  checkLineForPatterns(filename, lineNumber, line) {
    // Check for unescaped commit hashes in outputs
    if (line.includes('GITHUB_SHA') && line.includes('`')) {
      this.addWarning(filename, lineNumber, 'Potential commit hash in backticks', line.trim());
    }

    // Check for dangerous environment variable usage
    if (line.includes('${{') && line.includes('env.') && line.includes('`')) {
      this.addWarning(filename, lineNumber, 'Environment variable in template literal context', line.trim());
    }
  }

  /**
   * Check if line has unsafe template usage
   * @param {string} line - Line to check
   * @returns {boolean} True if unsafe usage detected
   */
  hasUnsafeTemplateUsage(line) {
    return /const\s+\w+\s*=\s*`\$\{\{/.test(line) || 
           /`\$\{\{.*\}\}`/.test(line);
  }

  /**
   * Add vulnerability
   * @param {string} filename - Workflow filename
   * @param {number} lineNumber - Line number
   * @param {string} description - Vulnerability description
   * @param {string} code - Code snippet
   * @param {string} risk - Risk level
   */
  addVulnerability(filename, lineNumber, description, code, risk) {
    this.vulnerabilities.push({
      filename,
      lineNumber,
      description,
      code,
      risk,
      type: 'vulnerability'
    });
  }

  /**
   * Add warning
   * @param {string} filename - Workflow filename
   * @param {number} lineNumber - Line number
   * @param {string} description - Warning description
   * @param {string} code - Code snippet
   */
  addWarning(filename, lineNumber, description, code) {
    this.warnings.push({
      filename,
      lineNumber,
      description,
      code,
      type: 'warning'
    });
  }

  /**
   * Generate vulnerability report with enhanced details
   */
  generateReport() {
    console.log('\n📋 Vulnerability Scan Report');
    console.log('============================');

    const criticalVulns = this.vulnerabilities.filter(v => v.risk === 'critical');
    const highVulns = this.vulnerabilities.filter(v => v.risk === 'high');
    const mediumVulns = this.vulnerabilities.filter(v => v.risk === 'medium');
    const lowVulns = this.vulnerabilities.filter(v => v.risk === 'low');

    console.log(`Total Vulnerabilities: ${this.vulnerabilities.length}`);
    console.log(`  Critical: ${criticalVulns.length}`);
    console.log(`  High: ${highVulns.length}`);
    console.log(`  Medium: ${mediumVulns.length}`);
    console.log(`  Low: ${lowVulns.length}`);
    console.log(`Total Warnings: ${this.warnings.length}\n`);

    if (this.vulnerabilities.length === 0 && this.warnings.length === 0) {
      console.log('✅ No vulnerabilities or warnings found!');
      this.generateSecurityScore();
      return;
    }

    // Report critical vulnerabilities
    if (criticalVulns.length > 0) {
      console.log('🚨 CRITICAL VULNERABILITIES:');
      criticalVulns.forEach(vuln => {
        console.log(`  ${vuln.filename}:${vuln.lineNumber} - ${vuln.description}`);
        console.log(`    Code: ${vuln.code}`);
      });
      console.log('');
    }

    // Report high vulnerabilities
    if (highVulns.length > 0) {
      console.log('❌ HIGH RISK VULNERABILITIES:');
      highVulns.forEach(vuln => {
        console.log(`  ${vuln.filename}:${vuln.lineNumber} - ${vuln.description}`);
        console.log(`    Code: ${vuln.code}`);
      });
      console.log('');
    }

    // Report medium vulnerabilities
    if (mediumVulns.length > 0) {
      console.log('⚠️  MEDIUM RISK VULNERABILITIES:');
      mediumVulns.forEach(vuln => {
        console.log(`  ${vuln.filename}:${vuln.lineNumber} - ${vuln.description}`);
        console.log(`    Code: ${vuln.code}`);
      });
      console.log('');
    }

    // Report warnings
    if (this.warnings.length > 0) {
      console.log('⚠️  WARNINGS:');
      this.warnings.forEach(warning => {
        console.log(`  ${warning.filename}:${warning.lineNumber} - ${warning.description}`);
        console.log(`    Code: ${warning.code}`);
      });
      console.log('');
    }

    // Recommendations
    console.log('💡 RECOMMENDATIONS:');
    console.log('  1. Use environment variables instead of direct template literal interpolation');
    console.log('  2. Sanitize all external input before using in JavaScript contexts');
    console.log('  3. Avoid using GitHub expressions directly in template literals');
    console.log('  4. Use the enhanced error handler for safe output generation');

    // Generate security score and recommendations
    this.generateSecurityScore();
    this.generateFixRecommendations();

    // Exit with appropriate code
    if (criticalVulns.length > 0) {
      process.exit(2);
    } else if (highVulns.length > 0) {
      process.exit(1);
    }
  }

  /**
   * Generate security score based on vulnerabilities
   */
  generateSecurityScore() {
    const total = this.vulnerabilities.length;
    const critical = this.vulnerabilities.filter(v => v.risk === 'critical').length;
    const high = this.vulnerabilities.filter(v => v.risk === 'high').length;
    const medium = this.vulnerabilities.filter(v => v.risk === 'medium').length;

    // Calculate weighted score (100 = perfect, 0 = very poor)
    const criticalWeight = 25;
    const highWeight = 10;
    const mediumWeight = 5;

    const deductions = (critical * criticalWeight) + (high * highWeight) + (medium * mediumWeight);
    const score = Math.max(0, 100 - deductions);

    console.log('\n🏆 Security Score');
    console.log('=================');
    console.log(`Overall Score: ${score}/100`);

    if (score >= 90) {
      console.log('Rating: ✅ EXCELLENT - Very secure workflows');
    } else if (score >= 75) {
      console.log('Rating: ✅ GOOD - Minor security improvements needed');
    } else if (score >= 50) {
      console.log('Rating: ⚠️  FAIR - Moderate security issues present');
    } else if (score >= 25) {
      console.log('Rating: ❌ POOR - Significant security vulnerabilities');
    } else {
      console.log('Rating: 🚨 CRITICAL - Immediate security attention required');
    }
  }

  /**
   * Generate specific fix recommendations
   */
  generateFixRecommendations() {
    console.log('\n🔧 Fix Recommendations');
    console.log('======================');

    const fixes = new Map();

    this.vulnerabilities.forEach(vuln => {
      const key = `${vuln.filename}:${vuln.lineNumber}`;
      if (!fixes.has(key)) {
        fixes.set(key, []);
      }
      fixes.get(key).push(vuln);
    });

    fixes.forEach((vulns, location) => {
      console.log(`\n📁 ${location}`);
      vulns.forEach(vuln => {
        console.log(`   ${this.getRiskEmoji(vuln.risk)} ${vuln.description}`);
        console.log(`   💡 Fix: ${this.getFixSuggestion(vuln)}`);
      });
    });
  }

  /**
   * Get emoji for risk level
   */
  getRiskEmoji(risk) {
    switch (risk) {
      case 'critical': return '🚨';
      case 'high': return '❌';
      case 'medium': return '⚠️ ';
      case 'low': return 'ℹ️ ';
      default: return '❓';
    }
  }

  /**
   * Get fix suggestion for vulnerability
   */
  getFixSuggestion(vuln) {
    if (vuln.description.includes('Template literal')) {
      return 'Use environment variables instead of direct interpolation';
    } else if (vuln.description.includes('commit hash')) {
      return 'Pass commit hash via environment variable and validate';
    } else if (vuln.description.includes('output/env variable')) {
      return 'Add input validation and sanitization';
    } else if (vuln.description.includes('eval')) {
      return 'Remove eval() calls and use safe alternatives';
    } else if (vuln.description.includes('Function constructor')) {
      return 'Remove Function() constructor calls';
    } else {
      return 'Review and apply security best practices';
    }
  }
}

// Run scanner if called directly
if (require.main === module) {
  const scanner = new WorkflowVulnerabilityScanner();
  scanner.scanAllWorkflows().catch(error => {
    console.error('Scanner failed:', error);
    process.exit(1);
  });
}

module.exports = { WorkflowVulnerabilityScanner };
