# Migration Proposal: SYSTEM Authorization Manager

Status: `EXECUTED_AND_VERIFIED`

## Scope
Grouped the authorization manager page, shared role-manager panels, page controller, and API controller under SYSTEM/Governance/Authorization. Shared authorization resolver/resource/menu/audit services and Menu Approval Matrix remain separate shared boundaries.

## Paths
```text
app/Http/Controllers/AdminRoleManagerController.php
→ app/Http/Controllers/SYSTEM/Governance/Authorization/AdminRoleManagerController.php
app/Http/Controllers/SystemAuthController.php
→ app/Http/Controllers/SYSTEM/Governance/Authorization/SystemAuthController.php
resources/js/Pages/Modules/Administrator/SystemManager/*
→ resources/js/Pages/SYSTEM/Governance/Authorization/SystemManager/*
resources/js/Pages/Modules/Administrator/shared/role-manager/*
→ resources/js/Pages/SYSTEM/Governance/Authorization/role-manager/*
```

## Preserved
`/admin/system-manager`, `/admin/role-manager`, `/api/v1/system-auth/*`, middleware and authorization semantics, registry page keys, shared services, and database contracts.

## Verification
PHP syntax/class loading passed; frontend build passed (4,398 modules); `AuthorizationScopeResolverTest` passed (9 tests, 22 assertions).

## Rollback
`git revert <migration-commit>`; no database rollback required.
