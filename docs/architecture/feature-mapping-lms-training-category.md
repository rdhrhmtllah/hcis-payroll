# Feature Mapping: LMS Training Category

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `LMSTrainingKategoriPage` dan pengelolaan kategori training existing. Batch path alignment telah dieksekusi; database contract dan generic API stub tetap dipertahankan.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : LMSTrainingKategoriPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

URL           : /lms/training-kategori
Middleware    : auth, check.jabatan:LMSTrainingKategoriPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : LMS                         # VERIFIED_MODULE
Submodule : Training / Master Data      # PROPOSAL_REVIEW_REQUIRED
Feature   : Training Category           # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Page, Route, Tests, DB contract
``

Kategori training adalah master data yang dipakai oleh admin training, training catalog, dan penjadwalan. Ownership feature tidak berarti tabel atau query otomatis boleh dipindahkan menjadi lokal tanpa caller analysis.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/Training/MasterData/TrainingCategory/TrainingCategoryController.php` | list, batch store, update, validation, hashid, transaction | `MIGRATED` |
| Generic API controller | `app/Http/Controllers/LmsTrainingKategori/LmsTrainingKategoriApi.php` | empty generic CRUD methods | `LEGACY_KEEP_UNUSED_REVIEW` |
| Web/API routes | `routes/LMS/Training/MasterData/TrainingCategory/TrainingCategory.php` | page plus `/api/v1/training/kategori/*` | `MIGRATED` |
| Generic route file | `routes/LmsTrainingKategori/LmsTrainingKategoriApi.php` | apiResource stub route | `LEGACY_KEEP_UNUSED_REVIEW` |
| Route loader | `routes/kpi/fransDevEvo.php` | route require | `LEGACY_KEEP` |
| Page | `resources/js/Pages/LMS/Training/MasterData/TrainingCategory/TrainingCategoryPage.vue` | search, pagination, batch create, edit | `MIGRATED` |
| DB table | `N_LMS_Training_Kategori` | direct controller reads/writes | `DEPENDENCY_REVIEW` |
| Tests | no dedicated `LMSTrainingKategoriPage` suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |
| Related tests | UAT/category/content tests reference adjacent LMS/SOP category behavior | repository search | `RELATED_TEST` |

## Route surface

```text
GET  /lms/training-kategori
GET  /api/v1/training/kategori/current
POST /api/v1/training/kategori/store
PUT  /api/v1/training/kategori/update/{id_training_kategori}
``

There is also a separate generic `apiResource` route file with empty controller methods. Its effective runtime usage and route-loader status require review; it is not treated as an active feature contract.

## Important findings

1. Controller memakai batch insert dengan duplicate-code validation, database transaction, audit user, dan Hashids untuk update. Perubahan boundary harus mempertahankan semua contract tersebut.
2. Kategori dipakai oleh training admin, catalog, dan scheduling sehingga tabel `N_LMS_Training_Kategori` merupakan shared data dependency.
3. Web/API route sudah memiliki endpoint custom, sementara generic `apiResource` stub masih ada. Ini adalah inventory finding, bukan izin untuk menghapus atau merapikan route pada fase structure-only.
4. Page frontend menggabungkan list, search, pagination, multi-row create, dan edit dalam satu page.
5. Tidak ditemukan test khusus untuk full CRUD page, authorization, dan browser flow registry ini. Test terkait tidak dianggap sebagai bukti penuh.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/Training/MasterData/TrainingCategoryController.php
routes/LMS/Training/MasterData/TrainingCategory.php
resources/js/Pages/LMS/Training/MasterData/TrainingCategory/TrainingCategoryPage.vue
tests/<type>/LMS/Training/MasterData/TrainingCategory/
``

These are proposal paths only. Generic stub cleanup, route consolidation, controller renaming, or frontend decomposition requires separate approval.

## Dependency and risk checklist

Before any move, verify:

- registry mapping and `LMSTrainingKategoriPage` authorization;
- all route loaders, named/direct URLs, and frontend Axios contracts;
- callers from admin training, catalog, and scheduling;
- table uniqueness and batch-insert semantics;
- transaction rollback and audit-user behavior;
- Hashids encoding/decoding contract;
- focused CRUD, authorization, frontend, browser, and impacted LMS tests;
- effective status of the generic API stub route;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`.

A future move requires explicit confirmation of master-data ownership, shared table callers, generic stub route disposition, test-gap remediation, exact old-path → new-path map, and rollback procedure.

Verification: PHP syntax, class loading, frontend build, and git diff checks passed. Commit: `e4baa37e` plus `d2c12f2b`.
