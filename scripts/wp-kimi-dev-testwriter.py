#!/usr/bin/env python3
"""
WordPress Plugin Test Writer Script using Kimi-Dev
Specialized for BlazeWooless WordPress plugin test generation
"""

import argparse
import json
import logging
import os
import sys
from pathlib import Path
from typing import Dict, List, Optional, Any
import re

# Add the scripts directory to the Python path
sys.path.insert(0, str(Path(__file__).parent))

from augment_kimi_integration import AugmentKimiIntegration, SecurityError, APIError, ValidationError

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# WordPress test types
TEST_TYPES = {
    'unit': 'WordPress Unit Tests using WP_UnitTestCase',
    'integration': 'WordPress Integration Tests with core',
    'admin': 'WordPress Admin Interface Tests',
    'frontend': 'WordPress Frontend Tests',
    'security': 'WordPress Security Tests',
    'performance': 'WordPress Performance Tests',
    'multisite': 'WordPress Multisite Compatibility Tests'
}


class WordPressTestWriter(AugmentKimiIntegration):
    """Specialized test writer for WordPress plugins"""
    
    def __init__(self, kimi_endpoint: str = "http://localhost:8000", plugin_context: str = "blazewooless"):
        super().__init__(kimi_endpoint)
        self.plugin_context = plugin_context
        logger.info(f"Initialized WordPress test writer for plugin: {plugin_context}")
    
    def generate_test_prompt(self, file_path: str, test_type: str, file_content: str) -> str:
        """Generate WordPress-specific test prompt"""
        test_description = TEST_TYPES.get(test_type, 'WordPress Unit Tests')
        
        test_prompt = f"""
# WordPress Plugin Test Generation - {self.plugin_context}

## Context
- Plugin: {self.plugin_context} (BlazeWooless)
- Source File: {file_path}
- Test Type: {test_description}

## Source Code to Test
```php
{file_content}
```

## WordPress Test Requirements

### For {test_type.upper()} Tests:
"""
        
        if test_type == 'unit':
            test_prompt += """
- Extend WP_UnitTestCase
- Test individual functions and methods
- Mock WordPress functions where needed
- Test edge cases and error conditions
- Verify proper return values and side effects
- Test WordPress hook/filter integration
"""
        elif test_type == 'integration':
            test_prompt += """
- Test plugin interaction with WordPress core
- Test database operations with WordPress
- Test plugin activation/deactivation
- Test WordPress API integrations
- Test with real WordPress environment
"""
        elif test_type == 'admin':
            test_prompt += """
- Test WordPress admin interface
- Test admin menu registration
- Test settings page functionality
- Test admin AJAX handlers
- Test admin scripts and styles
- Test user capability checks
"""
        elif test_type == 'frontend':
            test_prompt += """
- Test frontend output and rendering
- Test shortcodes and blocks
- Test frontend AJAX handlers
- Test frontend scripts and styles
- Test user-facing functionality
"""
        elif test_type == 'security':
            test_prompt += """
- Test input sanitization
- Test output escaping
- Test nonce verification
- Test capability checks
- Test SQL injection prevention
- Test XSS prevention
- Test CSRF protection
"""
        elif test_type == 'performance':
            test_prompt += """
- Test database query performance
- Test caching mechanisms
- Test memory usage
- Test execution time
- Test with large datasets
"""
        elif test_type == 'multisite':
            test_prompt += """
- Test multisite compatibility
- Test network admin features
- Test blog switching
- Test network-wide vs site-specific features
"""
        
        test_prompt += f"""

## WordPress Testing Standards
1. **Test Class Structure**:
   - Extend WP_UnitTestCase or WP_UnitTestCase_Base
   - Use proper setUp() and tearDown() methods
   - Follow WordPress test naming conventions

2. **WordPress Test Utilities**:
   - Use WordPress factory for test data
   - Use WordPress assertions where applicable
   - Mock WordPress functions properly
   - Clean up after tests

3. **Test Coverage**:
   - Test all public methods
   - Test WordPress hooks and filters
   - Test error conditions
   - Test edge cases
   - Test user permissions

4. **WordPress-Specific Assertions**:
   - Use WordPress-specific assertions
   - Test WordPress database operations
   - Test WordPress option handling
   - Test WordPress user and role management

## Expected Output
Generate a complete WordPress test file with:
- Proper class structure extending WP_UnitTestCase
- Comprehensive test methods
- WordPress-specific test utilities
- Proper test data setup and cleanup
- Comments explaining test purpose
- PHPDoc blocks for all methods

Return the complete test file content ready for WordPress testing framework.
"""
        
        return test_prompt
    
    def analyze_testable_code(self, file_path: str) -> Dict[str, Any]:
        """Analyze PHP file to identify testable components"""
        try:
            validated_path = self._validate_file_path(file_path)
            
            if not validated_path.exists():
                return {"error": f"File not found: {file_path}"}
            
            with open(validated_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
            
            analysis = {
                "file_path": str(validated_path),
                "classes": [],
                "functions": [],
                "methods": [],
                "hooks": [],
                "filters": [],
                "wordpress_features": []
            }
            
            # Find classes
            class_pattern = r'class\s+(\w+).*?{'
            classes = re.findall(class_pattern, content, re.IGNORECASE)
            analysis["classes"] = classes
            
            # Find functions
            function_pattern = r'function\s+(\w+)\s*\('
            functions = re.findall(function_pattern, content, re.IGNORECASE)
            analysis["functions"] = functions
            
            # Find methods
            method_pattern = r'(?:public|private|protected)\s+function\s+(\w+)\s*\('
            methods = re.findall(method_pattern, content, re.IGNORECASE)
            analysis["methods"] = methods
            
            # Find WordPress hooks
            hook_pattern = r'add_action\s*\(\s*[\'"]([^\'\"]+)[\'"]'
            hooks = re.findall(hook_pattern, content, re.IGNORECASE)
            analysis["hooks"] = hooks
            
            # Find WordPress filters
            filter_pattern = r'add_filter\s*\(\s*[\'"]([^\'\"]+)[\'"]'
            filters = re.findall(filter_pattern, content, re.IGNORECASE)
            analysis["filters"] = filters
            
            # Identify WordPress features
            wordpress_features = []
            if 'wp_enqueue_script' in content:
                wordpress_features.append('script_enqueuing')
            if 'wp_enqueue_style' in content:
                wordpress_features.append('style_enqueuing')
            if 'register_post_type' in content:
                wordpress_features.append('custom_post_type')
            if 'register_taxonomy' in content:
                wordpress_features.append('custom_taxonomy')
            if 'add_shortcode' in content:
                wordpress_features.append('shortcode')
            if 'wp_ajax_' in content:
                wordpress_features.append('ajax')
            if 'wp_nonce_' in content:
                wordpress_features.append('nonce')
            if 'current_user_can' in content:
                wordpress_features.append('capabilities')
            
            analysis["wordpress_features"] = wordpress_features
            
            return analysis
            
        except Exception as e:
            logger.error(f"Error analyzing file {file_path}: {e}")
            return {"error": str(e)}
    
    def generate_wordpress_test(self, file_path: str, test_type: str) -> Dict[str, Any]:
        """Generate WordPress test file"""
        logger.info(f"Generating {test_type} test for: {file_path}")
        
        try:
            # Analyze the file first
            analysis = self.analyze_testable_code(file_path)
            if "error" in analysis:
                return analysis
            
            # Read file content
            validated_path = self._validate_file_path(file_path)
            with open(validated_path, 'r', encoding='utf-8', errors='ignore') as f:
                file_content = f.read()
            
            # Generate test prompt
            prompt = self.generate_test_prompt(file_path, test_type, file_content)
            
            # Call API for test generation
            if self.config.endpoint:
                try:
                    response = self._call_kimi_api(prompt)
                    
                    # Generate test file path
                    test_file_path = self._generate_test_file_path(file_path, test_type)
                    
                    result = {
                        "source_file": file_path,
                        "test_file": test_file_path,
                        "test_type": test_type,
                        "analysis": analysis,
                        "test_content": response,
                        "success": True,
                        "timestamp": self._get_timestamp()
                    }
                    
                    logger.info(f"Successfully generated {test_type} test for: {file_path}")
                    return result
                    
                except APIError as e:
                    logger.error(f"API error generating test for {file_path}: {e}")
                    return {"error": f"API error: {e}", "success": False}
            else:
                return {
                    "source_file": file_path,
                    "test_type": test_type,
                    "test_content": f"<?php\n// TODO: WordPress {test_type} test for {file_path}\n// Analysis: {json.dumps(analysis, indent=2)}\n",
                    "success": False,
                    "note": "No API endpoint configured"
                }
        
        except Exception as e:
            logger.error(f"Error generating WordPress test for {file_path}: {e}")
            return {"error": str(e), "success": False}
    
    def _generate_test_file_path(self, source_file: str, test_type: str) -> str:
        """Generate appropriate test file path"""
        source_path = Path(source_file)
        base_name = source_path.stem
        
        if test_type == 'unit':
            return f"tests/unit/test-{base_name}.php"
        elif test_type == 'integration':
            return f"tests/integration/test-{base_name}-integration.php"
        elif test_type == 'admin':
            return f"tests/admin/test-{base_name}-admin.php"
        elif test_type == 'frontend':
            return f"tests/frontend/test-{base_name}-frontend.php"
        elif test_type == 'security':
            return f"tests/security/test-{base_name}-security.php"
        elif test_type == 'performance':
            return f"tests/performance/test-{base_name}-performance.php"
        elif test_type == 'multisite':
            return f"tests/multisite/test-{base_name}-multisite.php"
        else:
            return f"tests/test-{base_name}.php"
    
    def _get_timestamp(self) -> str:
        """Get formatted timestamp"""
        import datetime
        return datetime.datetime.now().isoformat()


def main():
    parser = argparse.ArgumentParser(description="WordPress Plugin Test Writer with Kimi-Dev")
    parser.add_argument("--file", required=True, help="WordPress file to test")
    parser.add_argument("--test-type", choices=list(TEST_TYPES.keys()), default="unit", 
                       help="Type of test to generate")
    parser.add_argument("--plugin-context", default="blazewooless", help="Plugin context")
    parser.add_argument("--kimi-endpoint", default="http://localhost:8000", help="Kimi-Dev API endpoint")
    parser.add_argument("--output", help="Output file for results")
    parser.add_argument("--save-test", action="store_true", help="Save generated test to file")
    parser.add_argument("--log-level", choices=['DEBUG', 'INFO', 'WARNING', 'ERROR'],
                       default='INFO', help="Logging level")
    
    args = parser.parse_args()
    
    # Configure logging
    logging.getLogger().setLevel(getattr(logging, args.log_level))
    
    try:
        test_writer = WordPressTestWriter(args.kimi_endpoint, args.plugin_context)
        result = test_writer.generate_wordpress_test(args.file, args.test_type)
        
        # Save test file if requested
        if args.save_test and result.get('success') and 'test_content' in result:
            try:
                test_file_path = Path(result['test_file'])
                test_file_path.parent.mkdir(parents=True, exist_ok=True)
                
                with open(test_file_path, 'w', encoding='utf-8') as f:
                    f.write(result['test_content'])
                logger.info(f"Test file saved to: {test_file_path}")
                result['test_file_saved'] = str(test_file_path)
            except Exception as e:
                logger.error(f"Failed to save test file: {e}")
        
        if args.output:
            try:
                output_path = Path(args.output)
                output_path.parent.mkdir(parents=True, exist_ok=True)
                
                with open(output_path, 'w', encoding='utf-8') as f:
                    json.dump(result, f, indent=2, ensure_ascii=False)
                logger.info(f"Results saved to {args.output}")
            except Exception as e:
                logger.error(f"Failed to save results: {e}")
                sys.exit(1)
        else:
            print(f"\n🧪 WordPress {args.test_type.title()} Test Results:")
            print(json.dumps(result, indent=2, ensure_ascii=False))
        
        sys.exit(0 if result.get('success', False) else 1)
    
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()