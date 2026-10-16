# N_PA_Rater_Policy — target (`N_PA_Rater_Policy` as-built)

> **Compatibility status:** target naming uses `N_PA_Rater_Policy`. Development still
> contains physical table `N_PA_Rater_Policy` from DDL 064. The columns,
> constraints, and indexes below describe that as-built table until a separate
> reviewed database change performs the rename.

## Purpose

Versioned Performance Appraisal policy header for one HRIS level. It stores blueprint identity, lifecycle, effective period, source version, and activation audit. It does not store rater categories, assignments, cycle snapshots, or scores.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Policy
- Database basis: active KPI Id_Grup_Fitur=2, verified 2026-08-30. No PA-specific
  page mapping exists yet; active `MasterPerformance` is the legacy KPI
  Performance Range page and must not be reused as PA ownership.
- Change: DDL-2026-064-performance-appraisal-policy-foundation
- DDL: database/ddl/DDL-2026-064-performance-appraisal-policy-foundation/forward.sql
- DML/registry: database/dml/DDL-2026-064-performance-appraisal-policy-foundation/data-change.sql

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Rater_Policy | int identity | No | Primary key. |
| Kode_Policy | varchar(50) | No | Stable business code, reusable for a new version. |
| Nama_Policy | nvarchar(150) | No | Display name. |
| Deskripsi | nvarchar(500) | Yes | Policy purpose/scope. |
| ID_Level | bigint | No | Target HRIS level. |
| Versi | int | No | Positive version number; default 1. |
| PA_Rater_Policy_Sumber_Id | int | Yes | Previous/source policy version. |
| Status_Policy | varchar(20) | No | FK ke `N_PA_Rater_Policy_Status.Kode_Status` (dipasang DDL-2026-070): DRAFT, ACTIVE, INACTIVE. Default DRAFT. |
| Berlaku_Dari | date | No | Effective start. |
| Berlaku_Sampai | date | Yes | Effective end; NULL is open-ended. |
| Catatan_Versi | nvarchar(500) | Yes | Version rationale. |
| Flag_Aktif | char(1) | No | Administrative Y/T; default Y. |
| Created_At | datetime2(0) | No | Creation time; database default. |
| Created_By | int | Yes | Human actor KPI_Users.Id_Users; NULL for system/job. |
| Updated_At, Activated_At | datetime2(0) | Yes | Update/activation times. |
| Updated_By, Activated_By | int | Yes | Human actor KPI_Users.Id_Users; no physical actor FK. |

## Relationships and constraints (as-built 064)

- FK_PA_Rater_Policy_Level to HRIS_Level.ID_Level.
- FK_PA_Rater_Policy_Source self-reference, without cascade.
- PK_PA_Rater_Policy.
- UX_PA_Rater_Policy_Kode_Versi prevents duplicate business version.
- Date range, positive version, and different-source CHECK constraints.
- IX_PA_Rater_Policy_Level_Period supports effective policy lookup.
- IX_PA_Rater_Policy_Source supports version lineage.

Status values, active-period overlap per level, immutability after activation, exact 100% detail weight, and activation audit consistency are application transaction rules.

## Application usage

No model, repository, service, controller, route, or page writes this table yet.
New application code should target the approved final `N_PA_Rater_Policy` name only
after the compatibility change is executed. A Cycle must copy selected Policy and
detail values rather than depend only on mutable blueprint rows.
