# Feature Mapping: SYSTEM Database Change Dashboard

Status: `ALREADY_ALIGNED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Boundary feature ini telah diverifikasi sudah memakai struktur target SYSTEM/DatabaseChanges; tidak diperlukan physical move tambahan.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : SYSTEM
Id            : 5
Nama         : System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : systemDatabaseChangeDashboardPage
Id_Grup_Fitur : 5
Flag_Aktif    : Y

URL           : /system/database-changes
Menu name     : Database Change Dashboard
Middleware    : auth, system.admin
``

`SYSTEM` dan page mapping di atas terverifikasi dari database/source. Registry belum menyediakan kolom `Submodule` atau `Feature`, sehingga nama submodule/feature berikut adalah proposal arsitektur berdasarkan domain dan source, bukan data registry authoritative.

## Proposed ownership

```text
Module    : SYSTEM                         # VERIFIED_MODULE
Submodule : Governance / DatabaseChanges    # PROPOSAL_REVIEW_REQUIRED
Feature   : Database Change Dashboard       # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Service, Model, Page, API module, Route, Test, DDL/DML docs
``

Role `System Admin` adalah authorization scope, bukan submodule atau feature owner.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/SYSTEM/DatabaseChanges/DatabaseChangeDashboardController.php` | Inertia page, list, SQL, status, batch actions | `ALREADY_ALIGNED` |
| Service | `app/Services/SYSTEM/DatabaseChanges/DatabaseChangeDashboardService.php` | Query, artifact read, status lifecycle | `ALREADY_ALIGNED` |
| Model | `app/Models/SystemDatabaseChange.php` | Table `N_SYSTEM_Database_Change` | `REVIEW_REQUIRED` |
| Route | `routes/SYSTEM/DatabaseChanges.php` | `/system/database-changes*` and `system.admin` | `ALREADY_ALIGNED` |
| Route loader | `routes/web.php` | Requires `routes/SYSTEM/DatabaseChanges.php` | `LEGACY_KEEP` |
| Page | `resources/js/Pages/SYSTEM/DatabaseChanges/DatabaseChangeDashboardPage.vue` | Inertia page matching controller render | `LEGACY_KEEP` |
| Feature test | `resources/js/Pages/SYSTEM/DatabaseChanges/DatabaseChangeDashboardPage.test.js` | Vitest lifecycle/error/mutation tests | `LEGACY_KEEP` |
| API module | `resources/js/Pages/SYSTEM/DatabaseChanges/api/databaseChangeApi.js` | Axios calls for feature endpoints | `LEGACY_KEEP` |
| Shell authorization | `app/Services/Shell/InertiaShellService.php` | Filters `systemDatabaseChangeDashboardPage` to system admin | `SHARED_KEEP` |
| DDL artifact | `database/ddl/DDL-2026-001-create-system-database-change/` | Table schema and manual drop artifact | `LEGACY_KEEP` |
| Registry/menu DML | `database/dml/DML-2026-002-register-system-dashboard-change/` | Change registration | `LEGACY_KEEP` |
| Menu DML | `database/dml/DML-2026-003-add-system-database-change-menu/` | Menu, URL, page mapping | `LEGACY_KEEP` |
| Restriction DML | `database/dml/DML-2026-004-restrict-system-database-change-menu/` | System Admin restriction | `LEGACY_KEEP` |
| Change documentation | `docs/changes/DML-2026-002-*`, `DML-2026-003-*`, `DML-2026-004-*`, `DDL-2026-001-*` | Audit/change records | `LEGACY_KEEP` |

## Proposed normalized paths for a future move

These paths are proposals only. They must not be created or used until the submodule/feature ownership is approved and a separate move task is opened.

```text
app/Http/Controllers/SYSTEM/Governance/DatabaseChanges/DatabaseChangeDashboardController.php
app/Services/SYSTEM/Governance/DatabaseChanges/DatabaseChangeDashboardService.php
app/Models/SYSTEM/Governance/DatabaseChanges/SystemDatabaseChange.php
routes/SYSTEM/Governance/DatabaseChanges.php
resources/js/Pages/SYSTEM/Governance/DatabaseChanges/DatabaseChangeDashboardPage.vue
resources/js/Pages/SYSTEM/Governance/DatabaseChanges/api/databaseChangeApi.js
tests/<type>/SYSTEM/Governance/DatabaseChanges/
```

The model target is especially subject to review because the current project convention keeps many models flat under `app/Models`. No model move is approved by this document.

## Dependency and risk checklist

Before any move, verify at minimum:

- `routes/web.php` loader and route names;
- Inertia component resolution and Vite page discovery;
- every import/reference to controller, service, model, page, and API module;
- middleware and shell menu authorization;
- feature test discovery and Vitest path;
- `N_SYSTEM_Database_Change` table/model primary key and route model binding;
- DDL/DML documentation references, which are artifacts and not runtime imports;
- no secrets or environment files are included;
- focused frontend test and relevant backend/route checks after a move.

## Decision gate

Current decision: `already aligned`; tidak ada perubahan path yang diperlukan.

A move may be proposed only after maintainer confirms:

1. `Governance/DatabaseChanges` is the intended SYSTEM submodule/feature boundary;
2. model placement is included or explicitly excluded;
3. route file nesting and loader change are in scope;
4. test location is selected;
5. exact old-path → new-path mapping and rollback are approved.

Controller, service, route, page, API module, dan test sudah berada pada boundary SYSTEM/DatabaseChanges. DDL/DML artifacts tetap pada lokasi masing-masing dan tidak disentuh.
