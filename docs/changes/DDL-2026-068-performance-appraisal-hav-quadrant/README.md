# DDL-2026-068 — HAV Map quadrant dictionary

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-068-performance-appraisal-hav-quadrant |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / HAV Map |
| Status | TRACKER_PENDING |
| Artifact revision | **r2** |
| Dependencies | None. The table stands alone. |
| Forward DDL | database/ddl/DDL-2026-068-performance-appraisal-hav-quadrant/forward.sql |
| Manual reset | database/ddl/DDL-2026-068-performance-appraisal-hav-quadrant/drop.sql |
| DML | database/dml/DDL-2026-068-performance-appraisal-hav-quadrant/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-29-performance-appraisal-design.md |
| Forward checksum SHA-256 | ee20d0547550ed38ac6efe93ce0d542295a80627fb21f3760284efccbef2074d |
| DML checksum SHA-256 | 468f6b71710ee34b04fdea40e66419a75462d1efa7228a8fbc8b75ca239705df |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

## Scope

Create `N_PA_Hav_Quadrant` and seed the nine boxes from the PIC deck. Register the
change.

## Why this table exists

`N_PA_Result` was designed to carry a quadrant code, but nothing defined what those
codes are. Without this table the nine box names and the combinations they stand for
would live in application code, where a change to either becomes a deployment rather
than an edit — and the mockup already puts those names and colours on screen.

## Physical decisions

- **The band references are codes, not foreign keys.** `Tingkat_Performa` and
  `Tingkat_Kompetensi` hold values such as `TINGGI` that also appear as `Kode_Range`
  in `N_PA_Score_Range` for `Score_Usage = 'HAVMAP'`. They are deliberately not a
  foreign key. A quadrant only names a combination — what counts as high is
  `N_PA_Score_Range`'s business, and that table is snapshotted per cycle while this
  one is not. Coupling them would drag a dictionary into a rule's lifecycle.
- **No CHECK listing the allowed levels.** Three levels is today's shape, not a law.
  If HC ever moves to four, a CHECK becomes an obstacle rather than a safeguard. The
  application validates the value against the active `HAVMAP` ranges on save. Project
  rules already forbid database enums for evolving business lists.
- **Two boxes share the name "Future Star".** Box 2 is high performance with medium
  competency; box 3 is the reverse. They are genuinely different positions, so they
  are separate rows with distinct codes `FUTURE_STAR_A` and `FUTURE_STAR_B`, and
  `Kode_Kuadran` is unique while `Nama_Kuadran` is not.
- **Three uniqueness rules, each guarding a different mistake.** `Kode_Kuadran` is
  unique outright. `Nomor_Box` and the pair
  `(Tingkat_Performa, Tingkat_Kompetensi)` are unique among active rows only, so a
  quadrant can be retired and replaced without deleting the record of what was used
  before.
- **This is a dictionary, so it is never snapshotted per cycle** (K26). The numbers
  that decide which box someone lands in live in `N_PA_Score_Range`, which is
  snapshotted; this table only supplies the label.
- `Warna` is left NULL. The hex values from the PIC mockup are not available yet, and
  filling them later needs no DDL.

## What is deliberately not enforced

Whether the nine rows tile the whole three-by-three grid cannot be a row CHECK, since
it spans rows. The DML asserts it before committing — exactly nine active rows and
exactly nine distinct level combinations — and rolls back otherwise.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- `N_PA_Hav_Quadrant` did not exist.
- Change ID 068 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in four batches and the exact
  data-change.sql in one batch.
- Verified 14 columns, two enabled and trusted CHECK constraints, and five index
  objects.
- Verified the seed: nine boxes covering all nine level combinations, from
  Striving Star (high × high) to Deadwood (low × low).
- Rollback behaviour tests, all rolled back, leaving the nine seeded rows intact:
  - rejected a duplicate level combination;
  - rejected a duplicate `Nomor_Box`;
  - rejected a duplicate `Kode_Kuadran`;
  - rejected `Nomor_Box = 0`;
  - accepted a level value outside the current three, confirming the design stays
    open to a four-level scheme;
  - accepted retiring a quadrant and adding its replacement for the same
    combination.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually, after 061 through 067.
- Confirm production execution before production verification.
