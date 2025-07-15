#!/bin/bash

# GitHub Repository Settings Configuration Script
# Automates the configuration of repository settings for workflow fixes

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
REPO_OWNER="blaze-commerce"
REPO_NAME="blazecommerce-wp-plugin"
GITHUB_TOKEN="${GITHUB_TOKEN:-}"

# Check if GitHub CLI is available
check_gh_cli() {
    if ! command -v gh &> /dev/null; then
        echo -e "${RED}❌ GitHub CLI (gh) is not installed${NC}"
        echo "Please install GitHub CLI: https://cli.github.com/"
        exit 1
    fi
    
    if ! gh auth status &> /dev/null; then
        echo -e "${RED}❌ GitHub CLI is not authenticated${NC}"
        echo "Please run: gh auth login"
        exit 1
    fi
    
    echo -e "${GREEN}✅ GitHub CLI is available and authenticated${NC}"
}

# Configure repository variables
configure_variables() {
    echo -e "${BLUE}🔧 Configuring repository variables...${NC}"
    
    local variables=(
        "TEST_TIMEOUT:20"
        "CIRCUIT_BREAKER_TIMEOUT:300"
        "HEALTH_CHECK_RETRIES:3"
        "WORDPRESS_VERSION:latest"
        "PHP_VERSION:8.1"
        "CIRCUIT_BREAKER_MAX_FAILURES:3"
        "FALLBACK_CACHE_TTL:3600"
    )
    
    for var in "${variables[@]}"; do
        local name=$(echo "$var" | cut -d: -f1)
        local value=$(echo "$var" | cut -d: -f2)
        
        echo "Setting variable: $name = $value"
        if gh variable set "$name" --body "$value" --repo "$REPO_OWNER/$REPO_NAME" 2>/dev/null; then
            echo -e "${GREEN}✅ Set variable: $name${NC}"
        else
            echo -e "${YELLOW}⚠️  Failed to set variable: $name (may already exist)${NC}"
        fi
    done
}

# Check required secrets
check_secrets() {
    echo -e "${BLUE}🔍 Checking required secrets...${NC}"
    
    local required_secrets=(
        "BC_GITHUB_APP_ID"
        "BC_GITHUB_APP_PRIVATE_KEY"
    )
    
    local missing_secrets=()
    
    for secret in "${required_secrets[@]}"; do
        if gh secret list --repo "$REPO_OWNER/$REPO_NAME" | grep -q "$secret"; then
            echo -e "${GREEN}✅ Secret exists: $secret${NC}"
        else
            echo -e "${RED}❌ Missing secret: $secret${NC}"
            missing_secrets+=("$secret")
        fi
    done
    
    if [ ${#missing_secrets[@]} -gt 0 ]; then
        echo -e "${YELLOW}⚠️  Missing secrets need to be configured manually:${NC}"
        for secret in "${missing_secrets[@]}"; do
            echo "  - $secret"
        done
        echo ""
        echo "Configure these secrets at:"
        echo "https://github.com/$REPO_OWNER/$REPO_NAME/settings/secrets/actions"
    fi
}

# Configure branch protection rules
configure_branch_protection() {
    echo -e "${BLUE}🛡️  Configuring branch protection rules...${NC}"
    
    # Main branch protection
    echo "Configuring protection for main branch..."
    
    local main_protection='{
        "required_status_checks": {
            "strict": true,
            "contexts": [
                "Tests / health-check",
                "Tests / run-tests",
                "Claude AI Approval Gate / claude-approval"
            ]
        },
        "enforce_admins": false,
        "required_pull_request_reviews": {
            "required_approving_review_count": 1,
            "dismiss_stale_reviews": true,
            "require_code_owner_reviews": false
        },
        "restrictions": null,
        "allow_force_pushes": false,
        "allow_deletions": false,
        "required_conversation_resolution": true
    }'
    
    if curl -s -X PUT \
        -H "Authorization: token $GITHUB_TOKEN" \
        -H "Accept: application/vnd.github.v3+json" \
        "https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/branches/main/protection" \
        -d "$main_protection" > /dev/null 2>&1; then
        echo -e "${GREEN}✅ Main branch protection configured${NC}"
    else
        echo -e "${YELLOW}⚠️  Could not configure main branch protection (may need admin access)${NC}"
    fi
    
    # Develop branch protection (if exists)
    if git ls-remote --heads origin develop | grep -q develop; then
        echo "Configuring protection for develop branch..."
        
        local develop_protection='{
            "required_status_checks": {
                "strict": true,
                "contexts": [
                    "Tests / health-check",
                    "Tests / run-tests"
                ]
            },
            "enforce_admins": false,
            "required_pull_request_reviews": {
                "required_approving_review_count": 1,
                "dismiss_stale_reviews": true
            },
            "restrictions": null,
            "allow_force_pushes": false,
            "allow_deletions": false
        }'
        
        if curl -s -X PUT \
            -H "Authorization: token $GITHUB_TOKEN" \
            -H "Accept: application/vnd.github.v3+json" \
            "https://api.github.com/repos/$REPO_OWNER/$REPO_NAME/branches/develop/protection" \
            -d "$develop_protection" > /dev/null 2>&1; then
            echo -e "${GREEN}✅ Develop branch protection configured${NC}"
        else
            echo -e "${YELLOW}⚠️  Could not configure develop branch protection${NC}"
        fi
    fi
}

# Validate workflow files
validate_workflows() {
    echo -e "${BLUE}🔍 Validating workflow files...${NC}"
    
    local workflows=(
        ".github/workflows/tests.yml"
        ".github/workflows/claude-approval-gate.yml"
        ".github/workflows/auto-version.yml"
        ".github/workflows/release.yml"
    )
    
    for workflow in "${workflows[@]}"; do
        if [ -f "$workflow" ]; then
            if python3 -c "import yaml; yaml.safe_load(open('$workflow'))" 2>/dev/null; then
                echo -e "${GREEN}✅ Valid YAML: $(basename "$workflow")${NC}"
            else
                echo -e "${RED}❌ Invalid YAML: $(basename "$workflow")${NC}"
            fi
        else
            echo -e "${YELLOW}⚠️  Missing workflow: $(basename "$workflow")${NC}"
        fi
    done
}

# Check GitHub App configuration
check_github_app() {
    echo -e "${BLUE}🤖 Checking GitHub App configuration...${NC}"
    
    if gh secret list --repo "$REPO_OWNER/$REPO_NAME" | grep -q "BC_GITHUB_APP_ID"; then
        echo -e "${GREEN}✅ GitHub App ID configured${NC}"
        
        if gh secret list --repo "$REPO_OWNER/$REPO_NAME" | grep -q "BC_GITHUB_APP_PRIVATE_KEY"; then
            echo -e "${GREEN}✅ GitHub App private key configured${NC}"
        else
            echo -e "${RED}❌ GitHub App private key missing${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  GitHub App not configured${NC}"
        echo ""
        echo "To configure GitHub App for Claude AI approval:"
        echo "1. Create GitHub App with these permissions:"
        echo "   - Contents: Read"
        echo "   - Issues: Write"
        echo "   - Pull requests: Write"
        echo "   - Actions: Read"
        echo "2. Install app on repository"
        echo "3. Add app ID and private key as secrets"
    fi
}

# Generate configuration summary
generate_summary() {
    echo -e "${BLUE}📋 Configuration Summary${NC}"
    echo "========================="
    
    echo ""
    echo "Repository: $REPO_OWNER/$REPO_NAME"
    echo "Configuration Date: $(date)"
    echo ""
    
    echo "✅ Completed Tasks:"
    echo "  - Repository variables configured"
    echo "  - Workflow files validated"
    echo "  - Secret requirements checked"
    echo ""
    
    echo "⚠️  Manual Tasks Required:"
    echo "  - Configure missing secrets (if any)"
    echo "  - Set up GitHub App for Claude AI"
    echo "  - Verify branch protection rules"
    echo "  - Test workflow execution"
    echo ""
    
    echo "📚 Documentation:"
    echo "  - Configuration Guide: docs/github-repository-settings-configuration-guide.md"
    echo "  - Troubleshooting: Check workflow logs for issues"
    echo ""
    
    echo "🔗 Useful Links:"
    echo "  - Repository Settings: https://github.com/$REPO_OWNER/$REPO_NAME/settings"
    echo "  - Actions Secrets: https://github.com/$REPO_OWNER/$REPO_NAME/settings/secrets/actions"
    echo "  - Branch Protection: https://github.com/$REPO_OWNER/$REPO_NAME/settings/branches"
}

# Main execution
main() {
    echo -e "${BLUE}🚀 GitHub Repository Settings Configuration${NC}"
    echo "=============================================="
    echo ""
    
    # Check prerequisites
    check_gh_cli
    
    # Get GitHub token if not set
    if [ -z "$GITHUB_TOKEN" ]; then
        GITHUB_TOKEN=$(gh auth token)
    fi
    
    echo ""
    
    # Run configuration tasks
    configure_variables
    echo ""
    
    check_secrets
    echo ""
    
    validate_workflows
    echo ""
    
    check_github_app
    echo ""
    
    # Configure branch protection (requires admin access)
    if [ -n "$GITHUB_TOKEN" ]; then
        configure_branch_protection
        echo ""
    fi
    
    # Generate summary
    generate_summary
    
    echo -e "${GREEN}🎉 Configuration completed!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Review any warnings or missing configurations above"
    echo "2. Test workflow execution with a test PR"
    echo "3. Monitor workflow performance and adjust settings as needed"
}

# Execute main function
main "$@"
