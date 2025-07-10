# Documentation Standards

This document outlines the organization structure, guidelines, and standards for all documentation in the Blaze Commerce WordPress Plugin project.

## Folder Structure

The `/docs` directory is organized into the following categories:

### `/docs/features/`
**Purpose**: Documentation for specific plugin features and functionality
**Content**: Feature guides, user manuals, configuration instructions
**Examples**: 
- Country-specific images feature
- Export/import settings
- Product filters
- Payment integrations

### `/docs/api/`
**Purpose**: Technical API documentation and integration guides
**Content**: API references, technical specifications, integration guides
**Examples**:
- Typesense collection aliasing
- GraphQL API documentation
- REST API endpoints
- Webhook configurations

### `/docs/development/`
**Purpose**: Developer workflows, automation, and development guidelines
**Content**: Development setup, automation scripts, coding standards, AI assistance
**Examples**:
- Automation workflows
- Daily usage guides
- Claude AI guidance
- Build processes

### `/docs/setup/`
**Purpose**: Installation, configuration, and getting started guides
**Content**: Installation instructions, initial setup, configuration guides
**Examples**:
- Plugin installation
- Initial configuration
- Environment setup
- Requirements and dependencies

### `/docs/reference/`
**Purpose**: Reference materials, changelogs, and legal documents
**Content**: Version history, legal notices, glossaries, appendices
**Examples**:
- Changelog
- Trademark information
- License details
- Version compatibility matrix

### `/docs/troubleshooting/`
**Purpose**: Problem-solving guides and common issues
**Content**: Error resolution, debugging guides, FAQ, known issues
**Examples**:
- Common installation issues
- Performance troubleshooting
- Error code references
- Debug procedures

## File Naming Conventions

### General Rules
- Use lowercase letters and hyphens for file names
- Be descriptive and specific
- Include version numbers when applicable
- Use `.md` extension for all documentation files

### Naming Patterns
- **Features**: `feature-name.md` (e.g., `country-specific-images.md`)
- **API Docs**: `api-component-name.md` (e.g., `typesense-aliases-api.md`)
- **Setup Guides**: `setup-component.md` (e.g., `setup-installation.md`)
- **Troubleshooting**: `troubleshooting-topic.md` (e.g., `troubleshooting-sync-issues.md`)

## Required Metadata/Frontmatter

All documentation files should include the following frontmatter at the top:

```yaml
---
title: "Document Title"
description: "Brief description of the document content"
category: "features|api|development|setup|reference|troubleshooting"
version: "1.0.0"
last_updated: "YYYY-MM-DD"
author: "Author Name"
tags: ["tag1", "tag2", "tag3"]
related_docs: ["related-doc-1.md", "related-doc-2.md"]
---
```

### Metadata Fields Explained
- **title**: Clear, descriptive title for the document
- **description**: 1-2 sentence summary of the document's purpose
- **category**: One of the six main categories
- **version**: Document version (semantic versioning)
- **last_updated**: Date of last significant update (YYYY-MM-DD format)
- **author**: Primary author or maintainer
- **tags**: Relevant keywords for searchability
- **related_docs**: Links to related documentation files

## Content Structure Guidelines

### Standard Document Structure
1. **Title** (H1)
2. **Overview/Description** - Brief summary of the topic
3. **Table of Contents** (for longer documents)
4. **Prerequisites/Requirements** (if applicable)
5. **Main Content** - Organized with clear headings
6. **Examples** - Code samples, screenshots, or use cases
7. **Troubleshooting** - Common issues and solutions
8. **Related Resources** - Links to related documentation
9. **Changelog** - Document version history (for major docs)

### Writing Style Guidelines
- Use clear, concise language
- Write in active voice when possible
- Include code examples with proper syntax highlighting
- Use numbered lists for sequential steps
- Use bullet points for non-sequential items
- Include screenshots or diagrams when helpful
- Provide both beginner and advanced information when relevant

### Code Documentation Standards
- Use proper markdown code blocks with language specification
- Include complete, working examples
- Provide context and explanation for code snippets
- Use consistent indentation and formatting
- Include error handling examples where appropriate

## Link Management

### Internal Links
- Use relative paths for internal documentation links
- Format: `[Link Text](../category/document-name.md)`
- Always verify links after moving or renaming files
- Use descriptive link text (avoid "click here")

### External Links
- Use absolute URLs for external resources
- Include link descriptions in parentheses when helpful
- Regularly audit external links for validity

## Image and Asset Guidelines

### Image Storage
- Store images in the same directory as the documentation file
- Use descriptive filenames: `feature-name-screenshot.png`
- Optimize images for web (reasonable file sizes)
- Use PNG for screenshots, JPG for photos, SVG for diagrams

### Image References
- Use relative paths: `![Alt Text](./image-name.png)`
- Always include descriptive alt text
- Provide captions for complex images

## Documentation Maintenance Process

### When Making Code Changes
1. **Identify affected documentation** - Review which docs need updates
2. **Update relevant files** - Modify documentation to reflect changes
3. **Update metadata** - Increment version and update last_modified date
4. **Test links and examples** - Verify all links and code examples work
5. **Review for accuracy** - Ensure technical accuracy of all content

### Regular Maintenance Tasks
- **Monthly**: Review and update external links
- **Quarterly**: Audit document structure and organization
- **Per Release**: Update version references and compatibility information
- **As Needed**: Reorganize content based on user feedback

## Quality Checklist

Before publishing or updating documentation, verify:

- [ ] Proper frontmatter metadata is included
- [ ] File is in the correct category directory
- [ ] File naming follows conventions
- [ ] All internal links work correctly
- [ ] Code examples are tested and functional
- [ ] Images are optimized and have alt text
- [ ] Content follows the standard structure
- [ ] Writing is clear and grammatically correct
- [ ] Technical information is accurate and up-to-date

## Validation and Enforcement

### Automated Checks (CI/CD)
The following automated checks should be implemented:
- Frontmatter validation
- Link checking (internal and external)
- File naming convention compliance
- Directory structure validation
- Markdown syntax validation

### Manual Review Process
- All new documentation requires peer review
- Major updates should be reviewed by subject matter experts
- Regular audits of documentation accuracy and relevance

## Contributing to Documentation

### For Developers
- Update documentation as part of feature development
- Include documentation changes in pull requests
- Follow the established standards and guidelines
- Test all examples and links before submitting

### For Technical Writers
- Coordinate with development team for technical accuracy
- Maintain consistency across all documentation
- Regularly review and improve existing content
- Gather user feedback and incorporate improvements

---

**Last Updated**: 2025-01-09
**Version**: 1.0.0
**Maintained By**: Blaze Commerce Team
