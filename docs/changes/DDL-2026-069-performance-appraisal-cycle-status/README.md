# DDL-2026-069 — Cycle status master

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-069-performance-appraisal-cycle-status |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Cycle |
| Status | TRACKER_PENDING |
| Artifact revision | r1 |
| Dependencies | **DDL-2026-066.** Must run after it. |
| Forward DDL | database/ddl/DDL-2026-069-performance-appraisal-cycle-status/forward.sql |
| Manual reset | database/ddl/DDL-2026-069-performance-appraisal-cycle-status/drop.sql |
| DML | database/dml/DDL-2026-069-performance-appraisal-cycle-status/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-29-performance-appraisal-design.md |
| Forward checksum SHA-256 | 3bd65dc1b8633901ce44143b6f3543477ef0f7783b556d47a8c5745cd3bc5aa9 |
| DML checksum SHA-256 | bf4ad1df04bac0b3ea9a333956220849f8dc08162a7109a19675d4d66bc48ea8 |

## Scope

Create `N_PA_Cycle_Status`, seed the seven statuses, and add a foreign key from
`N_PA_Cycle.Status_Cycle` to it. Register the change.

## Why a master table

Three documents each carried a different status list: nine states in the design
document, six in the cycle plan, four in the PIC mockup. Nothing in the database
disagreed with any of them, because `Status_Cycle` was a free `varchar`. Whichever
document an agent or developer happened to read became the truth for whatever they
built next.

A lookup table settles it in the one place every session reads the same way. This
follows the module's existing pattern — `N_PA_Rater_Category` and
`N_PA_Competency_Type` are masters for the same reason.

It is a lookup table, not an enum CHECK. Project rules forbid rigid CHECK constraints
on evolving business lists; adding a status here is inserting a row, and the test
below confirms a new eighth status is accepted.

## The seven statuses

| # | Kode | Nama | Badge | Gates |
|---|---|---|---|---|
| 1 | `DRAFT` | Draft | DRAFT | Nothing runs yet; dates may still be empty |
| 2 | `PREPARED` | Disiapkan | ACTIVE | Participants and copies exist; rater assignment runs |
| 3 | `LOCKED` | Dikunci | ACTIVE | Copies frozen, raters fixed; the stuck-case bucket must be empty first |
| 4 | `OPEN` | Pengisian Dibuka | ACTIVE | Raters may fill |
| 5 | `CLOSED` | Pengisian Ditutup | CLOSED | No new scores; calculation, calibration and review happen here |
| 6 | `PUBLISHED` | Terbit | CLOSED | Employees may see their conclusion |
| 7 | `ARCHIVED` | Diarsipkan | ARCHIVED | Read-only; gone from active lists |

### What was dropped, and why

The design document listed `KALKULASI`, `KALIBRASI`, and `REVIEW` as cycle states.
All three were dropped because each already has a better home.

Calculation progress is per participant and already lives in
`N_PA_Result.Status_Hasil`. A cycle-level "calculating" state is a transient that
gets stuck the moment a job dies, and then says something false about the cycle.

Review uses the generic HCIS approval engine (K16), which tracks its own state.
Mirroring it into `Status_Cycle` creates two answers to "is the review finished",
and they drift.

### `Kode_Badge` is the mockup's four labels

The mockup shows only Draft, Active, Closed, and Archived. That is not a contradiction
but a display simplification: `ACTIVE` covers `PREPARED`, `LOCKED`, and `OPEN`. Storing
the grouping as data keeps that mapping out of the front-end code, where it would be
invisible to anyone reading the schema.

### `ARCHIVED` is a status, not `Flag_Aktif = 'T'`

Archiving a finished cycle and retiring a row created by mistake are different events.
Merging them would make a completed 2025 cycle indistinguishable from one created by a
misclick. `N_PA_Cycle.Flag_Aktif` keeps its original meaning.

## Physical decisions

- **The foreign key points at the code, not the id.** Convention in this repository is
  `<Entitas>_Id` for foreign keys, and this deviates on purpose. Pointing at
  `Kode_Status` keeps `DEFAULT ('DRAFT')` working — a default constraint cannot contain
  a subquery, so an id-based key would have to hardcode an identity value or drop the
  default — and it keeps `N_PA_Cycle` rows readable without a join. `Kode_Status` has a
  `UNIQUE` constraint, which is what makes it a legal foreign-key target.
- **The seed lives in forward.sql, not data-change.sql.** The foreign key is created in
  the same change and refuses to attach while `N_PA_Cycle` holds a status the master
  does not list. Keeping the seed in the DML would make the forward run order-dependent
  on a file it is not allowed to assume ran.
- **`Urutan_Alur` is unique among active rows only**, so a status can be retired and
  replaced at the same position without deleting history.
- A guard before the foreign key rejects any existing `Status_Cycle` value the master
  does not know, with a message telling the operator to fix the data first rather than
  failing on a raw constraint error.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- `N_PA_Cycle_Status` did not exist; `N_PA_Cycle.Status_Cycle` did.
- Change ID 069 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in seven batches and the exact
  data-change.sql in one batch.
- Verified 11 columns, the seven seeded statuses in order, and `FK_PA_Cycle_Status`
  enabled and trusted.
- Rollback behaviour tests, all rolled back, leaving zero cycles and seven statuses:
  - rejected a cycle carrying a status absent from the master;
  - accepted a cycle with no status given, defaulting to `DRAFT`;
  - accepted a cycle in each of the seven statuses;
  - rejected a duplicate `Kode_Status`;
  - rejected a duplicate active `Urutan_Alur`;
  - accepted an eighth status at position 8, confirming the list stays open.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually, after 061 through 068 — **069 must follow 066**.
- Confirm production execution before production verification.
