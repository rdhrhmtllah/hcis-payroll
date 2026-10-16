# Migration Proposal: LMS Admin Training

Status: `EXECUTED_AND_VERIFIED`
Approval: `APPROVED_IN_SESSION`

## Scope

Controller, list page, builder page, local content picker, and active route file were aligned under the LMS Training/Content/AdminTraining boundary. Shared content-management implementation, storage, database, and services remain unchanged.

## Executed paths

```text
app/Http/Controllers/LmsMateri/LmsAdminTrainingController.php
→ app/Http/Controllers/LMS/Training/Content/AdminTraining/AdminTrainingController.php

resources/js/Pages/LMS/modules/admin-training/adminTrainingList.vue
→ resources/js/Pages/LMS/Training/Content/AdminTraining/AdminTrainingListPage.vue

resources/js/Pages/LMS/modules/admin-training/adminTrainingBuilder.vue
→ resources/js/Pages/LMS/Training/Content/AdminTraining/AdminTrainingBuilderPage.vue

resources/js/Pages/LMS/modules/admin-training/components/KontenPickerModal.vue
→ resources/js/Pages/LMS/Training/Content/AdminTraining/components/KontenPickerModal.vue

routes/LmsAdminTraining/LmsAdminTrainingWeb.php
→ routes/LMS/Training/Content/AdminTraining/AdminTraining.php
``

The SOP callers `ItemFields.vue`, `SopDokumenVersiModal.vue`, and `SopVersionPanel.vue` were updated only to the new picker import path.

## Preserved contracts

```text
/lms/admin/training/*
auth + access.context:admin + check.jabatan:LMSAdminTrainingPage
N_LMS_Materi and related content table contracts
storage/preview behavior
``

## Excluded

```text
shared content-management controller/services
GCS/Bunny/Cloud Tasks providers
database schema or data
training category, catalog, scheduling, monitoring
``

## Verification

```text
PHP syntax        : passed
Composer class    : passed
Old reference scan: passed
git diff --check  : passed
Frontend build    : passed (4401 modules transformed)
Focused page test : not available; no dedicated LMSAdminTrainingPage suite found
``

Build warnings are pre-existing font, deprecated `::v-deep`, CSS, and chunk-size warnings.

## Rollback

```text
git revert <migration-commit>
``

No database rollback is required.
