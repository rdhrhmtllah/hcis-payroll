# N_PA_Rater_Policy_Detail — target (`N_PA_Rater_Policy_Detail` as-built)

> **Compatibility status:** target naming uses `N_PA_Rater_Policy_Detail`.
> Development still contains physical table `N_PA_Rater_Policy_Detail` from DDL
> 064. The physical rename and dependent FK/index handling require a separate
> reviewed database change.

## Purpose

One active row per rater category within a Policy version. It stores category weight, candidate relationship type, rater-count gates, manual-selection permission, and display order. It is a blueprint, not an actual participant/rater assignment.

## Ownership and change

- Module/submodule/feature: KPI / Performance Appraisal / Policy
- Change: DDL-2026-064-performance-appraisal-policy-foundation
- DDL: database/ddl/DDL-2026-064-performance-appraisal-policy-foundation/forward.sql
- DML/registry: database/dml/DDL-2026-064-performance-appraisal-policy-foundation/data-change.sql

## Columns

| Column | Type | Null | Meaning |
|---|---|:---:|---|
| Id_PA_Rater_Policy_Detail | int identity | No | Primary key. |
| PA_Rater_Policy_Id | int | No | Parent policy FK. |
| PA_Rater_Category_Id | int | No | Appraisal category FK. |
| Organization_Relationship_Type_Id | int | Yes | Candidate relationship type FK; NULL for SELF. |
| Bobot_Persen | decimal(5,2) | No | Category contribution, 0 through 100. |
| Min_Rater | smallint | No | Minimum assigned rater count; at least 1. |
| Maks_Rater | smallint | Yes | Maximum assigned rater count; NULL is unbounded. |
| Min_Rater_Selesai | smallint | Yes | Explicit completion threshold; NULL means all assigned valid raters must finish. |
| Flag_Izinkan_Manual | char(1) | No | Y/T manual candidate override; default T. |
| Urutan_Tampil | smallint | No | Positive display order. |
| Catatan_Aturan | nvarchar(500) | Yes | Reviewable business note, not executable logic. |
| Flag_Aktif | char(1) | No | Active detail Y/T; default Y. |
| Created_At | datetime2(0) | No | Creation time; database default. |
| Created_By | int | Yes | Human actor KPI_Users.Id_Users; NULL for system/job. |
| Updated_At | datetime2(0) | Yes | Last update time. |
| Updated_By | int | Yes | Human actor KPI_Users.Id_Users; no physical actor FK. |

## Relationships, checks, and indexes (as-built 064)

- Trusted FKs to PA Policy, PA Rater Category, and Organization Relationship Type; no cascade.
- CHECK constraints validate weight range, minimum count, nullable maximum semantics, nullable completion semantics, and positive display order.
- UX_PA_Rater_Policy_Detail_Active_Category permits only one active row per policy/category.
- Category and organization-type indexes support policy assembly and impact lookup.

The database cannot safely enforce total active weight 100% with a row CHECK. Activation must lock the header/details, validate total 100.00, validate SELF has NULL organization type and other categories map to the matching explicit type, then update status atomically.

## Fixed scoring rule

There is no Metode_Agregasi and no Metode_Resolusi column. Every valid rater in one category has equal internal weight. The category score is the normalized average of valid completed ratings, then multiplied by Bobot_Persen. Candidate direction comes directly from ATASAN, BAWAHAN, or PEER; SELF resolves to the participant.

## Application usage and snapshot boundary

No application writer exists yet. Final participant/rater assignments and Policy
values must be copied to Cycle-owned snapshot tables before lock. Later blueprint
or organization changes must not alter a locked Cycle. New code must not assume
the target name exists physically before the compatibility change is verified.
