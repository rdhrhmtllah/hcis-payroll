# Migration Proposal: LMS Content Management

Status: `EXECUTED_AND_VERIFIED_SHARED_BOUNDARY`

## Scope
Grouped the shared LMS/SOP content controller and frontend library under LMS/Content without splitting scope behavior or moving storage/async services.

## Paths
```text
app/Http/Controllers/KontenKategoriController.php
→ app/Http/Controllers/LMS/Content/ContentManagementController.php
resources/js/Pages/LMS/modules/content-management/*
→ resources/js/Pages/LMS/Content/ContentManagement/*
```

## Preserved
`scope=LMS/SOP`, all `/lms/konten-kategori*` and SOP content routes, upload ticket/claim flow, Cloud Tasks, GCS/Bunny, delete safety, usage tracking, and Admin Training/SOP picker callers.

## Verification
PHP syntax and class loading passed; frontend build passed; content/upload/delete/picker suite passed (7 tests, 20 assertions); no database changes.

## Rollback
`git revert <migration-commit>`; no database rollback required.
