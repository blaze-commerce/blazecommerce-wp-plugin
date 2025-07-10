#!/usr/bin/env python3
"""
WordPress Plugin Security Audit Script using Kimi-Dev
Specialized for BlazeWooless WordPress plugin security analysis
"""

import argparse
import json
import logging
import os
import sys
from pathlib import Path
from typing import Dict, List, Optional, Any, Tuple
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

# WordPress security patterns
SECURITY_PATTERNS = {
    'sql_injection': [
        (r'\$wpdb->query\s*\(\s*[\'"].*?\$.*?[\'"]', 'Potential SQL injection in wpdb->query'),
        (r'mysql_query\s*\(', 'Deprecated MySQL function usage'),
        (r'mysqli_query\s*\(.*?\$', 'Direct mysqli query with user input'),
        (r'SELECT\s+.*?FROM\s+.*?\$', 'Raw SQL query with user input'),
    ],
    'xss': [
        (r'echo\s+\$_(GET|POST|REQUEST)\[', 'Direct output of user input'),
        (r'print\s+\$_(GET|POST|REQUEST)\[', 'Direct print of user input'),
        (r'<\?=\s*\$_(GET|POST|REQUEST)\[', 'Direct PHP short echo of user input'),
        (r'echo\s+\$[^;]+;(?!\s*//\s*escaped)', 'Echo without escaping'),
    ],
    'csrf': [
        (r'<form[^>]*method=[\'"]post[\'"][^>]*>(?!.*wp_nonce_field)', 'POST form without nonce'),
        (r'\$_POST\[.*?\](?!.*wp_verify_nonce)', 'POST processing without nonce verification'),
        (r'wp_ajax_(?!nopriv_).*?\(\s*[\'"][^\'\"]*[\'"].*?\);(?!.*wp_verify_nonce)', 'AJAX without nonce'),
    ],
    'file_inclusion': [
        (r'include\s*\(\s*\$', 'Dynamic file inclusion'),
        (r'require\s*\(\s*\$', 'Dynamic file require'),
        (r'include_once\s*\(\s*\$', 'Dynamic file include_once'),
        (r'require_once\s*\(\s*\$', 'Dynamic file require_once'),
    ],
    'command_injection': [
        (r'exec\s*\(\s*\$', 'Command execution with user input'),
        (r'system\s*\(\s*\$', 'System command with user input'),
        (r'shell_exec\s*\(\s*\$', 'Shell execution with user input'),
        (r'passthru\s*\(\s*\$', 'Passthru with user input'),
        (r'popen\s*\(\s*\$', 'Popen with user input'),
    ],
    'file_upload': [
        (r'\$_FILES\[.*?\]\[[\'"]tmp_name[\'"]', 'File upload handling'),
        (r'move_uploaded_file\s*\(', 'File move without validation'),
        (r'copy\s*\(\s*\$_FILES', 'File copy without validation'),
    ],
    'authentication': [
        (r'current_user_can\s*\(\s*[\'"].*?[\'"]', 'Capability check'),
        (r'wp_verify_nonce\s*\(', 'Nonce verification'),
        (r'is_user_logged_in\s*\(', 'Login check'),
        (r'wp_get_current_user\s*\(', 'Current user retrieval'),
    ],
}

# WordPress sanitization functions
SANITIZATION_FUNCTIONS = {
    'sanitize_text_field', 'sanitize_email', 'sanitize_url', 'sanitize_key',
    'sanitize_title', 'sanitize_user', 'sanitize_file_name', 'sanitize_html_class',
    'sanitize_textarea_field', 'wp_kses', 'wp_kses_post', 'wp_kses_data',
    'intval', 'floatval', 'absint', 'wp_strip_all_tags'
}

# WordPress escaping functions
ESCAPING_FUNCTIONS = {
    'esc_html', 'esc_attr', 'esc_url', 'esc_js', 'esc_textarea',
    'esc_sql', 'esc_xml', 'wp_kses', 'wp_kses_post'
}


class WordPressSecurityAuditor(AugmentKimiIntegration):
    """Specialized security auditor for WordPress plugins"""
    
    def __init__(self, kimi_endpoint: str = "http://localhost:8000", plugin_context: str = "blazewooless"):
        super().__init__(kimi_endpoint)
        self.plugin_context = plugin_context
        logger.info(f"Initialized WordPress security auditor for plugin: {plugin_context}")
    
    def generate_security_prompt(self, file_path: str, file_content: str, findings: Dict[str, Any]) -> str:
        """Generate WordPress-specific security audit prompt"""
        security_prompt = f"""
# WordPress Plugin Security Audit - {self.plugin_context}

## Context
- Plugin: {self.plugin_context} (BlazeWooless)
- File: {file_path}
- Automated Findings: {json.dumps(findings, indent=2)}

## File Content
```php
{file_content}
```

## WordPress Security Audit Requirements

### Critical Security Areas to Analyze:
1. **SQL Injection Prevention**:
   - Use $wpdb->prepare() for all database queries
   - Validate and sanitize all user inputs
   - Avoid dynamic SQL construction

2. **Cross-Site Scripting (XSS) Prevention**:
   - Escape all output with esc_html(), esc_attr(), esc_url()
   - Use wp_kses() for allowed HTML
   - Validate and sanitize all user inputs

3. **Cross-Site Request Forgery (CSRF) Protection**:
   - Use wp_nonce_field() in forms
   - Verify nonces with wp_verify_nonce()
   - Check referer where appropriate

4. **File Upload Security**:
   - Validate file types and extensions
   - Check file content, not just extension
   - Use WordPress media handling functions
   - Prevent direct file access

5. **Authentication and Authorization**:
   - Use current_user_can() for capability checks
   - Verify user permissions for all actions
   - Implement proper access controls

6. **Input Validation and Sanitization**:
   - Sanitize all user inputs with appropriate functions
   - Validate data types and ranges
   - Use WordPress sanitization functions

7. **Output Escaping**:
   - Escape all output based on context
   - Use appropriate escaping functions
   - Never trust user data in output

### WordPress-Specific Security Considerations:
- WordPress hooks and filters security
- Plugin activation/deactivation security
- Admin interface security
- AJAX security
- Database security
- File system security
- Option/meta data security

## Expected Output
Provide a comprehensive security analysis including:
1. **Vulnerability Assessment**: Detailed analysis of each security issue
2. **Risk Rating**: High/Medium/Low for each finding
3. **Remediation Code**: Secure code examples for each issue
4. **Prevention Strategies**: Long-term security improvements
5. **WordPress Best Practices**: Plugin-specific recommendations

Format the response as a structured security report with code examples.
"""
        
        return security_prompt
    
    def scan_file_for_vulnerabilities(self, file_path: str) -> Dict[str, Any]:
        """Scan PHP file for common WordPress security vulnerabilities"""
        try:
            validated_path = self._validate_file_path(file_path)
            
            if not validated_path.exists():
                return {"error": f"File not found: {file_path}"}
            
            with open(validated_path, 'r', encoding='utf-8', errors='ignore') as f:
                content = f.read()
            
            findings = {
                "file_path": str(validated_path),
                "file_size": len(content),
                "line_count": content.count('\n') + 1,
                "vulnerabilities": {},
                "security_features": {},
                "recommendations": []
            }
            
            # Check for vulnerabilities
            for category, patterns in SECURITY_PATTERNS.items():
                findings["vulnerabilities"][category] = []
                for pattern, description in patterns:
                    matches = list(re.finditer(pattern, content, re.IGNORECASE | re.MULTILINE))
                    if matches:
                        for match in matches:
                            line_num = content[:match.start()].count('\n') + 1
                            findings["vulnerabilities"][category].append({
                                "description": description,
                                "line": line_num,
                                "code": match.group(0).strip(),
                                "severity": self._assess_severity(category, match.group(0))
                            })
            
            # Check for security features
            findings["security_features"]["sanitization"] = self._find_security_functions(content, SANITIZATION_FUNCTIONS)
            findings["security_features"]["escaping"] = self._find_security_functions(content, ESCAPING_FUNCTIONS)
            
            # Generate recommendations
            findings["recommendations"] = self._generate_recommendations(findings["vulnerabilities"])
            
            return findings
            
        except Exception as e:
            logger.error(f"Error scanning file {file_path}: {e}")
            return {"error": str(e)}
    
    def _find_security_functions(self, content: str, functions: set) -> List[Dict[str, Any]]:
        """Find usage of security functions in code"""
        found_functions = []
        for func in functions:
            pattern = rf'{func}\s*\('
            matches = list(re.finditer(pattern, content, re.IGNORECASE))
            if matches:
                for match in matches:
                    line_num = content[:match.start()].count('\n') + 1
                    found_functions.append({
                        "function": func,
                        "line": line_num,
                        "usage": match.group(0)
                    })
        return found_functions
    
    def _assess_severity(self, category: str, code: str) -> str:
        """Assess the severity of a security issue"""
        high_risk_categories = {'sql_injection', 'command_injection', 'file_inclusion'}
        medium_risk_categories = {'xss', 'csrf', 'file_upload'}
        
        if category in high_risk_categories:
            return "HIGH"
        elif category in medium_risk_categories:
            return "MEDIUM"
        else:
            return "LOW"
    
    def _generate_recommendations(self, vulnerabilities: Dict[str, List]) -> List[str]:
        """Generate security recommendations based on findings"""
        recommendations = []
        
        for category, issues in vulnerabilities.items():
            if not issues:
                continue
                
            if category == 'sql_injection':
                recommendations.append("Use $wpdb->prepare() for all database queries with user input")
            elif category == 'xss':
                recommendations.append("Escape all output with appropriate WordPress functions (esc_html, esc_attr, etc.)")
            elif category == 'csrf':
                recommendations.append("Implement nonce verification for all forms and AJAX requests")
            elif category == 'file_inclusion':
                recommendations.append("Avoid dynamic file inclusion or validate file paths properly")
            elif category == 'command_injection':
                recommendations.append("Never execute system commands with user input")
            elif category == 'file_upload':
                recommendations.append("Validate file uploads thoroughly including type, size, and content")
        
        return recommendations
    
    def perform_security_audit(self, file_path: str) -> Dict[str, Any]:
        """Perform comprehensive security audit of WordPress file"""
        logger.info(f"Performing security audit of: {file_path}")
        
        try:
            # First, scan for vulnerabilities
            scan_results = self.scan_file_for_vulnerabilities(file_path)
            if "error" in scan_results:
                return scan_results
            
            # Read file content
            validated_path = self._validate_file_path(file_path)
            with open(validated_path, 'r', encoding='utf-8', errors='ignore') as f:
                file_content = f.read()
            
            # Generate security audit prompt
            prompt = self.generate_security_prompt(file_path, file_content, scan_results)
            
            # Call API for detailed analysis
            detailed_analysis = ""
            if self.config.endpoint:
                try:
                    detailed_analysis = self._call_kimi_api(prompt)
                except APIError as e:
                    logger.error(f"API error during security audit: {e}")
                    detailed_analysis = f"API Error: {e}"
            
            result = {
                "file_path": file_path,
                "scan_results": scan_results,
                "detailed_analysis": detailed_analysis,
                "security_score": self._calculate_security_score(scan_results),
                "priority_issues": self._get_priority_issues(scan_results),
                "success": True,
                "timestamp": self._get_timestamp()
            }
            
            logger.info(f"Security audit completed for: {file_path}")
            return result
            
        except Exception as e:
            logger.error(f"Error during security audit of {file_path}: {e}")
            return {"error": str(e), "success": False}
    
    def _calculate_security_score(self, scan_results: Dict[str, Any]) -> int:
        """Calculate security score (0-100, higher is better)"""
        base_score = 100
        vulnerabilities = scan_results.get("vulnerabilities", {})
        
        for category, issues in vulnerabilities.items():
            if not issues:
                continue
                
            for issue in issues:
                if issue["severity"] == "HIGH":
                    base_score -= 20
                elif issue["severity"] == "MEDIUM":
                    base_score -= 10
                elif issue["severity"] == "LOW":
                    base_score -= 5
        
        return max(0, base_score)
    
    def _get_priority_issues(self, scan_results: Dict[str, Any]) -> List[Dict[str, Any]]:
        """Get high-priority security issues"""
        priority_issues = []
        vulnerabilities = scan_results.get("vulnerabilities", {})
        
        for category, issues in vulnerabilities.items():
            for issue in issues:
                if issue["severity"] == "HIGH":
                    priority_issues.append({
                        "category": category,
                        "description": issue["description"],
                        "line": issue["line"],
                        "code": issue["code"],
                        "severity": issue["severity"]
                    })
        
        return priority_issues
    
    def _get_timestamp(self) -> str:
        """Get formatted timestamp"""
        import datetime
        return datetime.datetime.now().isoformat()


def main():
    parser = argparse.ArgumentParser(description="WordPress Plugin Security Audit with Kimi-Dev")
    parser.add_argument("--file", required=True, help="WordPress file to audit")
    parser.add_argument("--plugin-context", default="blazewooless", help="Plugin context")
    parser.add_argument("--kimi-endpoint", default="http://localhost:8000", help="Kimi-Dev API endpoint")
    parser.add_argument("--output", help="Output file for results")
    parser.add_argument("--format", choices=['json', 'text'], default='json', help="Output format")
    parser.add_argument("--log-level", choices=['DEBUG', 'INFO', 'WARNING', 'ERROR'],
                       default='INFO', help="Logging level")
    
    args = parser.parse_args()
    
    # Configure logging
    logging.getLogger().setLevel(getattr(logging, args.log_level))
    
    try:
        auditor = WordPressSecurityAuditor(args.kimi_endpoint, args.plugin_context)
        result = auditor.perform_security_audit(args.file)
        
        if args.output:
            try:
                output_path = Path(args.output)
                output_path.parent.mkdir(parents=True, exist_ok=True)
                
                if args.format == 'json':
                    with open(output_path, 'w', encoding='utf-8') as f:
                        json.dump(result, f, indent=2, ensure_ascii=False)
                else:
                    with open(output_path, 'w', encoding='utf-8') as f:
                        f.write(f"WordPress Security Audit Report\n")
                        f.write(f"File: {result.get('file_path', 'Unknown')}\n")
                        f.write(f"Security Score: {result.get('security_score', 0)}/100\n\n")
                        
                        priority_issues = result.get('priority_issues', [])
                        if priority_issues:
                            f.write("High Priority Issues:\n")
                            for issue in priority_issues:
                                f.write(f"- {issue['description']} (Line {issue['line']})\n")
                            f.write("\n")
                        
                        f.write("Detailed Analysis:\n")
                        f.write(result.get('detailed_analysis', 'No detailed analysis available'))
                
                logger.info(f"Security audit results saved to {args.output}")
            except Exception as e:
                logger.error(f"Failed to save results: {e}")
                sys.exit(1)
        else:
            if args.format == 'json':
                print("\n🔒 WordPress Security Audit Results:")
                print(json.dumps(result, indent=2, ensure_ascii=False))
            else:
                print(f"\n🔒 WordPress Security Audit Report")
                print(f"File: {result.get('file_path', 'Unknown')}")
                print(f"Security Score: {result.get('security_score', 0)}/100")
                
                priority_issues = result.get('priority_issues', [])
                if priority_issues:
                    print("\nHigh Priority Issues:")
                    for issue in priority_issues:
                        print(f"- {issue['description']} (Line {issue['line']})")
                
                print("\nDetailed Analysis:")
                print(result.get('detailed_analysis', 'No detailed analysis available'))
        
        # Exit with appropriate code based on security score
        security_score = result.get('security_score', 0)
        if security_score < 50:
            sys.exit(2)  # Critical security issues
        elif security_score < 80:
            sys.exit(1)  # Some security issues
        else:
            sys.exit(0)  # Good security
    
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()