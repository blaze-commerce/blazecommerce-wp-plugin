# 📚 BlazeCommerce Documentation Organization Guidelines

## 🚨 HIGH PRIORITY REPOSITORY GUIDELINE

**MANDATORY RULE**: All Markdown (`.md`) files MUST be organized into appropriate category folders under `/docs/` directory. No exceptions.

## 📁 Required Directory Structure

All documentation files must be placed in the following category-based structure:

```
/docs/
├── features/           # Feature guides and user documentation
├── api/               # Technical API references and integration guides  
├── development/       # Developer workflows and automation
├── setup/             # Installation and configuration guides
├── reference/         # Changelog, legal documents, and reference materials
└── troubleshooting/   # Problem-solving guides and common issues
```

## 📋 Documentation Standards

### 1. File Placement Rules

- **Feature Documentation** → `/docs/features/`
  - User guides, feature explanations, tutorials
  - Examples: product sync, export/import, country-specific images

- **API Documentation** → `/docs/api/`
  - GraphQL endpoints, REST API, integration guides
  - Examples: Typesense API, WooCommerce integration

- **Development Documentation** → `/docs/development/`
  - Developer workflows, automation, CI/CD, bot configurations
  - Examples: Claude AI setup, automation scripts, version management

- **Setup Documentation** → `/docs/setup/`
  - Installation guides, configuration instructions
  - Examples: plugin installation, environment setup

- **Reference Documentation** → `/docs/reference/`
  - Changelog, legal documents, version history
  - Examples: CHANGELOG.md, TRADEMARK.md, version notes

- **Troubleshooting Documentation** → `/docs/troubleshooting/`
  - Problem-solving guides, common issues, debugging
  - Examples: error resolution, performance issues

### 2. File Naming Conventions

- Use **lowercase** with **hyphens** for spaces
- Be descriptive and specific
- Examples:
  - ✅ `country-specific-images.md`
  - ✅ `claude-ai-setup.md`
  - ❌ `CountryImages.md`
  - ❌ `claude_setup.md`

### 3. Required Frontmatter

All documentation files MUST include structured metadata:

```yaml
---
title: "Document Title"
description: "Brief description of the document content"
category: "features|api|development|setup|reference|troubleshooting"
version: "1.0.0"
last_updated: "YYYY-MM-DD"
author: "Author Name"
tags: ["tag1", "tag2", "tag3"]
related_docs: ["related-document.md"]
---
```

## 🔄 Migration Process

### For New Documentation
1. Determine appropriate category based on content type
2. Create file in correct `/docs/category/` directory
3. Use proper naming convention
4. Include required frontmatter metadata
5. Update category index file if needed

### For Existing Documentation
1. **IMMEDIATE ACTION REQUIRED**: Move any `.md` files from repository root to appropriate `/docs/category/`
2. Rename files to follow naming conventions
3. Add required frontmatter metadata
4. Update internal links to reflect new paths
5. Verify all references are working

## ✅ Validation Requirements

### Pre-Commit Checklist
- [ ] File placed in correct `/docs/category/` directory
- [ ] File name follows lowercase-with-hyphens convention
- [ ] Required frontmatter metadata included
- [ ] Internal links updated to reflect new paths
- [ ] Category index file updated if necessary

### Automated Validation
Run the documentation validation script:
```bash
npm run validate-docs
```

This checks:
- Directory structure compliance
- File naming conventions
- Frontmatter metadata presence
- Internal link integrity

## 🚫 Prohibited Practices

- **NO** `.md` files in repository root (except README.md)
- **NO** documentation in random directories
- **NO** inconsistent naming conventions
- **NO** missing frontmatter metadata
- **NO** broken internal links

## 📞 Enforcement

### For Contributors
- All PRs with documentation changes will be reviewed for compliance
- Non-compliant documentation will require revision before merge
- Validation scripts must pass before approval

### For Maintainers
- Regular audits of documentation structure
- Automated validation in CI/CD pipeline
- Documentation quality gates in review process

## 🎯 Benefits

### Organization Benefits
- **Scalable Structure**: Easy to find and maintain documentation
- **Professional Presentation**: Industry-standard organization
- **Consistent Experience**: Uniform navigation and structure
- **Quality Assurance**: Automated validation prevents issues

### Developer Benefits
- **Clear Guidelines**: No confusion about where to place documentation
- **Easy Navigation**: Logical category-based organization
- **Automated Validation**: Catch issues before they become problems
- **Comprehensive Coverage**: Ensures all aspects are documented

## 📚 Reference

For complete documentation standards and detailed guidelines, see:
- **[Documentation Standards](docs/DOCUMENTATION_STANDARDS.md)** - Comprehensive formatting and content guidelines
- **[Documentation Organization Summary](docs/DOCUMENTATION_ORGANIZATION_SUMMARY.md)** - Overview of current structure

---

**This guideline is enforced as a HIGH PRIORITY repository rule and must be followed by all contributors.**

**Last Updated**: 2025-07-12  
**Version**: 1.0.0  
**Status**: ACTIVE - MANDATORY COMPLIANCE
