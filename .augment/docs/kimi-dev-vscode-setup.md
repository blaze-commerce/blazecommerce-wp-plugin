# Kimi-Dev VS Code Integration Setup for WordPress Plugin Development

## Prerequisites
- VS Code with Augment Code extension
- Python environment with kimi-dev installed
- WordPress development environment (Local, XAMPP, etc.)
- PHP CodeSniffer with WordPress Coding Standards
- vLLM server running (optional, for local model)

## Setup Steps

### 1. Install Kimi-Dev
```bash
git clone https://github.com/MoonshotAI/Kimi-Dev.git
cd Kimi-Dev
conda create -n kimidev python=3.12
conda activate kimidev
pip install -e .
```

### 2. WordPress Plugin VS Code Tasks Configuration
Create `.vscode/tasks.json`:

```json
{
    "version": "2.0.0",
    "tasks": [
        {
            "label": "WordPress Plugin Bug Fix",
            "type": "shell",
            "command": "python",
            "args": [
                "${workspaceFolder}/scripts/wp-kimi-dev-bugfix.py",
                "--file", "${file}",
                "--issue", "${input:issueDescription}",
                "--plugin-context", "blazewooless"
            ],
            "group": "build",
            "presentation": {
                "echo": true,
                "reveal": "always",
                "focus": false,
                "panel": "shared"
            }
        },
        {
            "label": "WordPress Plugin Test Writer",
            "type": "shell",
            "command": "python",
            "args": [
                "${workspaceFolder}/scripts/wp-kimi-dev-testwriter.py",
                "--file", "${file}",
                "--test-type", "${input:testType}"
            ],
            "group": "test"
        },
        {
            "label": "WordPress Security Audit",
            "type": "shell",
            "command": "python",
            "args": [
                "${workspaceFolder}/scripts/wp-kimi-dev-security.py",
                "--file", "${file}"
            ],
            "group": "build"
        },
        {
            "label": "WordPress Standards Check",
            "type": "shell",
            "command": "phpcs",
            "args": [
                "--standard=WordPress",
                "${file}"
            ],
            "group": "build"
        }
    ],
    "inputs": [
        {
            "id": "issueDescription",
            "description": "Describe the WordPress plugin issue to fix",
            "default": "",
            "type": "promptString"
        },
        {
            "id": "testType",
            "description": "Type of WordPress test to create",
            "default": "unit",
            "type": "pickString",
            "options": [
                "unit",
                "integration",
                "admin",
                "frontend",
                "security",
                "performance"
            ]
        }
    ]
}
```

### 3. Custom Scripts
Create helper scripts that bridge Augment Code context with Kimi-Dev:

#### Bug Fix Script (`scripts/kimi-dev-bugfix.py`)
```python
#!/usr/bin/env python3
import argparse
import sys
import os
from pathlib import Path

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--file', required=True)
    parser.add_argument('--issue', required=True)
    args = parser.parse_args()
    
    # Stage 1: File Localization using Augment Code context
    print("🔍 Stage 1: File Localization")
    # Use Augment Code's context engine to gather related files
    
    # Stage 2: Code Editing using Kimi-Dev approach
    print("🛠️ Stage 2: Code Editing")
    # Apply Kimi-Dev's precise editing approach
    
    print(f"Processing file: {args.file}")
    print(f"Issue: {args.issue}")

if __name__ == "__main__":
    main()
```

### 4. Keybindings
Add to `keybindings.json`:

```json
[
    {
        "key": "ctrl+shift+k ctrl+shift+b",
        "command": "workbench.action.tasks.runTask",
        "args": "Kimi-Dev Bug Fix"
    },
    {
        "key": "ctrl+shift+k ctrl+shift+t",
        "command": "workbench.action.tasks.runTask",
        "args": "Kimi-Dev Test Writer"
    }
]
```

## Workflow Examples

### 1. Bug Fixing Workflow
1. **Ctrl+Shift+K, Ctrl+Shift+B** → Trigger bug fix task
2. Describe the issue in the prompt
3. Kimi-Dev analyzes and provides fix
4. Review and apply changes

### 2. Test Writing Workflow
1. **Ctrl+Shift+K, Ctrl+Shift+T** → Trigger test writer
2. Kimi-Dev generates comprehensive tests
3. Review and integrate tests

### 3. Combined Workflow
1. Use Augment Code for broad context gathering
2. Apply Kimi-Dev's two-stage approach for specific tasks
3. Leverage both tools' strengths for comprehensive solutions

## Benefits of Integration

- **Augment Code**: Excellent context retrieval and codebase understanding
- **Kimi-Dev**: Specialized software engineering task execution
- **Combined**: Comprehensive solution with broad context and precise execution
