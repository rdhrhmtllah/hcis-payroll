# DML-2026-077 — Seed the Performance Appraisal rule tables

## Change record

| Field | Value |
|---|---|
| Change ID | DML-2026-077-seed-aturan-performance-appraisal |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Settings |
| Status | DEV_EXECUTED |
| Dependencies | **DDL-2026-064, 065, 067.** |
| DML | database/dml/DML-2026-077-seed-aturan-performance-appraisal/data-change.sql |
| Reference | plans/kpi/2026-08-31-angka-acuan-dari-deck-pic.md |
| DML checksum SHA-256 | 6561ee09e7389788701c625632528baee5a92db55089f4062de2ab5dfece02e8 |

## Why

Every PA dictionary was full; every PA *rule* table was empty. Cycle preparation works by
**copying** the blueprint, and copying from empty produces empty — so no cycle could have
been prepared at all. This change fills three of them.

## What was filled, and what deliberately was not

| Table | Rows | Source |
|---|---:|---|
| `N_PA_Rater_Policy` | 13 | deck slide 4, verbatim |
| `N_PA_Rater_Policy_Detail` | 45 | deck slide 4, verbatim |
| `N_PA_Pillar_Weight` | 13 | **provisional** — deck gives only four bands |
| `N_PA_Competency_Requirement` | 33 | deck slide 9 |
| `N_PA_Score_Range` | **0** | the deck contains no grade thresholds at all |
| `N_PA_Competency_Kode_Soal` | **0** | waiting on HC Learn's question codes |

`N_PA_Score_Range` is the important one to state plainly. The grade codes visible in the
deck (A, B+, B, C+) appear only inside sample screenshots — there is no band definition
anywhere, and no forced-distribution target either. That is part of T05, not something
that was merely skipped.

## Which slide won, and why it mattered

The deck states the rater weights **twice, differently**. Slide 4 gives a 12-row table per
level (Manager: superior 45, self 10, peer 15, subordinate 30). Slide 18 gives three coarse
groups instead (Managerial: superior 40, self 10, peer 25, subordinate 25), disagreeing on
almost every row.

Slide 4 was used, on the user's decision: the design is per level, and its level names
match `HRIS_Level` while slide 18's groups do not exist as levels at all.

Two consequences follow from that table's shape:

- The **"Officer (Specialist)"** row was dropped. Specialist is not a level and does not
  exist in `HRIS_Level`. It is handled as a per-participant override — precisely the case
  the override tables were built for.
- The **"Atasan 2" column marked *adjustable*** is not a separate category. Superior-1 and
  superior-2 are both `ATASAN`; what separates them is `Bobot_Dalam_Kategori` on
  `N_PA_Assignment` (K18).

## A zero weight means no row, not a row with zero

Pramukarya uses only `ATASAN`; Staff uses only `ATASAN` and `SELF`. Pramukarya gets one detail row and
Staff gets two, not four with zeros. A zero-weight row would still demand that raters
be found for a category the level does not use, and would show up in every coverage report
as permanently incomplete.

## What is provisional and must be replaced through the master page

1. **Pillar weights.** The deck gives four bands only — Staff 80/20, "SPV / Section Head"
   70/30, Manager 65/35, "Senior Manager / GM" 55/45 — and one of those names does not
   exist in `HRIS_Level`. The seeded values follow the deck's gradient (the higher the
   level, the smaller the KPI share) and are anchored on the levels the deck does name;
   levels in between were filled sensibly. The user has stated the real mapping will be
   managed through a page of its own.
2. **Minimum and maximum raters.** The deck gives exactly one example (slide 17: 1 self,
   1 superior, 3 peers, 5 subordinates for a managerial level). The seeded numbers are
   conservative, not copied. Subordinates were capped at 8 because one manager can have
   dozens of reports, and an uncapped rule turns a single manager into dozens of
   assignments.
3. **Where "Leaders" begins.** Slide 9 names three groups — Pramukarya, Staff, Leaders —
   without saying which level starts Leaders. Assisten Manager was taken as the boundary.

## Levels deliberately left without rules

- **FREELANCE (98 people)** — excluded from PA on the user's decision. The filtering
  happens during participant preparation, so no rule is needed at all.
- **NOT SET and BELUM** — not real job grades, zero people. Left ruleless *on purpose*:
  anyone landing there falls into the `TERTUNDA` basket and becomes visible, rather than
  being appraised under an invented rule.

`DIREKTUR` and `DIREKTUR UTAMA` are absent from the deck and hold zero people, but they are
real grades, so they were given rules continuing the General Manager pattern. That is
**extrapolation, not deck data**, and it is labelled as such in the SQL.

## Guards inside the change

The script refuses to commit rather than seeding something subtly wrong. It rolls back if
any rater row does not total 100, if any referenced `ID_Level` is absent from `HRIS_Level`,
if any pillar split does not total 100, if the active policy count is not 13, if any
persisted policy's detail weights do not total 100, if Pramukarya demands anything beyond
Behavior, if the count of levels carrying Qualitative Question is not 8, or if a rule ever
appears for FREELANCE, NOT SET, or BELUM.

## Agent completed

- Verified target `Web_HRIS`; read the deck's numbers rather than recalling them.
- Confirmed the real column names before writing: `N_PA_Rater_Policy` has `Catatan_Versi`,
  not `Catatan`, and carries `Activated_At`, which is now set because the seeded rows are
  `ACTIVE` rather than `DRAFT`.
- Executed the exact data-change.sql in two batches. Result: 13 policies, 45 detail rows,
  13 pillar splits, 33 competency requirements.
- Measured coverage: **860 of 958 active employees** now resolve to an active rater policy.
  The 98-person gap is exactly FREELANCE, which is intended.
- Spot-checked the per-level table against the deck: Staff 80/20 with superior 80 and self
  20; Officer 75/25 with 55/10/10/25; Manager 65/35 with 45/10/15/30; General Manager 55/45
  with 40/10/20/30.
- Re-ran the exact file and confirmed a no-op — counts unchanged at 13/45/13/33 and the
  change registered exactly once.

## User tasks

- Review the provisional pillar weights and the Leaders boundary; both are placeholders.
- Record the exact tested artifact and checksum in SQL Tracker.
- Execute production manually — **077 must follow 064, 065, and 067**.
