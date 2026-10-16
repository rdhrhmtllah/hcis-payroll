# Feature Mapping: KPI Form Parameter

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `FormKPIPage` dan jalur pengisian parameter KPI existing. Combined Form KPI controller, Team KPI compatibility controller, pages, and local team screens have been grouped under KPI/Performance/Parameter; services and contracts remain unchanged.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : KPI
Id            : 2
Nama         : Key Performance Indicator
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : FormKPIPage
Id_Grup_Fitur : 2
Flag_Aktif   : Y

URL           : /form-kpi
Related page  : FormKpiTeam -> /form-kpi/team
Middleware    : auth, check.jabatan:FormKPIPage / FormKpiTeam
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom registry.

## Proposed ownership

```text
Module    : KPI                         # VERIFIED_MODULE
Submodule : Performance / Parameter     # PROPOSAL_REVIEW_REQUIRED
Feature   : Form KPI Parameter          # PROPOSAL_REVIEW_REQUIRED
Related   : Form KPI Team                # related feature; separate access/page mapping
Layer     : Controller, Service, Page, Route, API, Tests
``

Nama `FormKPIPage` dan `FormKpiTeam` adalah page/authorization identifiers. Keduanya tidak otomatis menjadi nama module atau submodule.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/KPI/Performance/Parameter/FormKpiController.php` | individual/team page and API methods | `MIGRATED` |
| Service dependency | `app/Services/KPI/` | KPI services used by controller/related flows | `DEPENDENCY_REVIEW` |
| Approval dependency | `app/Services/Approval/KpiApprovalService.php` | referenced by KPI parameter/performance flows | `RELATED_SHARED_KEEP` |
| Route | `routes/kpi/ridhoDevEvo.php` | `/form-kpi`, `/api/v1/form-kpi/*`, team routes | `MIGRATED` |
| Page | `resources/js/Pages/KPI/Performance/Parameter/FormKpi/formKPI.vue` | individual parameter entry | `LEGACY_KEEP` |
| Related page | `resources/js/Pages/KPI/Performance/Parameter/FormKpi/team/FormKPITeam.vue` | team parameter entry | `MIGRATED` |
| API calls | inside `formKPI.vue` and `team/FormKPITeam.vue` | direct Axios calls | `DEPENDENCY_REVIEW` |
| Feature tests | `tests/Feature/KPI/Performance/KpiParameterStartPeriodeTest.php` | API and period-start behavior | `RELATED_TEST` |
| Feature tests | `tests/Feature/SYSTEM/Governance/DetailAnggotaDualEngineTest.php` | team detail behavior | `RELATED_TEST` |
| Feature tests | `tests/Feature/KPI/Performance/JadwalNonParameterTidakIkutTest.php` | schedule filtering | `RELATED_TEST` |
| Feature tests | `tests/Feature/KPI/Performance/KpiTeamGridJadwalAnggotaTest.php` | team grid behavior | `RELATED_TEST` |
| Feature tests | `tests/Feature/KPI/Performance/RevisiTidakBolehBercampurTest.php` | revision rules | `RELATED_TEST` |
| Notification dependency | `app/Services/Notification/KpiNotificationService.php` | `/form-kpi` and `/form-kpi/team` action URLs | `SHARED_KEEP` |
| Homepage dependency | `app/Services/Homepage/HomepagePersonalDashboardService.php` | action links to form KPI | `SHARED_KEEP` |

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/KPI/Performance/Parameter/FormKpiController.php
app/Services/KPI/Performance/Parameter/
routes/KPI/Performance/Parameter/FormKpi.php
resources/js/Pages/KPI/Performance/Parameter/FormKpi/FormKpiPage.vue
resources/js/Pages/KPI/Performance/Parameter/FormKpi/team/FormKpiTeamPage.vue
tests/Feature/KPI/Performance/Parameter/FormKpi/
``

The controller currently combines individual and team methods. Splitting it is not a file-structure-only move; it would require a separate behavior/refactor scope. A safe first move, if approved later, may therefore preserve the controller filename and namespace while only relocating a whole bounded feature chain.

## Dependency and risk checklist

Before any move, verify:

- every route in `routes/kpi/ridhoDevEvo.php`, including shared `/api/v1/form-kpi` endpoints;
- authorization identifiers `FormKPIPage` and `FormKpiTeam`;
- Inertia page resolution and direct API imports/calls;
- notification and homepage action URLs;
- KPI approval, schedule, formula, target, and transaction-number dependencies;
- individual/team behavior separation;
- transaction and after-commit behavior in controller methods;
- all related Feature tests and any frontend tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; individual/team controller remains a combined compatibility boundary.

A future move requires explicit confirmation of:

1. `KPI/Performance/Parameter/Form KPI` hierarchy;
2. whether individual and team pages are one feature or two related features;
3. whether the combined controller moves as-is or remains a compatibility boundary;
4. route file and namespace migration scope;
5. test relocation versus compatibility test paths;
6. exact old-path → new-path map and rollback procedure.

Verification: PHP syntax, Composer class loading, frontend build (4,398 modules), and KPI focused suite passed (35 tests, 89 assertions). No database or behavior change.
