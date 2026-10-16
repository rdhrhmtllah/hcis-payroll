# Feature Mapping: LMS Admin Monitoring

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `LMSAdminMonitoringPage` dan monitoring training admin existing. Controller, local monitoring pages/components, dan route telah dipindahkan; shared service/export/job/detail tetap di tempat semula.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : LMSAdminMonitoringPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif   : Y

URL           : /lms/admin/monitoring
Middleware    : auth, check.jabatan:LMSAdminMonitoringPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : LMS                         # VERIFIED_MODULE
Submodule : Training / Monitoring       # PROPOSAL_REVIEW_REQUIRED
Feature   : Admin Training Monitoring   # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Pages, Components, Route, Job, Service, Tests
``

`LMSAdminMonitoringPage` mengawasi progress training, detail training/test, dan export. `LMSPdfMonitoringPage` memakai authorization page yang sama tetapi merupakan related feature terpisah, bukan otomatis bagian dari mapping ini.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoringController.php` | page, employee list, detail, progress, test answer, export | `MIGRATED` |
| Route | `routes/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoring.php` | `/lms/admin/monitoring/*` | `MIGRATED` |
| Route loader | `routes/kpi/ridhoDevEvo.php` | route require | `LEGACY_KEEP` |
| Main page | `resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/MonitoringPage.vue` | filters, employee monitoring, export UI | `MIGRATED` |
| Test detail page | `resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/TestDetailPage.vue` | answer audit/detail | `MIGRATED` |
| Training detail | `resources/js/Pages/LMS/modules/training/detail/trainingDetail.vue` | reused with `viewMode=admin` | `SHARED_RELATED_KEEP` |
| Main components | `resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/components/ExportTrainingModal.vue` | export request UI | `MIGRATED` |
| Main components | `resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/components/ExportProgressPanel.vue` | export status/retry/download UI | `FEATURE_LOCAL_KEEP` |
| Shared services | `app/Services/LMS/TrainingProgressReadService.php` | progress reads | `SHARED_KEEP` |
| Shared services | `app/Services/LMS/TrainingScheduleQueryService.php` | schedule/enrollment queries | `SHARED_KEEP` |
| Export service | `app/Services/LMS/LmsTrainingExportService.php` | export lifecycle | `SHARED_REVIEW` |
| Export job | `app/Jobs/GenerateLmsTrainingExportJob.php` | asynchronous export | `RELATED_SHARED_KEEP` |
| Tests | no dedicated `LMSAdminMonitoringPage` frontend/browser suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |
| Related tests | LMS/SOP progress, export, and monitoring service tests | adjacent behavior | `RELATED_TEST` |

## Route surface

```text
GET  /lms/admin/monitoring/
GET  /lms/admin/monitoring/training/schedule/{penjadwalanId}/{kodeKaryawan}
GET  /lms/admin/monitoring/training/{materiId}/{kodeKaryawan}
GET  /lms/admin/monitoring/test-detail/{penjadwalanDetId}
GET  /lms/admin/monitoring/filter-options
GET  /lms/admin/monitoring/employees
GET  /lms/admin/monitoring/employees/{kodeKaryawan}/trainings
GET  /lms/admin/monitoring/training/.../progress
GET  /lms/admin/monitoring/test-detail/.../answer-detail
GET  /lms/admin/monitoring/export/*
POST /lms/admin/monitoring/export/request
POST /lms/admin/monitoring/export/retry/{exportId}
POST /lms/admin/monitoring/export/dismiss/{exportId}
``

## Important findings

1. `LmsMonitoringController` berukuran sekitar 2.646 baris dan mencampur page rendering, filter/list, progress, test-answer proxy, score/attendance summaries, asset resolution, export selection, export lifecycle, dan authorization check. Memindahkannya sebagai satu file bukan perubahan struktur yang rendah risiko.
2. Halaman utama menggunakan komponen shared LMS dan komponen monitoring lokal. Training detail dipakai ulang untuk user/admin melalui `viewMode`, sehingga tidak boleh dibuat feature-local tanpa caller analysis.
3. Export merupakan asynchronous flow melalui `LmsTrainingExportService` dan `GenerateLmsTrainingExportJob`; perubahan path harus mempertahankan job serialization, status, retry, download, dan dismiss contract.
4. Monitoring melakukan pembacaan lintas data karyawan, jadwal, materi, progress, ujian, dan integrasi third-party.
5. Tidak ditemukan test khusus lengkap untuk registry page ini. Test terkait belum cukup menjadi bukti end-to-end untuk monitoring admin.
6. `LMSPdfMonitoringPage` menggunakan middleware page yang sama, tetapi mapping feature harus dipisahkan agar ownership dan test scope tidak tercampur.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoringController.php
app/Services/LMS/Training/Monitoring/
app/Jobs/LMS/Training/Monitoring/GenerateTrainingExportJob.php
routes/LMS/Training/Monitoring/AdminTrainingMonitoring.php
resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/MonitoringPage.vue
resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/TestDetailPage.vue
resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/components/
tests/<type>/LMS/Training/Monitoring/AdminTrainingMonitoring/
``

These are proposal paths only. Controller decomposition, job relocation, or page renaming require a separate approved scope.

## Dependency and risk checklist

Before any move, verify:

- registry mapping and authorization behavior for `LMSAdminMonitoringPage`;
- all named routes, direct Axios URLs, Inertia page names, and breadcrumb links;
- shared `trainingDetail.vue` callers and `viewMode=admin` contract;
- `TrainingProgressReadService` and `TrainingScheduleQueryService` callers;
- export service/job serialization, retry, status, download, and cleanup;
- third-party signed request and test-answer payload normalization;
- score/formula and attendance summary dependencies;
- focused monitoring, authorization, export, frontend, browser, and regression tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; decomposition and shared service/job moves remain separate scopes.

A future move requires explicit confirmation of the monitoring versus PDF-monitoring boundary, shared training-detail ownership, controller decomposition strategy, export job ownership, test-gap remediation, exact old-path → new-path map, and rollback procedure.

Verification: PHP syntax, Composer class loading, UAT monitoring test (4 tests/9 assertions), frontend build, and git diff checks passed. No database change.
