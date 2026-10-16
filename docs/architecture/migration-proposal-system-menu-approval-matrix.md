# Migration Proposal: Shared Menu Approval Matrix

Status: `EXECUTED_AND_VERIFIED`

## Scope
Grouped the shared Menu Approval Matrix controller, page, and components under SYSTEM/Governance/Authorization. This is a shared authorization boundary serving ESS, KPI, LMS, and VISITOR context keys; it is not moved into LMS-only ownership.

## Paths
```text
app/Http/Controllers/MenuApprovalMatrixController.php
→ app/Http/Controllers/SYSTEM/Governance/Authorization/MenuApprovalMatrix/MenuApprovalMatrixController.php
resources/js/Pages/Modules/Administrator/MenuApprovalMatrix/*
→ resources/js/Pages/SYSTEM/Governance/Authorization/MenuApprovalMatrix/*
```

## Preserved
All menu-approval URLs, context page keys, middleware, route file, shared services, audit behavior, and database contracts.

## Verification
PHP syntax/class loading passed; frontend build passed (4,398 modules); old runtime namespace/path scan passed.

## Rollback
`git revert <migration-commit>`; no database rollback required.
