# DML-2026-079 — Correcting the score bands: exclusive bounds, open at the top

## Change record

| Field | Value |
|---|---|
| Change ID | DML-2026-079-koreksi-pita-nilai-eksklusif |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Settings |
| Status | DEV_EXECUTED |
| Corrects | **DML-2026-078** |
| DML | database/dml/DML-2026-079-koreksi-pita-nilai-eksklusif/data-change.sql |
| Reference | plans/kpi/2026-09-01-sumber-nilai-kpi-untuk-pa.md |
| DML checksum SHA-256 | 369c197e328fe5e0ec520988c4cdd2a2ccc1a302aa76fe3eeb93a85063633878 |

## Two defects, both introduced by DML-2026-078

### 1. The upper bound is exclusive; the seed treated it as inclusive

`N_PA_Score_Range` declares `Nilai_Dari <= x < Nilai_Sampai` — documented in the table
doc and in the DDL-067 README. DML-078 seeded inclusive-looking bands: `80.00–89.99`
then `90.00–100.00`.

That leaves a **hole at every boundary**. A score of exactly `89.99` is excluded from
B+ (its upper bound is exclusive) and excluded from A (which starts at 90). It matches
no band at all, and the result is an empty grade with nothing to explain it.

### 2. The top band closed at 100, but scores can exceed it

KPI is capped at 125 by `config('services.kpi.max_achievement_score')`. With the Staff
pillar split of 80/20, the highest reachable final score is
`0.8 × 125 + 0.2 × 100 = 120`.

Under the old bands, that person fell **outside every band** and came away with no grade
— precisely the highest achiever.

**User's decision, 1 September 2026:** the bands stay anchored to 0–100, but a value
above 100 is **not truncated**; it simply belongs to the top band. Truncating was
rejected as wrong, because performance is genuinely allowed to exceed target.

## Why DML-078's guard did not catch this

The guard there checked `MIN(Nilai_Dari) = 0`, `MAX(Nilai_Sampai) = 100`, and pairwise
overlap. The seeded bands were mutually **disjoint with gaps** (`89.99` then `90.00`),
so nothing overlapped, and the endpoints really were 0 and 100.

**The guard passed because of the defect, not despite it.** What it never checked was
*contiguity* — that each band's lower bound equals the previous band's upper bound.
This change adds that check, plus a real-value lookup test.

## The corrected bands

| Score_Type | Score_Usage | Bands (`[dari, sampai)`) |
|---|---|---|
| `FINAL` | `GRADE` | D `[0,50)` · C `[50,60)` · C+ `[60,70)` · B `[70,80)` · B+ `[80,90)` · **A `[90, 999.99)`** |
| `KPI` | `HAVMAP` | RENDAH `[0,70)` · SEDANG `[70,85)` · **TINGGI `[85, 999.99)`** |
| `KOMPETENSI` | `HAVMAP` | RENDAH `[0,70)` · SEDANG `[70,85)` · **TINGGI `[85, 999.99)`** |

The competency axis needed the open top too, for a subtler reason: with an exclusive
bound of `100`, a **perfect competency score of exactly 100** would have matched no band.

## Why 999.99 and not 125

`125` comes from `config('services.kpi.max_achievement_score')`, which can be changed.
Copying it into data would create a second source of truth that silently disagrees the
day the config moves. `999.99` reads as "open upward" and stays correct regardless.

## Guards added

1. **Contiguity** — each band's lower bound must equal the previous band's upper bound
   within its group. Any gap or overlap rolls the transaction back.
2. Every group must start at `0`.
3. Every group must reach `999.99` — the open top is now enforced, not just intended.
4. **A real-value lookup test**, not a shape check: six values that previously fell into
   a hole or outside every band must now each resolve to exactly one expected band —
   `89.99 → B_PLUS`, `100 → A`, `120 → A`, `49.99 → D`, KPI `125 → TINGGI`,
   competency `100 → TINGGI`.

## Agent completed

- Verified target `Web_HRIS`; confirmed the exclusive-bound rule from the table doc and
  the DDL-067 README before changing anything.
- Executed the exact data-change.sql in two batches; 12 bands updated.
- Verified the lookup independently after execution, outside the script's own guards:

  | Score_Type | Nilai | Pita |
  |---|---:|---|
  | FINAL | 89.99 | B_PLUS |
  | FINAL | 90 | A |
  | FINAL | 100 | A |
  | FINAL | 120 | A |
  | FINAL | 49.99 | D |
  | FINAL | 0 | D |
  | KPI | 125 | TINGGI |
  | KOMPETENSI | 100 | TINGGI |

  Every value resolved to exactly one band — none returned zero bands or two.
- Re-ran the exact file and confirmed a no-op: still 12 bands, change registered once.

## User tasks

- The band thresholds themselves remain provisional until HC answers T05; only their
  *shape* is fixed here.
- Record the exact tested artifact and checksum in SQL Tracker.
- Execute production manually — **079 must follow 078**.

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |
