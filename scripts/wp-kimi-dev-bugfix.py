#!/usr/bin/env python3
"""
WordPress Plugin Bug Fix Script using Kimi-Dev
Specialized for BlazeWooless WordPress plugin development
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

# WordPress-specific constants
WORDPRESS_EXTENSIONS = {'.php', '.js', '.css', '.html', '.vue', '.json'}
WORDPRESS_CORE_FILES = {
    'main': 'blaze-wooless.php',
    'classes': 'app/',
    'templates': 'views/',
    'assets': 'assets/',
    'blocks': 'blocks/',
    'lib': 'lib/',
    'tests': 'tests/'
}


class WordPressBugFixer(AugmentKimiIntegration):
    """Specialized bug fixer for WordPress plugins"""
    
    def __init__(self, kimi_endpoint: str = "http://localhost:8000", plugin_context: str = "blazewooless"):
        super().__init__(kimi_endpoint)
        self.plugin_context = plugin_context
        logger.info(f"Initialized WordPress bug fixer for plugin: {plugin_context}")
    
    def generate_wordpress_prompt(self, file_path: str, issue: str, file_content: str) -> str:
        """Generate WordPress-specific bug fix prompt"""
        wordpress_prompt = f"""
# WordPress Plugin Bug Fix - {self.plugin_context}

## Context
- Plugin: {self.plugin_context} (BlazeWooless)
- File: {file_path}
- Issue: {issue}

## File Content
```php
{file_content}
```

## WordPress Bug Fix Requirements
1. **WordPress Coding Standards (WPCS)** compliance
2. **Security Best Practices**:
   - Input sanitization with sanitize_text_field(), wp_kses(), etc.
   - Output escaping with esc_html(), esc_attr(), esc_url()
   - Nonce verification for forms and AJAX
   - Capability checks with current_user_can()
   - SQL injection prevention with $wpdb->prepare()
3. **WordPress API Usage**:
   - Proper hooks and filters
   - WordPress database abstraction layer
   - WordPress filesystem functions
   - WordPress HTTP API
4. **Error Handling**:
   - Use wp_die() for fatal errors
   - Proper error logging with error_log()
   - Graceful fallbacks
5. **Performance**:
   - Efficient database queries
   - Proper caching strategies
   - Minimize HTTP requests

## Expected Output
- Root cause analysis
- WordPress-compliant code fix
- Security considerations
- Testing recommendations
- Backward compatibility notes

Return the complete fixed file content with all WordPress best practices applied.
"""
        return wordpress_prompt
    
    def analyze_wordpress_file(self, file_path: str) -> Dict[str, Any]:
        """Analyze WordPress file for common issues"""
        try:
            validated_path = self._validate_file_path(file_path)
            
            if not validated_path.exists():
                return {"error": f"File not found: {file_path}"}
            
            with open(validated_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
            
            analysis = {
                "file_path": str(validated_path),
                "file_size": len(content),
                "line_count": content.count('\n') + 1,
                "has_php": content.startswith('<?php'),
                "security_issues": [],
                "wordpress_issues": [],
                "performance_issues": []
            }
            
            # Check for common security issues
            security_patterns = [
                (r'\$_GET\[.*?\](?!\s*\))', "Direct $_GET usage without sanitization"),
                (r'\$_POST\[.*?\](?!\s*\))', "Direct $_POST usage without sanitization"),
                (r'echo\s+\$[^;]+;', "Direct echo without escaping"),
                (r'mysql_.*\(', "Deprecated MySQL functions"),
                (r'eval\s*\(', "Dangerous eval() usage"),
                (r'system\s*\(', "System command execution"),
                (r'exec\s*\(', "Command execution"),
                (r'file_get_contents\s*\(\s*\$', "File access with user input"),
            ]
            
            for pattern, description in security_patterns:
                if re.search(pattern, content, re.IGNORECASE):
                    analysis["security_issues"].append(description)
            
            # Check for WordPress-specific issues
            wordpress_patterns = [
                (r'global\s+\$wpdb', "Uses global $wpdb"),
                (r'wp_enqueue_script', "Enqueues scripts"),
                (r'wp_enqueue_style', "Enqueues styles"),
                (r'add_action\s*\(', "Uses WordPress hooks"),
                (r'add_filter\s*\(', "Uses WordPress filters"),
                (r'wp_nonce_field', "Uses nonces"),
                (r'current_user_can', "Checks capabilities"),
            ]
            
            for pattern, description in wordpress_patterns:
                if re.search(pattern, content, re.IGNORECASE):
                    analysis["wordpress_issues"].append(description)
            
            return analysis
            
        except Exception as e:
            logger.error(f"Error analyzing file {file_path}: {e}")
            return {"error": str(e)}
    
    def fix_wordpress_bug(self, file_path: str, issue: str) -> Dict[str, Any]:
        """Fix WordPress plugin bug with specialized prompts"""
        logger.info(f"Fixing WordPress bug in: {file_path}")
        
        try:
            # Analyze the file first
            analysis = self.analyze_wordpress_file(file_path)
            if "error" in analysis:
                return analysis
            
            # Read file content
            validated_path = self._validate_file_path(file_path)
            with open(validated_path, 'r', encoding='utf-8', errors='ignore') as f:
                file_content = f.read()
            
            # Generate WordPress-specific prompt
            prompt = self.generate_wordpress_prompt(file_path, issue, file_content)
            
            # Call API for fix
            if self.config.endpoint:
                try:
                    response = self._call_kimi_api(prompt)
                    
                    result = {
                        "file_path": file_path,
                        "original_analysis": analysis,
                        "issue": issue,
                        "fixed_content": response,
                        "success": True,
                        "timestamp": self._get_timestamp()
                    }
                    
                    logger.info(f"Successfully generated fix for: {file_path}")
                    return result
                    
                except APIError as e:
                    logger.error(f"API error fixing {file_path}: {e}")
                    return {"error": f"API error: {e}", "success": False}
            else:
                return {
                    "file_path": file_path,
                    "issue": issue,
                    "fixed_content": f"# TODO: WordPress bug fix for {file_path}\n# Issue: {issue}\n# Original content preserved",
                    "success": False,
                    "note": "No API endpoint configured"
                }
        
        except Exception as e:
            logger.error(f"Error fixing WordPress bug in {file_path}: {e}")
            return {"error": str(e), "success": False}
    
    def _get_timestamp(self) -> str:
        """Get formatted timestamp"""
        import datetime
        return datetime.datetime.now().isoformat()


def main():
    parser = argparse.ArgumentParser(description="WordPress Plugin Bug Fix with Kimi-Dev")
    parser.add_argument("--file", required=True, help="WordPress file to fix")
    parser.add_argument("--issue", required=True, help="Bug description")
    parser.add_argument("--plugin-context", default="blazewooless", help="Plugin context")
    parser.add_argument("--kimi-endpoint", default="http://localhost:8000", help="Kimi-Dev API endpoint")
    parser.add_argument("--output", help="Output file for results")
    parser.add_argument("--log-level", choices=['DEBUG', 'INFO', 'WARNING', 'ERROR'],
                       default='INFO', help="Logging level")
    
    args = parser.parse_args()
    
    # Configure logging
    logging.getLogger().setLevel(getattr(logging, args.log_level))
    
    try:
        fixer = WordPressBugFixer(args.kimi_endpoint, args.plugin_context)
        result = fixer.fix_wordpress_bug(args.file, args.issue)
        
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
            print("\n🔧 WordPress Bug Fix Results:")
            print(json.dumps(result, indent=2, ensure_ascii=False))
        
        sys.exit(0 if result.get('success', False) else 1)
    
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()