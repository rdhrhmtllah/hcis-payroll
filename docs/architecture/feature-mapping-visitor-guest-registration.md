# Feature Mapping: VISITOR Guest Registration

Status: `MIGRATED_AND_VERIFIED`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`

## Scope

Dokumen ini memetakan page registry `visitorTamuPage` dan alur registrasi/riwayat tamu existing. Controller, route, halaman, dan komponen lokal telah dipindahkan; approval/effective services tetap shared.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : VISITOR
Id            : 4
Nama         : Visitor Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : visitorTamuPage
Id_Grup_Fitur : 4
Flag_Aktif   : Y

URL           : /transaksitamu
Scope         : self-service melalui check.jabatan:visitorTamuPage
``

Registry memverifikasi module dan page mapping. `Submodule` dan `Feature` belum tersedia sebagai kolom formal registry.

## Proposed ownership

```text
Module    : VISITOR                         # VERIFIED_MODULE
Submodule : Guest / Visit                   # PROPOSAL_REVIEW_REQUIRED
Feature   : Guest Registration Self-Service  # PROPOSAL_REVIEW_REQUIRED
Layer     : Controller, Service, Page, Components, Route, Tests, Storage/DB docs
``

Approval, check-in/check-out, reporting, dan verification adalah related workflows. Role/access middleware tidak menentukan module placement.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php` | index, store, list, detail, update, cancel, check-in/out, stats, master | `MIGRATED` |
| Service dependency | `app/Services/Visitor/VisitorApprovalPolicyService.php` | page config and approval flow | `SHARED_KEEP` |
| Service dependency | `app/Services/Visitor/VisitorEfektifService.php` | effective visitor projection/status | `RELATED_SHARED_KEEP` |
| Route | `routes/VISITOR/Guest/Visit/GuestRegistration.php` | resource plus cancel/check-in/check-out/list/stats/master | `MIGRATED` |
| Route loader | `routes/kpi/ridhoDevEvo.php` | loads guest web/API routes and visitor related routes | `LEGACY_KEEP` |
| API stub route | `routes/TransaksiTamu/TransaksiTamuApi.php` | API resource remains disabled as legacy stub | `LEGACY_KEEP` |
| Page | `resources/js/Pages/VISITOR/Guest/Visit/GuestRegistrationPage.vue` | Inertia page returned by controller | `LEGACY_KEEP` |
| Form component | `resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue` | registration and master data | `MIGRATED` |
| History component | `resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue` | list, detail, cancel, check-out | `MIGRATED` |
| Shared UI | `resources/js/Pages/VISITOR/components/` | visitor UI dependencies | `DEPENDENCY_REVIEW` |
| Tests | no dedicated visitorTamuPage test found | repository search | `TEST_GAP_REVIEW_REQUIRED` |
| Registry/menu artifact | `database/visitor/2026_07_29_fase3_rbac_visitor_menus.sql` | menu/page declaration | `LEGACY_KEEP` |

## Important findings

1. The controller owns both registration and visit lifecycle transitions; splitting these is behavior scope, not a simple move.
2. `TamuHistory.vue` performs cancel/check-out/detail requests directly, so endpoint paths and route names must remain stable during any future move.
3. Approval policy and effective projection are shared with Permit and other Visitor flows; they cannot be moved as feature-local files without caller analysis.
4. No dedicated feature/browser test was found for the complete guest registration flow. This is a separate test-planning gap.
5. The disabled API route/controller stub is retained and must not be deleted during structure cleanup without separate approval.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php
app/Services/VISITOR/Guest/Visit/
routes/VISITOR/Guest/Visit/GuestRegistration.php
resources/js/Pages/VISITOR/Guest/Visit/GuestRegistrationPage.vue
resources/js/Pages/VISITOR/Guest/Visit/components/TamuForm.vue
resources/js/Pages/VISITOR/Guest/Visit/components/TamuHistory.vue
tests/<type>/VISITOR/Guest/Visit/
``

These targets require an approved naming and decomposition decision; no move is approved.

## Dependency and risk checklist

Before any move, verify:

- all resource routes and route loader order;
- `check.jabatan:visitorTamuPage` behavior and global menu mapping;
- Inertia page resolution and component imports;
- direct frontend endpoint calls;
- approval policy and effective projection callers;
- transaction/status transitions for cancel, check-in, and check-out;
- test coverage for submit, duplicate request, cancellation, lifecycle transitions, and failure paths;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; registration and visit lifecycle remain one feature, while approval/effective services remain shared.

A future move requires explicit confirmation of:

1. `VISITOR/Guest/Visit` hierarchy;
2. whether registration and visit lifecycle remain one feature;
3. whether approval/effective services remain shared;
4. route and controller compatibility strategy;
5. test-gap remediation scope;
6. exact old-path → new-path map and rollback procedure.

Verification: PHP syntax, Composer class loading, frontend build (4,401 modules), old-reference scan, and diff checks passed. No dedicated visitor guest test was found; lifecycle coverage remains a documented test gap.
