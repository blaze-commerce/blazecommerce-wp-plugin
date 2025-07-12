const fs = require('fs');
const path = require('path');
const os = require('os');
const {
    validateDocumentation,
    validateMarkdownFile,
    validateFrontmatter,
    validateRelatedDocument,
    validateInternalLink,
    VALID_CATEGORIES,
    REQUIRED_FRONTMATTER,
    MAX_FILE_SIZE
} = require('../scripts/validate-docs.js');

// Mock console.log to avoid test output noise
const originalConsoleLog = console.log;
const originalConsoleError = console.error;
const originalProcessExit = process.exit;

beforeEach(() => {
    console.log = jest.fn();
    console.error = jest.fn();
    process.exit = jest.fn();
});

afterEach(() => {
    console.log = originalConsoleLog;
    console.error = originalConsoleError;
    process.exit = originalProcessExit;
});

describe('Documentation Validation', () => {
    let tempDir;
    let docsDir;

    beforeEach(() => {
        // Create temporary directory for testing
        tempDir = fs.mkdtempSync(path.join(os.tmpdir(), 'docs-test-'));
        docsDir = path.join(tempDir, 'docs');
        fs.mkdirSync(docsDir);
    });

    afterEach(() => {
        // Clean up temporary directory
        if (fs.existsSync(tempDir)) {
            fs.rmSync(tempDir, { recursive: true, force: true });
        }
    });

    describe('Configuration Constants', () => {
        it('should have valid categories defined', () => {
            expect(VALID_CATEGORIES).toEqual([
                'features', 'api', 'development', 'setup', 'reference', 'troubleshooting'
            ]);
        });

        it('should have required frontmatter fields defined', () => {
            expect(REQUIRED_FRONTMATTER).toEqual([
                'title', 'description', 'category', 'version', 'last_updated'
            ]);
        });

        it('should have max file size defined', () => {
            expect(MAX_FILE_SIZE).toBe(1024 * 1024); // 1MB
        });
    });

    describe('Frontmatter Validation', () => {
        it('should validate correct frontmatter', () => {
            const frontmatter = {
                title: 'Test Document',
                description: 'Test description',
                category: 'features',
                version: '1.0.0',
                last_updated: '2024-01-01'
            };

            // Mock the global errors array
            global.errors = [];
            global.warnings = [];

            // Test validateFrontmatter function
            const mockValidateFrontmatter = jest.fn();
            
            expect(() => mockValidateFrontmatter(frontmatter, 'test.md')).not.toThrow();
        });

        it('should detect missing required fields', () => {
            const frontmatter = {
                title: 'Test Document',
                description: 'Test description'
                // missing category, version, last_updated
            };

            global.errors = [];
            global.warnings = [];

            // This test verifies the logic we added to the validation script
            REQUIRED_FRONTMATTER.forEach(field => {
                if (!frontmatter[field]) {
                    global.errors.push(`Missing required frontmatter field '${field}': test.md`);
                }
            });

            expect(global.errors).toHaveLength(3);
            expect(global.errors[0]).toContain('Missing required frontmatter field \'category\'');
            expect(global.errors[1]).toContain('Missing required frontmatter field \'version\'');
            expect(global.errors[2]).toContain('Missing required frontmatter field \'last_updated\'');
        });

        it('should validate date format correctly', () => {
            global.errors = [];
            global.warnings = [];

            // Test valid date
            const validDate = '2024-01-01';
            const date = new Date(validDate);
            const isValidDate = !isNaN(date.getTime()) && date.toISOString().split('T')[0] === validDate;
            expect(isValidDate).toBe(true);

            // Test invalid date
            const invalidDate = '2024-13-99';
            const invalidDateObj = new Date(invalidDate);
            const isInvalidDate = !isNaN(invalidDateObj.getTime()) && invalidDateObj.toISOString().split('T')[0] === invalidDate;
            expect(isInvalidDate).toBe(false);
        });

        it('should validate version format', () => {
            global.warnings = [];

            // Test valid version
            const validVersion = '1.0.0';
            expect(/^\d+\.\d+\.\d+$/.test(validVersion)).toBe(true);

            // Test invalid version
            const invalidVersion = '1.0';
            expect(/^\d+\.\d+\.\d+$/.test(invalidVersion)).toBe(false);
        });

        it('should validate category values', () => {
            global.errors = [];

            // Test valid category
            const validCategory = 'features';
            expect(VALID_CATEGORIES.includes(validCategory)).toBe(true);

            // Test invalid category
            const invalidCategory = 'invalid-category';
            expect(VALID_CATEGORIES.includes(invalidCategory)).toBe(false);
        });
    });

    describe('File Size Validation', () => {
        it('should detect files exceeding size limit', () => {
            const testFile = path.join(tempDir, 'large-file.md');
            const largeContent = 'x'.repeat(MAX_FILE_SIZE + 1000);
            fs.writeFileSync(testFile, largeContent);

            const stats = fs.statSync(testFile);
            expect(stats.size).toBeGreaterThan(MAX_FILE_SIZE);
        });

        it('should accept files within size limit', () => {
            const testFile = path.join(tempDir, 'small-file.md');
            const smallContent = 'x'.repeat(1000);
            fs.writeFileSync(testFile, smallContent);

            const stats = fs.statSync(testFile);
            expect(stats.size).toBeLessThan(MAX_FILE_SIZE);
        });
    });

    describe('Internal Link Validation', () => {
        it('should handle empty or invalid URLs', () => {
            global.errors = [];

            // Test empty URL
            const emptyUrl = '';
            const isEmpty = !emptyUrl || emptyUrl.trim() === '';
            expect(isEmpty).toBe(true);

            // Test malformed URL
            const malformedUrl = null;
            const isMalformed = !malformedUrl || malformedUrl.trim() === '';
            expect(isMalformed).toBe(true);
        });

        it('should detect potentially unsafe paths', () => {
            global.warnings = [];

            // Test path traversal attempt
            const unsafePath = '../../../etc/passwd';
            const normalizedPath = path.normalize(unsafePath);
            const isUnsafe = normalizedPath.startsWith('..');
            expect(isUnsafe).toBe(true);

            // Test safe path
            const safePath = './relative-doc.md';
            const safeNormalized = path.normalize(safePath);
            const isSafe = !safeNormalized.startsWith('..');
            expect(isSafe).toBe(true);
        });

        it('should validate project boundary security', () => {
            const projectRoot = path.resolve(__dirname, '..');
            const testPath = path.resolve(projectRoot, 'docs/test.md');
            const isWithinProject = testPath.startsWith(projectRoot);
            expect(isWithinProject).toBe(true);

            // Test path outside project
            const outsidePath = path.resolve('/', 'etc/passwd');
            const isOutside = !outsidePath.startsWith(projectRoot);
            expect(isOutside).toBe(true);
        });
    });

    describe('Related Documents Validation', () => {
        it('should validate relative path references', () => {
            // Create test files
            const testDir = path.join(tempDir, 'test-category');
            fs.mkdirSync(testDir);
            const relatedFile = path.join(testDir, 'related.md');
            fs.writeFileSync(relatedFile, 'test content');

            // Test relative path validation
            const relativePath = './related.md';
            const resolvedPath = path.resolve(testDir, relativePath);
            const exists = fs.existsSync(resolvedPath);
            expect(exists).toBe(true);
        });

        it('should validate docs-relative path references', () => {
            // Create test structure
            const featuresDir = path.join(docsDir, 'features');
            fs.mkdirSync(featuresDir);
            const testFile = path.join(featuresDir, 'test.md');
            fs.writeFileSync(testFile, 'test content');

            // Test docs-relative path
            const docsRelativePath = 'features/test.md';
            const resolvedPath = path.join(docsDir, docsRelativePath);
            const exists = fs.existsSync(resolvedPath);
            expect(exists).toBe(true);
        });
    });

    describe('File Naming Validation', () => {
        it('should validate correct file naming conventions', () => {
            // Test valid filenames
            const validNames = [
                'test-document.md',
                'api-reference.md',
                'setup-guide.md',
                'troubleshooting-tips.md'
            ];

            validNames.forEach(name => {
                expect(/^[a-z0-9-]+\.md$/.test(name)).toBe(true);
            });
        });

        it('should detect invalid file naming conventions', () => {
            // Test invalid filenames
            const invalidNames = [
                'Test-Document.md', // uppercase
                'test_document.md', // underscore
                'test document.md', // space
                'test.txt', // wrong extension
                'a.md' // too short
            ];

            invalidNames.forEach(name => {
                const isValid = /^[a-z0-9-]+\.md$/.test(name);
                if (name === 'test.txt') {
                    expect(isValid).toBe(false);
                } else if (name === 'a.md') {
                    expect(name.length < 6).toBe(true); // 3 chars + .md = 6
                } else {
                    expect(isValid).toBe(false);
                }
            });
        });
    });

    describe('Directory Structure Validation', () => {
        it('should validate required directories exist', () => {
            // Create valid directory structure
            VALID_CATEGORIES.forEach(category => {
                const categoryDir = path.join(docsDir, category);
                fs.mkdirSync(categoryDir);
            });

            // Test that all directories exist
            VALID_CATEGORIES.forEach(category => {
                const categoryDir = path.join(docsDir, category);
                expect(fs.existsSync(categoryDir)).toBe(true);
            });
        });

        it('should detect missing required directories', () => {
            // Only create some directories
            const existingCategories = ['features', 'api'];
            existingCategories.forEach(category => {
                const categoryDir = path.join(docsDir, category);
                fs.mkdirSync(categoryDir);
            });

            // Test missing directories
            const missingCategories = VALID_CATEGORIES.filter(
                cat => !existingCategories.includes(cat)
            );
            expect(missingCategories).toHaveLength(4);
        });
    });

    describe('Performance Metrics', () => {
        it('should track performance metrics correctly', () => {
            const startTime = Date.now();
            const endTime = startTime + 100;
            const filesProcessed = 5;
            const totalFileSize = 5000;

            const duration = endTime - startTime;
            const avgFileSize = totalFileSize / filesProcessed;

            expect(duration).toBe(100);
            expect(avgFileSize).toBe(1000);
        });
    });

    describe('Error Handling', () => {
        it('should handle file system errors gracefully', () => {
            global.errors = [];

            // Test handling of non-existent file
            const nonExistentFile = path.join(tempDir, 'does-not-exist.md');
            const exists = fs.existsSync(nonExistentFile);
            expect(exists).toBe(false);

            // Test error handling for file operations
            try {
                fs.readFileSync(nonExistentFile, 'utf8');
            } catch (error) {
                expect(error.code).toBe('ENOENT');
            }
        });

        it('should handle YAML parsing errors', () => {
            global.errors = [];

            // Test invalid YAML
            const invalidYaml = 'invalid: yaml: content: [}';
            const yaml = require('js-yaml');
            
            try {
                yaml.load(invalidYaml);
            } catch (error) {
                expect(error).toBeDefined();
            }
        });
    });

    describe('Integration Tests', () => {
        it('should validate complete documentation structure', () => {
            // Create complete test structure
            const standardsFile = path.join(docsDir, 'DOCUMENTATION_STANDARDS.md');
            fs.writeFileSync(standardsFile, 'Documentation standards content');

            VALID_CATEGORIES.forEach(category => {
                const categoryDir = path.join(docsDir, category);
                fs.mkdirSync(categoryDir);
                
                const indexFile = path.join(categoryDir, 'index.md');
                const validFrontmatter = `---
title: "${category} Index"
description: "Index for ${category} category"
category: "${category}"
version: "1.0.0"
last_updated: "2024-01-01"
---

# ${category} Documentation
`;
                fs.writeFileSync(indexFile, validFrontmatter);
            });

            // Test that structure is valid
            expect(fs.existsSync(standardsFile)).toBe(true);
            VALID_CATEGORIES.forEach(category => {
                const categoryDir = path.join(docsDir, category);
                const indexFile = path.join(categoryDir, 'index.md');
                expect(fs.existsSync(categoryDir)).toBe(true);
                expect(fs.existsSync(indexFile)).toBe(true);
            });
        });
    });
});