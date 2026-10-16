# DDL-2026-067 — Competency masters, source-code mapping, requirements, score ranges

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-067-performance-appraisal-competency-and-score-range |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Competency |
| Status | TRACKER_PENDING |
| Artifact revision | **r3** |
| Dependencies | HRIS_Level |
| Forward DDL | database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/forward.sql |
| Manual reset | database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/drop.sql |
| DML | database/dml/DDL-2026-067-performance-appraisal-competency-and-score-range/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-29-performance-appraisal-design.md |
| Forward checksum SHA-256 | a1f9b820ab453206c942d2364482ba9ddfed81c41e773b9e22b3d644d729a2df |
| DML checksum SHA-256 | 76dea05341a786f5a4ec3f61fc5bd43ca5792476644ac3824e0af929930d5723 |

## Amendment history

| Rev | Date | Trigger | Change |
|---|---|---|---|
| r1 | 2026-08-30 | first authoring | Five tables; `N_PA_Score_Range` with 15 columns. |
| r2 | 2026-08-30 | mockup review (user decision) | Added `Target_Persen decimal(5,2) NULL` to `N_PA_Score_Range` with a range CHECK, and carried it in the lookup index. |

The calibration screen in the mockup compares each grade's actual share against a
target — A 10%, B+ 25%, B 40%, C+ 20%, C 5% — to drive forced distribution. That
target had no home anywhere in the design. It is an attribute of the grade band
itself, not a separate entity: grade A means "score at or above 86 **and** aiming at
10% of the population". A column, not a table.

The targets across active grade rows must total 100. That spans rows, so it is an
application gate, like the weight totals elsewhere in this module.

Development was rebuilt from the r2 artifacts under the development rebuild
exception in `AGENTS.md`; the change is still `TRACKER_PENDING` and the tables held
only the seed, asserted before anything was dropped.

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r2 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r3 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

## Scope

Five tables: `N_PA_Competency_Type`, `N_PA_Competency`, `N_PA_Competency_Kode_Soal`,
`N_PA_Competency_Requirement`, `N_PA_Score_Range`. Seeds four competency types and
the twenty-four competencies from the PIC deck. Registers the change.

Three tables are created empty on purpose, each for a stated reason below.

## Kamus versus aturan (K26)

Two of these tables are dictionaries and three are rules, and the split decides
which of them a cycle later copies.

`N_PA_Competency_Type`, `N_PA_Competency`, and `N_PA_Competency_Kode_Soal` are
dictionaries: they state what something *means*. A meaning must not differ between
cycles — a source code `FRUITS` that means K01 in one cycle and K02 in another makes
results incomparable. A wrong dictionary entry is corrected globally, never versioned
per cycle. The audit trail survives anyway, because `N_PA_Assignment_Score` stores
both the raw `Kode_Soal_Sumber` and the resolved `Id_Competency`, so every result
carries the translation it was computed with.

`N_PA_Competency_Requirement` and `N_PA_Score_Range` are rules: changing them changes
people's outcomes. Both get a cycle snapshot. `N_PA_Score_Range` stops at the cycle
layer and gets no per-participant layer — two people with the same score must receive
the same grade.

## Physical decisions

- **`Flag_Berskor` on `N_PA_Competency`.** The four Qualitative items — Strength,
  Development Area, Initiative, Recommendation — belong to the same assessment form
  but carry text, not numbers. Without the flag a mapper could average them as zero
  and quietly drag the competency score down. They are seeded with
  `Flag_Berskor = 'T'`, and the DML refuses to commit unless exactly four rows carry
  that value.
- **Source-code mapping is a child table, not a column.** One competency may arrive
  under different `Kode_Soal` values from different HC Learn packages, so a single
  column on `N_PA_Competency` would fail on the second package. The reverse does not
  hold: one code must resolve to exactly one competency, so uniqueness sits on
  `Kode_Soal`, filtered to active rows. Filtering lets a wrong mapping be retired and
  replaced without deleting the record of what was used before.
- **`N_PA_Competency_Requirement` is keyed by level, not by band.** The deck groups
  dimensions by three bands (Pramukarya, Staff, Leaders), but no band concept exists
  in this database and inventing one would mean three more tables. Expressing the
  rule per level says the same thing — a band is simply the same row repeated for the
  levels it covers. This does not merge the two matrices the design keeps separate:
  this one is competency requirement, the rater matrix is weighting.
- ~~**`Score_Type` and `Score_Usage` stay varchar, not masters.**~~ **Superseded by
  DDL-2026-070.** The original argument was that, unlike competency types, these are
  fixed by the design rather than curated by HC, so a lookup table would add nothing.
  That reasoning asked whether the list grows, which was the wrong question: the risk
  is that different sessions spell a fixed value differently, and a free `varchar`
  accepts every spelling. Both columns now carry foreign keys to `N_PA_Score_Type`
  and `N_PA_Score_Usage`.
- `Nilai_Sampai` is the **exclusive** upper bound. With inclusive bounds the existing
  KPI bands (below 75, 75–89, 90–105) leave 89.5 in a gap belonging to no band.
  Half-open bounds tile the axis with neither gaps nor overlap.
- Score values are `decimal(6,2)`: KPI runs 50–125, competency 0–100, and the final
  axis is not yet fixed.

## What is deliberately not enforced

Bands within one `(Score_Type, Score_Usage)` must not overlap. That spans rows, so no row
CHECK can express it; it is an application gate, like the 100% total on
`N_PA_Rater_Policy_Detail`.

## Why three tables ship empty

- `N_PA_Competency_Kode_Soal`: the HC Learn contract for PA is not settled. The
  sample payload comes from the recruitment module, where the join key is
  `Kode_Soal`. Tracked as T01 and T04.
- `N_PA_Competency_Requirement`: the deck's band matrix is known, but which HRIS
  levels belong to Pramukarya, Staff, and Leaders has never been stated. Guessing it
  would put a business decision into a seed.
- `N_PA_Score_Range`: the canonical scale and the band boundaries are still open
  (T05).

## Pre-execution audit

- Verified target `Web_HRIS` development.
- None of the five tables existed.
- `HRIS_Level.ID_Level` is `bigint` with primary key `PK_Level`.
- Change ID 067 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in nine batches and the exact
  data-change.sql in one batch.
- Verified column counts 10/12/10/10/16, four enabled and trusted foreign keys, five
  enabled and trusted CHECK constraints, and thirteen index objects.
- Verified the seed: 4 types and 24 competencies — 11 Managerial, 6 Core Value,
  3 Behavior, 4 Qualitative — with exactly the four Qualitative items unscored.
- Rollback behaviour tests, all rolled back, leaving the seed untouched and the three
  empty tables still empty:
  - rejected a duplicate `Kode_Competency`;
  - rejected a competency pointing at a non-existent type;
  - rejected `Urutan_Tampil = 0`;
  - rejected one source code mapped to two competencies;
  - accepted retiring a mapping and remapping the same code to another competency;
  - accepted one competency carrying several source codes;
  - rejected a duplicate requirement for the same level and type;
  - rejected a score band whose upper bound is below its lower bound;
  - rejected a duplicate band code within one axis and use;
  - accepted the same band code on a different axis;
  - rejected `Target_Persen = 120`;
  - accepted grade A with a 10% target;
  - accepted a NULL target on a non-grade band.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually, after 061 through 066.
- Confirm production execution before production verification.
