#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Read the current version from package.json
const packageJson = JSON.parse(fs.readFileSync('package.json', 'utf8'));
const newVersion = packageJson.version;

console.log(`Updating plugin version to ${newVersion}`);

// Update the main plugin file
const pluginFile = 'blaze-wooless.php';
let pluginContent = fs.readFileSync(pluginFile, 'utf8');

// Update the plugin header version
pluginContent = pluginContent.replace(
  /^Version:\s*[\d.]+$/m,
  `Version: ${newVersion}`
);

// Update the version constant
pluginContent = pluginContent.replace(
  /define\(\s*'BLAZE_COMMERCE_VERSION',\s*'[\d.]+'\s*\);/,
  `define( 'BLAZE_COMMERCE_VERSION', '${newVersion}' );`
);

fs.writeFileSync(pluginFile, pluginContent);

console.log(`✅ Updated ${pluginFile} with version ${newVersion}`);

// Update blocks package.json if it exists
const blocksPackageFile = 'blocks/package.json';
if (fs.existsSync(blocksPackageFile)) {
  const blocksPackage = JSON.parse(fs.readFileSync(blocksPackageFile, 'utf8'));
  blocksPackage.version = newVersion;
  fs.writeFileSync(blocksPackageFile, JSON.stringify(blocksPackage, null, '\t') + '\n');
  console.log(`✅ Updated ${blocksPackageFile} with version ${newVersion}`);
}

console.log('🎉 Version update complete!');
