# Kimi-Dev VS Code Integration Setup for WordPress Plugin Development

## Prerequisites
- VS Code with Augment Code extension
- Python 3.8+ environment with required packages:
  - `requests` for API communication
  - `kimi-dev` (optional, for local model)
- WordPress development environment (Local, XAMPP, etc.)
- PHP CodeSniffer with WordPress Coding Standards
- vLLM server running (optional, for local model)

### Python Dependencies
```bash
pip install requests
# Optional: For local Kimi-Dev model
pip install kimi-dev
```

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
The following WordPress-specific scripts are available:

#### Bug Fix Script (`scripts/wp-kimi-dev-bugfix.py`)
- WordPress-compliant bug fixes with comprehensive testing
- Security-focused analysis and remediation
- Integration with WordPress coding standards

#### Test Writer Script (`scripts/wp-kimi-dev-testwriter.py`)
- Generates WordPress unit tests using WP_UnitTestCase
- Supports multiple test types (unit, integration, admin, security)
- WordPress-specific test utilities and assertions

#### Security Audit Script (`scripts/wp-kimi-dev-security.py`)
- Comprehensive WordPress security vulnerability scanning
- Automated detection of common security issues
- Detailed security recommendations and remediation

### 4. Keybindings
Add to `keybindings.json`:

```json
[
    {
        "key": "ctrl+shift+w ctrl+shift+b",
        "command": "workbench.action.tasks.runTask",
        "args": "WordPress Plugin Bug Fix"
    },
    {
        "key": "ctrl+shift+w ctrl+shift+t",
        "command": "workbench.action.tasks.runTask",
        "args": "WordPress Plugin Test Writer"
    },
    {
        "key": "ctrl+shift+w ctrl+shift+s",
        "command": "workbench.action.tasks.runTask",
        "args": "WordPress Security Audit"
    },
    {
        "key": "ctrl+shift+w ctrl+shift+c",
        "command": "workbench.action.tasks.runTask",
        "args": "WordPress Standards Check"
    }
]
```

## Workflow Examples

### 1. Bug Fixing Workflow
1. **Ctrl+Shift+W, Ctrl+Shift+B** → Trigger WordPress bug fix task
2. Describe the issue in the prompt
3. Kimi-Dev analyzes and provides WordPress-compliant fix
4. Review and apply changes

### 2. Test Writing Workflow
1. **Ctrl+Shift+W, Ctrl+Shift+T** → Trigger WordPress test writer
2. Select test type (unit, integration, admin, security, etc.)
3. Kimi-Dev generates comprehensive WordPress tests
4. Review and integrate tests

### 3. Security Audit Workflow
1. **Ctrl+Shift+W, Ctrl+Shift+S** → Trigger WordPress security audit
2. Kimi-Dev scans for vulnerabilities and security issues
3. Review security recommendations
4. Apply security fixes

### 3. Combined Workflow
1. Use Augment Code for broad context gathering
2. Apply Kimi-Dev's two-stage approach for specific tasks
3. Leverage both tools' strengths for comprehensive solutions

## Benefits of Integration

- **Augment Code**: Excellent context retrieval and codebase understanding
- **Kimi-Dev**: Specialized software engineering task execution
- **Combined**: Comprehensive solution with broad context and precise execution
