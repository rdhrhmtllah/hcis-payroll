# DDL-2026-073 — HC Learn question package contract per cycle and level

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-073-performance-appraisal-cycle-question-package |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Cycle |
| Status | TRACKER_PENDING |
| Artifact revision | **r2** |
| Dependencies | **DDL-2026-066 and DDL-2026-067.** Must run after both. |
| Forward DDL | database/ddl/DDL-2026-073-performance-appraisal-cycle-question-package/forward.sql |
| Manual reset | database/ddl/DDL-2026-073-performance-appraisal-cycle-question-package/drop.sql |
| DML | database/dml/DDL-2026-073-performance-appraisal-cycle-question-package/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-29-performance-appraisal-design.md |
| Forward checksum SHA-256 | 7df2d5c3b6ba403573d9e350187ae46567202ae52c1b6d9247793f91650c18ee |
| DML checksum SHA-256 | 12969ec17af6953d851833bbb7ef6ce1ff30836e6b49f46a8b9d468385c39bcf |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

## Scope

`N_PA_Cycle_Question_Package` records which HC Learn package was chosen for a
(cycle, level), and `N_PA_Cycle_Question_Package_Item` holds a copy of the source
codes that package contained at the moment it was chosen. No seed.

## What this is for

HC Learn owns the questions; HCIS owns the standard. When an admin picks a package,
HCIS pulls its source codes, translates them through
`N_PA_Competency_Kode_Soal`, and can then say immediately whether the package covers
everything `N_PA_Cycle_Competency_Requirement_Snapshot` demands for that level.

The copy is the point. A package can be edited inside HC Learn *after* it was chosen,
so validating only at selection time is not enough. Storing the contract lets the
callback compare what actually arrived against what was promised. Missing items send
the participant to the held bucket; unrecognised codes land with `PA_Competency_Id`
NULL so they stay visible rather than disappearing.

## Physical decisions

- **`Skor_Maksimal` is stored per item, never assumed.** The sample payload returns
  `Skor_Maksimal: 100` while the PIC deck shows a 1–5 scale. While T05 is open, the
  only safe move is to read the scale from the data that arrives. The rollback test
  below confirms one package can carry a 5-point item and a 100-point item at once —
  so a mixed-scale package cannot silently corrupt the arithmetic.
- **`Bobot_Penilaian` is stored as a cross-check.** In the sample payload,
  `(41×20 + 60×30 + 80×50) ÷ 100` equals `66.2`, exactly the `Total_Nilai` HC Learn
  reported. Keeping their weights lets our mapper prove it computes the same thing
  they do instead of trusting it. The check was re-run during testing and still holds.
- **`Sumber_Kontrak` separates an automatic pull from a manual mapping.** If HC Learn
  declines to expose package contents through `options`, an admin maps the codes by
  hand once per package. This column records which is which, so the quality of the
  contract is visible rather than assumed.
- **Grain is (cycle, level), not (cycle, level, rater category).** This follows deck
  slide 9, which states every dimension is combined into **one** assessment form. It
  is the one assumption in this change that could force a shape change: if HC Learn
  ever needs a different package for Self than for Bawahan, a category column is
  required here.
- **`Id_Nilai_Jenis_Soal` is deliberately not stored.** The 64-character token in the
  sample payload looks per-result, not a stable identifier for a question type, so
  keeping it as a join key would mislead whoever reads it next. `Kode_Soal` is the
  join key.
- **No separate `Ditarik_At`.** The draft carried both `Ditarik_At` and `Snapshot_At`,
  which name the same event — the moment the contract was pulled. Only `Snapshot_At`
  survives, matching every other cycle-scoped copy in this module.

## What is deliberately not enforced

Whether the package actually covers the required competency types spans three tables
and is computed, not constrained. So is the rule that a package may no longer be
changed once the cycle is locked.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- Neither table existed.
- `N_PA_Cycle`, `N_PA_Competency`, and `HRIS_Level` were present.
- Change ID 073 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in six batches and the exact
  data-change.sql in one batch.
- Verified column counts 12/13, four enabled and trusted foreign keys, three enabled
  and trusted CHECK constraints, and five PK/index objects.
- Rollback behaviour tests, all rolled back, leaving both tables empty:
  - accepted a package with an item mapped to competency K01;
  - accepted an item whose code is not yet recognised, with a NULL competency;
  - accepted a 5-point item and a 100-point item inside the same package;
  - rejected `Skor_Maksimal = 0`;
  - rejected `Bobot_Penilaian = 150`;
  - rejected a duplicate source code inside one package;
  - rejected two active packages for the same cycle and level;
  - accepted retiring a package and choosing a replacement marked `MANUAL`;
  - rejected an item pointing at a non-existent competency.
- Re-verified the payload arithmetic cross-check: `(41×20 + 60×30 + 80×50) ÷ 100 = 66.2`.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **073 must follow 066 and 067**.
- Confirm production execution before production verification.
