# Claude AI Workflow Sequence Configuration

## Overview
This document describes the proper configuration and sequence between the Claude AI review workflows to ensure Priority 2 only triggers after Priority 1 completes successfully.

## Workflow Sequence

### Priority 1: Workflow Pre-flight Check (`workflow-preflight-check.yml`)
- **Trigger**: Pull request opened/synchronized/reopened
- **Purpose**: Test workflow connectivity and initialization
- **Output**: Workflow connectivity status and PR context

### Priority 2: Claude AI Code Review (`claude-code-review.yml`)
- **Trigger**: After Priority 1 completes successfully
- **Purpose**: Automated code review using Claude AI
- **Output**: Standardized review with APPROVED/CONDITIONAL APPROVAL/BLOCKED verdict

### Priority 3: Claude AI Approval Gate (`claude-approval-gate.yml`)
- **Trigger**: After Priority 2 completes successfully
- **Purpose**: Parse Claude's review and auto-approve if appropriate
- **Dependencies**: Waits for Priority 2 completion

## Configuration Details

### 1. Workflow Trigger Configuration
```yaml
on:
  workflow_run:
    workflows: ["Priority 1: Claude AI Code Review"]
    types: [completed]
```

### 2. Dependency Validation
The `wait-for-claude-review` job ensures proper sequencing:
- Checks Priority 1 workflow completion status
- Only proceeds if Priority 1 completed successfully
- Sets `should-proceed` output for downstream jobs

### 3. Job Dependencies
```yaml
claude-approval-gate:
  needs: [check-trigger, wait-for-claude-review]
  if: needs.wait-for-claude-review.outputs.should-proceed == 'true'
```

### 4. Standardized Review Format
Priority 1 workflow instructs Claude to use this format:
```
### FINAL VERDICT
**Status**: [APPROVED | CONDITIONAL APPROVAL | BLOCKED]
**Merge Readiness**: [READY TO MERGE | READY AFTER FIXES | NOT READY]
**Recommendation**: [Brief explanation]
```

### 5. Auto-Approval Logic
Priority 2 workflow parses the standardized format:
- **APPROVED**: Auto-approves the PR
- **CONDITIONAL APPROVAL**: Auto-approves if no critical issues
- **BLOCKED**: Blocks merge until issues are resolved

## Testing Scenarios

### Scenario 1: PR with No Issues
1. Priority 1 runs and outputs "APPROVED"
2. Priority 2 detects approval and auto-approves
3. PR is ready for merge

### Scenario 2: PR with Critical Issues
1. Priority 1 runs and outputs "BLOCKED"
2. Priority 2 detects blocking status
3. PR merge is blocked until issues are resolved

### Scenario 3: PR with Minor Issues
1. Priority 1 runs and outputs "CONDITIONAL APPROVAL"
2. Priority 2 detects conditional approval
3. PR is auto-approved for merge

### Scenario 4: Failed Claude Review
1. Priority 1 fails to complete
2. Priority 2 wait-for-claude-review job blocks execution
3. Manual intervention required

## Error Handling

### Workflow Failure Recovery
- If Priority 1 fails, Priority 2 waits indefinitely
- Manual re-trigger of Priority 1 will resume the sequence
- Error states are logged for debugging

### Missing Review Format
- Legacy pattern matching as fallback
- Manual approval required if format not recognized
- Clear error messages in workflow logs

## Maintenance Notes

### Key Configuration Points
1. Workflow name must match exactly: "Priority 1: Claude AI Code Review"
2. Job dependencies must include both check-trigger and wait-for-claude-review
3. Conditional execution prevents premature Priority 2 execution
4. Standardized format ensures reliable parsing

### Common Issues
- YAML syntax errors in workflow names with colons (must be quoted)
- Missing job dependencies causing race conditions
- Incorrect workflow name references breaking triggers
- Missing standardized format causing parsing failures

## Validation Checklist

- [ ] Priority 1 workflow name is properly quoted
- [ ] Priority 2 workflow_run trigger references correct name
- [ ] wait-for-claude-review job validates completion status
- [ ] claude-approval-gate job has proper dependencies
- [ ] Standardized review format is enforced
- [ ] Auto-approval logic handles all verdict types
- [ ] Error handling covers failure scenarios
- [ ] Testing covers all approval scenarios
