# Migration Proposal: VISITOR Approval, Verification, and Reporting

Status: `EXECUTED_AND_VERIFIED`

## Grouping
Approval, verification, and reporting source boundaries are grouped under `VISITOR/Workflow` while retaining their independent route files and contracts. Shared Visitor query, approval, policy, effective projection, storage, and reporting services remain unchanged.

## Paths
```text
app/Http/Controllers/ApprovalVisitor/*
→ app/Http/Controllers/VISITOR/Workflow/Approval/*
app/Http/Controllers/VerifikatorVisitor/*
→ app/Http/Controllers/VISITOR/Workflow/Verification/*
app/Http/Controllers/LaporanVisitor/*
→ app/Http/Controllers/VISITOR/Workflow/Reporting/*
routes/ApprovalVisitor/*
→ routes/VISITOR/Workflow/Approval/*
routes/VerifikatorVisitor/*
→ routes/VISITOR/Workflow/Verification/*
routes/LaporanVisitor/*
→ routes/VISITOR/Workflow/Reporting/*
resources/js/Pages/VISITOR/modules/approvalPermit/*
→ resources/js/Pages/VISITOR/Workflow/Approval/ApprovalPermit/*
resources/js/Pages/VISITOR/modules/verifikator/*
→ resources/js/Pages/VISITOR/Workflow/Verification/Verifikator/*
resources/js/Pages/VISITOR/modules/laporan/*
→ resources/js/Pages/VISITOR/Workflow/Reporting/Laporan/*
```

## Preserved
All existing Visitor URLs, middleware, route names, API payloads, shared services, and database contracts.

## Verification
PHP syntax/class loading passed; frontend build passed; old namespace/path scan passed; no database change.

## Rollback
`git revert <migration-commit>`; no database rollback required.
