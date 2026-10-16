# DDL-2026-065 — Performance Appraisal Pillar Weight

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-065-performance-appraisal-pillar-weight |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Pillar Weight |
| Status | TRACKER_PENDING |
| Artifact revision | r1 |
| Dependencies | HRIS_Level only. Independent of DDL-2026-064. |
| Forward DDL | database/ddl/DDL-2026-065-performance-appraisal-pillar-weight/forward.sql |
| Manual reset | database/ddl/DDL-2026-065-performance-appraisal-pillar-weight/drop.sql |
| DML | database/dml/DDL-2026-065-performance-appraisal-pillar-weight/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md |
| Forward checksum SHA-256 | 68533f89f422c0a88b7d7469ee873471179a9fe3752fe64526f739b8b6d76796 |
| DML checksum SHA-256 | e49d124c99695b8ad6d5d4de062324d62d4e7b37dc1c56a052d4a1f55af15acd |

## Scope

- Create `N_PA_Pillar_Weight`.
- Register the change in `N_SYSTEM_Database_Change`.

No seed rows. No cycle, snapshot, participant, assignment, result, application code,
production execution, or SQL Tracker execution is included.

## Why this is not a column on N_PA_Rater_Policy

The pillar split (KPI versus competency) was deliberately kept out of
`N_PA_Rater_Policy`, recorded as K23 in the design document. The two freeze at
different times.

Rater weights must be settled *before* filling starts: they are not merely
multipliers, they also decide who is assigned, through `Min_Rater`, `Maks_Rater`,
and the source relationship type. Pillar weights decide nothing about who fills
what; they apply only in the final arithmetic, once every raw score exists.

Had the pillar split lived on the policy, an admin wanting to change 60/40 to 70/30
after everyone finished rating would face a policy version bump — and the cycle's
frozen snapshot deliberately ignores new versions. The only way through would be to
break the guarantee that rule changes cannot cross the lock line. Keeping it
separate lets the weights change and the result be recomputed from the raw scores,
which are stored permanently.

## Physical decisions

- `ID_Level` is `bigint` with a trusted FK to `HRIS_Level.ID_Level`, matching
  `N_PA_Rater_Policy`.
- **Two columns, not two rows.** The sibling table `N_PA_Rater_Policy_Detail` models
  its weights as rows, so consistency argued for rows here too. The column form was
  chosen because it lets the database enforce the "must total 100" rule directly
  through `CK_PA_Pillar_Weight_Total`. A row form makes that a multi-row rule, which
  a row CHECK cannot validate — exactly why the 100% gate on
  `N_PA_Rater_Policy_Detail` had to be pushed into the application. There are two
  pillars and a third would be a redesign, not an addition.
- **No `Flag_Aktif`.** A level either has a pillar weight or it does not; an
  "inactive weight" carries no business meaning. Adding the flag would force every
  weight lookup to filter, and a single missed filter would not raise an error — it
  would silently produce a wrong final score.
- **No `Versi`, `Berlaku_Dari`, or `Status`.** `N_PA_Rater_Policy` versions its
  rules because changes to rater policy must be traceable per version. Pillar
  history is already recorded elsewhere: each cycle stores the copy it used, and
  each result stores the weights it was actually computed with. One row per level
  stating the current standing rule is enough.
- `Bobot_KPI = 0` is allowed. A level assessed purely on competency — a freelance
  population with no KPI, for instance — is a business decision, not bad data.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- `N_PA_Pillar_Weight` did not exist; no object matching `%PA_Pillar%` existed.
- `HRIS_Level.ID_Level` is `bigint` with primary key `PK_Level`; 16 rows.
- `KPI_Users.Id_Users` is `int`, matching the audit-actor convention.
- Change ID 065 was unused in both the repository and `N_SYSTEM_Database_Change`.
- Pending ledger reported before starting: DDL-2026-061 through 064 all
  `TRACKER_PENDING`.

## Agent completed

- Created and executed the exact forward.sql in five batches and the exact
  data-change.sql in one batch.
- Verified 9 columns with the intended types, `Bobot_KPI` and `Bobot_Kompetensi` as
  `decimal(5,2)`, one enabled and trusted FK, two enabled and trusted CHECK
  constraints, and two indexes (`PK_PA_Pillar_Weight`, `UX_PA_Pillar_Weight_Level`).
- Rollback behaviour tests, all rolled back and leaving zero rows:
  - rejected a total other than 100;
  - rejected a negative weight;
  - rejected an `ID_Level` absent from `HRIS_Level`;
  - rejected a duplicate level;
  - accepted 60/40, 0/100, and 100/0.
- Re-ran the exact artifacts and confirmed a no-op: still zero weight rows and one
  registry row.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually, after 061 through 064.
- Confirm production execution before production verification.

## Open dependency

The weight numbers themselves do not exist yet. The PIC deck's level matrix covers
only rater weights; the KPI-versus-competency split has never been stated. Tracked
as T11 in the design document, and the reason this change ships with no seed.
