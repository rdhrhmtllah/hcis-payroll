# Feature Mapping: LMS-Registered SOP Saya

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopSayaPage` ke implementation existing `/sop/saya`. Registry menempatkan page ini di module `LMS`, tetapi source/business boundary menggunakan domain SOP. Source controller/page/route telah dikelompokkan di bawah `LMS/SOP`; registry mismatch tetap dipertahankan dan tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopSayaPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/saya
Middleware    : auth
```

Registry memverifikasi mapping database ke `LMS`, tetapi tidak ada module `SOP` aktif pada registry. Source identifier, controller, service, frontend, route, dan business language seluruhnya menunjukkan domain SOP. Ini harus dipertahankan sebagai mismatch terdokumentasi, bukan diperbaiki diam-diam.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Employee / Compliance       # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Saya / Employee Tasks   # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

SOP bukan module resmi dalam live registry saat ini. Keputusan apakah SOP kelak menjadi module sendiri atau tetap berada di bawah LMS membutuhkan keputusan registry/business, bukan perubahan struktur file sepihak.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Employee/SopSayaController.php` | page, employee task API, compliance card export | `LEGACY_KEEP` |
| Web/API route | `routes/LMS/SOP/Employee/SopEmployee.php` | `/sop/saya`, `/api/v1/sop/saya*` | `LEGACY_KEEP` |
| Page | `resources/js/Pages/LMS/SOP/Employee/Saya/SopSaya.vue` | assigned SOP list, progress/compliance UI | `SOURCE_DOMAIN_KEEP` |
| Palette helper | `resources/js/Pages/LMS/SOP/Employee/Saya/sumberPalet.js` | data/palette helper for page | `SOURCE_DOMAIN_KEEP` |
| Progress service | `app/Services/LMS/SOP/SopProgressReadService.php` | task list/status read | `SHARED_SOP_KEEP` |
| Priority service | `app/Services/LMS/SOP/SopPrioritasService.php` | focus/priority page data | `SHARED_SOP_KEEP` |
| Team service | `app/Services/LMS/SOP/SopTeamService.php` | leader/compliance team shortcut | `SHARED_SOP_KEEP` |
| Export service | `app/Services/LMS/SOP/SopComplianceExportService.php` | async compliance card export | `SHARED_SOP_KEEP` |
| Notification | `app/Services/LMS/SOP/SopNotificationService.php` | assignment/overdue links to page | `SHARED_SOP_KEEP` |
| Tests | UAT access, notification, client-load references | adjacent behavior | `RELATED_TEST` |
| Dedicated page suite | no dedicated `menuSopSayaPage` full suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET  /sop/saya
GET  /api/v1/sop/saya?tab=all|perlu_dibaca|selesai|terlambat
POST /api/v1/sop/saya/kartu-kepatuhan
```

Related employee-side SOP routes remain separate:

```text
/sop/repository
/sop/tim
/sop/{id}
/sop/{id}/baca/{item}
/sop/{id}/kuis/{item}
```

## Important findings

1. `menuSopSayaPage` is mapped to LMS in the database, while source/business ownership is SOP. Status is `REGISTRY_SOURCE_MISMATCH_REVIEW`, not `SOURCE_MATCH`.
2. `/sop/saya` is an employee-facing page protected only by `auth`; it is not an admin page and must not be placed under an admin/role folder.
3. `SopSayaController` depends on progress, priority, team, compliance export, notifications, and employee identity.
4. Compliance card export is asynchronous and uses `SopComplianceExportService`; the export contract and Activity Center linkage must be preserved.
5. Notifications and SOP detail/repository pages link into `/sop/saya`, so this page is part of a broader SOP employee workflow.
6. Existing UAT/e2e references provide related coverage, but no dedicated full page/authorization/browser suite was found.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/SOP/Employee/SopSayaController.php
routes/LMS/SOP/Employee/SopSaya.php
resources/js/Pages/LMS/SOP/Employee/SopSaya/SopSayaPage.vue
tests/<type>/LMS/SOP/Employee/SopSaya/
```

Physical grouping has been executed under `LMS/SOP` while preserving the live LMS registry mapping and current URL contracts.

## Dependency and risk checklist

Before any move, verify:

- registry decision for `menuSopSayaPage` and the absent formal SOP module;
- all `/sop/saya` links from notifications, repository, detail, and team flows;
- employee identity and authorization behavior;
- progress/status tabs and completion contract;
- priority/leader shortcut behavior;
- async compliance card export and Activity Center contract;
- focused SOP employee, notification, export, frontend, and browser tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Registry/business ownership must be resolved before a target path is approved. Source files are now grouped under `LMS/SOP`; live LMS mapping and URL contracts remain unchanged.
