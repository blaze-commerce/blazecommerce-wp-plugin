#!/bin/bash
# Shared ignore patterns for GitHub workflows
# This script outputs the list of file patterns that should be ignored
# for version bumping and release creation purposes.
#
# These patterns match files that are development/configuration related
# and should NOT trigger version bumps when changed.

cat << 'EOF'
# Development & Tooling
.augment/
.claude/
.github/
.vscode/
.idea/
scripts/
bin/
setup-templates/

# Testing & Quality Assurance
tests/
test/
phpunit.xml
jest.config.js
github-workflows-tests.yml

# Dependencies & Build Configuration (excluding dependency files that should trigger version bumps)
vendor/
node_modules/
# Note: composer.json and package.json changes should trigger version bumps
# Only lock files are ignored as they're auto-generated
composer.lock
package-lock.json
blocks/package-lock.json
blocks/yarn.lock

# Documentation & Configuration
README.md
CHANGELOG.md
CONTRIBUTING.md
DOCUMENTATION_GUIDELINES.md
license.txt
.gitignore
.augmentignore
.augment-guidelines
.editorconfig

# System & Temporary Files
.DS_Store
Thumbs.db
desktop.ini
*.tmp
*.temp
*.log
*.swp
*.swo
*~
*.bak
*.orig

# IDE-specific files
*.sublime-project
*.sublime-workspace
.vscode/
.idea/
EOF
