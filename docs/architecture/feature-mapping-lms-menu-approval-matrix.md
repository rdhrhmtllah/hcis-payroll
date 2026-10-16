# Feature Mapping: LMS Menu & Approval Matrix

Status: `GROUPED_SHARED_AUTHORIZATION_BOUNDARY_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `menuApprovalMatrixLmsPage` ke implementation Menu & Approval Matrix existing. Tidak ada source file yang dipindahkan atau diubah pada fase ini.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuApprovalMatrixLmsPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Page URL      : /admin/lms/menu-approval-matrix
Route access  : menuApprovalMatrixPage, menuApprovalMatrixLmsPage,
                menuApprovalMatrixEssPage, menuApprovalMatrixKpiPage,
                menuApprovalMatrixVisitorPage
``

Registry memverifikasi page mapping ke module `LMS`. Namun implementation adalah satu shared Menu & Approval Matrix page untuk ESS, KPI, LMS, dan VISITOR. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Registry module : LMS                       # VERIFIED_MODULE
Business area   : Governance / Authorization # SHARED_BOUNDARY
Feature         : LMS Menu & Approval Matrix # PROPOSAL_REVIEW_REQUIRED
Shared page     : Menu & Approval Matrix    # SHARED_KEEP
Layer           : Controller, Service, Page, Components, Routes, Tests
``

Role atau module-specific page key tidak boleh diterjemahkan menjadi folder role. Ownership LMS di sini adalah context/grup fitur, sedangkan engine authorization dan matrix tetap shared.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/MenuApprovalMatrixController.php` | index per grup, bootstrap, menu, membership, matrix, config APIs | `SHARED_LEGACY_KEEP` |
| Core service | `app/Services/Approval/ApprovalMatrixService.php` | capability, group scope, config, matrix, approver resolution | `SHARED_KEEP` |
| Menu service | `app/Services/MenuBindingService.php` | menu bindings/history/bulk operations | `SHARED_KEEP` |
| Membership service | `app/Services/MembershipManagementService.php` | membership overview/assign/remove/history | `SHARED_KEEP` |
| Target scope | `app/Services/EmployeeTargetScopeService.php` | employee target filtering and scope | `SHARED_KEEP` |
| Main page | `resources/js/Pages/SYSTEM/Governance/Authorization/MenuApprovalMatrix/menusnApprovals.vue` | shared page with active group context | `SHARED_KEEP` |
| Matrix tab | `resources/js/Pages/SYSTEM/Governance/Authorization/MenuApprovalMatrix/components/ApprovalMatrixTab.vue` | approval config/assignment/history UI | `SHARED_KEEP` |
| Related tabs | `MenuTab.vue`, `MembershipTab.vue` | menu binding and membership UI | `SHARED_KEEP` |
| Route | `routes/kpi/ridhoDevEvo.php` | group-specific pages and shared API endpoints | `SHARED_LEGACY_KEEP` |
| Audit | `app/Http/Middleware/AuditAdminTransaction.php` | mutation audit for matrix/menu operations | `SHARED_KEEP` |
| Tests | `tests/Integration/SYSTEM/Governance/AuthorizationScopeResolverTest.php` | authorization scope baseline | `RELATED_TEST` |
| Dedicated LMS matrix suite | not found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET /admin/lms/menu-approval-matrix
GET /api/v1/menu-approval-matrix/bootstrap/{grupId}
GET /api/v1/menu-approval-matrix/issue-counts/{grupId}

Shared menu endpoints:
GET/POST /api/v1/menu-approval-matrix/menu-binding/{grupId}
POST     /api/v1/menu-approval-matrix/menu-binding/{grupId}/bulk
GET      /api/v1/menu-approval-matrix/menu-binding/{grupId}/history
GET      /api/v1/menu-approval-matrix/menu-binding/{grupId}/summary
POST     /api/v1/menu-approval-matrix/menu-binding/{grupId}/purge-stale

Shared membership endpoints:
GET/POST /api/v1/menu-approval-matrix/membership/{grupId}/*

Shared approval endpoints:
GET      /api/v1/menu-approval-matrix/configs
GET      /api/v1/menu-approval-matrix/approval-summary
GET      /api/v1/menu-approval-matrix/configs/{id}/matrix
GET/POST /api/v1/menu-approval-matrix/assignments*
GET/POST/PUT/DELETE /api/v1/menu-approval-matrix/config*
GET/POST/PUT/DELETE /api/v1/menu-approval-matrix/jenis-approval*
``

## Important findings

1. `menuApprovalMatrixLmsPage` bukan implementation page yang berdiri sendiri. Ia memilih context `LMS` pada shared `MenuApprovalMatrixController` dan shared Inertia page.
2. Satu route group menerima beberapa page key: ESS, KPI, LMS, dan VISITOR. Pemindahan file ke folder LMS akan salah ownership karena akan memisahkan shared authorization engine.
3. `ApprovalMatrixService` mengatur capability tab, group scope, System Admin assertion, config/step/jenis approval, serta resolusi approver. Service ini shared dan sensitif terhadap authorization.
4. `MenuBindingService`, `MembershipManagementService`, `EmployeeTargetScopeService`, dan audit middleware membentuk shared mutation boundary.
5. Ada perbedaan antara context page LMS dan aksi konfigurasi: sebagian aksi struktur hanya boleh System Admin, sedangkan assignment granular mengikuti capability resource access.
6. Belum ada test khusus lengkap untuk route/page context LMS. Test authorization scope yang ada hanya memberi coverage related, bukan bukti penuh seluruh UI/API LMS matrix.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/SYSTEM/Governance/MenuApprovalMatrixController.php
app/Services/SYSTEM/Governance/ApprovalMatrix/
routes/SYSTEM/Governance/MenuApprovalMatrix.php
resources/js/Pages/SYSTEM/Governance/MenuApprovalMatrix/
tests/<type>/SYSTEM/Governance/MenuApprovalMatrix/
``

Proposal ini menunjukkan shared business ownership, bukan instruksi pemindahan. Karena implementation juga melayani empat module context, perubahan path memerlukan checkpoint global authorization.

## Dependency and risk checklist

Before any move, verify:

- registry mapping `menuApprovalMatrixLmsPage` versus shared page keys;
- all ESS/KPI/LMS/VISITOR route callers and active group resolution;
- capability/resource access rules and System Admin assertions;
- menu binding, membership, approval assignment, and rollback/audit contracts;
- `EmployeeTargetScopeService` and authorization scope behavior;
- frontend active group, tabs, capability badges, and API URLs;
- focused authorization, mutation audit, LMS context, and browser tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `grouped shared authorization boundary`; ownership remains SYSTEM/shared, not LMS-only.

This mapping requires a separate global authorization checkpoint before any physical migration, namespace change, route change, service split, or frontend relocation.

Until approved, all current files remain in place.
