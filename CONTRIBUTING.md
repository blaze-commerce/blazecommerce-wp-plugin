# Contributing to Blaze Commerce WordPress Plugin

Thank you for your interest in contributing to the Blaze Commerce WordPress Plugin! This document provides guidelines for contributing to the project.

## Getting Started

### Prerequisites
- WordPress development environment
- PHP 7.4 or higher
- Node.js and npm (for block development)
- Git for version control

### Development Setup
1. Fork the repository
2. Clone your fork locally
3. Install dependencies: `npm install`
4. Set up a local WordPress development environment
5. Install and activate the plugin

## Development Workflow

### Making Changes
1. Create a feature branch from `main`
2. Make your changes following our coding standards
3. Test your changes thoroughly
4. Update documentation as needed
5. Submit a pull request

### Coding Standards
- Follow WordPress PHP Coding Standards
- Use PSR-4 autoloading with `BlazeWooless\` namespace
- Write clear, self-documenting code
- Include appropriate comments and documentation

### Testing
- Test all functionality in a WordPress environment
- Verify WP-CLI commands work correctly
- Test with various WooCommerce configurations
- Include unit tests for new functionality when applicable

## Documentation Standards

**All contributors must follow our documentation standards.** See [Documentation Standards](docs/DOCUMENTATION_STANDARDS.md) for complete guidelines.

### Key Documentation Requirements

#### When Adding New Features
1. **Create feature documentation** in `docs/features/`
2. **Update API documentation** if applicable in `docs/api/`
3. **Include setup instructions** in relevant setup guides
4. **Add troubleshooting information** in `docs/troubleshooting/`

#### When Modifying Existing Features
1. **Update existing documentation** to reflect changes
2. **Verify all links and examples** still work
3. **Update version numbers** and last_modified dates
4. **Test all code examples** in documentation

#### Documentation File Requirements
- Include proper frontmatter metadata
- Follow naming conventions (lowercase, hyphens)
- Place files in appropriate category directories
- Use clear, descriptive titles and content
- Include working code examples
- Provide troubleshooting information

### Documentation Organization
```
docs/
├── DOCUMENTATION_STANDARDS.md
├── features/           # Feature guides and user documentation
├── api/               # API references and technical docs
├── development/       # Developer workflows and automation
├── setup/            # Installation and configuration
├── reference/        # Changelog, legal, and reference materials
└── troubleshooting/  # Problem-solving guides
```

## Pull Request Guidelines

### Before Submitting
- [ ] Code follows WordPress coding standards
- [ ] All tests pass
- [ ] Documentation is updated and follows standards
- [ ] Commit messages follow conventional commit format
- [ ] Changes are tested in WordPress environment

### Pull Request Requirements
1. **Clear description** of changes and motivation
2. **Updated documentation** for any new or changed functionality
3. **Test instructions** for reviewers
4. **Screenshots or examples** for UI changes
5. **Breaking change notes** if applicable

### Commit Message Format
Use conventional commit messages for automatic version bumping:

```
feat: add new product filter functionality
fix: resolve issue with cart synchronization
docs: update installation guide
feat!: redesign API endpoints (breaking change)
```

## Documentation Validation

### Automated Checks
Our CI/CD pipeline validates:
- Frontmatter metadata completeness
- Internal link integrity
- File naming conventions
- Directory structure compliance
- Markdown syntax

### Manual Review
- All documentation changes require peer review
- Technical accuracy verification
- Writing quality and clarity
- Consistency with existing documentation

## Types of Contributions

### Code Contributions
- Bug fixes
- New features
- Performance improvements
- Security enhancements

### Documentation Contributions
- Feature documentation
- API documentation
- Troubleshooting guides
- Setup and configuration guides
- Code examples and tutorials

### Other Contributions
- Bug reports with detailed reproduction steps
- Feature requests with clear use cases
- Testing and quality assurance
- Community support and assistance

## Code Review Process

1. **Automated checks** run on all pull requests
2. **Peer review** by maintainers or experienced contributors
3. **Documentation review** to ensure standards compliance
4. **Testing verification** in various environments
5. **Final approval** and merge by maintainers

## Getting Help

### Resources
- **Documentation**: Browse the `/docs` directory
- **Development Guide**: See `docs/development/`
- **CLI Reference**: Run `wp bc-sync --help`
- **GitHub Issues**: Search existing issues and discussions

### Communication
- **GitHub Issues**: For bug reports and feature requests
- **GitHub Discussions**: For questions and community support
- **Pull Request Comments**: For code-specific discussions

## Recognition

Contributors are recognized in:
- GitHub contributor list
- Release notes for significant contributions
- Documentation credits where appropriate

## License

By contributing to this project, you agree that your contributions will be licensed under the same license as the project.

---

Thank you for contributing to the Blaze Commerce WordPress Plugin! Your contributions help make headless commerce more accessible and powerful for the WordPress community.
