#!/usr/bin/env node

const fs = require('fs');
const { execSync } = require('child_process');

// Read the current version from package.json
const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const version = packageJson.version;

console.log(`Updating CHANGELOG.md for version ${version}`);

try {
  // Get the latest tag
  let lastTag;
  try {
    lastTag = execSync('git describe --tags --abbrev=0', { encoding: 'utf8' }).trim();
  } catch (error) {
    lastTag = null;
    console.log('No previous tags found, generating changelog from beginning');
  }

  // Get commits since last tag
  const gitLogCommand = lastTag 
    ? `git log ${lastTag}..HEAD --oneline --pretty=format:"- %s (%h)"`
    : `git log --oneline --pretty=format:"- %s (%h)"`;
  
  let commits;
  try {
    commits = execSync(gitLogCommand, { encoding: 'utf8' }).trim();
  } catch (error) {
    commits = '';
  }

  if (!commits) {
    console.log('No new commits found since last tag');
    return;
  }

  // Read existing changelog
  let changelogContent = '';
  if (fs.existsSync('CHANGELOG.md')) {
    changelogContent = fs.readFileSync('CHANGELOG.md', 'utf8');
  } else {
    changelogContent = '# Changelog\n\nAll notable changes to this project will be documented in this file.\n\n';
  }

  // Create new entry
  const today = new Date().toISOString().split('T')[0];
  const newEntry = `## [${version}] - ${today}\n\n${commits}\n\n`;

  // Insert new entry after the header
  const lines = changelogContent.split('\n');
  const headerEndIndex = lines.findIndex(line => line.trim() === '' && lines.indexOf(line) > 2) || 3;
  
  lines.splice(headerEndIndex + 1, 0, newEntry);
  
  const updatedChangelog = lines.join('\n');
  fs.writeFileSync('CHANGELOG.md', updatedChangelog);

  console.log(`✅ Updated CHANGELOG.md with version ${version}`);
  console.log('New entries:');
  console.log(commits);

} catch (error) {
  console.error('❌ Error updating changelog:', error.message);
  process.exit(1);
}
