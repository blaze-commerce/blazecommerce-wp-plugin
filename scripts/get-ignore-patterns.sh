#!/bin/bash
# Shared ignore patterns for GitHub workflows
# This script outputs the list of file patterns that should be ignored
# for version bumping and release creation purposes.

cat << 'EOF'
CHANGELOG.md
package.json
package-lock.json
blaze-wooless.php
blocks/package.json
.github/
scripts/
tests/
test/
bin/
docs/
CONTRIBUTING.md
TODO
IMPLEMENTATION_SUMMARY.md
VERSION_BUMP_BEHAVIOR_EXPLANATION.md
CHANGELOG_VERSION_FIX.md
phpunit.xml
jest.config.js
github-workflows-tests.yml
test.html
composer.lock
vendor/
blocks/yarn.lock
blocks/package-lock.json
.gitignore
.editorconfig
.DS_Store
.augment/
.claude/
.vscode/
EOF
