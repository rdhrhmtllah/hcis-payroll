# Feature Mapping: LMS-Registered SOP Content Library

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopKontenPage` ke library konten SOP existing. Registry menempatkan page ini di module `LMS`, tetapi implementation menggunakan domain SOP pada route, label UI, scope data, dan caller SOP. Library ini sengaja berbagi controller dan frontend dengan LMS; isolasi dilakukan melalui route default `scope=SOP`, bukan melalui duplicate implementation.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopKontenPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/konten
Middleware    : auth, check.jabatan:menuSopKontenPage
```

Belum ada module SOP formal pada registry. Mapping live ke LMS harus dipertahankan sebagai fakta registry dan mismatch terhadap source domain harus direview, bukan dikoreksi diam-diam.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Content Library             # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Content Library         # PROPOSAL_REVIEW_REQUIRED
Implementation  : SHARED_LMS_SOP_BOUNDARY
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

Feature ini adalah library/master content, bukan SOP Builder, Master Dokumen, Repository reader, atau Training Catalog. Ia menyediakan kategori dan konten yang dipilih oleh builder, master document, training, dan reader flows.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/Content/ContentManagementController.php` | shared index, category/content CRUD, upload, metadata, verification, rendering, download | `SHARED_LMS_SOP_KEEP` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | SOP route group with `defaults('scope', 'SOP')` | `SOURCE_DOMAIN_KEEP` |
| Shared LMS route | `routes/LMS/Content/ContentManagement.php` | same controller for `/lms/konten-kategori` | `CROSS_MODULE_DEPENDENCY` |
| Frontend | `resources/js/Pages/LMS/Content/ContentManagement/contentDashboard.vue` | `scope`-aware shared page; SOP-specific header/base path | `SHARED_LMS_SOP_KEEP` |
| Upload composable | `resources/js/composables/useMediaUpload.js` | chooses `/sop/admin/konten` for SOP scope | `SHARED_KEEP` |
| Content picker | `resources/js/Pages/LMS/Training/Content/AdminTraining/components/KontenPickerModal.vue` | links/reads SOP content endpoint | `CROSS_FEATURE_DEPENDENCY` |
| Category modal | `resources/js/Pages/LMS/Content/ContentManagement/components/ui/CreateCategoryModal.vue` | scope-aware SOP base path | `SHARED_KEEP` |
| Storage | `app/Services/LMS/GCS/GcsService.php` | file storage/download path | `SHARED_PROVIDER_KEEP` |
| Video provider | `app/Services/LMS/Bunny/BunnyStreamService.php` | video upload/thumbnail/delete path | `SHARED_PROVIDER_KEEP` |
| Verification | `app/Services/LMS/Content/Verification/KontenVerifierService.php` | content/file verification and lifecycle | `SHARED_SERVICE_KEEP` |
| Upload activity | `app/Services/ESS/Activity/Center/UploadTaskService.php` | upload task lifecycle/progress/failure | `SHARED_SERVICE_KEEP` |
| Cloud task | `app/Services/SYSTEM/TaskProcessing/CloudTaskService.php` | async content verification callback | `SHARED_PROVIDER_KEEP` |
| Policy | `app/Services/LMS/Content/Verification/KontenPolicy.php` | content access/operation policy | `SHARED_AUTH_KEEP` |

## Route surface

```text
GET    /sop/admin/konten
GET    /sop/admin/konten/kategori
GET    /sop/admin/konten/konten/{id}
GET    /sop/admin/konten/berkas/{id}/metadata
POST   /sop/admin/konten/berkas/{id}/render-ulang
POST   /sop/admin/konten/berkas/{id}/verifikasi-ulang
GET    /sop/admin/konten/berkas/{id}/unduh
GET    /sop/admin/konten/bunny-thumbnail/{videoId}/{fileName}
POST   /sop/admin/konten/create-kategori
POST   /sop/admin/konten/rename
POST   /sop/admin/konten/upload-konten
POST   /sop/admin/konten/get-upload-ticket
POST   /sop/admin/konten/upload-task/start
POST   /sop/admin/konten/upload-task/{exportId}/progress
POST   /sop/admin/konten/upload-task/{exportId}/fail
DELETE /sop/admin/konten/delete-upload-ticket
POST   /sop/admin/konten/delete-konten
POST   /sop/admin/konten/delete-kategori
```

Semua endpoint memakai middleware `auth` dan `check.jabatan:menuSopKontenPage`; route defaults mengirim `scope=SOP` ke controller.

## Data and provider boundary

Controller menggunakan tabel shared dengan kolom scope, antara lain:

```text
N_LMS_Kategori_Konten
N_LMS_Master_Konten
N_LMS_Master_Konten_Media
N_LMS_Master_Konten_Berkas
```

SOP dan LMS tidak boleh tercampur. Setiap read/write/delete harus mempertahankan filter `Scope` dan validasi ownership scope. Delete kategori juga memiliki side effect ke media provider dan GCS file, sehingga bukan operasi database sederhana.

Upload dapat melewati beberapa tahap:

```text
upload ticket
→ upload/content record
→ activity task start/progress/fail
→ Cloud Tasks verification
→ metadata/render/download lifecycle
```

GCS/Bunny credential dan Cloud Tasks adalah dependency environment. Konfigurasi credential yang invalid tidak boleh diselesaikan melalui pemindahan struktur file.

## Important findings

1. `ContentManagementController` sekitar 2.034 baris dan melayani scope LMS serta SOP; memindahkannya ke folder SOP atau LMS akan memutus shared boundary.
2. `currentScope()` mengambil route parameter dan hanya menerima `LMS` atau `SOP`; route default adalah mekanisme isolasi utama.
3. Frontend yang sama menampilkan UI LMS atau SOP berdasarkan `scope`, termasuk base path dan navigasi Builder/Kebijakan SOP.
4. Konten SOP dipakai oleh SOP Builder, Master Dokumen, Repository, dan sebagian LMS training/content picker; perubahan endpoint perlu caller inventory.
5. Test `SopKontenPickerTanpaBunnyTest` membuktikan daftar content SOP harus tetap dapat dibuka tanpa kredensial Bunny ketika content hanya berupa dokumen.
6. Related tests mencakup delete aman, upload lifecycle, Cloud Task URL, picker, master document, dan document lifecycle; tidak semuanya merupakan dedicated page regression.
7. Mutation boundaries meliputi create/rename/delete kategori atau konten, upload, re-render, re-verification, dan download. Authorization serta audit perlu tetap dipetakan secara terpisah.

## Related tests

```text
tests/Feature/LMS/SOP/SopKontenPickerTanpaBunnyTest.php
tests/Feature/LMS/SOP/Uat/HapusKontenAmanTest.php
tests/Feature/LMS/Content/KontenCloudTaskUrlTest.php
tests/Feature/LMS/SOP/Uat/Alur005BerkasTerpusatTest.php
tests/Feature/LMS/SOP/SopFormBerkasTest.php
tests/Feature/LMS/SOP/SopMasterDokumenPageTest.php
tests/Feature/LMS/SOP/SopDokumenVerifyTest.php
tests/Feature/LMS/SOP/SopWatermarkToggleTest.php
resources/js/Pages/LMS/SOP/Shared/components/ui/SopKontenViewer.test.js
resources/js/Pages/LMS/SOP/Shared/components/ui/SopKontenTerpakai.test.js
```

Status coverage: `RELATED_TEST`; dedicated end-to-end page coverage, scope isolation matrix, and full upload-provider lifecycle remain `TEST_GAP_REVIEW_REQUIRED`.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/Shared/Content/KontenKategoriController.php
app/Services/Shared/Content/
routes/shared/content/
resources/js/Pages/Shared/ContentManagement/contentDashboard.vue
tests/<type>/Shared/Content/
```

This is intentionally a shared-boundary proposal, not an instruction to move the existing controller. The actual target requires registry decision, caller analysis, route-loader impact review, and an approved migration batch.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Keep the current shared controller/frontend and SOP route defaults until: (1) LMS/SOP ownership is resolved in the live registry, (2) shared caller and provider contracts are approved, and (3) focused/impacted tests cover scope isolation, upload lifecycle, provider failure, and destructive content operations.
