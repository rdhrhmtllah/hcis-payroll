# Migration Proposal: LMS Training Catalog

Status: `EXECUTION_IN_PROGRESS`
Approval: `APPROVED_IN_SESSION`
Tanggal: 2026-08-18
Branch: `refactor/file-structure-alignment`

## Scope

Batch ini menyelaraskan path Training Catalog tanpa mengubah URL, API payload, authorization, query service, database, atau behavior. Detail training tetap dianggap related boundary dan tidak dipindahkan dalam batch ini.

## Registry

```text
Module       : LMS
Page         : LMSTrainingCatalogPage
Web URL      : /lms/training/catalog
Middleware   : auth, check.jabatan:LMSTrainingCatalogPage
Submodule    : Training / Catalog       # reviewed proposal
Feature      : Training Catalog         # reviewed proposal
```

## Executed file map

| Layer | Old path | Target path |
|---|---|---|
| Web controller | `app/Http/Controllers/TrainingCatalog/TrainingCatalogController.php` | `app/Http/Controllers/LMS/Training/Catalog/TrainingCatalogController.php` |
| API controller | `app/Http/Controllers/TrainingCatalog/TrainingCatalogApi.php` | `app/Http/Controllers/LMS/Training/Catalog/TrainingCatalogApi.php` |
| Web route | `routes/TrainingCatalog/TrainingCatalogWeb.php` | `routes/LMS/Training/Catalog/TrainingCatalog.php` |
| API route | `routes/TrainingCatalog/TrainingCatalogApi.php` | `routes/LMS/Training/Catalog/TrainingCatalogApi.php` |
| Page | `resources/js/Pages/LMS/modules/training/trainingCatalog.vue` | `resources/js/Pages/LMS/Training/Catalog/TrainingCatalogPage.vue` |
| Local UI | `resources/js/Pages/LMS/modules/training/components/ui/*` | `resources/js/Pages/LMS/Training/Catalog/components/ui/*` |
| Local data | `resources/js/Pages/LMS/modules/training/data/*` | `resources/js/Pages/LMS/Training/Catalog/data/*` |

Excluded from this batch:

```text
resources/js/Pages/LMS/modules/training/detail/*
app/Services/LMS/TrainingScheduleQueryService.php
app/Services/LMS/TrainingProgressReadService.php
``

## Contracts preserved

```text
GET /lms/training/catalog
GET /lms/training/filter-options
GET /lms/training/schedule/{penjadwalanId}
GET /lms/training/{id}
GET /api/v1/lms/training/{id}
``

Authorization remains `auth` plus `check.jabatan:LMSTrainingCatalogPage` for web routes. The API route/controller remains behaviorally unchanged, including its existing separate route ownership and unused CRUD stubs.

## Dependencies

Shared services and related detail flows remain in place. No database or provider operation is included. Direct Axios paths and notification links remain unchanged.

## Verification plan

```text
- PHP syntax for both controllers and route files
- class autoload check
- old reference scan
- route loader reference scan
- git diff --check
- frontend production build
- route list if environment allows
``

Known environment blocker: Laravel route discovery may initialize `CloudTaskService` and fail on missing Google ADC credentials. This is reported separately from path verification.

## Rollback

```text
git revert <migration-commit>
``

No database rollback is needed.
