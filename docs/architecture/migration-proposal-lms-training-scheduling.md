# Migration Proposal: LMS Training Scheduling

Status: `EXECUTION_IN_PROGRESS`
Approval: `APPROVED_IN_SESSION`
Tanggal: 2026-08-18

## Scope

Batch ini hanya menyelaraskan path controller, page, dan active web/API route file untuk `LMSPenjadwalanPage`. Shared query service, Outbox services, async job, dan generic API stub tidak dipindahkan. URL, middleware, payload, database, dan behavior dipertahankan.

## Registry

```text
Module       : LMS
Page         : LMSPenjadwalanPage
URL          : /lms/penjadwalan
Middleware   : auth, check.jabatan:LMSPenjadwalanPage
Submodule    : Training / Scheduling
Feature      : Training Assignment Scheduling
``

## File map

```text
app/Http/Controllers/LmsPenjadwalanTraining/LmsPenjadwalanTrainingController.php
→ app/Http/Controllers/LMS/Training/Scheduling/TrainingSchedulingController.php

resources/js/Pages/LMS/modules/penjadwalan-training/HomePenjadwalanTraining.vue
→ resources/js/Pages/LMS/Training/Scheduling/TrainingSchedulingPage.vue

routes/LmsPenjadwalanTraining/LmsPenjadwalanTrainingWeb.php
→ routes/LMS/Training/Scheduling/TrainingScheduling.php
``

Excluded:

```text
app/Http/Controllers/LmsPenjadwalanTraining/LmsPenjadwalanTrainingApi.php
routes/LmsPenjadwalanTraining/LmsPenjadwalanTrainingApi.php
app/Services/LMS/TrainingScheduleQueryService.php
app/Jobs/ProcessBulkPenjadwalan.php
app/Services/SYSTEM/TaskProcessing/*
``

## Preserved contracts

All existing `/lms/penjadwalan` and `/api/v1/lms/penjadwalan/*` endpoints remain unchanged. The route loader path is updated only to the new route file.

## Verification

Required: PHP syntax, class autoload, old-reference scan, diff check, focused UAT scheduling test, and frontend build. Laravel route:list may be blocked by existing Google ADC initialization.

## Rollback

`git revert <migration-commit>`; no database rollback is applicable.
