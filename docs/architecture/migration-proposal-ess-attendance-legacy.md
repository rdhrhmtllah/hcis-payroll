# Migration Proposal: ESS Attendance Legacy Compatibility Boundary

Status: `EXECUTED_AND_VERIFIED`

## Scope
Moved the mixed legacy `AbsensiController` and personal attendance page into ESS/Attendance without splitting methods. Leave, approval, shift, overtime, storage, and transaction behavior remain exactly in the same compatibility controller.

## Paths
```text
app/Http/Controllers/AbsensiController.php
→ app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php
resources/js/Pages/absensiSales.vue
→ resources/js/Pages/ESS/Attendance/Personal/AbsensiSalesPage.vue
```

## Preserved
All `/absensi*`, `/izin*`, `/lembur`, and related route URLs, middleware, route names, controller methods, Inertia behavior, storage paths, and database contracts.

## Verification
PHP syntax/class loading passed; frontend build passed (4,398 modules); `AbsensiIzinAttachmentStorageTest` passed (1 test, 3 assertions). HCAbsensi suite ran with 31 intentional skips because real DB-writing flags were not enabled.

## Rollback
`git revert <migration-commit>`; no database rollback required.
