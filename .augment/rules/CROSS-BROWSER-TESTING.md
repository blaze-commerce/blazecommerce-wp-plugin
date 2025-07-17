---
type: "always_apply"
priority: 1
scope: "all_repositories"
---

# Cross-Browser Testing Requirements for Next.js Applications

## Mandatory Testing Protocol
- **ALWAYS** test Next.js sites across ALL major browsers before deployment
- **REQUIRED BROWSERS**: Chrome, Firefox, Safari, Edge
- **TESTING TOOLS**: Use Playwright for automated cross-browser testing
- **SCOPE**: Both local development testing and production link verification
- **FOCUS AREAS**: 
  - UI rendering consistency across browsers
  - UX functionality verification
  - Performance validation
  - Responsive design compliance
- **EXECUTION**: Implement unit tests and integration tests for comprehensive coverage
- **BLOCKING CONDITION**: No deployment without successful cross-browser test completion

## Browser Testing Requirements

### Supported Browsers
- **Chrome**: Latest stable version + 2 previous versions
- **Firefox**: Latest stable version + 2 previous versions
- **Safari**: Latest stable version + 1 previous version
- **Edge**: Latest stable version + 2 previous versions
- **Mobile**: Chrome Mobile, Safari Mobile (iOS), Samsung Internet

### Testing Scope
- **Functional Testing**: All interactive elements work correctly
- **Visual Testing**: UI renders consistently across browsers
- **Performance Testing**: Load times and responsiveness
- **Accessibility Testing**: WCAG compliance across browsers
- **Responsive Testing**: Mobile and desktop layouts

## Automated Testing Implementation

### Playwright Configuration
```javascript
// playwright.config.js
module.exports = {
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
    { name: 'webkit', use: { ...devices['Desktop Safari'] } },
    { name: 'edge', use: { ...devices['Desktop Edge'] } },
    { name: 'mobile-chrome', use: { ...devices['Pixel 5'] } },
    { name: 'mobile-safari', use: { ...devices['iPhone 12'] } },
  ],
};
```

### Test Categories
- **Smoke Tests**: Critical functionality across all browsers
- **Regression Tests**: Previously identified browser-specific issues
- **Visual Tests**: Screenshot comparison across browsers
- **Performance Tests**: Core Web Vitals measurement
- **Accessibility Tests**: Screen reader and keyboard navigation

## Manual Testing Protocol

### Pre-Deployment Checklist
- [ ] **Homepage**: Loads correctly in all browsers
- [ ] **Navigation**: Menu and links work properly
- [ ] **Forms**: Contact forms, search, checkout process
- [ ] **Interactive Elements**: Buttons, dropdowns, modals
- [ ] **Media**: Images, videos load and display correctly
- [ ] **Responsive**: Mobile and tablet layouts work
- [ ] **Performance**: Page load times acceptable

### Browser-Specific Considerations
- **Safari**: WebKit rendering differences, iOS-specific issues
- **Firefox**: Gecko engine compatibility, privacy features
- **Edge**: Chromium-based compatibility, legacy Edge issues
- **Chrome**: V8 engine features, experimental APIs

## Performance Testing

### Core Web Vitals
- **LCP (Largest Contentful Paint)**: < 2.5s across all browsers
- **FID (First Input Delay)**: < 100ms across all browsers
- **CLS (Cumulative Layout Shift)**: < 0.1 across all browsers

### Browser Performance Monitoring
```javascript
// Performance measurement across browsers
function measurePerformance() {
  const navigation = performance.getEntriesByType('navigation')[0];
  const metrics = {
    loadTime: navigation.loadEventEnd - navigation.loadEventStart,
    domContentLoaded: navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart,
    firstPaint: performance.getEntriesByName('first-paint')[0]?.startTime,
    firstContentfulPaint: performance.getEntriesByName('first-contentful-paint')[0]?.startTime,
  };
  
  // Report metrics for each browser
  console.log('Browser Performance:', metrics);
}
```

## Continuous Integration

### CI/CD Integration
- **Pre-merge**: Run cross-browser tests on pull requests
- **Staging**: Full browser test suite on staging deployment
- **Production**: Smoke tests after production deployment
- **Scheduled**: Daily full test suite execution

### Test Reporting
- **Visual Diffs**: Screenshot comparisons between browsers
- **Performance Reports**: Core Web Vitals across browsers
- **Accessibility Reports**: WCAG compliance status
- **Compatibility Matrix**: Feature support across browsers

## Issue Resolution

### Browser-Specific Fixes
- **Progressive Enhancement**: Ensure basic functionality works everywhere
- **Feature Detection**: Use feature detection instead of browser detection
- **Polyfills**: Include necessary polyfills for older browsers
- **Graceful Degradation**: Provide fallbacks for unsupported features

### Common Issues
- **CSS Grid/Flexbox**: Browser-specific implementations
- **JavaScript APIs**: Feature availability differences
- **Font Rendering**: Typography consistency across browsers
- **Video/Audio**: Codec support variations
- **Touch Events**: Mobile browser differences

## Quality Gates

### Deployment Blocking Conditions
- **Critical Functionality Broken**: Core features don't work in any supported browser
- **Visual Regression**: Significant UI differences between browsers
- **Performance Degradation**: Core Web Vitals fail in any browser
- **Accessibility Failure**: WCAG violations in any browser

### Warning Conditions
- **Minor Visual Differences**: Acceptable variations in rendering
- **Performance Variations**: Small differences in load times
- **Feature Limitations**: Non-critical features unavailable in some browsers

---
**Priority**: ALWAYS | **Scope**: All repositories | **Enforcement**: Automated + Manual
