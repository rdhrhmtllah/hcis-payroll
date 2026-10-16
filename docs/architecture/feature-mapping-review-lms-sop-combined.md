# Consolidated Feature Mapping Review: LMS and SOP Boundaries

Status: `MAPPING_REVIEW_UPDATED`
Tanggal snapshot: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Branch: `refactor/file-structure-alignment`

## Tujuan

Dokumen ini menggabungkan hasil inventory dan feature mapping untuk seluruh active page registry pada module `LMS`, khususnya area SOP yang secara source/business menggunakan domain `SOP`. Dokumen ini adalah bahan review ownership dan migration planning; bukan persetujuan pemindahan file.

Fase yang sudah dilakukan:

```text
live registry verification
→ source/route/controller/frontend/service/test mapping
→ shared-boundary identification
→ migration risk documentation
```

Fase yang belum dilakukan:

```text
registry ownership decision
→ migration batch approval
→ physical file movement
→ namespace/import/route changes
→ behavior refactor
```

## Registry authority

Registry aktif yang diverifikasi:

```text
ESS      : 30 mappings
KPI      : 20 mappings
LMS      : 16 mappings
VISITOR  : 6 mappings
SYSTEM   : 1 mapping
```

Registry tidak memiliki kolom formal `Submodule` atau `Feature`. Karena itu hanya module registry yang dapat dianggap `VERIFIED_MODULE`; hierarchy lebih rendah tetap `REVIEW_REQUIRED` sampai ada keputusan maintainer/business.

Tidak ada module aktif berkode `SOP`. Seluruh page SOP berikut masih terdaftar sebagai `LMS` dan harus dipertahankan sebagai mismatch yang terlihat:

```text
REGISTRY_SOURCE_MISMATCH_REVIEW
```

## Mapped LMS page boundaries

| Registry page | Proposed source boundary | Implementation boundary | Key status |
|---|---|---|---|
| `LMSTrainingCatalogPage` | LMS / Training / Catalog | LMS training catalog | `MIGRATED_AND_VERIFIED` |
| `LMSTrainingKategoriPage` | LMS / Training / Category | training category controller/page | `MIGRATED_AND_VERIFIED` |
| `LMSContentManagementPage` | LMS / Content Management | shared LMS/SOP content controller | `SHARED_BOUNDARY` |
| `LMSAdminTrainingPage` | LMS / Training / Content Authoring | admin training controller/builder | `MIGRATED_AND_VERIFIED` |
| `LMSPenjadwalanPage` | LMS / Training / Scheduling | training scheduling controller/service/job | `MIGRATED_AND_VERIFIED` |
| `LMSAdminMonitoringPage` | LMS / Training / Monitoring | monitoring controller/pages; shared services/export retained | `MIGRATED_AND_VERIFIED` |
| `menuApprovalMatrixLmsPage` | LMS context / shared Authorization | shared ESS/KPI/LMS/VISITOR authorization engine | `SHARED_BOUNDARY` |
| `menuSopSayaPage` | SOP / Employee Access | employee SOP workflow | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |
| `menuSopRepositoryPage` | SOP / Employee Repository | SOP repository/content access | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |
| `menuSopKategoriPage` | SOP / Governance / Category | SOP category service/controller | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |
| `menuSopBuilderPage` | SOP / Governance / Builder | SOP package builder and draft contracts | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |
| `menuSopPenjadwalanPage` | SOP / Operations / Scheduling | target selection, assignment, revoke, async bulk | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |
| `menuSopMonitoringPage` | SOP / Governance / Monitoring | analytics, people deep-dive, export, reminder/reset | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |
| `menuSopKebijakanPage` | SOP / Governance / Automation | policy settings, assignment rules, run history/job | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |
| `menuSopKontenPage` | SOP / Content Library | shared LMS/SOP content library with `scope=SOP` | `SHARED_BOUNDARY` |
| `menuSopDokumenPage` | SOP / Governance / Master Data | master document/version/publish/rollback | `MIGRATED_AND_VERIFIED; REGISTRY_SOURCE_MISMATCH` |

Detailed evidence is maintained in the individual documents under `docs/architecture/feature-mapping-*.md`.

## Shared boundaries that must not be moved casually

### Shared LMS/SOP content library

```text
app/Http/Controllers/KontenKategoriController.php
resources/js/Pages/LMS/modules/content-management/contentDashboard.vue
```

One controller and one frontend serve LMS and SOP. Route defaults and `Scope` filtering isolate data. A direct move to an LMS-only or SOP-only folder would break the shared contract.

### Shared authorization engine

`menuApprovalMatrixLmsPage` is an LMS registry context over shared authorization implementation used by ESS, KPI, LMS, and VISITOR. It must not become an LMS-local feature simply because the page mapping is LMS.

### SOP canonical document services

```text
SopDokumenService
SopDokumenFanoutService
SopReconciliationService
SopAksesBerkas
```

These are used by Master Dokumen, Builder, Repository, Monitoring, item/version services, commands, and UAT helpers. Their location and ownership require caller analysis before any move.

### Cross-feature scheduling and automation

```text
SopPenjadwalanService
SopPenjadwalanTargetService
SopAssignmentRuleService
SopAutomationRunService
RunSopAutomation
```

Policy automation, manual scheduling, monitoring, and assignment flows share side effects and run-state contracts. They cannot be split by page name alone.

## Migration candidates versus blockers

### Candidate for later focused migration

A feature may become a migration candidate only when all of the following are true:

```text
module registry verified
submodule/feature approved
old/new path exact
namespace/import impact mapped
route loader impact mapped
shared callers classified
focused test identified
impacted test identified
rollback path documented
owner/maintainer assigned
```

Potentially focused boundaries, subject to approval:

```text
LMS Training Category
LMS Training Catalog
SOP Category
SOP Master Document UI boundary
SOP Monitoring UI boundary
```

These are candidates for review only; no one is approved for physical movement.

### Blockers requiring decision first

```text
1. No formal SOP module exists in live registry.
2. All SOP page mappings currently point to LMS.
3. Registry has no Submodule/Feature columns.
4. Several controllers are large mixed legacy boundaries.
5. Shared services and UI are used by multiple pages/modules.
6. Publish, rollback, delete, upload, reminder, reset, and run-now are state-changing operations.
7. Provider dependencies include GCS, Bunny, Cloud Tasks, and external HC Learn.
8. Existing tests are related but do not prove a complete browser/page matrix.
```

## Proposed migration batch format

No batch is approved yet. A future proposal must use a table like this for each file:

| Old path | Proposed new path | Module | Submodule | Feature | Layer | Shared callers | Focused test | Impacted test | Rollback |
|---|---|---|---|---|---|---|---|---|---|
| existing path | approved target only | registry module | reviewed | reviewed | controller/service/page/route/test | enumerated | named | named | reverse move/revert commit |

A batch must not include unrelated cleanup, controller rewrite, database migration, route redesign, or broad namespace refactor.

## Test governance for the next phase

Mapping status is not runtime verification. Before an approved move:

```text
feature scope  : focused feature tests
impacted scope : shared callers, route, authorization, provider contracts
full scope     : only when explicitly approved or release-critical
```

Use truthful statuses:

```text
implemented
verified
pending
blocked
```

Known existing environment/test issues remain separate from this structure branch:

```text
SOP-016 drag/drop ordering
SOP-031 invalid Linux GCS credential path
missing SQLite driver for some Feature/Integration paths
Cloud Tasks/GCS credential availability
```

They must not be silently treated as resolved by documentation mapping.

## Current decision

The LMS/SOP mapped source boundaries have now been grouped and verified; registry/source mismatches remain explicit. Remaining features are still documentation-only.

```text
MAPPING_REVIEW_UPDATED
LMS_TRAINING_CATEGORY_MIGRATED
LMS_TRAINING_CATALOG_MIGRATED
LMS_TRAINING_SCHEDULING_MIGRATED
LMS_ADMIN_TRAINING_MIGRATED
LMS_ADMIN_MONITORING_MIGRATED
LMS_SOP_GROUPED_MIGRATED
SOP_CATEGORY_MIGRATED
REMAINING_PHYSICAL_MIGRATION_NOT_APPROVED
SOURCE_CODE_CHANGE_APPROVED_ONLY_FOR_TRAINING_CATEGORY
DATABASE_CHANGE_NOT_APPROVED
PUSH_NOT_APPROVED
PR_NOT_APPROVED
```

Safe next action is maintainer review of ownership and selection of one concrete migration batch. Until that approval exists:

```text
existing source paths remain unchanged
registry mismatch remains documented
shared boundaries remain shared
no new folders are created
no git mv is performed
```

## Verification record

```text
git diff --check : required after this document and passed before commit
git status       : must remain clean after commit
source/runtime   : no changes in this phase
database         : read-only registry verification only
```
