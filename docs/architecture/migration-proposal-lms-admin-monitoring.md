# Migration Proposal: LMS Admin Monitoring

Status: `EXECUTED_AND_VERIFIED`

## Scope
Path alignment only for the LMS admin monitoring controller, route, local pages, and local export components. Shared progress/schedule services, export service/job, and training detail remain unchanged.

## Paths
```text
app/Http/Controllers/LmsMonitoring/LmsMonitoringController.php
→ app/Http/Controllers/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoringController.php
routes/LmsMonitoring/LmsMonitoringWeb.php
→ routes/LMS/Training/Monitoring/AdminTrainingMonitoring/AdminTrainingMonitoring.php
resources/js/Pages/LMS/modules/monitoring/monitoringTraining.vue
→ resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/MonitoringPage.vue
resources/js/Pages/LMS/modules/monitoring/testDetail.vue
→ resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/TestDetailPage.vue
resources/js/Pages/LMS/modules/monitoring/components/*
→ resources/js/Pages/LMS/Training/Monitoring/AdminTrainingMonitoring/components/*
```

## Preserved
URL `/lms/admin/monitoring/*`, middleware, route names, API payloads, export lifecycle, database behavior, and shared callers.

## Verification
PHP syntax passed; Composer class loading passed; `Alur040MonitoringTest` passed (4 tests, 9 assertions); frontend build passed; `git diff --check` passed. Existing font/deprecation/chunk warnings remain.

## Rollback
`git revert <migration-commit>`; no database rollback required.
