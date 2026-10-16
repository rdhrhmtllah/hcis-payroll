# Feature Mapping: KPI Staff Dashboard

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `dashboardStaff` dan dashboard performa KPI personal. Controller dan halaman staff telah dipindahkan; route file tetap shared dengan KPI Formula/Satuan Formula dan KpiScoringService tetap shared.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : KPI
Id            : 2
Nama         : Key Performance Indicator
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : dashboardStaff
Id_Grup_Fitur : 2
Flag_Aktif   : Y

URL           : /dashboard-my-performance
Middleware    : auth, check.jabatan:dashboardStaff
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : KPI                         # VERIFIED_MODULE
Submodule : Performance / Dashboard     # PROPOSAL_REVIEW_REQUIRED
Feature   : Staff Performance Dashboard  # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Services, Page, Route, Tests
``

`dashboardCeo` adalah related KPI dashboard dengan scope akses dan data berbeda; tidak dimasukkan ke mapping staff ini.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/KPI/Performance/Dashboard/StaffPerformanceDashboardController.php` | index, personal performance API/detail, score aggregation | `MIGRATED` |
| Route | `routes/kpi/fransDevEvo.php` | `/dashboard-my-performance` and `/api/v1/dashboard-my-performance/*` | `MIGRATED` |
| Page | `resources/js/Pages/KPI/Performance/Dashboard/Staff/StaffPerformanceDashboardPage.vue` | Inertia page returned by controller | `LEGACY_KEEP` |
| Scoring service | `app/Services/KPI/KpiScoringService.php` | canonical score/cap behavior used by controller | `SHARED_KEEP` |
| KPI service dependencies | `app/Services/KPI/` | formula/period/performance related services | `DEPENDENCY_REVIEW` |
| Storage/cache | controller cache and DB reads | performance bands and KPI aggregate reads | `DEPENDENCY_REVIEW` |
| Feature/browser tests | no dedicated dashboardStaff test found | repository search | `TEST_GAP_REVIEW_REQUIRED` |
| Related test | `tests/Feature/ESS/KpiLifecycleE2ETest.php` | lifecycle access/data path with dashboardStaff user | `RELATED_TEST` |

## Important findings

1. `DashboardStaffController` is a large legacy controller with score aggregation, period resolution, detail, storage/cache, and KPI read logic.
2. Score behavior delegates to `KpiScoringService`; that service must remain shared and cannot be moved as feature-local without caller analysis.
3. `dashboardCeo` shares the KPI dashboard domain but has separate authorization and endpoint scope.
4. No dedicated frontend/browser test for the staff dashboard page was found.
5. The controller and route live in generic/root KPI legacy locations, so a move would require Inertia page discovery, route loader, API, and test verification.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/KPI/Performance/Dashboard/StaffPerformanceDashboardController.php
app/Services/KPI/Performance/Dashboard/
routes/KPI/Performance/Dashboard/StaffPerformanceDashboard.php
resources/js/Pages/KPI/Performance/Dashboard/Staff/StaffPerformanceDashboardPage.vue
tests/<type>/KPI/Performance/Dashboard/Staff/
``

These targets are not approved movement paths. Splitting scoring/aggregation from the legacy controller would be a separate behavior refactor.

## Dependency and risk checklist

Before any move, verify:

- all staff dashboard web/API route references and loader order;
- `dashboardStaff` authorization and registry mapping;
- Inertia page resolution and frontend API calls;
- `KpiScoringService` and other KPI service callers;
- performance band cache keys and invalidation behavior;
- period, approval, verification, and detail query behavior;
- focused backend, frontend, and browser test plan;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; CEO dashboard and scoring services remain separate/shared.

A future move requires explicit confirmation of hierarchy, staff/CEO dashboard boundaries, controller extraction strategy, frontend scope, test-gap remediation, exact old-path → new-path map, and rollback procedure.

Verification: PHP syntax, Composer class loading, frontend build, old-reference scan, and diff checks passed. Related `KpiLifecycleE2ETest` had 8 passed, 4 skipped, and 1 pre-existing bypass endpoint failure (HTTP 500), outside this path-only batch.
