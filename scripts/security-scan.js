#!/usr/bin/env node

/**
 * BlazeCommerce Security Scanner
 * 
 * Scans the codebase for hardcoded sensitive information including:
 * - API keys, tokens, passwords
 * - Database credentials
 * - JWT secrets
 * - Other sensitive data that should be environment variables
 */

const fs = require('fs');
const path = require('path');

// Patterns to detect sensitive information
const SENSITIVE_PATTERNS = [
  // API Keys and Tokens
  { pattern: /api[_-]?key\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'API Key', severity: 'HIGH' },
  { pattern: /token\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'Token', severity: 'HIGH' },
  { pattern: /secret[_-]?key\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'Secret Key', severity: 'HIGH' },
  { pattern: /access[_-]?token\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'Access Token', severity: 'HIGH' },
  
  // Database Credentials
  { pattern: /password\s*[:=]\s*['"`]([^'"`\s]{3,})/gi, type: 'Password', severity: 'HIGH' },
  { pattern: /db[_-]?pass\s*[:=]\s*['"`]([^'"`\s]{3,})/gi, type: 'DB Password', severity: 'HIGH' },
  { pattern: /database[_-]?url\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'Database URL', severity: 'HIGH' },
  
  // JWT and Auth
  { pattern: /jwt[_-]?secret\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'JWT Secret', severity: 'HIGH' },
  { pattern: /auth[_-]?key\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'Auth Key', severity: 'HIGH' },
  
  // Cloud Services
  { pattern: /aws[_-]?access[_-]?key\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'AWS Access Key', severity: 'HIGH' },
  { pattern: /aws[_-]?secret\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'AWS Secret', severity: 'HIGH' },
  { pattern: /github[_-]?token\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'GitHub Token', severity: 'HIGH' },
  { pattern: /klaviyo[_-]?api[_-]?key\s*[:=]\s*['"`]([^'"`\s]{3,})/gi, type: 'Klaviyo API Key', severity: 'HIGH' },
  
  // Generic sensitive patterns
  { pattern: /private[_-]?key\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'Private Key', severity: 'HIGH' },
  { pattern: /client[_-]?secret\s*[:=]\s*['"`]([^'"`\s]{10,})/gi, type: 'Client Secret', severity: 'HIGH' },
  
  // Suspicious hardcoded values (medium severity)
  { pattern: /['"`][a-zA-Z0-9]{32,}['"`]/g, type: 'Potential Secret (32+ chars)', severity: 'MEDIUM' },
  { pattern: /sk-[a-zA-Z0-9]{20,}/g, type: 'Potential API Key (sk- prefix)', severity: 'HIGH' },
  { pattern: /pk-[a-zA-Z0-9]{20,}/g, type: 'Potential Public Key (pk- prefix)', severity: 'MEDIUM' },
];

// Files and directories to ignore
const IGNORE_PATTERNS = [
  'node_modules',
  '.git',
  'vendor',
  'coverage',
  'dist',
  'build',
  '.augment',
  '.claude',
  'scripts/security-scan.js', // Don't scan this file itself
];

// File extensions to scan
const SCAN_EXTENSIONS = ['.js', '.php', '.json', '.yml', '.yaml', '.env', '.config', '.xml'];

// Whitelist patterns (known safe values)
const WHITELIST_PATTERNS = [
  /youremptytestdbnamehere/i,
  /yourusernamehere/i,
  /yourpasswordhere/i,
  /example\.org/i,
  /admin@example\.org/i,
  /localhost/i,
  /127\.0\.0\.1/i,
  /wordpress_test/i,
  /test_user/i,
  /test_password/i,
  /\$\{\{\s*secrets\./i, // GitHub Actions secrets
  /process\.env\./i, // Environment variables
  /get_option\(/i, // WordPress options
  /\[REPLACE_WITH_ACTUAL_VALUE_FROM_USER_CREDENTIALS\]/i, // Our placeholder
];

class SecurityScanner {
  constructor() {
    this.findings = [];
    this.scannedFiles = 0;
    this.totalFiles = 0;
  }

  shouldIgnoreFile(filePath) {
    return IGNORE_PATTERNS.some(pattern => filePath.includes(pattern));
  }

  shouldScanFile(filePath) {
    const ext = path.extname(filePath).toLowerCase();
    return SCAN_EXTENSIONS.includes(ext) || path.basename(filePath).startsWith('.env');
  }

  isWhitelisted(content, match) {
    return WHITELIST_PATTERNS.some(pattern => pattern.test(content) || pattern.test(match));
  }

  scanFile(filePath) {
    try {
      const content = fs.readFileSync(filePath, 'utf8');
      const lines = content.split('\n');
      
      SENSITIVE_PATTERNS.forEach(({ pattern, type, severity }) => {
        let match;
        pattern.lastIndex = 0; // Reset regex state
        
        while ((match = pattern.exec(content)) !== null) {
          const matchText = match[0];
          const value = match[1] || matchText;
          
          // Skip if whitelisted
          if (this.isWhitelisted(content, matchText)) {
            continue;
          }
          
          // Find line number
          const beforeMatch = content.substring(0, match.index);
          const lineNumber = beforeMatch.split('\n').length;
          const line = lines[lineNumber - 1];
          
          this.findings.push({
            file: filePath,
            line: lineNumber,
            type,
            severity,
            match: matchText,
            value: value.substring(0, 20) + (value.length > 20 ? '...' : ''),
            context: line.trim()
          });
        }
      });
      
      this.scannedFiles++;
    } catch (error) {
      console.warn(`⚠️ Could not scan ${filePath}: ${error.message}`);
    }
  }

  scanDirectory(dirPath) {
    try {
      const items = fs.readdirSync(dirPath);
      
      items.forEach(item => {
        const fullPath = path.join(dirPath, item);
        
        if (this.shouldIgnoreFile(fullPath)) {
          return;
        }
        
        const stat = fs.statSync(fullPath);
        
        if (stat.isDirectory()) {
          this.scanDirectory(fullPath);
        } else if (stat.isFile() && this.shouldScanFile(fullPath)) {
          this.totalFiles++;
          this.scanFile(fullPath);
        }
      });
    } catch (error) {
      console.warn(`⚠️ Could not scan directory ${dirPath}: ${error.message}`);
    }
  }

  generateReport() {
    console.log('\n🔍 BlazeCommerce Security Scan Report');
    console.log('=====================================\n');
    
    console.log(`📊 Scan Summary:`);
    console.log(`   Files Scanned: ${this.scannedFiles}/${this.totalFiles}`);
    console.log(`   Findings: ${this.findings.length}\n`);
    
    if (this.findings.length === 0) {
      console.log('✅ No hardcoded sensitive information detected!\n');
      return true;
    }
    
    // Group findings by severity
    const highSeverity = this.findings.filter(f => f.severity === 'HIGH');
    const mediumSeverity = this.findings.filter(f => f.severity === 'MEDIUM');
    
    if (highSeverity.length > 0) {
      console.log('🔴 HIGH SEVERITY FINDINGS (Must Fix):');
      console.log('=====================================');
      highSeverity.forEach((finding, index) => {
        console.log(`${index + 1}. ${finding.type} in ${finding.file}:${finding.line}`);
        console.log(`   Context: ${finding.context}`);
        console.log(`   Value: ${finding.value}`);
        console.log(`   Recommendation: Replace with environment variable\n`);
      });
    }
    
    if (mediumSeverity.length > 0) {
      console.log('🟡 MEDIUM SEVERITY FINDINGS (Review Recommended):');
      console.log('=================================================');
      mediumSeverity.forEach((finding, index) => {
        console.log(`${index + 1}. ${finding.type} in ${finding.file}:${finding.line}`);
        console.log(`   Context: ${finding.context}`);
        console.log(`   Value: ${finding.value}\n`);
      });
    }
    
    console.log('🛠️ Remediation Steps:');
    console.log('=====================');
    console.log('1. Replace hardcoded values with environment variables');
    console.log('2. Add sensitive values to GitHub Secrets');
    console.log('3. Use proper configuration management');
    console.log('4. Update .gitignore to prevent future commits of sensitive files\n');
    
    return highSeverity.length === 0; // Return true if no high severity findings
  }

  run() {
    console.log('🔍 Starting BlazeCommerce Security Scan...\n');
    
    const startTime = Date.now();
    this.scanDirectory('.');
    const endTime = Date.now();
    
    console.log(`⏱️ Scan completed in ${endTime - startTime}ms`);
    
    return this.generateReport();
  }
}

// Run the scanner
if (require.main === module) {
  const scanner = new SecurityScanner();
  const isClean = scanner.run();
  
  // Exit with appropriate code
  process.exit(isClean ? 0 : 1);
}

module.exports = SecurityScanner;
