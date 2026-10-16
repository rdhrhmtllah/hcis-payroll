# Feature Mapping: VISITOR Permit Kendaraan Self-Service

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `visitorPermitPage` dan implementation chain existing. Controller, route, halaman, komponen lokal, dan test namespace telah diselaraskan; query/approval/effective services tetap shared.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : VISITOR
Id            : 4
Nama         : Visitor Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : visitorPermitPage
Id_Grup_Fitur : 4
Flag_Aktif   : Y

Menu/URL      : /transaksipermit
Menu label    : Permit Kendaraan
Scope         : self-service melalui check.jabatan:visitorPermitPage
``

Registry memverifikasi module dan page mapping. Registry belum memiliki kolom formal `Submodule` atau `Feature`; hierarchy berikut adalah proposal berdasarkan domain dan implementasi source.

## Proposed ownership

```text
Module    : VISITOR                         # VERIFIED_MODULE
Submodule : Permit                          # PROPOSAL_REVIEW_REQUIRED
Feature   : Vehicle Permit Self-Service     # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Service, Page, Components, Route, Test, Storage/DDL docs
``

`self-service`, `Admin`, dan `Verifikator` adalah access/workflow concerns. Mereka tidak otomatis menjadi module baru. Approval dan verification yang memakai tabel permit yang sama harus dipetakan sebagai related workflow, bukan dicampur tanpa scope.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php` | index, store, show, destroy, list, stats, master, upload | `MIGRATED` |
| Controller stub | `app/Http/Controllers/TransaksiPermit/TransaksiPermitApi.php` | API resource dinonaktifkan; dipertahankan karena legacy scope | `MIGRATED` |
| Shared query service | `app/Services/Visitor/VisitorPermitQueryService.php` | permit query/checklist dipakai lintas approval/verifikator/laporan | `SHARED_KEEP` |
| Approval policy | `app/Services/Visitor/VisitorApprovalPolicyService.php` | page config, requester, approval flow | `SHARED_KEEP` |
| Effective projection | `app/Services/Visitor/VisitorEfektifService.php` | sync/revoke `N_VISITOR_Permit_Efektif` | `RELATED_SHARED_KEEP` |
| Route | `routes/VISITOR/Permit/VehiclePermit/VehiclePermit.php` | resource + list/my/stats/master/upload | `MIGRATED` |
| Route loader | `routes/kpi/ridhoDevEvo.php` | requires TransaksiPermit web/API routes | `LEGACY_KEEP` |
| Page | `resources/js/Pages/VISITOR/Permit/VehiclePermit/VehiclePermitPage.vue` | Inertia page returned by controller | `LEGACY_KEEP` |
| Feature component | `resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitForm.vue` | new permit submission | `LEGACY_KEEP` |
| Feature component | `resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitStatusList.vue` | active/history list and detail | `LEGACY_KEEP` |
| Reusable UI dependency | `resources/js/Pages/VISITOR/modules/permit/...` imports KPI UI components | shared/legacy cross-module dependency | `DEPENDENCY_REVIEW` |
| Direct frontend API | `permit.vue`, `PermitForm.vue`, `PermitStatusList.vue` | fetch/axios calls to transaksipermit endpoints | `LEGACY_KEEP` |
| Unit test | `tests/Unit/VISITOR/Permit/TransaksiPermitDocumentStorageTest.php` | storage behavior only | `RELATED_TEST` |
| Feature/browser tests | no dedicated `visitorPermitPage` test found | search of tests | `TEST_GAP_REVIEW_REQUIRED` |
| Registry DDL/DML | `database/visitor/2026_07_29_fase3_rbac_visitor_menus.sql` | menu/page seed definition | `LEGACY_KEEP` |
| Projection DDL | `database/visitor/2026_07_30_visitor_efektif_projection.sql` | effective permit projection | `LEGACY_KEEP` |

## Related workflow boundaries

Permit data is also consumed by these existing paths:

```text
Approval:    app/Http/Controllers/ApprovalVisitor/
Verification: app/Http/Controllers/VerifikatorVisitor/
Reporting:   app/Http/Controllers/LaporanVisitor/
``

These are not included in a self-service-only move. Any future relocation must either map them in the same approved batch or explicitly preserve cross-boundary imports through a compatibility step.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/VISITOR/Permit/VehiclePermitController.php
app/Services/VISITOR/Permit/VehiclePermitService.php
routes/VISITOR/Permit/VehiclePermit.php
resources/js/Pages/VISITOR/Permit/VehiclePermit/VehiclePermitPage.vue
resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitForm.vue
resources/js/Pages/VISITOR/Permit/VehiclePermit/components/PermitStatusList.vue
tests/<type>/VISITOR/Permit/VehiclePermit/
``

These paths intentionally do not prescribe a model move: current implementation uses query builder/table names and has no dedicated Eloquent model for the complete transaction chain.

## Dependency and risk checklist

Before any move, verify:

- all route loader references from `routes/kpi/ridhoDevEvo.php`;
- `check.jabatan:visitorPermitPage` middleware and menu authorization behavior;
- Inertia page resolution and import paths;
- frontend fetch/axios endpoint paths;
- shared `VisitorPermitQueryService`, approval policy, and effective projection callers;
- GCS document upload and cleanup behavior;
- transaction boundary for permit submission and upload;
- route model binding/resource route names;
- tests for submit, upload failure cleanup, status list, delete/revoke, approval, and effective projection;
- no database mutation or DDL as part of a file move.

## Decision gate

Current decision: `migrated and verified`; approval, verification, reporting, query, and effective projection remain separate/shared boundaries.

A future move requires explicit confirmation of:

1. `VISITOR/Permit/Vehicle Permit Self-Service` as the intended hierarchy;
2. whether controller/service are moved together or preserved as compatibility wrappers;
3. whether related approval, verification, and reporting paths are a separate batch;
4. whether route loader nesting is allowed to change;
5. the test gap remediation scope;
6. exact old-path → new-path map and rollback procedure.

Verification: PHP syntax, Composer class loading, focused storage test (1 test/3 assertions), frontend build (4,401 modules), old-reference scan, and diff checks passed. No database change.
