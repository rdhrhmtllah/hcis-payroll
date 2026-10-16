# Feature Mapping: LMS-Registered SOP Category

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopKategoriPage` ke implementation existing Master Kategori SOP. Registry menempatkan page ini di module `LMS`, sedangkan source dan business boundary menggunakan domain `SOP`. Controller dan page telah dipindahkan dalam batch kecil; registry/source mismatch tetap tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopKategoriPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/kategori
Middleware    : auth, check.jabatan:menuSopKategoriPage
```

Registry memverifikasi mapping ke `LMS`, tetapi source identifier, controller, frontend, route, service, dan database table menunjukkan domain SOP. Belum ada module SOP aktif pada registry.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Governance / Master Data    # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Category Master        # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : NOT_APPROVED
```

Page ini adalah master data admin SOP. Ia berbeda dari `LMS Training Category` dan tidak boleh digabung hanya karena kedua page mengandung kata kategori.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopCategory/SopCategoryController.php` | page, pagination/search, create, update, soft-delete | `MIGRATED` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | `/sop/admin/kategori*`; shared SOP route file retained | `MIGRATED_IMPORT_ONLY` |
| Main page | `resources/js/Pages/LMS/SOP/Governance/SopCategory/SopCategoryPage.vue` | admin category CRUD UI | `MIGRATED` |
| Service | `app/Services/LMS/SOP/SopKategoriService.php` | paginate, Hashids, create/update, usage-safe soft delete | `SOURCE_DOMAIN_KEEP` |
| Status helper | `app/Services/LMS/SOP/Support/SopStatus.php` | active/inactive semantics | `SHARED_SOP_KEEP` |
| DB table | `N_SOP_Kategori` | category master | `DEPENDENCY_REVIEW` |
| Consumer table | `N_SOP_Master_Paket` | prevents delete while category is used | `DEPENDENCY_REVIEW` |
| Related page | `SopAdminPrioritasController`/`SopPrioritas.vue` | priority page accepts category access | `RELATED_BOUNDARY` |
| Tests | `Kontrol004KategoriTerpakaiTest.php`, `Alur024KebijakanOtomasiTest.php` | service/usage behavior | `RELATED_TEST` |
| Dedicated page suite | no dedicated full page/browser suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET    /sop/admin/kategori
GET    /api/v1/sop/admin/kategori/current
POST   /api/v1/sop/admin/kategori/store
PUT    /api/v1/sop/admin/kategori/update/{id}
DELETE /api/v1/sop/admin/kategori/delete/{id}
```

Related priority page has a separate route and should remain a separate feature boundary:

```text
/sop/admin/prioritas
/api/v1/sop/admin/prioritas/*
```

## Important findings

1. `menuSopKategoriPage` is LMS-registered but source/business ownership is SOP. Status is `REGISTRY_SOURCE_MISMATCH_REVIEW`.
2. `SopCategoryController` follows a thin-controller-to-service pattern and is a useful vertical-slice reference, but that does not authorize physical migration during this phase.
3. `SopKategoriService::softDelete()` prevents deletion while active packages reference the category. This is an important data safety contract.
4. Hashids encode list IDs and decode update/delete IDs; route/API consumers must preserve this contract.
5. `N_SOP_Kategori` is consumed by package builder, monitoring/filtering, policy, and scheduling-related workflows.
6. `menuSopPrioritasPage` is a related but separate feature; it accepts category access for compatibility but must not be merged into category ownership.
7. Existing service/UAT tests cover category usage and related flows, but no dedicated complete page/authorization/browser suite was found.

## Migration record

Executed paths:

```text
app/Http/Controllers/Sop/SopAdminKategoriController.php
→ app/Http/Controllers/LMS/SOP/Governance/SopCategory/SopCategoryController.php

resources/js/Pages/LMS/SOP/Governance/Admin/SopKategori.vue
→ resources/js/Pages/LMS/SOP/Governance/SopCategory/SopCategoryPage.vue
```

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/SOP/Governance/SopCategoryController.php
app/Services/LMS/SOP/Governance/SopCategoryService.php
routes/LMS/SOP/Governance/SopCategory.php
resources/js/Pages/LMS/SOP/Governance/SopCategory/SopCategoryPage.vue
tests/<type>/LMS/SOP/Governance/SopCategory/
```

This proposal is blocked until registry/business ownership decides whether SOP becomes a formal module or remains registered under LMS. Current SOP paths are the safe state.

## Dependency and risk checklist

Before any move, verify:

- registry decision for `menuSopKategoriPage` and absent formal SOP module;
- all category consumers, especially package builder, monitoring, policy, and scheduling;
- soft-delete protection and active/inactive semantics;
- Hashids and pagination response contract;
- category access relationship with separate priority page;
- focused CRUD, authorization, usage-safety, frontend, and browser tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `controller/page migrated; shared route file retained`.

Commit: `2c951d03 refactor: align sop category paths`. URL/API/middleware/database/service contracts remain unchanged. Registry/business ownership remains unresolved and the live LMS mapping was not silently changed. Route extraction is a separate future batch.
