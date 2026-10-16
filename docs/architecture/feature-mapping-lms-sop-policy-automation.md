# Feature Mapping: LMS-Registered SOP Policy & Automation

Status: `MIGRATED_AND_VERIFIED_WITH_REGISTRY_MISMATCH`
Tanggal verifikasi registry: 2026-08-18
Environment registry: `Web_HRIS` pada `team311.dyndns.info:1433`
Tanggal verifikasi path source: 2026-08-22 (filesystem, tabel `Current implementation map`)

## Scope

Dokumen ini memetakan page registry `menuSopKebijakanPage` ke implementation existing SOP Policy & Automation. Registry menempatkan page ini di module `LMS`, sedangkan source dan business boundary menggunakan domain `SOP`. Source controller/page/route telah dikelompokkan di bawah `LMS/SOP`; registry mismatch tetap dipertahankan dan tidak diubah.

## Registry basis

```text
Master module : N_HRIS_Master_Grup_Fitur
Module       : LMS
Id            : 3
Nama         : Learning Management System
Flag_Aktif   : Y

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopKebijakanPage
Id_Grup_Fitur : 3
Default       : T
Sidebar       : Y
Flag_Aktif    : Y

Source URL    : /sop/admin/kebijakan
Middleware    : auth, check.jabatan:menuSopKebijakanPage
```

Registry memverifikasi mapping ke `LMS`, tetapi source identifier, controller, frontend, route, service, job, database, dan business language menunjukkan domain SOP. Belum ada module SOP aktif pada registry.

## Ownership decision

```text
Registry module : LMS                         # VERIFIED_MODULE
Source domain   : SOP                         # SOURCE_BUSINESS_DOMAIN
Submodule       : Governance / Automation     # PROPOSAL_REVIEW_REQUIRED
Feature         : SOP Policy & Assignment Automation # PROPOSAL_REVIEW_REQUIRED
Registry status : REGISTRY_SOURCE_MISMATCH    # REVIEW_REQUIRED
File movement   : EXECUTED_WITH_REGISTRY_MISMATCH
```

Feature ini mengelola kebijakan global, assignment rules, scheduler health, run history, dan manual automation trigger. Ia berbeda dari manual SOP Penjadwalan dan Monitoring.

## Current implementation map

| Layer | Current path | Evidence | Status |
|---|---|---|---|
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopAdminKebijakanController.php` | summary, settings, rule CRUD, preview, activation, run-now, history | `LEGACY_KEEP` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` | `/sop/admin/kebijakan*` | `LEGACY_KEEP` |
| Main page | `resources/js/Pages/LMS/SOP/Governance/Admin/SopKebijakan.vue` | rules, settings, automation, history tabs | `SOURCE_DOMAIN_KEEP` |
| Rule service | `app/Services/LMS/SOP/SopAssignmentRuleService.php` | rule list/draft/preview/activate and assignment logic | `SHARED_SOP_KEEP` |
| Run service | `app/Services/LMS/SOP/SopAutomationRunService.php` | run lifecycle/history/status | `SHARED_SOP_KEEP` |
| Scheduler job | `app/Jobs/LMS/SOP/Automation/RunSopAutomation.php` | queued automation execution | `SHARED_SOP_KEEP` |
| Schedule service | `app/Services/LMS/SOP/SopPenjadwalanService.php` | executes assignment side effects | `CROSS_FEATURE_DEPENDENCY` |
| Target service | `app/Services/LMS/SOP/SopPenjadwalanTargetService.php` | rule target filters/options | `CROSS_FEATURE_DEPENDENCY` |
| Settings support | `app/Services/LMS/SOP/Support/SopSetting.php` | typed global policy settings | `SHARED_SOP_KEEP` |
| Rule config support | `app/Services/LMS/SOP/Support/SopAssignmentRuleConfig.php` | config normalization/contract | `SHARED_SOP_KEEP` |
| Tests | `tests/Feature/LMS/SOP/Uat/Alur024KebijakanOtomasiTest.php` | policy/automation UAT | `RELATED_TEST` |
| Tests | `tests/Unit/LMS/SOP/SopAssignmentRuleConfigTest.php` | config contract | `RELATED_TEST` |
| Dedicated page suite | no complete page/authorization/async suite found | repository search | `TEST_GAP_REVIEW_REQUIRED` |

## Route surface

```text
GET  /sop/admin/kebijakan
GET  /api/v1/sop/admin/kebijakan/summary
GET  /api/v1/sop/admin/kebijakan/options
GET  /api/v1/sop/admin/kebijakan/settings
PUT  /api/v1/sop/admin/kebijakan/settings
GET  /api/v1/sop/admin/kebijakan/runs
POST /api/v1/sop/admin/kebijakan/runs/{type}
GET  /api/v1/sop/admin/kebijakan/rules
POST /api/v1/sop/admin/kebijakan/rules
GET  /api/v1/sop/admin/kebijakan/rules/{id}
PUT  /api/v1/sop/admin/kebijakan/rules/{id}
POST /api/v1/sop/admin/kebijakan/rules/{id}/preview
POST /api/v1/sop/admin/kebijakan/rules/{id}/activate
POST /api/v1/sop/admin/kebijakan/rules/{id}/deactivate
```

## Important findings

1. `SopAdminKebijakanController` menggabungkan summary, policy settings, rule CRUD, target options, preview, activate/deactivate, run-now, dan history.
2. Settings memiliki typed bounds dan memengaruhi behavior global, termasuk dwell threshold, video threshold, reminder, auto assign/release, async scheduling threshold, dan reconciliation chunk size. Perubahan setting adalah global behavior boundary.
3. Rule activation dan run-now dapat memicu assignment melalui `SopPenjadwalanService`; ini bukan operasi read-only dan perlu authorization/audit serta idempotency review.
4. Automation execution memakai `RunSopAutomation` dan run state `QUEUED/RUNNING/FAILED/NEEDS_ATTENTION`; worker availability dan recovery adalah bagian dari feature contract.
5. `SopAssignmentRuleService` dan `SopPenjadwalanTargetService` juga digunakan oleh workflow lain; jangan dijadikan feature-local.
6. UI memiliki empat tab dengan state dan endpoint berbeda. Belum ada suite tunggal yang memverifikasi seluruh tab, global settings mutation, rule lifecycle, async run, dan authorization.

## Proposed normalized paths for a future move

Proposal only; do not create or use these paths yet:

```text
app/Http/Controllers/LMS/SOP/Governance/Automation/SopPolicyAutomationController.php
app/Services/LMS/SOP/Governance/Automation/
app/Jobs/LMS/SOP/Governance/Automation/RunSopAutomation.php
routes/LMS/SOP/Governance/Automation/SopPolicyAutomation.php
resources/js/Pages/LMS/SOP/Governance/Automation/SopPolicyAutomation/SopPolicyAutomationPage.vue
tests/<type>/LMS/SOP/Governance/Automation/SopPolicyAutomation/
```

This proposal is blocked until registry/business ownership decides whether SOP becomes a formal module or remains registered under LMS. Current SOP paths are the safe state.

## Dependency and risk checklist

Before any move, verify:

- registry decision for `menuSopKebijakanPage` and absent formal SOP module;
- global settings consumers and backward-compatible defaults/bounds;
- rule config normalization, preview, activation, deactivation, and duplicate/idempotency behavior;
- assignment side effects through `SopPenjadwalanService`;
- async job/run state, retry, failure, and scheduler health;
- authorization/audit for settings and manual run triggers;
- frontend tabs, API URLs, optimistic state, and error handling;
- focused policy, automation, assignment, authorization, async, frontend, browser, and UAT tests;
- no database mutation, migration, DDL, or DML is part of a file move.

## Decision gate

Current decision: `migrated and verified`; registry/source mismatch remains explicitly documented.

Registry/business ownership must be resolved before any target path is approved. Source files are now grouped under `LMS/SOP`; live LMS mapping and URL contracts remain unchanged.
