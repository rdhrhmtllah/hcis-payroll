# Feature Mapping: LMS Training Scheduling

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `LMSPenjadwalanPage` dan alur penjadwalan training existing. Controller, page, dan active route file telah dipindahkan; service/job/outbox tetap shared.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : LMSPenjadwalanPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

URL           : /lms/penjadwalan
Middleware    : auth, check.jabatan:LMSPenjadwalanPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : LMS                         # VERIFIED_MODULE
Submodule : Training / Scheduling       # PROPOSAL_REVIEW_REQUIRED
Feature   : Training Assignment Scheduling # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Page, Route, Job, Services, Tests, DB contract
``

Penjadwalan adalah assignment/scheduling workflow, bukan sekadar page master data. Ia berhubungan langsung dengan training package, employee selection, progress, third-party test assignment, dan monitoring.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/Training/Scheduling/TrainingSchedulingController.php` | page, options, current, store, update, toggle, bulk toggle, progress, reassign | `LEGACY_KEEP` |
| API controller | `app/Http/Controllers/LmsPenjadwalanTraining/LmsPenjadwalanTrainingApi.php` | generic empty CRUD methods | `LEGACY_KEEP_UNUSED_REVIEW` |
| Web/API routes | `routes/LMS/Training/Scheduling/TrainingScheduling.php` | `/lms/penjadwalan` and `/api/v1/lms/penjadwalan/*` | `LEGACY_KEEP` |
| Page | `resources/js/Pages/LMS/Training/Scheduling/TrainingSchedulingPage.vue` | filter, selection, create/update/toggle/progress UI | `FEATURE_LOCAL_KEEP` |
| Shared query service | `app/Services/LMS/TrainingScheduleQueryService.php` | active scheduling and enrollment queries | `SHARED_KEEP` |
| Async job | `app/Jobs/ProcessBulkPenjadwalan.php` | bulk scheduling processing | `RELATED_SHARED_KEEP` |
| Outbox | `app/Services/SYSTEM/TaskProcessing/TaskOutboxService.php` | task persistence/dispatch integration | `SHARED_KEEP` |
| Outbox dispatcher | `app/Services/SYSTEM/TaskProcessing/TaskOutboxDispatcher.php` | dispatch lifecycle | `SHARED_KEEP` |
| Tests | `tests/Feature/LMS/SOP/Uat/Alur020PenjadwalanTest.php` | UAT scheduling flow | `RELATED_TEST` |
| Tests | `tests/Integration/SYSTEM/TaskProcessing/LmsReassignThirdPartyHandlerTest.php` | reassignment integration behavior | `RELATED_TEST` |
| Dedicated page suite | no dedicated `LMSPenjadwalanPage` frontend/browser suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET  /lms/penjadwalan
GET  /api/v1/lms/penjadwalan/options/karyawan-current
GET  /api/v1/lms/penjadwalan/options/lokasi-karyawan
GET  /api/v1/lms/penjadwalan/options/lokasi-kerja
GET  /api/v1/lms/penjadwalan/options/master-paket/training
GET  /api/v1/lms/penjadwalan/options/kategori-ujian
GET  /api/v1/lms/penjadwalan/options/formula
GET  /api/v1/lms/penjadwalan/options/level
POST /api/v1/lms/penjadwalan/store
GET  /api/v1/lms/penjadwalan/current
GET  /api/v1/lms/penjadwalan/all-ids
POST /api/v1/lms/penjadwalan/update
POST /api/v1/lms/penjadwalan/toggle
POST /api/v1/lms/penjadwalan/reassign-third-party
POST /api/v1/lms/penjadwalan/bulk-toggle
GET  /api/v1/lms/penjadwalan/progress/{process_id}
``

## Important findings

1. `LmsPenjadwalanTrainingController` menangani page rendering, option/master-data queries, employee filtering, current schedule listing, create/update, single/bulk activation, progress polling, dan reassignment third-party dalam satu controller sekitar 1.478 baris. Pemindahan atau pemecahan bukan perubahan struktur sederhana.
2. `ProcessBulkPenjadwalan` membuat scope asynchronous dan harus dianalisis bersama outbox/task processing sebelum controller/job dipindahkan.
3. `TrainingScheduleQueryService` dipakai oleh fitur LMS lain seperti catalog dan monitoring; statusnya shared dan tidak boleh dibuat lokal tanpa caller analysis.
4. Flow scheduling membaca/menulis tabel `N_LMS_Penjadwalan` beserta detail/participant/integration dan bergantung pada master paket, karyawan, level, kategori ujian, formula, serta third-party assignment.
5. `LmsPenjadwalanTrainingApi` berisi generic CRUD kosong. Tidak ada cleanup otomatis karena file tersebut tidak boleh dihapus atau diubah pada structure-only phase.
6. Test UAT dan integration mencakup alur terkait, tetapi belum membuktikan seluruh page/API/authorization/browser contract registry page.

## Migration record

Executed batch:

```text
app/Http/Controllers/LmsPenjadwalanTraining/LmsPenjadwalanTrainingController.php
→ app/Http/Controllers/LMS/Training/Scheduling/TrainingSchedulingController.php
resources/js/Pages/LMS/modules/penjadwalan-training/HomePenjadwalanTraining.vue
→ resources/js/Pages/LMS/Training/Scheduling/TrainingSchedulingPage.vue
routes/LmsPenjadwalanTraining/LmsPenjadwalanTrainingWeb.php
→ routes/LMS/Training/Scheduling/TrainingScheduling.php
```

```text
app/Http/Controllers/LMS/Training/Scheduling/TrainingSchedulingController.php
app/Services/LMS/Training/Scheduling/
app/Jobs/LMS/Training/Scheduling/ProcessBulkTrainingScheduling.php
routes/LMS/Training/Scheduling/TrainingScheduling.php
resources/js/Pages/LMS/Training/Scheduling/TrainingSchedulingPage.vue
resources/js/Pages/LMS/Training/Scheduling/components/
tests/<type>/LMS/Training/Scheduling/
``

These are proposal paths only. Renaming, decomposition, route changes, job relocation, or outbox changes require separate approval.

## Dependency and risk checklist

Before any move, verify:

- registry mapping and `LMSPenjadwalanPage` authorization;
- all direct API URLs, polling behavior, and route loader order;
- employee/package/level/location/filter contracts;
- scheduling table relationships, participant assignment, and integration records;
- formula and test-category dependencies;
- `ProcessBulkPenjadwalan` serialization, retry, progress, and idempotency;
- Outbox persistence/dispatch behavior and rollback boundaries;
- third-party reassignment contract;
- focused scheduling, authorization, frontend, browser, UAT, and integration tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`.

A future move requires explicit confirmation of scheduling versus monitoring/catalog boundaries, async job/outbox ownership, controller decomposition strategy, test-gap remediation, exact old-path → new-path map, and rollback procedure.

Until approved, all current files remain in place.
