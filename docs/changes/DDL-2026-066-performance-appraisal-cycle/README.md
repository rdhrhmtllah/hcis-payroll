# DDL-2026-066 — Performance Appraisal Cycle

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-066-performance-appraisal-cycle |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Cycle |
| Status | TRACKER_PENDING |
| Artifact revision | r2 |
| Dependencies | None. The table stands alone. |
| Forward DDL | database/ddl/DDL-2026-066-performance-appraisal-cycle/forward.sql |
| Manual reset | database/ddl/DDL-2026-066-performance-appraisal-cycle/drop.sql |
| DML | database/dml/DDL-2026-066-performance-appraisal-cycle/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md |
| Forward checksum SHA-256 | 064db0b358476566092456f381bcfb59a4c1fb150b501c0fa8be97d04ef5a933 |
| DML checksum SHA-256 | ce3e31083af0e573586783fea8383c5e5c60485fbe8cc37e25f222b218c3cf35 |

## Amendment history

| Rev | Date | Trigger | Change |
|---|---|---|---|
| r1 | 2026-08-30 | first authoring | 20 columns; all five date columns NOT NULL. |
| r2 | 2026-08-30 | mockup review (user decision) | Added `Tahun`; relaxed all five date columns to NULL so a DRAFT cycle can exist before its schedule is set. Range CHECKs made NULL-tolerant; added `CK_PA_Cycle_Tahun`. |

The mockup lists a row "Performance Appraisal 2027 (Draft)" whose start and end
dates are empty. Under r1 that row was impossible to create: an admin had to invent
dates just to open a draft, and invented dates are indistinguishable from real ones.
Requiring the dates before a cycle may leave DRAFT is an application gate, not a
column constraint.

Development was rebuilt from the r2 artifacts under the development rebuild
exception in `AGENTS.md`; the change is still `TRACKER_PENDING` and the table held
zero rows, asserted before anything was dropped.

## Scope

- Create `N_PA_Cycle`.
- Register the change in `N_SYSTEM_Database_Change`.

No sample cycle, no snapshot tables, no participant, assignment, or result. The
three second-layer snapshot tables follow in a later change and will carry foreign
keys to this one.

## Physical decisions

- **Two date ranges, not a cycle type.** `Periode_Dinilai_*` is the work being
  assessed; `Pengisian_*` is when raters may fill. Quarter, semester, and yearly
  are labels derived from the first range, not stored kinds. This is K03, and it
  removed the YTD-versus-full-year branching entirely.
- `Tanggal_Acuan_Organisasi` sits with the business columns, not the audit ones.
  It decides which organisational state the cycle reads when resolving participants
  and rater candidates, so it is an input, not a timestamp of something that
  happened.
- **`Status_Cycle` has no CHECK.** Project rules forbid database enums for evolving
  business value lists; the application validates it. The intended progression is
  DRAFT, PREPARED, LOCKED, OPEN, CLOSED, PUBLISHED, where LOCKED freezes the copies
  and OPEN opens filling — two different events that may happen close together.
- **`Flag_Aktif` is not a duplicate of `Status_Cycle`.** The status says where the
  cycle got to in its lifecycle; the flag retires a row created by mistake or
  abandoned, independent of that. `N_PA_Rater_Policy` carries the same pair for the
  same reason.
- **No index beyond the primary key and the unique code.** DDL-2026-064 indexed
  status and period on `N_PA_Rater_Policy`, but this table holds a handful of rows
  per year. Extra indexes would be write cost with no read benefit. Project rules
  require index choices to come from query context rather than habit.
- Audit actor columns are nullable `int` from `KPI_Users.Id_Users`; NULL is
  reserved for documented system actors. No actor FK, matching the current
  new-schema convention.

## What the database deliberately does not enforce

Overlapping assessment periods are forbidden per employee (K04, guard G1). That rule
spans many rows and depends on participant membership, so no row-level CHECK can
express it. It is an application gate, checked before a cycle may be locked.

`Tanggal_Acuan_Organisasi` should normally not fall after the lock time. That is
also an application rule, because the lock timestamp is written later than the row.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- `N_PA_Cycle` did not exist; no object matching `%PA_Cycle%` existed.
- Change ID 066 was unused in both the repository and `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in five batches and the exact
  data-change.sql in one batch.
- Verified 21 columns, one enabled and trusted FK, four enabled and trusted CHECK
  constraints, three default constraints, and two indexes
  (`PK_PA_Cycle`, `UX_PA_Cycle_Kode`).
- Rollback behaviour tests, all rolled back and leaving zero rows:
  - rejected an assessment range ending before it starts;
  - rejected a filling window ending before it starts;
  - rejected a duplicate `Kode_Cycle`;
  - rejected a cycle whose source points at itself;
  - accepted a normal range and a single-day assessment period;
  - confirmed defaults `Status_Cycle = 'DRAFT'` and `Flag_Aktif = 'Y'`;
  - accepted a DRAFT cycle carrying no dates at all;
  - accepted a cycle with only the start date filled;
  - rejected `Tahun = 199`;
  - still rejected a reversed period even though the columns are now nullable.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually, after 061 through 065.
- Confirm production execution before production verification.
