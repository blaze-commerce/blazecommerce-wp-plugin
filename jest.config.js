module.exports = {
  // Test environment
  testEnvironment: 'node',
  
  // Test file patterns
  testMatch: [
    '<rootDir>/test/**/*.test.js',
    '<rootDir>/tests/**/*.test.js'
  ],
  
  // Coverage configuration
  collectCoverage: true,
  coverageDirectory: 'coverage',
  coverageReporters: ['text', 'lcov', 'html'],
  
  // Coverage thresholds
  coverageThreshold: {
    global: {
      branches: 70,
      functions: 70,
      lines: 70,
      statements: 70
    },
    // Higher threshold for validation script
    './scripts/validate-docs.js': {
      branches: 80,
      functions: 80,
      lines: 80,
      statements: 80
    }
  },
  
  // Files to collect coverage from
  collectCoverageFrom: [
    'scripts/**/*.js',
    '!scripts/**/node_modules/**',
    '!**/coverage/**',
    '!**/dist/**'
  ],
  
  // Setup files
  setupFilesAfterEnv: [],
  
  // Test timeout
  testTimeout: 10000,
  
  // Verbose output
  verbose: true
};