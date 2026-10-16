# Feature Mapping: SYSTEM Authorization Manager

Status: `GROUPED_AND_VERIFIED_REGISTRY_MISMATCH_RETAINED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `systemAuthManagerPage` dan implementation chain manajemen authorization. Tidak ada source file yang dipindahkan atau diubah pada fase ini.

## Registry basis and mismatch

```text
Page mapping  : systemAuthManagerPage
Registry Id   : 1
Registry code : ESS
Registry name : Employee Self Service
Flag_Aktif    : Y
``

Live registry saat snapshot menempatkan `systemAuthManagerPage` pada `ESS`, tetapi source dan proses bisnisnya jelas merupakan governance authorization/system administration. Ini dicatat sebagai `REGISTRY_SOURCE_MISMATCH`; jangan memindahkan atau membuat module registry baru secara otomatis.

```text
Source URL    : /admin/system-manager
Authorization : systemAuthManagerPage / superAdminManajemenPeranPage
Backend guard : auth, check.jabatan, audit.admin.transaksi, authorize.resource
``

## Proposed ownership

```text
Module    : SYSTEM                         # BUSINESS/TECHNICAL PROPOSAL
Submodule : Governance / Authorization     # PROPOSAL_REVIEW_REQUIRED
Feature   : System Authorization Manager   # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Services, Page, Components, Routes, Tests, Registry
``

The proposed SYSTEM ownership requires maintainer/registry decision because the current live page mapping points to ESS. Role names such as System Admin are authorization scope, not sufficient proof of registry module ownership.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Page controller | `app/Http/Controllers/AdminRoleManagerController.php` | `indexSystem()` renders SystemManager page | `SHARED_AUTH_LEGACY_KEEP` |
| API controller | `app/Http/Controllers/SystemAuthController.php` | 2,600+ lines; governance, menu, role resource, distribution, matrix, monitoring | `SHARED_AUTH_LEGACY_KEEP` |
| Authorization resolver | `app/Services/AuthorizationScopeResolver.php` | page/role/scope resolution and system admin checks | `SHARED_KEEP` |
| Resource registry | `app/Services/AuthorizationResourceRegistryService.php` | governed resource validation | `SHARED_KEEP` |
| Menu binding | `app/Services/MenuBindingService.php` | module/page binding and capability changes | `SHARED_KEEP` |
| Delegation policy | `app/Services/MenuDelegationPolicyService.php` | menu delegation configuration | `SHARED_KEEP` |
| Audit | `app/Services/AuthorizationAuditLogger.php` and `app/Http/Middleware/AuditAdminTransaction.php` | authorization mutation/audit boundary | `SHARED_KEEP` |
| Web route | `routes/kpi/ridhoDevEvo.php` | `/admin/system-manager` and `/admin/role-manager` | `LEGACY_KEEP` |
| API routes | `routes/kpi/ridhoDevEvo.php` | `/api/v1/system-auth/*` | `LEGACY_KEEP` |
| Page | `resources/js/Pages/SYSTEM/Governance/Authorization/SystemManager/index.vue` | system authorization page | `LEGACY_KEEP` |
| Shared panels | `resources/js/Pages/SYSTEM/Governance/Authorization/role-manager/` | governance, roles, scopes, menu, distribution, matrix, monitoring | `SHARED_FEATURE_KEEP` |
| Related page | `resources/js/Pages/Modules/Administrator/PagesAccessSystem*` | page access management | `RELATED_FEATURE_REVIEW` |
| Backend tests | `tests/Integration/SYSTEM/Governance/AuthorizationScopeResolverTest.php` | authorization scope behavior | `RELATED_TEST` |
| Frontend test | no dedicated SystemManager page test found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Important findings

1. This is not an ESS business feature merely because the live page mapping currently points to ESS. It is a registry/source mismatch that needs an explicit ownership decision.
2. `SystemAuthController` is a broad shared authorization controller, not a safe candidate for simple folder relocation.
3. The page shares components and services with role manager, page access, menu binding, capability, delegation, audit, and permission matrix flows.
4. Authorization mutation, audit, and database registry are shared boundaries; any refactor requires a separate checkpoint and wider testing.
5. No dedicated frontend/browser test was found for the full system manager page.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/SYSTEM/Governance/Authorization/SystemAuthorizationPageController.php
app/Http/Controllers/SYSTEM/Governance/Authorization/SystemAuthorizationApiController.php
app/Services/SYSTEM/Governance/Authorization/
routes/SYSTEM/Governance/Authorization/SystemAuthorization.php
resources/js/Pages/SYSTEM/Governance/Authorization/SystemAuthorizationManagerPage.vue
resources/js/Pages/SYSTEM/Governance/Authorization/components/
tests/<type>/SYSTEM/Governance/Authorization/
``

These targets require a registry decision and a separate authorization refactor scope. They are not approved movement paths.

## Dependency and risk checklist

Before any move, verify:

- live registry correction or explicit decision for `systemAuthManagerPage`;
- all web/API routes and route loader order;
- authorization resolver, middleware, resource registry, and audit behavior;
- all shared role-manager/page-access panels and API endpoints;
- menu binding/capability/delegation/permission matrix contracts;
- database registry and authorization audit tables;
- focused authorization tests, frontend tests, browser authorization matrix, and regression scope;
- no database mutation, DDL, DML, or permission change is part of a file move.

## Decision gate

Current decision: `BLOCKED_ON_REGISTRY_DECISION`; no move.

Before any structural change, maintainer must decide:

1. whether `systemAuthManagerPage` should be remapped from ESS to SYSTEM;
2. the intended SYSTEM/Governance/Authorization hierarchy;
3. whether role manager and page access are separate features or one authorization suite;
4. whether controller decomposition is included;
5. authorization/audit/database test scope;
6. exact old-path → new-path map and rollback procedure.

Until then, all current files remain in place.
