# Feature Mapping: LMS Content Management

Status: `MIGRATED_AND_VERIFIED_SHARED_BOUNDARY`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `LMSContentManagementPage` dan library content management existing. Shared controller, page, components, dan cross-feature callers telah dikelompokkan di bawah LMS/Content; scope LMS/SOP dan storage/async contracts tetap.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : LMSContentManagementPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

URL           : /lms/konten-kategori
Middleware    : auth, check.jabatan:LMSContentManagementPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : LMS                         # VERIFIED_MODULE
Submodule : Content / Library            # PROPOSAL_REVIEW_REQUIRED
Feature   : LMS Content Management      # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Page, Components, Route, Services, Jobs, Storage, Tests
``

Controller dan frontend yang sama juga melayani scope `SOP` melalui `currentScope()` dan route default scope. Karena itu, ownership LMS page tidak berarti implementation boleh dipisahkan tanpa analisis shared LMS/SOP contract.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/Content/ContentManagementController.php` | category/content CRUD, upload, preview, metadata, render/verify retry, usage tracking | `MIGRATED_SHARED` |
| Route | `routes/kpi/ridhoDevEvo.php` | `/lms/konten-kategori/*`, upload tasks, usage tracking | `MIGRATED_SHARED` |
| Main page | `resources/js/Pages/LMS/Content/ContentManagement/contentDashboard.vue` | content library/dashboard | `MIGRATED_SHARED` |
| UI components | `resources/js/Pages/LMS/Content/ContentManagement/components/ui/` | category, file, upload, media, detail, picker UI | `MIGRATED_SHARED` |
| Training picker caller | `resources/js/Pages/LMS/modules/admin-training/components/KontenPickerModal.vue` | reuses `ContentContainer.vue` | `CROSS_FEATURE_DEPENDENCY` |
| Query service | `app/Services/LMS/TrainingScheduleQueryService.php` | usage/scheduling lookup dependency | `SHARED_KEEP` |
| Upload service | `app/Services/Activity/UploadTaskService.php` | upload task lifecycle | `SHARED_KEEP` |
| Cloud task | `app/Services/GCP/CloudTaskService.php` | async PDF/content processing | `SHARED_KEEP` |
| Storage services | `app/Services/LMS/GCS/GcsService.php` and `app/Services/LMS/Bunny/BunnyStreamService.php` | file/video storage and delivery | `SHARED_KEEP` |
| Policy/verifier | `app/Services/Konten/KontenPolicy.php`, `KontenVerifierService.php` | content authorization/verification | `SHARED_KEEP` |
| Tests | no dedicated `LMSContentManagementPage` suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |
| Related tests | UAT content lifecycle, SOP content, cloud task, picker tests | adjacent/shared behavior | `RELATED_TEST` |

## Route surface

```text
GET    /lms/konten-kategori
POST   /lms/konten-kategori/create-kategori
GET    /lms/konten-kategori/kategori
GET    /lms/konten-kategori/konten/{id}
GET    /lms/konten-kategori/berkas/{id}/metadata
POST   /lms/konten-kategori/berkas/{id}/render-ulang
POST   /lms/konten-kategori/berkas/{id}/verifikasi-ulang
GET    /lms/konten-kategori/berkas/{id}/unduh
GET    /lms/konten-kategori/bunny-thumbnail/{videoId}/{fileName}
POST   /lms/konten-kategori/upload-konten
POST   /lms/konten-kategori/get-upload-ticket
POST   /lms/konten-kategori/upload-task/start
POST   /lms/konten-kategori/upload-task/{exportId}/progress
POST   /lms/konten-kategori/upload-task/{exportId}/fail
DELETE /lms/konten-kategori/delete-upload-ticket
POST   /lms/konten-kategori/rename
GET    /lms/api/content/{id}/usage-tracking
POST   /lms/api/content/usage-tracking-batch
POST   /lms/konten-kategori/delete-konten
POST   /lms/konten-kategori/delete-kategori
``

## Important findings

1. `KontenKategoriController` sekitar 1.995 baris dan mencampur category CRUD, content listing, upload ticket, upload, storage delivery, async task progress, retry render/verification, usage tracking, dan delete safety. Pemindahan atau pemecahan bukan perubahan struktur sederhana.
2. `currentScope()` membedakan `LMS` dan `SOP`; implementation yang sama dipakai lintas domain. Ini adalah shared boundary dan membutuhkan checkpoint terpisah sebelum perubahan.
3. Upload menggunakan ticket/claim flow, UploadTaskService, Cloud Tasks, dan storage provider GCS/Bunny. Perubahan path tidak boleh mengubah idempotency, cleanup, signed URL, atau async processing contract.
4. Content usage tracking memeriksa pemakaian pada training dan scheduling. Delete content/category memiliki safety dependency terhadap consumer feature.
5. `ContentContainer.vue` dipakai oleh `KontenPickerModal.vue` pada Admin Training, sehingga frontend library memiliki cross-feature caller.
6. Test terkait cukup banyak pada UAT/SOP/content/cloud task, tetapi belum ada suite khusus yang membuktikan seluruh registry page LMS termasuk authorization, browser, upload, dan delete flow.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/Content/ContentManagementController.php
app/Services/LMS/Content/
app/Jobs/LMS/Content/
routes/LMS/Content/ContentManagement.php
resources/js/Pages/LMS/Content/ContentManagement/ContentManagementPage.vue
resources/js/Pages/LMS/Content/ContentManagement/components/
tests/<type>/LMS/Content/ContentManagement/
``

These are proposal paths only. Splitting LMS/SOP implementations, moving shared services, changing storage providers, or consolidating routes requires separate explicit approval.

## Dependency and risk checklist

Before any move, verify:

- registry mapping and `LMSContentManagementPage` authorization;
- LMS versus SOP `scope` behavior and all shared route callers;
- content picker caller from Admin Training;
- category/content table relationships and usage-tracking safety checks;
- upload ticket ownership, claim, cleanup, and duplicate handling;
- Cloud Tasks, UploadTaskService, activity tracking, and retry semantics;
- GCS/Bunny storage and signed/download/thumbnail contracts;
- render/verification retry behavior and invalid credential environment assumptions;
- focused content CRUD, authorization, upload, delete safety, frontend, browser, and impacted SOP/LMS tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated shared boundary`; LMS/SOP scope behavior remains unchanged.

A future move requires explicit confirmation of LMS/SOP shared ownership, content library versus training picker boundary, storage/async ownership, test-gap remediation, exact old-path → new-path map, and rollback procedure.

Until approved, all current files remain in place.
