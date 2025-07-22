#!/usr/bin/env python3
"""
Basic tests for Kimi-Dev integration scripts
"""

import unittest
import os
import sys
import tempfile
import json
from pathlib import Path
from unittest.mock import patch, MagicMock

# Add scripts directory to path
sys.path.insert(0, str(Path(__file__).parent.parent / 'scripts'))

from augment_kimi_integration import AugmentKimiIntegration, SecurityError, APIError, ValidationError


class TestKimiIntegration(unittest.TestCase):
    """Test cases for Kimi-Dev integration"""
    
    def setUp(self):
        """Set up test fixtures"""
        self.integration = AugmentKimiIntegration("http://localhost:8000")
        
        # Create temporary test files
        self.temp_dir = tempfile.mkdtemp()
        self.test_file = Path(self.temp_dir) / "test.php"
        self.test_file.write_text("<?php\necho 'Hello World';\n")
    
    def tearDown(self):
        """Clean up test fixtures"""
        import shutil
        shutil.rmtree(self.temp_dir, ignore_errors=True)
    
    def test_endpoint_validation(self):
        """Test API endpoint validation"""
        # Valid endpoints
        valid_endpoints = [
            "http://localhost:8000",
            "https://api.example.com",
            "http://127.0.0.1:8000"
        ]
        
        for endpoint in valid_endpoints:
            try:
                integration = AugmentKimiIntegration(endpoint)
                self.assertEqual(integration.config.endpoint, endpoint)
            except ValidationError:
                self.fail(f"Valid endpoint {endpoint} was rejected")
        
        # Invalid endpoints
        invalid_endpoints = [
            "",
            "ftp://localhost",
            "javascript:alert(1)",
            "not-a-url"
        ]
        
        for endpoint in invalid_endpoints:
            with self.assertRaises(ValidationError):
                AugmentKimiIntegration(endpoint)
    
    def test_file_path_validation(self):
        """Test file path validation"""
        # Valid paths (within workspace)
        valid_path = str(self.test_file)
        validated_path = self.integration._validate_file_path(valid_path)
        self.assertTrue(validated_path.exists())
        
        # Invalid paths (outside workspace)
        invalid_paths = [
            "/etc/passwd",
            "../../../etc/passwd",
            "//etc/passwd",
            "/dev/null"
        ]
        
        for path in invalid_paths:
            with self.assertRaises(SecurityError):
                self.integration._validate_file_path(path)
    
    def test_input_sanitization(self):
        """Test input sanitization"""
        # Test cases
        test_cases = [
            ("normal text", "normal text"),
            ("text with <script>", "text with "),
            ("text with \"quotes\"", "text with quotes"),
            ("text with '; DROP TABLE users;", "text with  DROP TABLE users"),
            ("a" * 20000, "a" * 10000)  # Length limit
        ]
        
        for input_text, expected in test_cases:
            result = self.integration._sanitize_input(input_text)
            self.assertEqual(result, expected)
    
    def test_extract_files_from_response(self):
        """Test file extraction from API response"""
        # JSON response
        json_response = json.dumps(["file1.php", "file2.js", "file3.css"])
        files = self.integration._extract_files_from_response(json_response)
        # Files won't be validated since they don't exist, so result will be empty
        self.assertIsInstance(files, list)
        
        # Text response
        text_response = """
        Here are the files:
        - file1.php
        - file2.js
        - file3.css
        """
        files = self.integration._extract_files_from_response(text_response)
        self.assertIsInstance(files, list)
    
    def test_basic_file_detection(self):
        """Test basic file detection fallback"""
        # Create a test issue that mentions our test file
        issue = f"Fix the issue in {self.test_file.name}"
        context = {}
        
        # Set workspace to our temp directory
        self.integration.workspace_root = Path(self.temp_dir)
        
        files = self.integration._basic_file_detection(issue, context)
        self.assertIsInstance(files, list)
        # Should find our test file
        self.assertTrue(any(self.test_file.name in f for f in files))
    
    def test_gather_context(self):
        """Test context gathering"""
        query = "test query"
        context = self.integration.gather_augment_context(query)
        
        self.assertIsInstance(context, dict)
        self.assertIn("query", context)
        self.assertIn("relevant_files", context)
        self.assertIn("code_snippets", context)
        self.assertIn("dependencies", context)
    
    @patch('requests.post')
    def test_api_call_success(self, mock_post):
        """Test successful API call"""
        # Mock successful response
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = {
            "choices": [{"message": {"content": "test response"}}]
        }
        mock_post.return_value = mock_response
        
        result = self.integration._call_kimi_api("test prompt")
        self.assertEqual(result, "test response")
    
    @patch('requests.post')
    def test_api_call_failure(self, mock_post):
        """Test API call failure"""
        # Mock failed response
        mock_response = MagicMock()
        mock_response.status_code = 500
        mock_response.text = "Internal Server Error"
        mock_post.return_value = mock_response
        
        with self.assertRaises(APIError):
            self.integration._call_kimi_api("test prompt")
    
    def test_run_integration_no_api(self):
        """Test integration without API endpoint"""
        # Create integration without API
        integration = AugmentKimiIntegration("")
        integration.config.endpoint = None
        
        result = integration.run_integration("test issue")
        
        self.assertIsInstance(result, dict)
        self.assertIn("issue", result)
        self.assertIn("success", result)
        self.assertTrue(result["success"])  # Should still succeed without API


class TestWordPressScripts(unittest.TestCase):
    """Test WordPress-specific scripts"""
    
    def setUp(self):
        """Set up test fixtures"""
        self.temp_dir = tempfile.mkdtemp()
        self.test_php_file = Path(self.temp_dir) / "test.php"
        self.test_php_file.write_text("""<?php
class TestClass {
    public function test_method() {
        echo $_GET['user_input'];  // Security issue
        return sanitize_text_field($_POST['data']);
    }
}
""")
    
    def tearDown(self):
        """Clean up test fixtures"""
        import shutil
        shutil.rmtree(self.temp_dir, ignore_errors=True)
    
    def test_bug_fixer_import(self):
        """Test that bug fixer script can be imported"""
        try:
            from wp_kimi_dev_bugfix import WordPressBugFixer
            fixer = WordPressBugFixer()
            self.assertIsNotNone(fixer)
        except ImportError as e:
            self.fail(f"Failed to import WordPressBugFixer: {e}")
    
    def test_test_writer_import(self):
        """Test that test writer script can be imported"""
        try:
            from wp_kimi_dev_testwriter import WordPressTestWriter
            writer = WordPressTestWriter()
            self.assertIsNotNone(writer)
        except ImportError as e:
            self.fail(f"Failed to import WordPressTestWriter: {e}")
    
    def test_security_auditor_import(self):
        """Test that security auditor script can be imported"""
        try:
            from wp_kimi_dev_security import WordPressSecurityAuditor
            auditor = WordPressSecurityAuditor()
            self.assertIsNotNone(auditor)
        except ImportError as e:
            self.fail(f"Failed to import WordPressSecurityAuditor: {e}")


if __name__ == '__main__':
    unittest.main()