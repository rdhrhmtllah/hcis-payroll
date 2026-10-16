# Feature Mapping: ESS Absensi

Status: `GROUPED_COMPATIBILITY_BOUNDARY_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `absensiPage` dan jalur absensi personal existing. Legacy attendance controller and personal attendance page have been grouped under ESS/Attendance without decomposition or behavior change.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : ESS
Id            : 1
Nama         : Employee Self Service
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : absensiPage
Id_Grup_Fitur : 1
Flag_Aktif   : Y

URL           : /absensiSales
Middleware    : auth, check.jabatan:absensiPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : ESS                         # VERIFIED_MODULE
Submodule : Attendance / Personal       # PROPOSAL_REVIEW_REQUIRED
Feature   : Personal Attendance         # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Services, Page, Route, API, Tests, Storage
``

`IzinPage`, `IzinPageApprover`, `IzinPageAdmin`, `ShiftManagement`, dan `OvertimeManagement` adalah page mapping/workflow lain. Walaupun beberapa endpoint memakai `AbsensiController`, mereka tidak otomatis menjadi bagian dari feature `absensiPage`.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php` | `indexAbsensi`, `getDataToday`, shift, attendance submission and many legacy izin methods | `LEGACY_KEEP` |
| Route | `routes/web.php` | `/absensiSales`, `/absensi/getDataToday`, `/absensi/getUserShift`, related attendance endpoints | `LEGACY_KEEP` |
| Page | `resources/js/Pages/ESS/Attendance/Personal/AbsensiSalesPage.vue` | Inertia page returned by `indexAbsensi` | `LEGACY_KEEP` |
| Service dependency | `app/Services/HCIS/AttendanceDirtyResolverService.php` | attendance recalculation/dirty resolution | `DEPENDENCY_REVIEW` |
| Service dependency | `app/Services/ShiftRequestService.php` | shift data/request dependency | `RELATED_SHARED_KEEP` |
| Service dependency | `app/Services/TransactionService.php` | transaction number/business transaction dependency | `RELATED_SHARED_KEEP` |
| Service dependency | `app/Services/HCIS/CUTI/cutiBersamaService.php` | shared leave/attendance controller dependency | `RELATED_SHARED_KEEP` |
| Service dependency | `app/Services/Absensi/IzinCancellationService.php` | leave cancellation path in same controller | `RELATED_SCOPE_KEEP` |
| Models | `app/Models/Karyawan.php`, `app/Models/User.php` | page identity and employee context | `SHARED_KEEP` |
| Storage | GCS/local storage calls in controller | attendance photo upload and retrieval | `DEPENDENCY_REVIEW` |
| Feature tests | no dedicated `absensiPage`/`absensiSales` test found | repository test search | `TEST_GAP_REVIEW_REQUIRED` |
| Related tests | `tests/Feature/ESS/Attendance/` | leave/cancellation/attendance-adjacent behavior | `RELATED_TEST` |
| Unit test | `tests/Unit/ESS/Attendance/AbsensiIzinAttachmentStorageTest.php` | attachment storage path, not full personal attendance page | `RELATED_TEST` |

## Important findings

1. `AbsensiController.php` is approximately 7,450 lines and combines personal attendance, leave, approval, export, and related legacy flows. It cannot be moved or split as a simple structure-only change.
2. The page entry `absensiSales.vue` is a large legacy page and directly participates in attendance modal, photo upload, shift, and daily data behavior.
3. Several controller constructor dependencies are shared with non-`absensiPage` flows; moving the controller without a compatibility plan would affect unrelated routes.
4. Attendance photo upload uses storage/external infrastructure. A structural move must not accidentally change upload path, transaction boundary, cleanup, or credential behavior.
5. No dedicated browser or feature test was found for the complete `absensiPage` flow. This is a separate quality/test planning gap, not a reason to refactor during inventory.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/ESS/Attendance/Personal/PersonalAttendanceController.php
app/Services/ESS/Attendance/Personal/
routes/ESS/Attendance/Personal/PersonalAttendance.php
resources/js/Pages/ESS/Attendance/Personal/PersonalAttendancePage.vue
tests/<type>/ESS/Attendance/Personal/
``

These paths imply controller/page decomposition and are therefore not approved as a move-only batch. A safe first step would require a separate bounded feature implementation or compatibility wrapper, not renaming the 7,450-line legacy controller.

## Dependency and risk checklist

Before any move or split, verify:

- every `/absensi*` route and page mapping, including routes accidentally grouped with Izin/Overtime/Shift;
- `check.jabatan:absensiPage` authorization behavior;
- Inertia page resolution and all imports from `absensiSales.vue`;
- controller methods used by attendance page versus leave/approval/admin flows;
- constructor dependency callers and service ownership;
- GCS/local photo upload, signed URL, cleanup, and Linux credential behavior;
- transaction number generation and database transaction boundaries;
- dedicated submit, upload failure, duplicate submission, daily status, and browser tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `grouped compatibility boundary`; controller remains intentionally undivided because it owns attendance, leave, approval, shift, and overtime methods.

A future move requires explicit confirmation of:

1. `ESS/Attendance/Personal` hierarchy;
2. exact method boundary for personal attendance versus Izin/approval/admin;
3. whether a new thin controller/service is implemented first or legacy controller is relocated;
4. whether frontend decomposition is part of the same scope;
5. test-gap remediation scope;
6. exact old-path → new-path map and rollback procedure.

Controller and personal attendance page are grouped under ESS/Attendance while legacy route URLs and all mixed workflow methods remain unchanged. Verification: PHP syntax/class loading, frontend build (4,398 modules), and attendance attachment test passed (1 test, 3 assertions). Related HCAbsensi suite had 31 intentionally skipped real-DB tests.
