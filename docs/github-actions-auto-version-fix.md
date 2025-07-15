# GitHub Actions Auto-Version Workflow Error Fix

## Problem Summary

The auto-version workflow successfully creates commits to bump the version but fails to push to the main branch with these errors:
- "Changes must be made through a pull request"
- "2 of 2 required status checks are expected"
- "You're not authorized to push to this branch"

## Root Cause

The main branch has branch protection rules that require:
1. All changes to go through pull requests
2. Status checks to pass before merging
3. The current token doesn't have bypass permissions

## Solution 1: GitHub App Configuration (Recommended)

### Step 1: Verify GitHub App Permissions

Your GitHub App needs these permissions:
- **Contents**: Write (to push commits and tags)
- **Metadata**: Read (to access repository information)
- **Pull Requests**: Write (to create PRs if needed)
- **Actions**: Read (to read workflow information)

### Step 2: Configure Branch Protection Bypass

1. Go to your repository settings
2. Navigate to **Branches** → **Branch protection rules** → **main**
3. Under "Restrict pushes that create files", ensure your GitHub App is in the bypass list
4. Under "Allow specified actors to bypass required pull requests", add your GitHub App
5. Save the changes

### Step 3: Verify GitHub App Installation

Ensure your GitHub App is installed on the repository with the correct permissions:
1. Go to GitHub Settings → Developer settings → GitHub Apps
2. Find your BlazeCommerce Automation Bot
3. Check that it's installed on the `blazecommerce-wp-plugin` repository
4. Verify it has the permissions listed in Step 1

### Step 4: Update Repository Secrets

Ensure these secrets are properly configured:
- `BC_GITHUB_APP_ID`: Your GitHub App ID
- `BC_GITHUB_APP_PRIVATE_KEY`: Your GitHub App private key (PEM format)
- `BC_GITHUB_TOKEN`: Fallback personal access token (admin permissions)

## Solution 2: Alternative Approach - Use Pull Request Workflow

If the bypass approach doesn't work, modify the workflow to create a pull request instead:

### Create a new workflow file: `.github/workflows/auto-version-pr.yml`

```yaml
name: "Auto Version Bump via PR"

on:
  push:
    branches:
      - main

jobs:
  version-bump-pr:
    runs-on: ubuntu-latest
    if: |
      github.ref == 'refs/heads/main' &&
      !contains(github.event.head_commit.message, '[skip ci]') &&
      !contains(github.event.head_commit.message, 'chore(release)')
    
    steps:
    - name: Generate GitHub App Token
      id: app_token
      uses: actions/create-github-app-token@v1
      with:
        app-id: ${{ secrets.BC_GITHUB_APP_ID }}
        private-key: ${{ secrets.BC_GITHUB_APP_PRIVATE_KEY }}
        owner: ${{ github.repository_owner }}
        repositories: ${{ github.event.repository.name }}

    - name: Checkout code
      uses: actions/checkout@v4
      with:
        token: ${{ steps.app_token.outputs.token }}
        fetch-depth: 0

    - name: Create version bump branch
      run: |
        BRANCH_NAME="auto-version-bump-$(date +%s)"
        git checkout -b "$BRANCH_NAME"
        echo "BRANCH_NAME=$BRANCH_NAME" >> $GITHUB_ENV

    - name: Setup Node.js and bump version
      uses: actions/setup-node@v4
      with:
        node-version: '18'
        cache: 'npm'

    - name: Install dependencies and bump version
      run: |
        npm install
        # Your existing version bump logic here
        node scripts/update-version.js --force

    - name: Configure Git and commit changes
      run: |
        git config --local user.email "blazecommerce-automation[bot]@users.noreply.github.com"
        git config --local user.name "BlazeCommerce Automation Bot"
        
        NEW_VERSION=$(node -p "require('./package.json').version")
        git add .
        git commit -m "chore(release): bump version to $NEW_VERSION [auto]"

    - name: Push branch and create PR
      run: |
        git push origin "$BRANCH_NAME"
        
        NEW_VERSION=$(node -p "require('./package.json').version")
        
        gh pr create \
          --title "chore(release): bump version to $NEW_VERSION" \
          --body "Automated version bump to $NEW_VERSION" \
          --base main \
          --head "$BRANCH_NAME" \
          --label "automated" \
          --label "version-bump"
      env:
        GH_TOKEN: ${{ steps.app_token.outputs.token }}
```

## Solution 3: Workflow Modifications Made

I've already updated your `auto-version.yml` workflow with these improvements:

1. **Enhanced GitHub App Token Generation**: Added explicit permissions
2. **Improved Git Configuration**: Uses GitHub App identity when available
3. **Better Remote URL Handling**: Sets remote URL with token for better authentication
4. **Consistent Token Usage**: Uses the same approach for both commits and tags

## Testing the Fix

1. **Test with a small change**: Make a minor commit to trigger the workflow
2. **Monitor the workflow**: Check the "Commit version changes" step specifically
3. **Verify bypass**: Ensure the push succeeds without requiring a PR

## Troubleshooting

### If the workflow still fails:

1. **Check GitHub App permissions**: Verify all required permissions are granted
2. **Verify bypass configuration**: Ensure the GitHub App is properly added to bypass lists
3. **Check token validity**: Ensure secrets are correctly configured and not expired
4. **Review branch protection**: Double-check all branch protection settings

### Debug commands to add to workflow:

```bash
# Add this before the push step to debug
echo "DEBUG: Current user: $(git config user.name)"
echo "DEBUG: Current email: $(git config user.email)"
echo "DEBUG: Remote URL: $(git remote get-url origin)"
echo "DEBUG: Token available: ${{ steps.app_token.outputs.token != '' }}"
```

## Expected Behavior After Fix

1. Workflow detects changes requiring version bump
2. Creates version bump commit using GitHub App identity
3. Pushes directly to main branch (bypassing protection rules)
4. Creates and pushes git tag
5. Triggers release workflow automatically

## Questions Answered

1. **Most recommended fix**: Use GitHub App with proper bypass permissions (Solution 1)
2. **Can bot run version bump after PR merge**: Yes, the current setup does this
3. **Required permission scopes**: Contents (write), Metadata (read), Pull Requests (write), Actions (read)
4. **GitHub App setup**: Needs proper permissions and bypass configuration as detailed above
