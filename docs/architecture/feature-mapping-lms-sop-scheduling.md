# Feature Mapping: LMS-Registered SOP Scheduling

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopPenjadwalanPage` ke implementation existing SOP Admin Penjadwalan. Registry menempatkan page ini di module `LMS`, sedangkan source dan business boundary menggunakan domain `SOP`. Source controller/page/route telah dikelompokkan di bawah `LMS/SOP`; registry mismatch tetap dipertahankan dan tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopPenjadwalanPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/penjadwalan
Middleware    : auth, check.jabatan:menuSopPenjadwalanPage
```

Registry memverifikasi mapping ke `LMS`, tetapi source identifier, controller, frontend, route, service, job, database, dan business language menunjukkan domain SOP. Belum ada module SOP aktif pada registry.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Governance / Assignment     # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Assignment Scheduling   # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

Penjadwalan SOP berbeda dari LMS Training Scheduling dan SOP Monitoring: ia menugaskan paket SOP published kepada karyawan, seed progress, dan mengelola pencabutan akses.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopAdminPenjadwalanController.php` | options, target filters, batches, assign, cabut, detail edits | `LEGACY_KEEP` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | `/sop/admin/penjadwalan*` | `LEGACY_KEEP` |
| Main page | `resources/js/Pages/LMS/SOP/Governance/Admin/SopPenjadwalan.vue` | batch cards, filters, wizard, edit deadline, add employee | `SOURCE_DOMAIN_KEEP` |
| Wizard | `resources/js/Pages/LMS/SOP/Governance/Admin/penjadwalan/WizardPenjadwalan.vue` | target/package/options/preflight/assign | `SOURCE_DOMAIN_KEEP` |
| Target components | `TargetPilihOrang.vue`, `TargetPilihUnit.vue` (+ `FilterPilihSemua.vue`) | employee/unit target selection; paste mode removed 2026-08-20 | `SOURCE_DOMAIN_KEEP` |
| Batch components | `BatchKaryawanTable.vue`, `BatchRiwayat.vue` | batch details/history | `SOURCE_DOMAIN_KEEP` |
| Dialogs | `EditTenggatDialog.vue`, `TambahKaryawanDialog.vue` | deadline and participant mutation | `SOURCE_DOMAIN_KEEP` |
| Core service | `app/Services/LMS/SOP/SopPenjadwalanService.php` | package eligibility, assign, header/detail, revoke | `SHARED_SOP_KEEP` |
| Target service | `app/Services/LMS/SOP/SopPenjadwalanTargetService.php` | filters, candidate validation, target preview | `SHARED_SOP_KEEP` |
| List service | `app/Services/LMS/SOP/SopPenjadwalanListService.php` | batch list/detail/history | `SHARED_SOP_KEEP` |
| Async job | `app/Jobs/LMS/SOP/Automation/ProsesBulkAssignSop.php` | queued bulk assignment | `SHARED_SOP_KEEP` |
| Async service | `app/Services/LMS/SOP/SopBulkAssignTaskService.php` | task progress/failure | `SHARED_SOP_KEEP` |
| Related rules | `SopAssignmentRuleService.php`, `SopPenjadwalanService.php` | automation/manual assignment callers | `CROSS_FEATURE_DEPENDENCY` |
| Tests | `SopPenjadwalanServiceTest.php`, `SopPenjadwalanTargetServiceTest.php`, UAT scheduling/cabut tests | substantial service/UAT coverage | `RELATED_TEST` |
| Dedicated full page suite | no complete page/authorization/browser suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET  /sop/admin/penjadwalan
GET  /api/v1/sop/admin/penjadwalan/options/paket
GET  /api/v1/sop/admin/penjadwalan/options/filter
GET  /api/v1/sop/admin/penjadwalan/options/kandidat
GET  /api/v1/sop/admin/penjadwalan/options/kandidat-ringkas
POST /api/v1/sop/admin/penjadwalan/options/validasi-tempel   # tak lagi dipanggil UI sejak mode Tempel Daftar dicabut (2026-08-20); masih dipakai UAT-SOP-022
GET  /api/v1/sop/admin/penjadwalan/preflight
GET  /api/v1/sop/admin/penjadwalan/ringkas
GET  /api/v1/sop/admin/penjadwalan/batch/{id}*
GET  /api/v1/sop/admin/penjadwalan/karyawan
GET  /api/v1/sop/admin/penjadwalan/list
POST /api/v1/sop/admin/penjadwalan/assign
POST /api/v1/sop/admin/penjadwalan/cabut
```

## Important findings

1. `SopAdminPenjadwalanController` menangani target filtering, package options, preflight, summary, batch list/detail/history, participant changes, assignment, dan revoke. Boundary-nya melampaui CRUD sederhana.
2. `SopPenjadwalanService::assign()` membuat header/detail assignment dan seed progress/rollup; `bulkCabut()` dan `cabutAkses()` mengubah akses serta progress. Ini adalah transaction/data-integrity boundary.
3. Target selection di UI kini hanya individual dan unit/filter; jalur paste list masih ada di service (`validasiTempel`) tetapi tak lagi punya pemanggil UI. Validasi kandidat dan preview harus konsisten dengan hasil assignment aktual.
4. Bulk assignment menggunakan `ProsesBulkAssignSop` dan `SopBulkAssignTaskService`, sehingga worker/queue availability, progress, retry, dan failure state adalah bagian dari feature contract.
5. Assignment service juga dipanggil oleh automation/rule flows dan UAT helpers; jangan dijadikan feature-local tanpa caller analysis.
6. UI memiliki banyak child components yang berbagi API contract dan state batch.
7. Test service/UAT cukup luas, tetapi belum ada suite tunggal lengkap untuk page render, authorization, wizard browser, async bulk lifecycle, dan revoke UI.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/SOP/Governance/Assignment/SopSchedulingController.php
app/Services/LMS/SOP/Governance/Assignment/
app/Jobs/LMS/SOP/Governance/Assignment/ProcessBulkSopAssignment.php
routes/LMS/SOP/Governance/Assignment/SopScheduling.php
resources/js/Pages/LMS/SOP/Governance/Assignment/SopScheduling/SopSchedulingPage.vue
tests/<type>/LMS/SOP/Governance/Assignment/SopScheduling/
```

This proposal is blocked until registry/business ownership decides whether SOP becomes a formal module or remains registered under LMS. Current SOP paths are the safe state.

## Dependency and risk checklist

Before any move, verify:

- registry decision for `menuSopPenjadwalanPage` and absent formal SOP module;
- published package eligibility and package/version status gates;
- target filters, candidate validation, deduplication, and preflight consistency;
- assignment header/detail, progress seed, rollup, deadline, and revoke semantics;
- async job/task progress, retry, failure, and worker availability;
- automation/rule callers and manual assignment callers;
- all child components, API URLs, polling, and batch history links;
- focused assignment, revoke, authorization, async, frontend, browser, and UAT tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Registry/business ownership must be resolved before any target path is approved. Source files are now grouped under `LMS/SOP`; live LMS mapping and URL contracts remain unchanged.
