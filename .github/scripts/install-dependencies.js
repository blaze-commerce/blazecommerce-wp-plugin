#!/usr/bin/env node

/**
 * Dependency Installer for Claude Review Enhancer
 * Ensures required dependencies are available for the progressive review system
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

class DependencyInstaller {
  constructor() {
    this.requiredPackages = [
      { name: 'node-fetch', version: '^2.6.7', reason: 'GitHub API integration' }
    ];
  }

  log(message, type = 'info') {
    const prefix = type === 'error' ? 'ERROR:' : type === 'success' ? 'SUCCESS:' : 'INFO:';
    console.log(`${prefix} ${message}`);
  }

  checkPackage(packageName) {
    try {
      require.resolve(packageName);
      return true;
    } catch (error) {
      return false;
    }
  }

  installPackage(pkg) {
    try {
      this.log(`Installing ${pkg.name} for ${pkg.reason}...`);
      
      // Install without saving to package.json (temporary install)
      execSync(`npm install ${pkg.name}@${pkg.version} --no-save --silent`, {
        stdio: 'pipe',
        cwd: process.cwd()
      });
      
      this.log(`Successfully installed ${pkg.name}`, 'success');
      return true;
    } catch (error) {
      this.log(`Failed to install ${pkg.name}: ${error.message}`, 'error');
      return false;
    }
  }

  async installDependencies() {
    this.log('Checking dependencies for Claude Progressive Review System...');
    
    let allInstalled = true;
    
    for (const pkg of this.requiredPackages) {
      if (this.checkPackage(pkg.name)) {
        this.log(`${pkg.name} is already available`);
      } else {
        this.log(`${pkg.name} not found, installing...`);
        const installed = this.installPackage(pkg);
        if (!installed) {
          allInstalled = false;
        }
      }
    }
    
    if (allInstalled) {
      this.log('All dependencies are ready', 'success');
      return true;
    } else {
      this.log('Some dependencies failed to install', 'error');
      return false;
    }
  }

  createFallbackFetch() {
    // Create a simple fallback for environments where node-fetch can't be installed
    const fallbackCode = `
// Fallback fetch implementation for environments without node-fetch
global.fetch = global.fetch || function(url, options = {}) {
  const https = require('https');
  const http = require('http');
  const { URL } = require('url');
  
  return new Promise((resolve, reject) => {
    const parsedUrl = new URL(url);
    const client = parsedUrl.protocol === 'https:' ? https : http;
    
    const req = client.request({
      hostname: parsedUrl.hostname,
      port: parsedUrl.port,
      path: parsedUrl.pathname + parsedUrl.search,
      method: options.method || 'GET',
      headers: options.headers || {}
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        resolve({
          ok: res.statusCode >= 200 && res.statusCode < 300,
          status: res.statusCode,
          statusText: res.statusMessage,
          json: () => Promise.resolve(JSON.parse(data)),
          text: () => Promise.resolve(data)
        });
      });
    });
    
    req.on('error', reject);
    
    if (options.body) {
      req.write(options.body);
    }
    
    req.end();
  });
};
`;
    
    const fallbackPath = path.join(__dirname, 'fetch-fallback.js');
    fs.writeFileSync(fallbackPath, fallbackCode);
    this.log('Created fetch fallback implementation');
    return fallbackPath;
  }
}

// Main execution
if (require.main === module) {
  const installer = new DependencyInstaller();
  
  installer.installDependencies().then(success => {
    if (!success) {
      installer.log('Creating fallback implementation...');
      installer.createFallbackFetch();
    }
    
    process.exit(success ? 0 : 1);
  }).catch(error => {
    installer.log(`Installation failed: ${error.message}`, 'error');
    installer.createFallbackFetch();
    process.exit(1);
  });
}

module.exports = { DependencyInstaller };
