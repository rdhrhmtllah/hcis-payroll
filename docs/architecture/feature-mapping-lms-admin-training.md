# Feature Mapping: LMS Admin Training

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `LMSAdminTrainingPage` dan alur admin training existing. Batch controller/page/route alignment telah dieksekusi; shared content/storage/database contract tetap.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : LMSAdminTrainingPage
Id_Grup_Fitur : 3
Flag_Aktif   : Y

URL           : /lms/admin/training
Middleware    : auth, access.context:admin, check.jabatan:LMSAdminTrainingPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : LMS                         # VERIFIED_MODULE
Submodule : Training / Content          # PROPOSAL_REVIEW_REQUIRED
Feature   : Admin Training Management   # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Page, Components, Route, Tests, Storage/DB docs
``

Content management, training category, scheduling, catalog, monitoring, dan SOP content adalah related LMS boundaries dan tidak otomatis masuk satu batch.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/Training/Content/AdminTraining/AdminTrainingController.php` | page, list, master-data, show, store, update, destroy, preview | `LEGACY_KEEP` |
| Route | `routes/LMS/Training/Content/AdminTraining/AdminTraining.php` | `/lms/admin/training/*` | `LEGACY_KEEP` |
| Route loader | `routes/kpi/ridhoDevEvo.php` | route require | `LEGACY_KEEP` |
| List page | `resources/js/Pages/LMS/Training/Content/AdminTraining/AdminTrainingListPage.vue` | catalog/admin training list | `FEATURE_LOCAL_KEEP` |
| Builder page | `resources/js/Pages/LMS/Training/Content/AdminTraining/AdminTrainingBuilderPage.vue` | create/edit training | `FEATURE_LOCAL_KEEP` |
| Content picker | `resources/js/Pages/LMS/Training/Content/AdminTraining/components/KontenPickerModal.vue` | select content for training | `FEATURE_LOCAL_KEEP` |
| DB tables | `N_LMS_Materi`, detail/package/content tables | direct query and write chain | `DEPENDENCY_REVIEW` |
| Storage | controller Storage usage and content preview | thumbnail/content handling | `DEPENDENCY_REVIEW` |
| Tests | no dedicated `LMSAdminTrainingPage` test found | repository search | `TEST_GAP_REVIEW_REQUIRED` |
| Related tests | LMS/SOP content tests and third-party handler tests | adjacent content behavior | `RELATED_TEST` |

## Important findings

1. The controller combines page rendering, list filtering, master data, CRUD, content preview, and database writes. A direct move is not a pure structure change if namespace/import/route binding changes.
2. Builder and content picker touch multiple LMS content tables; table contracts and cleanup behavior must remain unchanged.
3. The page is admin-only through both `access.context:admin` and `LMSAdminTrainingPage`; these must not be replaced by a role folder or inferred module.
4. No dedicated backend/frontend/browser suite for the complete admin training flow was found.
5. Related SOP/content tests do not prove the admin training CRUD flow.

## Migration record

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/Training/Content/AdminTrainingController.php
app/Services/LMS/Training/Content/
routes/LMS/Training/Content/AdminTraining.php
resources/js/Pages/LMS/Training/Content/AdminTraining/AdminTrainingListPage.vue
resources/js/Pages/LMS/Training/Content/AdminTraining/AdminTrainingBuilderPage.vue
resources/js/Pages/LMS/Training/Content/AdminTraining/components/
tests/<type>/LMS/Training/Content/AdminTraining/
``

These are proposal paths only. Controller decomposition and page renaming require a separate approved scope.

## Dependency and risk checklist

Before any move, verify:

- all page/API routes and route loader order;
- `LMSAdminTrainingPage` and admin context authorization;
- Inertia page resolution and builder/list/picker imports;
- direct API calls and validation/error contracts;
- LMS material/detail/package/content table relationships;
- thumbnail/upload/preview storage and cleanup behavior;
- focused CRUD, authorization, frontend, browser, and regression tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`.

A future move requires explicit confirmation of hierarchy, content/catalog/scheduling boundaries, controller extraction strategy, frontend scope, test-gap remediation, exact old-path → new-path map, and rollback procedure.

Commit: `bd895e4f refactor: align lms admin training paths`. Focused page suite remains a test gap; frontend build passed and shared SOP picker callers were updated.
