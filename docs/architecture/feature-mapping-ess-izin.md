# Feature Mapping: ESS Izin

Status: `GROUPED_COMPATIBILITY_BOUNDARY_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `IzinPage` dan workflow pengajuan izin personal. Legacy attendance controller and personal attendance page have been grouped under ESS/Attendance without decomposition or behavior change.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : ESS
Id            : 1
Nama         : Employee Self Service
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : IzinPage
Id_Grup_Fitur : 1
Flag_Aktif   : Y

URL           : /izin
Middleware    : auth, check.jabatan:IzinPage
```

Related registry pages: `IzinPageApprover`, `IzinPageAdmin`. Registry belum memiliki kolom formal `Submodule` atau `Feature`.

## Proposed ownership

```text
Module    : ESS                         # VERIFIED_MODULE
Submodule : Attendance / Leave          # PROPOSAL_REVIEW_REQUIRED
Feature   : Personal Leave Request      # PROPOSAL_REVIEW_REQUIRED
Related   : Leave Approval / Admin      # separate page/workflow mapping
```

`IzinPageApprover` dan `IzinPageAdmin` bukan role folder dan tidak boleh ikut dipindahkan tanpa mapping workflow terpisah.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php` | `indexIzin`, `submitIzin`, list, attachment, approval methods | `LEGACY_KEEP` |
| Personal route | `routes/web.php` | `/izin`, data, work hours, submit, destroy, export | `LEGACY_KEEP` |
| Approver route | `routes/web.php` | confirm/data/level-up workflow | `RELATED_ROUTE_KEEP` |
| Admin route | `routes/web.php` | `/izinAdmin`, admin data/export/cancel | `RELATED_ROUTE_KEEP` |
| Personal page | `resources/js/Pages/ESS/Attendance/Leave/Cuti/Karyawan/CutiPengajuan.vue` | Inertia page from `indexIzin` | `LEGACY_KEEP` |
| Admin page | `resources/js/Pages/izinPageAdmin.vue` | Inertia page from `indexIzinAdmin` | `RELATED_LEGACY_KEEP` |
| Approver page | `resources/js/Pages/izinPageDH.vue` | Inertia page from `indexIzinDH` | `RELATED_LEGACY_KEEP` |
| Attachment test | `tests/Unit/ESS/Attendance/AbsensiIzinAttachmentStorageTest.php` | storage behavior | `RELATED_TEST` |
| Feature tests | `tests/Feature/ESS/Attendance/` | cancellation, submit, balance, related leave behavior | `RELATED_TEST` |
| Transaction docs | `docs/architecture/transaction-and-cloud-tasks.md` | pre-upload, short transaction, after-commit | `REFERENCE_KEEP` |

## Important findings

1. `IzinPage` shares the 7,000+ line `AbsensiController` with attendance, admin, approver, shift, and overtime paths.
2. `submitIzin()` has upload, transaction, cleanup, approval, and after-commit notification behavior; extraction is not a move-only change.
3. Personal, approver, and admin pages share tables/controller methods but have different authorization/workflow boundaries.
4. Existing page files are legacy root pages and remain in place until an approved decomposition task exists.
5. Selected leave tests exist, but no complete browser test for personal Izin was found.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/ESS/Attendance/Leave/PersonalLeaveController.php
app/Services/ESS/Attendance/Leave/
routes/ESS/Attendance/Leave/PersonalLeave.php
resources/js/Pages/ESS/Attendance/Leave/PersonalLeave/PersonalLeavePage.vue
tests/<type>/ESS/Attendance/Leave/PersonalLeave/
```

Approver/admin workflows require separate mapping.

## Decision gate

Current decision: `grouped compatibility boundary`; controller remains intentionally undivided because it owns attendance, leave, approval, shift, and overtime methods.

A future move requires explicit confirmation of hierarchy, personal/approver/admin batch boundaries, controller extraction or compatibility strategy, frontend scope, test-gap scope, and exact old-path → new-path rollback map.

Controller and personal attendance page are grouped under ESS/Attendance while legacy route URLs and all mixed workflow methods remain unchanged. Verification: PHP syntax/class loading, frontend build (4,398 modules), and attendance attachment test passed (1 test, 3 assertions). Related HCAbsensi suite had 31 intentionally skipped real-DB tests.
