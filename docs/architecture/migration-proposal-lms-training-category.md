# Migration Proposal: LMS Training Category

Status: `EXECUTED_AND_VERIFIED`
Approval: `APPROVED_IN_SESSION`
Tanggal: 2026-08-18
Branch: `refactor/file-structure-alignment`

## Objective

Proposal batch pertama untuk merapikan boundary `LMSTrainingKategoriPage` tanpa mengubah behavior, database schema, route contract, atau authorization. Kandidat ini dipilih karena implementation page relatif terisolasi dibanding SOP Master Document, Monitoring, dan shared Content Library.

Pemindahan file telah dijalankan dalam batch kecil dan diverifikasi.

## Verified registry

```text
Module       : LMS
Module id    : 3
Page         : LMSTrainingKategoriPage
URL          : /lms/training-kategori
Middleware   : auth, check.jabatan:LMSTrainingKategoriPage
Default      : T
Sidebar      : Y
Flag aktif   : Y
``

```text
Submodule    : Training / Master Data       # proposal
Feature      : Training Category            # proposal
``

Registry belum memiliki kolom formal Submodule/Feature; kedua nilai di atas memerlukan review maintainer.

## Proposed file map

Batch ini telah dijalankan. Tabel berikut menjadi catatan old → new path yang dieksekusi.

| Layer | Old path | Proposed new path | Impact |
|---|---|---|---|
| Controller | `app/Http/Controllers/LmsTrainingKategori/LmsTrainingKategoriController.php` | `app/Http/Controllers/LMS/Training/MasterData/TrainingCategory/TrainingCategoryController.php` | namespace, route import, route loader |
| Page | `resources/js/Pages/LMS/modules/training-kategori/HomeTrainingKategori.vue` | `resources/js/Pages/LMS/Training/MasterData/TrainingCategory/TrainingCategoryPage.vue` | Inertia component key, relative imports, component name |
| Web/API route | `routes/LmsTrainingKategori/LmsTrainingKategoriWeb.php` | `routes/LMS/Training/MasterData/TrainingCategory.php` | route loader and controller import |
| Focused tests | no dedicated page suite | `tests/Feature/LMS/Training/MasterData/TrainingCategory/` | new tests required before/with move |

The following files are explicitly out of this batch:

```text
app/Http/Controllers/LmsTrainingKategori/LmsTrainingKategoriApi.php
routes/LmsTrainingKategori/LmsTrainingKategoriApi.php
``

They contain generic API/resource stubs and require a separate disposition decision. They must not be silently deleted, merged, or moved as cleanup.

## Runtime contracts to preserve

### Routes

```text
GET  /lms/training-kategori
GET  /api/v1/training/kategori/current
POST /api/v1/training/kategori/store
PUT  /api/v1/training/kategori/update/{id_training_kategori}
``

Route middleware must remain:

```text
auth
check.jabatan:LMSTrainingKategoriPage
``

Route URLs should remain unchanged to avoid breaking sidebar, bookmarks, frontend Axios calls, and external/internal links.

### Controller behavior

The move must preserve:

```text
- batch insert semantics
- duplicate code detection inside request
- duplicate code detection against database
- transaction begin/commit/rollback
- audit user Id_User
- Hashids encoding for list/update ids
- safe error response via ResponseHelper
- pagination and search response shape
- update endpoint contract
``

### Frontend behavior

The move must preserve:

```text
- Inertia page key resolution
- search debounce and pagination
- multi-row create form
- edit modal
- API URLs and payload names
- loading/error behavior
- existing shared HeaderKpi, ModalV1, table, and FormV1 imports
``

## Caller and dependency map

The table `N_LMS_Training_Kategori` is shared data, even though the controller/page is feature-specific. Existing callers include:

```text
app/Http/Controllers/LmsMateri/LmsAdminTrainingController.php
app/Http/Controllers/TrainingCatalog/TrainingCatalogController.php
app/Http/Controllers/LmsMonitoring/LmsMonitoringController.php
app/Services/LMS/TrainingScheduleQueryService.php
app/Services/LMS/LmsTrainingExportService.php
``

These callers must not be moved or rewritten in this batch. The database table and schema remain unchanged.

Known route loader evidence:

```text
routes/kpi/fransDevEvo.php
``

The loader, direct route URLs, route cache behavior, and any route snapshot must be verified immediately before and after an approved move.

## Test plan

Current status: `TEST_GAP_REVIEW_REQUIRED`.

### Required focused tests before completion

```text
1. page authorization: allowed user receives 200; denied user receives 403
2. list pagination and search response
3. batch create success
4. duplicate code inside request rolls back
5. duplicate code in database rolls back
6. invalid Hashids update returns stable error
7. update success preserves response contract
8. frontend page renders and calls the expected API paths
``

### Required impacted checks

```text
- LmsAdminTraining category option loading
- Training Catalog category join/list
- LMS scheduling option/query path
- LMS monitoring category filter
- LMS export category join
- route list and Inertia component resolution
``

No full regression is implied. Test result must distinguish `implemented`, `verified`, `pending`, and `blocked`.

## Migration procedure after approval

1. Confirm maintainer approval for Module/Submodule/Feature and exact target paths.
2. Create the focused test baseline without changing production behavior.
3. Verify clean working tree and record current commit.
4. Move only the controller, page, and active route file in one small commit/batch.
5. Update namespace/import, Inertia key, route loader, and relative imports only as required.
6. Verify route list, focused tests, impacted checks, and frontend build/lint scope.
7. Review diff for accidental generic stub deletion or shared caller changes.
8. Stop before push and report working-tree, commit, and test state separately.

Database operations are not part of this batch:

```text
no migration
no DDL
no DML
no DROP/TRUNCATE
no production database action
``

## Rollback

Rollback must be a reverse file move plus restoration of namespace/import, Inertia key, and route loader changes. If runtime verification fails, revert the migration commit rather than editing unrelated legacy logic. Database rollback is not applicable because this batch contains no database changes.

## Decision gate

```text
Candidate : APPROPRIATE_FOR_REVIEW
Approval  : REQUIRED
Execution : BLOCKED_UNTIL_APPROVED
``

Until explicit approval is recorded, all old paths remain unchanged and no target folder is created.
