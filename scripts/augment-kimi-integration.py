#!/usr/bin/env python3
"""
Augment Code + Kimi-Dev Integration Script
Combines Augment Code's context engine with Kimi-Dev's specialized coding capabilities
"""

import argparse
import json
import logging
import os
import re
import sys
from pathlib import Path
from typing import List, Dict, Any, Optional
from urllib.parse import urlparse
import requests
import time
from dataclasses import dataclass
from enum import Enum

# Constants
MAX_FILE_SIZE = 10 * 1024 * 1024  # 10MB
MAX_RETRIES = 3
RETRY_DELAY = 1.0
ALLOWED_EXTENSIONS = {'.py', '.js', '.ts', '.tsx', '.jsx', '.php', '.css', '.html', '.vue', '.md'}
ALLOWED_HOSTS = {'localhost', '127.0.0.1', '0.0.0.0'}

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


class SecurityError(Exception):
    """Raised when security validation fails"""
    pass


class APIError(Exception):
    """Raised when API calls fail"""
    pass


class ValidationError(Exception):
    """Raised when input validation fails"""
    pass


@dataclass
class KimiConfig:
    """Configuration for Kimi-Dev integration"""
    endpoint: str
    max_tokens: int = 4000
    temperature: float = 0.1
    timeout: int = 30
    model: str = "kimi-dev"

class AugmentKimiIntegration:
    def __init__(self, kimi_endpoint: str = "http://localhost:8000"):
        self.config = KimiConfig(endpoint=self._validate_endpoint(kimi_endpoint))
        self.workspace_root = Path.cwd().resolve()
        logger.info(f"Initialized integration with endpoint: {self.config.endpoint}")
        logger.info(f"Workspace root: {self.workspace_root}")
    
    def _validate_endpoint(self, endpoint: str) -> str:
        """Validate API endpoint for security"""
        if not endpoint:
            raise ValidationError("Endpoint cannot be empty")
        
        try:
            parsed = urlparse(endpoint)
            if not parsed.scheme in ('http', 'https'):
                raise ValidationError("Endpoint must use HTTP or HTTPS")
            
            # For security, only allow localhost and explicitly configured hosts
            if parsed.hostname not in ALLOWED_HOSTS:
                logger.warning(f"Endpoint host {parsed.hostname} not in allowed hosts")
                # In production, you might want to be more restrictive
                # raise SecurityError(f"Host {parsed.hostname} not allowed")
            
            return endpoint
        except Exception as e:
            raise ValidationError(f"Invalid endpoint URL: {e}")
    
    def _validate_file_path(self, file_path: str) -> Path:
        """Validate file path to prevent directory traversal"""
        try:
            path = Path(file_path).resolve()
            # Ensure path is within workspace
            path.relative_to(self.workspace_root)
            return path
        except ValueError:
            raise SecurityError(f"File path {file_path} is outside workspace")
    
    def _sanitize_input(self, user_input: str) -> str:
        """Sanitize user input to prevent injection attacks"""
        # Basic sanitization - remove potentially dangerous characters
        sanitized = re.sub(r'[<>"\';\x00-\x08\x0b\x0c\x0e-\x1f\x7f-\x84\x86-\x9f]', '', user_input)
        # Limit length to prevent DoS
        return sanitized[:10000]
        
    def gather_augment_context(self, query: str) -> Dict[str, Any]:
        """
        Simulate gathering context using Augment Code's approach
        In practice, this would interface with Augment Code's context engine
        """
        sanitized_query = self._sanitize_input(query)
        logger.info(f"Gathering context for: {sanitized_query[:100]}...")
        
        context = {
            "query": sanitized_query,
            "relevant_files": [],
            "code_snippets": [],
            "dependencies": []
        }
        
        # This would be replaced with actual Augment Code context retrieval
        logger.info("Context gathering complete")
        return context
    
    def kimi_file_localization(self, issue: str, context: Dict[str, Any]) -> List[str]:
        """
        Stage 1: Use Kimi-Dev approach for file localization
        """
        sanitized_issue = self._sanitize_input(issue)
        logger.info("Starting file localization phase")
        
        prompt = f"""
        # File Localization Phase
        Issue: {sanitized_issue}
        
        Repository context: {json.dumps(context, indent=2)}
        
        Identify the key files that need modification for this issue.
        Consider:
        1. Files directly mentioned in the issue
        2. Related files that might be affected  
        3. Test files that need updates
        4. Configuration files that might need changes
        
        Return a JSON list of file paths.
        """
        
        if self.config.endpoint:
            try:
                response = self._call_kimi_api(prompt)
                files = self._extract_files_from_response(response)
                logger.info(f"Found {len(files)} files via API")
                return files
            except APIError as e:
                logger.error(f"Kimi API call failed: {e}")
            except Exception as e:
                logger.error(f"Unexpected error in API call: {e}")
        
        # Fallback: basic file detection
        logger.info("Using fallback file detection")
        return self._basic_file_detection(sanitized_issue, context)
    
    def kimi_code_editing(self, files: List[str], issue: str) -> Dict[str, str]:
        """
        Stage 2: Use Kimi-Dev approach for code editing
        """
        sanitized_issue = self._sanitize_input(issue)
        edits = {}
        logger.info(f"Starting code editing phase for {len(files)} files")
        
        for file_path in files:
            try:
                validated_path = self._validate_file_path(file_path)
                
                if not validated_path.exists():
                    logger.warning(f"File does not exist: {file_path}")
                    continue
                
                # Check file size
                if validated_path.stat().st_size > MAX_FILE_SIZE:
                    logger.warning(f"File too large, skipping: {file_path}")
                    continue
                
                # Check file extension
                if validated_path.suffix not in ALLOWED_EXTENSIONS:
                    logger.warning(f"File extension not allowed: {file_path}")
                    continue
                
                with open(validated_path, 'r', encoding='utf-8', errors='ignore') as f:
                    file_content = f.read()
                
                prompt = f"""
                # Code Editing Phase
                File: {file_path}
                Issue: {sanitized_issue}
                
                Current file content:
                ```
                {file_content}
                ```
                
                Provide precise code modifications:
                1. Explain the root cause
                2. Provide exact code changes
                3. Include error handling
                4. Ensure backward compatibility
                
                Return the modified file content.
                """
                
                if self.config.endpoint:
                    try:
                        response = self._call_kimi_api(prompt)
                        edits[file_path] = response
                        logger.info(f"Generated edits for: {file_path}")
                    except APIError as e:
                        logger.error(f"Kimi API call failed for {file_path}: {e}")
                        edits[file_path] = f"# ERROR: API call failed - {e}"
                    except Exception as e:
                        logger.error(f"Unexpected error for {file_path}: {e}")
                        edits[file_path] = f"# ERROR: Unexpected error - {e}"
                else:
                    edits[file_path] = f"# TODO: Apply Kimi-Dev editing for {file_path}"
            
            except SecurityError as e:
                logger.error(f"Security error for {file_path}: {e}")
                edits[file_path] = f"# SECURITY ERROR: {e}"
            except Exception as e:
                logger.error(f"Error processing {file_path}: {e}")
                edits[file_path] = f"# ERROR: {e}"
        
        return edits
    
    def _call_kimi_api(self, prompt: str) -> str:
        """Call Kimi-Dev API endpoint with retry logic"""
        sanitized_prompt = self._sanitize_input(prompt)
        
        payload = {
            "model": self.config.model,
            "messages": [{"role": "user", "content": sanitized_prompt}],
            "max_tokens": self.config.max_tokens,
            "temperature": self.config.temperature
        }
        
        for attempt in range(MAX_RETRIES):
            try:
                logger.debug(f"API call attempt {attempt + 1}/{MAX_RETRIES}")
                response = requests.post(
                    f"{self.config.endpoint}/v1/chat/completions",
                    json=payload,
                    headers={"Content-Type": "application/json"},
                    timeout=self.config.timeout
                )
                
                if response.status_code == 200:
                    result = response.json()
                    if "choices" in result and len(result["choices"]) > 0:
                        content = result["choices"][0]["message"]["content"]
                        logger.debug(f"API call successful, response length: {len(content)}")
                        return content
                    else:
                        raise APIError("Invalid response format")
                else:
                    error_msg = f"API call failed with status {response.status_code}"
                    if response.text:
                        error_msg += f": {response.text}"
                    raise APIError(error_msg)
            
            except requests.exceptions.Timeout:
                logger.warning(f"API call timeout on attempt {attempt + 1}")
                if attempt < MAX_RETRIES - 1:
                    time.sleep(RETRY_DELAY * (attempt + 1))
                else:
                    raise APIError("API call timed out after all retries")
            
            except requests.exceptions.RequestException as e:
                logger.warning(f"API call failed on attempt {attempt + 1}: {e}")
                if attempt < MAX_RETRIES - 1:
                    time.sleep(RETRY_DELAY * (attempt + 1))
                else:
                    raise APIError(f"API call failed after all retries: {e}")
        
        raise APIError("API call failed after all retries")
    
    def _extract_files_from_response(self, response: str) -> List[str]:
        """Extract file paths from Kimi-Dev response"""
        files = []
        
        # Try to parse as JSON first
        try:
            data = json.loads(response)
            if isinstance(data, list):
                files = [str(f) for f in data if isinstance(f, str)]
            elif isinstance(data, dict) and 'files' in data:
                files = [str(f) for f in data['files'] if isinstance(f, str)]
        except json.JSONDecodeError:
            # Fallback to text parsing
            lines = response.split('\n')
            for line in lines:
                line = line.strip()
                # Look for common file extensions
                if any(line.endswith(ext) for ext in ALLOWED_EXTENSIONS):
                    # Remove common prefixes
                    line = re.sub(r'^[-*+]\s*', '', line)
                    line = re.sub(r'^\d+\.\s*', '', line)
                    if line:
                        files.append(line)
        
        # Validate and filter files
        validated_files = []
        for file_path in files:
            try:
                validated_path = self._validate_file_path(file_path)
                if validated_path.exists():
                    validated_files.append(str(validated_path))
            except SecurityError:
                logger.warning(f"Skipping invalid file path: {file_path}")
        
        return validated_files[:10]  # Limit to prevent DoS
    
    def _basic_file_detection(self, issue: str, context: Dict[str, Any]) -> List[str]:
        """Fallback file detection when Kimi API is unavailable"""
        files = []
        
        # Look for files mentioned in the issue
        try:
            for root, dirs, filenames in os.walk(self.workspace_root):
                # Skip hidden directories and common build directories
                dirs[:] = [d for d in dirs if not d.startswith('.') and d not in {'node_modules', 'vendor', '__pycache__'}]
                
                for filename in filenames:
                    if filename.lower() in issue.lower():
                        file_path = os.path.join(root, filename)
                        try:
                            validated_path = self._validate_file_path(file_path)
                            if validated_path.suffix in ALLOWED_EXTENSIONS:
                                files.append(str(validated_path))
                        except SecurityError:
                            continue
        except Exception as e:
            logger.error(f"Error in file detection: {e}")
        
        return files[:5]  # Limit to 5 files
    
    def run_integration(self, issue: str) -> Dict[str, Any]:
        """
        Main integration workflow
        """
        logger.info("Starting Augment Code + Kimi-Dev Integration")
        
        try:
            # Step 1: Gather context using Augment Code approach
            context = self.gather_augment_context(issue)
            
            # Step 2: File localization using Kimi-Dev
            logger.info("Stage 1: File Localization")
            files = self.kimi_file_localization(issue, context)
            logger.info(f"Found {len(files)} files to modify")
            
            # Step 3: Code editing using Kimi-Dev
            logger.info("Stage 2: Code Editing")
            edits = self.kimi_code_editing(files, issue)
            
            result = {
                "issue": self._sanitize_input(issue),
                "context": context,
                "files_to_modify": files,
                "proposed_edits": edits,
                "timestamp": time.time(),
                "success": True
            }
            
            logger.info("Integration complete successfully")
            return result
        
        except Exception as e:
            logger.error(f"Integration failed: {e}")
            return {
                "issue": self._sanitize_input(issue),
                "error": str(e),
                "timestamp": time.time(),
                "success": False
            }

def main():
    parser = argparse.ArgumentParser(description="Augment Code + Kimi-Dev Integration")
    parser.add_argument("--issue", required=True, help="Issue description")
    parser.add_argument("--kimi-endpoint", default="http://localhost:8000", 
                       help="Kimi-Dev API endpoint")
    parser.add_argument("--output", help="Output file for results")
    parser.add_argument("--log-level", choices=['DEBUG', 'INFO', 'WARNING', 'ERROR'],
                       default='INFO', help="Logging level")
    
    args = parser.parse_args()
    
    # Configure logging level
    logging.getLogger().setLevel(getattr(logging, args.log_level))
    
    try:
        integration = AugmentKimiIntegration(args.kimi_endpoint)
        result = integration.run_integration(args.issue)
        
        if args.output:
            try:
                output_path = Path(args.output)
                # Ensure output directory exists
                output_path.parent.mkdir(parents=True, exist_ok=True)
                
                with open(output_path, 'w', encoding='utf-8') as f:
                    json.dump(result, f, indent=2, ensure_ascii=False)
                logger.info(f"Results saved to {args.output}")
            except Exception as e:
                logger.error(f"Failed to save results: {e}")
                sys.exit(1)
        else:
            print("\n📋 Results:")
            print(json.dumps(result, indent=2, ensure_ascii=False))
        
        # Exit with appropriate code
        sys.exit(0 if result.get('success', False) else 1)
    
    except Exception as e:
        logger.error(f"Fatal error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
