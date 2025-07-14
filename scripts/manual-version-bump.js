#!/usr/bin/env node

/**
 * Manual Version Bump Script
 * 
 * This script manually completes the version bump that failed in the GitHub Actions workflow.
 * It updates all version files and creates the necessary commit and tag.
 */

const fs = require('fs');
const { execSync } = require('child_process');
const path = require('path');

// Configuration
const VERSION_FILES = [
    'package.json',
    'blaze-wooless.php',
    'README.md',
    'blocks/package.json'
];

function getCurrentVersion() {
    const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
    return packageJson.version;
}

function incrementVersion(version, type = 'patch') {
    const [major, minor, patch] = version.split('.').map(Number);

    switch (type) {
        case 'major':
            return `${major + 1}.0.0`;
        case 'minor':
            return `${major}.${minor + 1}.0`;
        case 'patch':
        default:
            return `${major}.${minor}.${patch + 1}`;
    }
}

function findNextAvailableVersion(baseVersion, type = 'patch') {
    let version = incrementVersion(baseVersion, type);

    // Check if tag exists
    while (tagExists(version)) {
        console.log(`⚠️  Tag v${version} already exists, trying next version...`);
        version = incrementVersion(version, 'patch');
    }

    return version;
}

function tagExists(version) {
    try {
        execSync(`git tag | grep -q "^v${version}$"`, { stdio: 'ignore' });
        return true;
    } catch {
        return false;
    }
}

function updatePackageJson(newVersion) {
    const packagePath = 'package.json';
    const packageJson = JSON.parse(fs.readFileSync(packagePath, 'utf8'));
    packageJson.version = newVersion;
    fs.writeFileSync(packagePath, JSON.stringify(packageJson, null, 2) + '\n');
    console.log(`✅ Updated ${packagePath}: ${newVersion}`);
}

function updatePhpFile(newVersion) {
    const phpPath = 'blaze-wooless.php';
    if (!fs.existsSync(phpPath)) {
        console.log(`⚠️  ${phpPath} not found, skipping`);
        return;
    }
    
    let content = fs.readFileSync(phpPath, 'utf8');
    
    // Update plugin header version
    content = content.replace(
        /Version:\s*[\d.]+/,
        `Version: ${newVersion}`
    );
    
    // Update version constant
    content = content.replace(
        /define\(\s*['"]BLAZE_COMMERCE_VERSION['"],\s*['"][\d.]+['"]\s*\)/,
        `define( 'BLAZE_COMMERCE_VERSION', '${newVersion}' )`
    );
    
    fs.writeFileSync(phpPath, content);
    console.log(`✅ Updated ${phpPath}: ${newVersion}`);
}

function updateReadme(newVersion) {
    const readmePath = 'README.md';
    if (!fs.existsSync(readmePath)) {
        console.log(`⚠️  ${readmePath} not found, skipping`);
        return;
    }
    
    let content = fs.readFileSync(readmePath, 'utf8');
    
    // Update version badge
    content = content.replace(
        /\*\*Version:\*\*\s*[\d.]+/,
        `**Version:** ${newVersion}`
    );
    
    fs.writeFileSync(readmePath, content);
    console.log(`✅ Updated ${readmePath}: ${newVersion}`);
}

function updateBlocksPackageJson(newVersion) {
    const blocksPath = 'blocks/package.json';
    if (!fs.existsSync(blocksPath)) {
        console.log(`⚠️  ${blocksPath} not found, skipping`);
        return;
    }
    
    const packageJson = JSON.parse(fs.readFileSync(blocksPath, 'utf8'));
    packageJson.version = newVersion;
    fs.writeFileSync(blocksPath, JSON.stringify(packageJson, null, 2) + '\n');
    console.log(`✅ Updated ${blocksPath}: ${newVersion}`);
}

function createCommitAndTag(newVersion, bumpType) {
    try {
        // Configure git
        execSync('git config --local user.email "github-actions[bot]@users.noreply.github.com"');
        execSync('git config --local user.name "github-actions[bot]"');
        
        // Add files
        execSync('git add package.json');
        if (fs.existsSync('blaze-wooless.php')) {
            execSync('git add blaze-wooless.php');
        }
        if (fs.existsSync('README.md')) {
            execSync('git add README.md');
        }
        if (fs.existsSync('blocks/package.json')) {
            execSync('git add blocks/package.json');
        }
        
        // Create commit
        const commitMessage = `chore(release): bump version to ${newVersion} [${bumpType}]`;
        execSync(`git commit -m "${commitMessage}"`);
        console.log(`✅ Created commit: ${commitMessage}`);
        
        // Create tag
        const tagName = `v${newVersion}`;
        execSync(`git tag ${tagName}`);
        console.log(`✅ Created tag: ${tagName}`);
        
        return { commitMessage, tagName };
        
    } catch (error) {
        console.error('❌ Error creating commit and tag:', error.message);
        throw error;
    }
}

function main() {
    console.log('🚀 Starting manual version bump...\n');
    
    // Get current version
    const currentVersion = getCurrentVersion();
    console.log(`📦 Current version: ${currentVersion}`);
    
    // Determine bump type (patch for fix: commits)
    const bumpType = 'patch'; // Since PR #370 was a fix: commit
    const newVersion = findNextAvailableVersion(currentVersion, bumpType);
    console.log(`🔄 New version: ${newVersion} (${bumpType} bump)\n`);
    
    // Update all version files
    console.log('📝 Updating version files...');
    updatePackageJson(newVersion);
    updatePhpFile(newVersion);
    updateReadme(newVersion);
    updateBlocksPackageJson(newVersion);
    
    console.log('\n🏷️  Creating commit and tag...');
    const { commitMessage, tagName } = createCommitAndTag(newVersion, bumpType);
    
    console.log('\n✅ Manual version bump completed successfully!');
    console.log('\n📋 Summary:');
    console.log(`   • Version: ${currentVersion} → ${newVersion}`);
    console.log(`   • Bump type: ${bumpType}`);
    console.log(`   • Commit: ${commitMessage}`);
    console.log(`   • Tag: ${tagName}`);
    
    console.log('\n🚀 Next steps:');
    console.log('   1. Push the commit: git push origin main');
    console.log('   2. Push the tag: git push origin ' + tagName);
    console.log('   3. Monitor the release workflow');
}

if (require.main === module) {
    main();
}

module.exports = {
    getCurrentVersion,
    incrementVersion,
    findNextAvailableVersion,
    tagExists,
    updatePackageJson,
    updatePhpFile,
    updateReadme,
    updateBlocksPackageJson,
    createCommitAndTag
};
