#!/usr/bin/env node

const fs = require('fs');
const { execSync } = require('child_process');

// Read the current version from package.json
const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const version = packageJson.version;

console.log(`Creating release for version ${version}`);

try {
  // Check if we're in a git repository
  execSync('git rev-parse --git-dir', { stdio: 'ignore' });
  
  // Check if there are any uncommitted changes
  const status = execSync('git status --porcelain', { encoding: 'utf8' });
  if (status.trim()) {
    console.log('⚠️  Warning: There are uncommitted changes. Please commit them first.');
    console.log('Uncommitted files:');
    console.log(status);
    process.exit(1);
  }

  // Create and push the tag
  const tagName = `v${version}`;
  
  console.log(`Creating tag: ${tagName}`);
  execSync(`git tag -a ${tagName} -m "Release version ${version}"`, { stdio: 'inherit' });
  
  console.log(`Pushing tag: ${tagName}`);
  execSync(`git push origin ${tagName}`, { stdio: 'inherit' });
  
  console.log('🎉 Release tag created and pushed successfully!');
  console.log(`GitHub Actions will automatically create the release and build the plugin ZIP.`);
  
} catch (error) {
  console.error('❌ Error creating release:', error.message);
  process.exit(1);
}
