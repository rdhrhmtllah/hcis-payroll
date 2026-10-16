# Feature Mapping: LMS-Registered SOP Repository

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopRepositoryPage` ke implementation existing `/sop/repository`. Registry menempatkan page ini di module `LMS`, sedangkan source dan business boundary menggunakan domain `SOP`. Source controller/page/route telah dikelompokkan di bawah `LMS/SOP`; registry mismatch tetap dipertahankan dan tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopRepositoryPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/repository
Middleware    : auth
```

Registry memverifikasi mapping ke `LMS`, tetapi source identifier, controller, frontend, route, service, dan business language menunjukkan domain SOP. Karena belum ada module SOP aktif pada registry, status mismatch tidak boleh diperbaiki diam-diam.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Employee / Repository       # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Repository / Library    # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

SOP Repository adalah employee-facing library, bukan admin content-management page. Jangan menggabungkannya dengan `LMSContentManagementPage` hanya karena keduanya menangani content.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Employee/SopRepositoryController.php` | repository page, detail, visibility/check-in, content APIs, favorites | `LEGACY_KEEP` |
| Web/API route | `routes/LMS/SOP/Employee/SopEmployee.php` | `/sop/repository*`, detail and reader routes | `LEGACY_KEEP` |
| Main page | `resources/js/Pages/LMS/SOP/Employee/Repository/SopRepository.vue` | library home, search, favorites, categories, task bridge | `SOURCE_DOMAIN_KEEP` |
| Category page | `resources/js/Pages/LMS/SOP/Employee/Repository/HalamanKategori.vue` | category listing/pagination | `SOURCE_DOMAIN_KEEP` |
| Preview drawer | `resources/js/Pages/LMS/SOP/Employee/Repository/IntipDrawer.vue` | limited preview | `SOURCE_DOMAIN_KEEP` |
| Cards/navigation | `KartuSop.vue`, `BilahTugas.vue`, `FolderKategori.vue` | library/task UI | `SOURCE_DOMAIN_KEEP` |
| Read service | `app/Services/LMS/SOP/SopRepositoryReadService.php` | beranda, category, search, preview, favorites | `SHARED_SOP_KEEP` |
| Document view | `LensaDokumen.vue` | axis picker + rail + document rows | `SOURCE_DOMAIN_KEEP` |
| Borrowed from SOP Saya | `Employee/Saya/RailSumbu.vue`, `BarisDokumenSaya.vue`, `sumbuTugas.js` | grouping rail, document row, axis vocabulary | `SHARED_MOVE_PENDING` |
| Progress service | `app/Services/LMS/SOP/SopProgressReadService.php` | task summary/employee progress | `SHARED_SOP_KEEP` |
| Package/content services | `SopPaketService.php`, `SopKontenViewService.php` | package visibility and content access | `SHARED_SOP_KEEP` |
| Attendance/access | `SopAbsensiService.php`, `Support/SopAksesBerkas.php` | check-in and file visibility gates | `SHARED_SOP_KEEP` |
| Tests | `tests/Feature/LMS/SOP/Uat/Alur026SisiKaryawanTest.php` and related SOP tests | service/UAT behavior | `RELATED_TEST` |
| Dedicated page suite | no dedicated `menuSopRepositoryPage` full suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET  /sop/repository
GET  /api/v1/sop/repository
GET  /api/v1/sop/repository/beranda
GET  /api/v1/sop/repository/dokumen
GET  /api/v1/sop/repository/cari?q=
GET  /api/v1/sop/repository/kategori/{id}
GET  /api/v1/sop/repository/intip/{id}
POST /api/v1/sop/repository/favorit/{id}
GET  /sop/{id}
GET  /api/v1/sop/{id}
GET  /api/v1/sop/{idPaket}/item/{idItem}/versi
GET  /api/v1/sop/{idPaket}/versi/{idVersi}/konten
```

Related reader/check-in routes are part of the broader employee SOP workflow and must not be silently absorbed into repository ownership.

## Important findings

1. `menuSopRepositoryPage` is an LMS-registered page whose source/business ownership is SOP. Status is `REGISTRY_SOURCE_MISMATCH_REVIEW`.
2. `SopRepositoryController` owns both the library page and package detail/check-in branching. Moving only the repository page without analyzing detail, reader, and attendance gates can break access control.
3. `SopRepositoryReadService` centralizes visibility, category, search, preview, and favorites. Its service contract is shared across employee SOP flows.
4. `SopKontenViewService` and `SopAksesBerkas` protect content/file access; repository is not a plain read-only catalog.
5. `/sop/repository` is linked from SOP Saya, detail, admin policy, and check-in pages.
6. Existing UAT/service tests cover related behavior, but no dedicated complete registry-page authorization/browser suite was found.
7. Since 2026-08-27 the page carries **two views over one library**: the original package view (folders per SOP category) and a document view. The grouping axes (departemen, jenis dokumen, APQC) live on `N_SOP_Dokumen`, one level below packages, which is why they can never be expressed on package cards.
8. **Documents have no visibility gate of their own, and must not be given one.** A document appears in the library because a PUBLIK package contains it — exactly as SOP Saya lists documents because an assignment contains them. `SopRepositoryReadService::katalogDokumen()` therefore starts from `basePaket()` like every other read path in that class. `DDL-2026-052`, which briefly added `Mode_Distribusi` to `N_SOP_Dokumen`, was cancelled the same day for violating this rule; see `docs/changes/DDL-2026-052-sop-dokumen-mode-distribusi/README.md`.
9. Rows carry no lock state. Each row links to the **package page** (`?item=`), where the attendance gate, section order, and item prerequisites already explain themselves — recomputing them in the library would create a second explanation that can drift from the guard that actually enforces it.
10. `RailSumbu.vue`, `BarisDokumenSaya.vue`, and `sumbuTugas.js` are now used by two features but still live under `Employee/Saya/`. Moving them to `Shared/support/` touches `SopSaya.vue` and three test files, so it is tracked as a separate task.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/SOP/Employee/SopRepositoryController.php
routes/LMS/SOP/Employee/SopRepository.php
resources/js/Pages/LMS/SOP/Employee/SopRepository/SopRepositoryPage.vue
tests/<type>/LMS/SOP/Employee/SopRepository/
```

Physical grouping has been executed while preserving the live LMS registry mapping; a future registry decision may rename the source domain without changing current URL contracts.

## Dependency and risk checklist

Before any move, verify:

- registry decision for `menuSopRepositoryPage` and absent formal SOP module;
- all links from SOP Saya, detail, reader, check-in, and admin pages;
- package visibility, employee assignment, and check-in gates;
- content/file access authorization and secure delivery;
- category/search/preview/favorites contracts;
- Hashids and route ordering for static versus dynamic paths;
- focused repository, detail, access, UAT, frontend, and browser tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Registry/business ownership must be resolved before any target path is approved. Source files are now grouped under `LMS/SOP`; live LMS mapping and URL contracts remain unchanged.
