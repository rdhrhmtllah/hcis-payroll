# Feature Mapping: LMS-Registered SOP Builder

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopBuilderPage` ke implementation existing SOP Builder. Registry menempatkan page ini di module `LMS`, sedangkan source dan business boundary menggunakan domain `SOP`. Source controller/page/route telah dikelompokkan di bawah `LMS/SOP`; registry mismatch tetap dipertahankan dan tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopBuilderPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/builder
Middleware    : auth, check.jabatan:menuSopBuilderPage
```

Registry memverifikasi mapping ke `LMS`, tetapi source identifier, controller, frontend, route, service, database, dan business language menunjukkan domain SOP. Belum ada module SOP aktif pada registry.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Governance / Builder        # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Package Builder         # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

Builder berbeda dari Content Management, Master Dokumen, Penjadwalan, dan Monitoring, walaupun semuanya memakai data SOP yang saling berhubungan.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopAdminBuilderController.php` | package, section, item, clone/copy, reorder, publish impact, reconcile | `LEGACY_KEEP` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | `/sop/admin/builder*` and version endpoints | `LEGACY_KEEP` |
| Main page | `resources/js/Pages/LMS/SOP/Governance/Admin/SopBuilder.vue` | three-step package builder | `SOURCE_DOMAIN_KEEP` |
| Shared UI | `resources/js/Pages/LMS/SOP/Shared/components/ui/ItemFields.vue` | item form/types/pickers | `SHARED_SOP_KEEP` |
| Shared UI | `SopDokumenPickerModal.vue`, `SopPengesahPicker.vue`, `SopVersionPanel.vue`, `SopPublishPaketPratinjau.vue` | document/approver/version/publish flows | `SHARED_SOP_KEEP` |
| Package services | `SopPaketService.php`, `SopPaketDraftService.php`, `SopPaketVersionService.php` | package lifecycle/draft/version | `SHARED_SOP_KEEP` |
| Structure services | `SopSectionService.php`, `SopItemService.php` | section/item lifecycle | `SHARED_SOP_KEEP` |
| Version/reconcile | `SopItemVersionService.php`, `SopReconciliationService.php` | version and fan-out/reconcile | `SHARED_SOP_KEEP` |
| Scope guard | `app/Services/LMS/SOP/Support/SopBuilderScope.php` | package/section/item identity and draft tokens | `SHARED_SOP_KEEP` |
| Related controller | `SopBerkasController.php` | item version endpoints used by builder | `RELATED_SHARED_KEEP` |
| Related controller | `SopAdminDokumenController.php` | document/approver/pemakai endpoints reused by builder | `RELATED_SHARED_KEEP` |
| Tests | multiple builder/document/version/copy/fanout tests | substantial related coverage | `RELATED_TEST` |
| Dedicated full page suite | no single complete browser/authorization suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET    /sop/admin/builder
GET    /api/v1/sop/admin/builder/options/*
GET    /api/v1/sop/admin/builder/paket/current
GET    /api/v1/sop/admin/builder/paket/counts
GET    /api/v1/sop/admin/builder/paket/{id}
GET    /api/v1/sop/admin/builder/paket/{id}/riwayat
GET    /api/v1/sop/admin/builder/paket/{id}/dampak-publish
POST   /api/v1/sop/admin/builder/paket/store
PUT    /api/v1/sop/admin/builder/paket/update/{id}
PATCH  /api/v1/sop/admin/builder/paket/status/{id}
DELETE /api/v1/sop/admin/builder/paket/{id}/draft
DELETE /api/v1/sop/admin/builder/paket/{id}/isi
DELETE /api/v1/sop/admin/builder/delete/{id}
POST   /api/v1/sop/admin/builder/paket/{id}/reconcile
POST   /api/v1/sop/admin/builder/paket|section|item/* clone/copy
GET/POST/PUT/DELETE /api/v1/sop/admin/builder/section/*
GET/POST/PUT/DELETE /api/v1/sop/admin/builder/item/*
GET    /api/v1/sop/admin/builder/versi/{id}/dampak
GET    /api/v1/sop/admin/builder/dokumen/options

Related version routes:
GET/POST /api/v1/sop/admin/berkas/item/{idItem}/versi*
PATCH    /api/v1/sop/admin/berkas/versi/{id}/*
```

## Important findings

1. `SopAdminBuilderController` sekitar 2.554 baris dan mengelola paket, draft, section, item, clone/copy, reorder, publish status, dampak, reconcile, document picker, serta version integration. Pemindahan controller secara utuh bukan perubahan struktur sederhana.
2. Builder memakai tujuh service utama dan helper `SopBuilderScope`; draft token (`dp_`, `ds_`, `di_`, `dv_`) adalah bagian penting dari identity contract.
3. Publish/rollback berdampak pada shared dokumen dan paket lain melalui fan-out/reconcile. Ini adalah data/transaction boundary sensitif, bukan sekadar CRUD page.
4. Builder memakai endpoint dari Master Dokumen dan Berkas/Versi, termasuk approver picker dan secure PDF admin gate.
5. UI builder berukuran besar dan menggunakan shared components untuk item, dokumen, pengesah, versi, publish preview, dan copy.
6. Coverage test existing cukup luas: item/document/version/copy/fanout/impact/authorization/UAT/e2e. Namun belum ada satu suite yang membuktikan seluruh wizard dari page render hingga publish pada browser.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/SOP/Governance/SopBuilderController.php
app/Services/LMS/SOP/Governance/Builder/
routes/LMS/SOP/Governance/SopBuilder.php
resources/js/Pages/LMS/SOP/Governance/SopBuilder/SopBuilderPage.vue
tests/<type>/LMS/SOP/Governance/SopBuilder/
```

This proposal is blocked until registry/business ownership decides whether SOP becomes a formal module or remains registered under LMS. Current SOP paths are the safe state.

## Dependency and risk checklist

Before any move, verify:

- registry decision for `menuSopBuilderPage` and absent formal SOP module;
- package/draft/section/item identity and `SopBuilderScope` token contracts;
- publish, rollback, version, impact preview, reconcile, and fan-out semantics;
- shared Master Dokumen, Berkas/Versi, approver, secure PDF, and content-picker callers;
- audit logging, stale-row 409 behavior, transaction boundaries, and cleanup;
- frontend wizard step state, deep links, shared UI imports, and direct API URLs;
- focused builder/document/version/copy/fanout, authorization, browser, and UAT tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Registry/business ownership must be resolved before any target path is approved. Source files are now grouped under `LMS/SOP`; live LMS mapping and URL contracts remain unchanged.
