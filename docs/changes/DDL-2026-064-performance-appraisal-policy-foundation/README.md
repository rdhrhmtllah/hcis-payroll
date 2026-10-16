# DDL-2026-064 — Performance Appraisal Policy Foundation

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-064-performance-appraisal-policy-foundation |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Policy |
| Database basis | Id_Grup_Fitur=2 (KPI), verified 2026-08-30; PA-specific page mapping pending |
| Status | TRACKER_PENDING |
| Dependencies | DDL-2026-061, 062, 063 |
| Forward DDL | database/ddl/DDL-2026-064-performance-appraisal-policy-foundation/forward.sql |
| Manual reset | database/ddl/DDL-2026-064-performance-appraisal-policy-foundation/drop.sql |
| DML | database/dml/DDL-2026-064-performance-appraisal-policy-foundation/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-30-performance-appraisal-pa-policy-concept.md |
| Forward checksum SHA-256 | 45531782eb18e96e89d45ae120766b26da077cbc6ce3a3db4199255d6a452238 |
| DML checksum SHA-256 | a037766b287b5b8096383214d95b66789843fe1b938826076b5769b6db8887c3 |

## Amendment history

| Rev | Date | Trigger | Change |
|---|---|---|---|
| r1 | 2026-08-30 | first authoring | Objects created as `N_HRIS_PA_Policy`, `N_HRIS_PA_Rater_Category`, `N_HRIS_PA_Policy_Detail` with matching `*_HRIS_PA_*` constraint and index names. |
| r2 | 2026-08-30 | pre-tracker amendment (user decision) | Prefix fixed to `N_PA_*` and `*_PA_*` across forward.sql, drop.sql, and data-change.sql. |
| r3 | 2026-08-30 | pre-tracker amendment (user decision) | `N_PA_Policy` → `N_PA_Rater_Policy` and `N_PA_Policy_Detail` → `N_PA_Rater_Policy_Detail`, with their constraints, indexes, and identity columns. Pillar weights (KPI vs competency) were decided to live in a cycle-scoped table instead, so this table is now purely a rater policy. |

This change is `TRACKER_PENDING`: nothing had been recorded in SQL Tracker and
nothing had run against production. Per the amend-before-tracker rule in
`AGENTS.md`, the r1 artifacts were edited in place instead of adding a corrective
change ID. There is no drop-and-recreate change, and no `DDL-2026-065` rename
change exists or is needed. Production will execute only the r2 artifacts and
will therefore create `N_PA_*` directly, never the `N_HRIS_PA_*` intermediate.

### Development rebuild (not part of the artifact)

Development had already run r1 and r2. Rather than patching names, it was rebuilt
from the r3 artifacts: the change's own `drop.sql`, then `forward.sql`, then
`data-change.sql`. Nothing outside this change was touched.

The r2 realignment had used `sp_rename` for 36 objects. That path did not finish
for r3: SQL Server refuses `sp_rename` on `Id_PA_Policy` and
`PA_Policy_Sumber_Id` because both take part in enforced dependencies — the check
constraint `CK_PA_Rater_Policy_Source_Different` names them in its predicate, and
the filtered index `IX_PA_Rater_Policy_Source` filters on the second. 32 of 34
renames succeeded and 2 could not, which would have left development permanently
out of step with the artifact.

The rebuild was authorized as the development rebuild exception in `AGENTS.md`:
the change is still `TRACKER_PENDING`, production has never seen it, and the
tables held nothing the artifact does not rebuild. Before dropping, the script
asserted 0 policy rows, 0 detail rows, and exactly the four seed category codes,
and would have aborted otherwise.

Post-rebuild verification: zero objects, indexes, and columns matching the r1/r2
shapes; identity columns are `Id_PA_Rater_Policy`, `PA_Rater_Policy_Sumber_Id`,
`Id_PA_Rater_Policy_Detail`, and `PA_Rater_Policy_Id`; 5 foreign keys and 8 check
constraints enabled and trusted; 11 PK/index objects; column counts 18/9/16; four
seed categories with `Updated_At` still `NULL`; one registry row. Re-running the
exact r3 artifacts (7 DDL batches + 1 DML batch) completed as a no-op — the
evidence that development now matches what r3 builds from empty.

## Scope

- Create N_PA_Rater_Policy.
- Create N_PA_Rater_Category.
- Create N_PA_Rater_Policy_Detail.
- Seed ATASAN, BAWAHAN, PEER, and SELF idempotently.
- Register the change in N_SYSTEM_Database_Change.

No sample policy/detail, appraisal cycle, assignment, question, result, context feature, application code, production execution, or SQL Tracker execution is included.

## Physical decisions

- ID_Level is bigint and has a trusted FK to HRIS_Level.ID_Level.
- Kode_Policy is reusable across versions; (Kode_Policy, Versi) is unique.
- Status_Policy is application-validated and has no database enum CHECK.
- Audit actor columns are nullable int values sourced from KPI_Users.Id_Users for human actors; system/job actors may use NULL. No audit-actor FK is added.
- Maks_Rater NULL means unbounded.
- Min_Rater_Selesai NULL means every assigned valid rater must finish.
- Organization_Relationship_Type_Id is nullable for SELF.
- No Metode_Agregasi or Metode_Resolusi column exists. Category scoring is fixed normalized average.
- Total active detail weight of 100.00 is an application activation gate because a row CHECK cannot safely validate a set of child rows. No trigger is introduced.

## Pre-execution audit

- Web_HRIS target and KPI module Id 2 verified.
- `MasterPerformance` is an active KPI page at `/performance-kpi`, but source
  inspection confirms that it manages legacy `KPI_Master_Performance`
  Performance Range data. It is not the PA policy page.
- The three PA tables do not exist (audit taken before the r1 run).
- HRIS_Level.ID_Level is bigint PK.
- Organization Relationship Type int PK and active ATASAN/BAWAHAN/PEER are available.
- KPI_Users.Id_Users is int PK; current new-schema audit actor convention uses nullable int without actor FK.
- Change ID 064 is unused.
- Dependencies 061–063 are development DONE and SQL Tracker/production PENDING.

## Expected first-run DML

- N_PA_Rater_Category: insert up to four rows; update up to four only if drift exists.
- N_SYSTEM_Database_Change: insert one row.
- N_PA_Rater_Policy and N_PA_Rater_Policy_Detail: zero rows.

## Agent completed

- Verified target team311.dyndns.info:1433 / Web_HRIS development.
- Verified KPI Id_Grup_Fitur=2 on 2026-08-30. A new PA-specific page mapping is
  required before application implementation; the legacy `MasterPerformance`
  route must not be reused.
- Verified HRIS_Level.ID_Level bigint PK, Organization Relationship Type int PK, KPI_Users.Id_Users int PK, absent PA tables, and unused change ID 064.
- Created and executed exact forward.sql in seven batches and exact data-change.sql in one batch (r1, then re-verified as a no-op after the r2 amendment).
- Verified table column counts 18/9/16, no Metode_Agregasi or Metode_Resolusi columns, five enabled/trusted FKs, eight enabled/trusted CHECK constraints, and eleven PK/index objects.
- Verified four active seed categories, zero policy rows, zero detail rows, and one registry row DONE/PENDING/PENDING/DEV_DONE.
- Exact rerun preserved all category timestamps/counts and registry cardinality.
- Transaction validation of exact artifacts rolled back all three tables and registry before permanent execution.
- Rollback behavior tests rejected invalid dates, invalid level/type FKs, weight above 100, maximum below minimum, completed threshold above maximum, and duplicate active category. NULL maximum/completion semantics were accepted. All test policy/detail rows rolled back.
- Confirmed an ACTIVE header without 100% details is database-allowed by design; the 100.00 gate must be enforced atomically by the application activation service.
- Executable destructive SQL, scope, checksum, content, ledger, and git diff checks passed.

## User tasks

- Review the exact r2 SQL and the r2 checksums. The r1 checksums are superseded and must not be recorded.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually in order 061, 062, 063, 064.
- Confirm production execution before production verification.
