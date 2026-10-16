# DML-2026-078 — Seed the HAV Map tiers and grade bands

## Change record

| Field | Value |
|---|---|
| Change ID | DML-2026-078-seed-pita-nilai-performance-appraisal |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Settings |
| Status | DEV_EXECUTED |
| Dependencies | **DDL-2026-067, 068.** |
| DML | database/dml/DML-2026-078-seed-pita-nilai-performance-appraisal/data-change.sql |
| Reference | plans/kpi/2026-08-31-angka-acuan-dari-deck-pic.md |
| DML checksum SHA-256 | 8534c0d83c33b049890e0b7312ccacc5a9235c779b95dca285128567ccc66c40 |

## These numbers are provisional, and that has to be said first

The PIC deck contains **no grade thresholds at all**. The codes visible there — A, B+, B,
C+ — appear only inside sample screenshots, and there is no forced-distribution target
anywhere in the file. The values seeded here are *plausible*, not *authoritative*. They
exist on the user's instruction so the calculation path can be assembled and tested before
HC answers T05.

## Which scale, and why it does not lock anything in

The bounds are on **0–100**. The deck uses two different scales for the same thing: 1–5 on
slides 20 and 13 (4.6, 4.05) and percentages on another slide 13 image and slide 23 (78%,
83%). Percentages were chosen because the mockup reads HAV Map and grades as percentages.

If T05 comes back saying the canonical scale is 1–5, only the **numbers in this table**
change — not the schema, and not the calculation code. That was the point of storing
`Skor_Maksimal` per item back in DDL-076.

## What was seeded

| Score_Type | Score_Usage | Bands |
|---|---|---|
| `KPI` | `HAVMAP` | RENDAH 0–69.99, SEDANG 70–84.99, TINGGI 85–100 |
| `KOMPETENSI` | `HAVMAP` | RENDAH 0–69.99, SEDANG 70–84.99, TINGGI 85–100 |
| `FINAL` | `GRADE` | A 90–100 (10%), B+ 80–89.99 (20%), B 70–79.99 (40%), C+ 60–69.99 (20%), C 50–59.99 (8%), D 0–49.99 (2%) |

Twelve rows. The deck shows only the top four grade codes; **C and D were added** so the
bands reach zero. Without them any score below 60 would have no grade at all, and the
result would come out empty with nothing to explain why.

`Kode_Range` uses `B_PLUS` and `C_PLUS` rather than `B+` and `C+`. The plus sign is fine in
a display name but a poor code — it survives badly through URLs and query strings, where it
decodes as a space. `Nama_Range` carries the human label.

## KONVERSI was deliberately left empty

`N_PA_Score_Range` also supports a `KONVERSI` use, and it was **not** seeded. Normalising a
score needs no lookup table: every item already carries its own `Skor_Maksimal` in
`N_PA_Assignment_Score`, so the conversion is arithmetic. Planting a conversion table now
would create a second source of truth capable of disagreeing with the maximum HC Learn
actually sent.

## The guard that matters most

Three tiers on two axes produce the nine boxes, so the tier codes here must match
`N_PA_Hav_Quadrant.Tingkat_Performa` and `Tingkat_Kompetensi` **exactly**. The script
refuses to commit if any tier used by an active quadrant lacks a band on its axis.

That failure would not have surfaced as an error. It would have surfaced as a box on the
9-box grid that no employee could ever land in — silently, and only noticed once someone
asked why a quadrant was always empty.

Two more guards run before anything is written: every `(Score_Type, Score_Usage)` group must cover
0 to 100 with no gap and no overlap, and the grade targets must total 100. A gap is the
same class of defect — a score with no band produces an empty result and no explanation.

## Agent completed

- Verified target `Web_HRIS`.
- Read `N_PA_Hav_Quadrant`'s real tier values before choosing codes, rather than assuming
  them: `TINGGI`, `SEDANG`, `RENDAH` across all nine active quadrants.
- Confirmed the unique index is `(Score_Type, Score_Usage, Kode_Range)` filtered on active, so
  `RENDAH` can legitimately exist on both axes.
- Executed the exact data-change.sql in two batches. Result: 12 active bands.
- Cross-checked afterwards by joining the quadrants to both axes: **9 of 9 quadrants** are
  reachable.
- Re-ran the exact file and confirmed a no-op — 12 rows unchanged, change registered once.

## User tasks

- Treat every number here as a placeholder until HC answers T05.
- Record the exact tested artifact and checksum in SQL Tracker.
- Execute production manually — **078 must follow 067 and 068**.

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |
