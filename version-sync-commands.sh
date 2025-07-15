#!/bin/bash

# BlazeCommerce Version Synchronization Script
# Updates all version files to match the latest git tag v1.14.0

echo "🔄 Synchronizing all version files to v1.14.0..."

# 1. Update package.json
echo "📝 Updating package.json..."
sed -i 's/"version": "1.12.0"/"version": "1.14.0"/g' package.json

# 2. Update blaze-wooless.php (Plugin Header)
echo "📝 Updating blaze-wooless.php plugin header..."
sed -i 's/Version: 1.12.0/Version: 1.14.0/g' blaze-wooless.php

# 3. Update blaze-wooless.php (PHP Constant)
echo "📝 Updating blaze-wooless.php PHP constant..."
sed -i "s/define( 'BLAZE_COMMERCE_VERSION', '1.12.0' );/define( 'BLAZE_COMMERCE_VERSION', '1.14.0' );/g" blaze-wooless.php

# 4. Update blocks/package.json
echo "📝 Updating blocks/package.json..."
sed -i 's/"version": "1.12.0"/"version": "1.14.0"/g' blocks/package.json

# 5. Update README.md
echo "📝 Updating README.md..."
sed -i 's/\*\*Version:\*\* 1.12.0/**Version:** 1.14.0/g' README.md

# 6. Regenerate package-lock.json
echo "📝 Regenerating package-lock.json..."
npm install --package-lock-only

echo "✅ Version synchronization complete!"
echo "🔍 Verifying changes..."

# Verify the changes
echo "📊 Current versions after update:"
echo "- package.json: $(grep '"version"' package.json | head -1 | cut -d'"' -f4)"
echo "- blaze-wooless.php header: $(grep 'Version:' blaze-wooless.php | cut -d' ' -f2)"
echo "- blaze-wooless.php constant: $(grep 'BLAZE_COMMERCE_VERSION' blaze-wooless.php | cut -d"'" -f4)"
echo "- blocks/package.json: $(grep '"version"' blocks/package.json | cut -d'"' -f4)"
echo "- README.md: $(grep '**Version:**' README.md | cut -d' ' -f2)"

echo ""
echo "🚀 Next steps:"
echo "1. Run: npm run validate-version"
echo "2. Test the plugin functionality"
echo "3. Commit changes: git add . && git commit -m 'chore: sync version files to v1.14.0'"
echo "4. Verify no additional tags are needed"
