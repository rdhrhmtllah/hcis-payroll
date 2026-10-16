# Feature Mapping: LMS Training Catalog

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `LMSTrainingCatalogPage` dan implementation chain training catalog existing. Batch path alignment telah dieksekusi; behavior, URL, API, database, dan shared service contract tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : LMSTrainingCatalogPage
Id_Grup_Fitur : 3
Flag_Aktif   : Y

Web URL      : /training-catalog
Route API    : /lms/training/*
Middleware   : auth, check.jabatan:LMSTrainingCatalogPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : LMS                          # VERIFIED_MODULE
Submodule : Training / Catalog           # PROPOSAL_REVIEW_REQUIRED
Feature   : Training Catalog              # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Service, Page, Components, Route, API, Tests
``

Training detail, schedule, progress, dan third-party reassignment adalah dependency/related workflow sampai mapping terpisah membuktikan boundary feature masing-masing.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Web controller | `app/Http/Controllers/LMS/Training/Catalog/TrainingCatalogController.php` | catalog index, filter options, detail/schedule responses | `MIGRATED` |
| API controller | `app/Http/Controllers/LMS/Training/Catalog/TrainingCatalogApi.php` | detail endpoint; unused CRUD stubs remain | `MIGRATED` |
| Query service | `app/Services/LMS/TrainingScheduleQueryService.php` | enrolled schedule/package/material queries | `SHARED_KEEP` |
| Progress service | `app/Services/LMS/TrainingProgressReadService.php` | catalog progress/status calculation | `RELATED_SHARED_KEEP` |
| Web route | `routes/LMS/Training/Catalog/TrainingCatalog.php` | `/lms/training/catalog`, filter, schedule, detail, API detail | `MIGRATED` |
| API route | `routes/TrainingCatalog/TrainingCatalogApi.php` | duplicate `/api/v1/lms/training/{id}` route/controller path | `REVIEW_REQUIRED` |
| Route loader | `routes/kpi/ridhoDevEvo.php`, `routes/api.php` | route requires | `LEGACY_KEEP` |
| Page | `resources/js/Pages/LMS/Training/Catalog/TrainingCatalogPage.vue` | Inertia catalog page | `LEGACY_KEEP` |
| Page components | `resources/js/Pages/LMS/modules/training/components/ui/` | card, drawer, pagination, toolbar | `FEATURE_LOCAL_KEEP` |
| Page data | `resources/js/Pages/LMS/modules/training/data/` | mock data and copy variant | `REVIEW_REQUIRED` |
| Detail related page | `resources/js/Pages/LMS/modules/training/detail/trainingDetail.vue` | opened from training/catalog flow | `RELATED_LEGACY_KEEP` |
| Frontend tests | no dedicated `LMSTrainingCatalogPage` test found | repository test search | `TEST_GAP_REVIEW_REQUIRED` |
| Related backend tests | LMS/SOP tests exist, but no direct catalog suite found | repository test search | `RELATED_TEST` |
| Registry/menu artifacts | existing LMS/visitor menu seed and change docs | page registry source | `LEGACY_KEEP` |

## Important findings

1. The implementation has both `TrainingCatalogController` and `TrainingCatalogApi`; their endpoint overlap must be resolved by inspection before any route move.
2. `TrainingCatalogApi` contains unimplemented CRUD methods. This is not a reason to delete or refactor it in the structure phase.
3. The page imports shared UI from other page areas, including KPI components. These imports are migration risk.
4. The page contains direct Axios calls and a large Options API component; splitting it is behavior/refactor scope, not a move-only operation.
5. No dedicated frontend or browser test named for `LMSTrainingCatalogPage` was found. This is a quality gap to track separately.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/Training/Catalog/TrainingCatalogController.php
app/Http/Controllers/LMS/Training/Catalog/TrainingCatalogApi.php
app/Services/LMS/Training/Catalog/
routes/LMS/Training/Catalog/TrainingCatalog.php
resources/js/Pages/LMS/Training/Catalog/TrainingCatalogPage.vue
resources/js/Pages/LMS/Training/Catalog/components/
resources/js/Pages/LMS/Training/Catalog/api/
tests/<type>/LMS/Training/Catalog/
``

The exact target naming is subject to maintainer approval. Existing page name `trainingCatalog.vue` should not be renamed as part of a broad structure batch without checking Inertia/Vite discovery and all imports.

## Dependency and risk checklist

Before any move, verify:

- `/training-catalog` route source and route loader;
- `LMSTrainingCatalogPage` authorization behavior;
- duplicate API route registration and controller ownership;
- Inertia page resolution and dynamic imports;
- direct Axios endpoint paths;
- TrainingScheduleQueryService and TrainingProgressReadService callers;
- training detail/schedule navigation;
- shared KPI/UI component imports;
- mock data and any runtime fallback behavior;
- dedicated catalog test plan and browser verification;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated in approved small batch`.

A future move requires explicit confirmation of:

1. `LMS/Training/Catalog` hierarchy;
2. whether catalog and detail are one feature or separate related features;
3. which API controller owns `/api/v1/lms/training/{id}`;
4. whether direct Axios calls are kept or separately refactored;
5. test-gap remediation scope;
6. exact old-path → new-path map and rollback procedure.

Execution record: commits `caf241bf` and `ecf9cbfa`. Old generic API stubs were moved without cleanup/refactor. Route list verification remains blocked by pre-existing Google ADC initialization; frontend build passed.
