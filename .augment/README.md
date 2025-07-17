# .augment Directory - AI Context Documentation

This directory contains copies of key documentation files from the main `/docs` directory, specifically maintained to provide better context for AI assistants working with this codebase.

## Purpose

The `.augment` directory serves as a centralized location for AI assistants to access the most important documentation without needing to navigate the full documentation structure. This improves AI understanding of the project architecture, standards, and workflows.

## Important Notice

⚠️ **DO NOT EDIT FILES IN THIS DIRECTORY DIRECTLY**

🚨 **CRITICAL: Rule File Standards**
- **MANDATORY**: All rule files in .augment/rules/ MUST use .yaml format ONLY
- **PROHIBITED**: Creating .md files in any .augment/rules/ directory
- **REFERENCE**: See `.augment/rules/MANDATORY-YAML-STANDARDS.yaml` for requirements

These files are **copies** of the original documentation maintained in the `/docs` directory. Any changes should be made to the original files in `/docs`, and then the copies in `.augment` should be updated accordingly.

## Files Included

### Core Documentation Standards
- **`DOCUMENTATION_STANDARDS.md`** - Complete documentation guidelines and standards
- **`DOCUMENTATION_ORGANIZATION_SUMMARY.md`** - Overview of documentation reorganization project

### Category Index Files
- **`features-index.md`** - Features documentation overview (copy of `/docs/features/index.md`)
- **`api-index.md`** - API documentation overview (copy of `/docs/api/index.md`)
- **`development-index.md`** - Development workflows overview (copy of `/docs/development/index.md`)
- **`setup-index.md`** - Setup and installation overview (copy of `/docs/setup/index.md`)
- **`reference-index.md`** - Reference materials overview (copy of `/docs/reference/index.md`)
- **`troubleshooting-index.md`** - Troubleshooting overview (copy of `/docs/troubleshooting/index.md`)

## Path References

All relative path references in the copied files have been updated to use absolute paths from the project root (e.g., `docs/features/` instead of `../features/`) to ensure proper linking when accessed from the `.augment` directory.

## Maintenance

### When to Update
Update the files in this directory when:
- Original documentation in `/docs` is modified
- New major documentation is added that should be included for AI context
- Documentation structure changes significantly
- Standards or guidelines are updated

### How to Update
1. **Make changes to original files** in the `/docs` directory first
2. **Copy updated files** to `.augment` directory
3. **Update path references** from relative to absolute paths
4. **Test that all links work** from the `.augment` context
5. **Update this README** if the file list changes

### Automated Sync (Future Enhancement)
Consider implementing automated synchronization between `/docs` and `.augment` directories to ensure consistency and reduce maintenance overhead.

## AI Assistant Guidelines

### For AI Assistants Using This Directory
1. **Use these files for context** about project structure and standards
2. **Reference original files** in `/docs` when providing specific guidance
3. **Understand the project architecture** through the index files
4. **Follow the documentation standards** outlined in `DOCUMENTATION_STANDARDS.md`
5. **Consider the organization summary** for understanding recent changes

### Key Information for AI Context
- **Project Type**: WordPress plugin for headless commerce
- **Documentation Structure**: Category-based organization with 6 main categories
- **Standards**: Comprehensive documentation standards with automated validation
- **Architecture**: Features, API, development workflows, setup, reference, troubleshooting
- **Validation**: Automated CI/CD validation for documentation quality

## File Relationships

```
.augment/
├── README.md (this file)
├── DOCUMENTATION_STANDARDS.md → docs/DOCUMENTATION_STANDARDS.md
├── DOCUMENTATION_ORGANIZATION_SUMMARY.md → docs/DOCUMENTATION_ORGANIZATION_SUMMARY.md
├── features-index.md → docs/features/index.md
├── api-index.md → docs/api/index.md
├── development-index.md → docs/development/index.md
├── setup-index.md → docs/setup/index.md
├── reference-index.md → docs/reference/index.md
└── troubleshooting-index.md → docs/troubleshooting/index.md
```

## Benefits for AI Assistants

### Improved Context Understanding
- **Project Overview**: Complete understanding of project structure and purpose
- **Documentation Standards**: Clear guidelines for maintaining documentation quality
- **Category Organization**: Understanding of how documentation is organized
- **Workflow Knowledge**: Insight into development and contribution processes

### Better Assistance Quality
- **Accurate Guidance**: AI can provide more accurate guidance based on project standards
- **Consistent Recommendations**: Recommendations align with established patterns
- **Proper File Placement**: Understanding of where new documentation should be placed
- **Standards Compliance**: Ability to ensure contributions follow established standards

### Efficient Access
- **Centralized Location**: All key documentation accessible from one directory
- **Reduced Navigation**: No need to traverse complex directory structures
- **Quick Reference**: Fast access to most important project information
- **Context Preservation**: Maintains context across different AI interactions

## Version Information

- **Created**: January 9, 2025
- **Last Updated**: January 9, 2025
- **Source Documentation Version**: 1.0.0
- **Maintenance Status**: Manual (consider automation for future)

## Related Resources

- **Main Documentation**: `/docs` directory
- **Documentation Standards**: `/docs/DOCUMENTATION_STANDARDS.md`
- **Contributing Guidelines**: `/CONTRIBUTING.md`
- **Project README**: `/README.md`

---

**Note**: This directory is specifically designed to enhance AI assistant capabilities when working with the Blaze Commerce WordPress Plugin codebase. It represents a snapshot of key documentation at the time of creation and should be kept synchronized with the main documentation as the project evolves.
