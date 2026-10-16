# Migration Proposal: ESS Leave Sources

Status: `EXECUTED_AND_VERIFIED`

## Scope
Grouped existing leave controllers and pages into ESS/Attendance/Leave with separate Personal, Admin, and Approval sub-boundaries. No business logic or controller decomposition was performed.

## Paths
```text
app/Http/Controllers/HCAbsensi/cuti/karyawan/*
→ app/Http/Controllers/ESS/Attendance/Leave/Personal/*
app/Http/Controllers/HCAbsensi/cuti/admin/*
→ app/Http/Controllers/ESS/Attendance/Leave/Admin/*
app/Http/Controllers/HCAbsensi/ManajemenHCIS/Izin/IzinAtasanApprovalController.php
→ app/Http/Controllers/ESS/Attendance/Leave/Approval/IzinAtasanApprovalController.php
resources/js/Pages/Modules/Cuti/*
→ resources/js/Pages/ESS/Attendance/Leave/Cuti/*
resources/js/Pages/Modules/ManajemenHCIS/Izin/*
→ resources/js/Pages/ESS/Attendance/Leave/Izin/*
```

## Preserved
All leave URLs, middleware, route names, controller methods, Inertia payloads, transaction/approval behavior, shared services, and database contracts.

## Verification
PHP syntax/class loading passed; frontend build passed (4,398 modules); storage attendance test passed. HCAbsensi suite ran with 31 intentional skips because real DB-writing flags were not enabled.

## Rollback
`git revert <migration-commit>`; no database rollback required.
