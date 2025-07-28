---
title: "Features Documentation"
description: "Documentation for specific plugin features and functionality"
category: "features"
version: "1.0.0"
last_updated: "2025-01-09"
author: "Blaze Commerce Team"
tags: ["features", "functionality", "user-guides", "configuration"]
related_docs: ["../setup/installation-and-configuration.md", "../api/index.md"]
---

# Features Documentation

This directory contains documentation for specific features and functionality of the Blaze Commerce WordPress Plugin. Each document provides comprehensive guides for configuring, using, and troubleshooting individual features.

## What Belongs Here

### Feature Documentation Types

- **User Guides**: Step-by-step instructions for using features
- **Configuration Guides**: How to set up and configure features
- **Feature Overviews**: Comprehensive descriptions of feature capabilities
- **Use Cases**: Real-world examples and scenarios
- **Integration Guides**: How features work with other plugins/services

### Content Guidelines

- Focus on end-user functionality rather than technical implementation
- Include screenshots and visual aids where helpful
- Provide both basic and advanced configuration options
- Include troubleshooting sections for common issues
- Link to related API documentation when relevant

## Available Features

### [Country-Specific Images](country-specific-images.md)

Display different product images based on customer location using Aelia Currency Switcher integration.

**Key Topics:**

- Aelia Currency Switcher integration
- Country detection and image selection
- Admin interface configuration
- Typesense data synchronization
- Testing and troubleshooting

### [Export/Import Settings](export-import-feature.md)

Backup and restore all Blaze Commerce plugin settings in JSON format for migration and configuration management.

**Key Topics:**

- Settings backup and restore
- JSON export/import functionality
- Migration between environments
- Security considerations
- Troubleshooting import/export issues

### [Kajal Collection Menu Block](kajal-collection-menu-block.md)

A customizable Gutenberg block for creating collection menus with titles, badges, and menu items.

**Key Topics:**

- Gutenberg block configuration
- Menu item management
- Styling with Tailwind CSS
- Link types (URL and anchor)
- Responsive design
- Accessibility features

## Feature Categories

### Core Features

Features that are part of the base plugin functionality:

- Product synchronization
- Search integration
- GraphQL API
- Settings management

### Extension Features

Features that integrate with third-party plugins:

- Country-specific images (Aelia Currency Switcher)
- Review integrations (JudgeMe, Yotpo)
- Payment gateway integrations
- Shipping method integrations

### Advanced Features

Features for advanced users and developers:

- Custom field synchronization
- Advanced filtering options
- Performance optimization
- Custom hooks and filters

## Documentation Standards for Features

### Required Sections

1. **Overview** - Brief description and purpose
2. **Requirements** - Prerequisites and dependencies
3. **Installation/Setup** - How to enable and configure
4. **Usage** - How to use the feature
5. **Configuration** - Available settings and options
6. **Examples** - Real-world use cases
7. **Troubleshooting** - Common issues and solutions
8. **API Reference** - Related hooks, filters, and functions

### Writing Guidelines

- Start with a clear overview and benefits
- Include step-by-step instructions with screenshots
- Provide both basic and advanced configuration examples
- Include code examples where relevant
- Link to related documentation
- Keep content up-to-date with plugin versions

## Related Documentation

- **[Setup & Installation](../setup/)** - Initial plugin configuration
- **[API Documentation](../api/)** - Technical implementation details
- **[Troubleshooting](../troubleshooting/)** - Problem-solving guides
- **[Development](../development/)** - Developer workflows and tools

## Contributing Feature Documentation

### Adding New Feature Documentation

1. Create a new markdown file following naming conventions
2. Include proper frontmatter metadata
3. Follow the standard document structure
4. Include working examples and screenshots
5. Test all instructions and code examples
6. Update this index file to include the new feature

### Updating Existing Documentation

1. Verify all information is current and accurate
2. Update version numbers and last_modified dates
3. Test all links and code examples
4. Ensure screenshots reflect current UI
5. Update related documentation as needed

### Review Process

- All feature documentation should be reviewed by the feature developer
- Include testing instructions for reviewers
- Verify compatibility with current plugin version
- Ensure consistency with other feature documentation

---

For questions about feature documentation or to request new feature guides, please create an issue in the GitHub repository or contact the Blaze Commerce team.
