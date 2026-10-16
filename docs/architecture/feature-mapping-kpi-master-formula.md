# Feature Mapping: KPI Master Formula

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `MasterFormula` dan CRUD master formula KPI existing. Controller, route import, halaman formula, dan komponen lokal telah dipindahkan; scoring service, Satuan Formula, dan database tetap shared/related.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : KPI
Id            : 2
Nama         : Key Performance Indicator
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : MasterFormula
Id_Grup_Fitur : 2
Flag_Aktif   : Y

URL           : /formula-kpi
Middleware    : auth, check.jabatan:MasterFormula
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : KPI                         # VERIFIED_MODULE
Submodule : Master Data / Scoring       # PROPOSAL_REVIEW_REQUIRED
Feature   : KPI Formula Master          # PROPOSAL_REVIEW_REQUIRED
Related   : Satuan Formula, Scoring     # separate/related boundaries
Layer     : Controller, Page, Route, Tests, Database artifact references
``

`MasterFormula` adalah page/access identifier, bukan bukti formal submodule. `KpiScoringService` memakai formula master sebagai runtime dependency dan harus diperlakukan shared.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/KPI/MasterData/Scoring/Formula/FormulaKpiController.php` | index/create/edit/current/by/store/update/toggle | `MIGRATED` |
| Route | `routes/kpi/fransDevEvo.php` | `/formula-kpi*` and `/api/v1/formula-kpi/*` | `MIGRATED` |
| List page | `resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaListPage.vue` | current list/toggle/edit/create navigation | `LEGACY_KEEP` |
| Create page | `resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaCreatePage.vue` | create form and store call | `MIGRATED` |
| Edit page | `resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaEditPage.vue` | edit form and update call | `MIGRATED` |
| Scoring service | `app/Services/KPI/KpiScoringService.php` | formula resolution in KPI scoring | `SHARED_KEEP` |
| Related controller | `app/Http/Controllers/SatuanFormulaKpiController.php` | related unit/formula master page | `RELATED_LEGACY_KEEP` |
| Related page | `resources/js/Pages/KPI/modules/satuanFormula/` | related formula unit flow | `RELATED_LEGACY_KEEP` |
| Tests | no dedicated Formula feature test found | repository search | `TEST_GAP_REVIEW_REQUIRED` |
| Database references | KPI master formula tables in controller queries | source inspection | `DEPENDENCY_REVIEW` |

## Important findings

1. Formula master is a runtime dependency for staff/CEO dashboards, performance approval, and update performance; a move must preserve table/query contracts.
2. The controller is a legacy CRUD controller with direct validation, DB access, and logging; relocating it is not behavior-neutral if namespace/import/route binding changes.
3. Create/edit/list pages are separate files but share endpoint and navigation contracts.
4. `SatuanFormulaKpiController` is related but should not be moved in the same batch without explicit boundary approval.
5. No dedicated backend/frontend/browser test for the formula master feature was found.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/KPI/MasterData/Scoring/FormulaKpiController.php
routes/KPI/MasterData/Scoring/FormulaKpi.php
resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaListPage.vue
resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaCreatePage.vue
resources/js/Pages/KPI/MasterData/Scoring/Formula/FormulaEditPage.vue
tests/<type>/KPI/MasterData/Scoring/Formula/
``

These are proposals only; page renaming or controller namespace changes require a separate approved migration.

## Dependency and risk checklist

Before any move, verify:

- all formula route names, direct Axios paths, and `window.location` navigation;
- `MasterFormula` authorization and registry mapping;
- formula table names/columns and active flag semantics;
- `KpiScoringService`, dashboard, approval, and update-performance callers;
- create/update/toggle validation and audit expectations;
- focused CRUD, authorization, frontend, and browser tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; KpiScoringService and Satuan Formula remain separate shared/related boundaries.

A future move requires explicit confirmation of hierarchy, related Satuan Formula boundary, controller/page naming strategy, test-gap remediation, exact old-path → new-path map, and rollback procedure.

Verification: PHP syntax, Composer class loading, frontend build, and static old-reference scan passed. No dedicated Formula feature test was found; behavior coverage remains a documented test gap.
