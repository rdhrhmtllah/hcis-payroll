# DDL-2026-080 — Result status master, note-type master, and the result row

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-080-performance-appraisal-result |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Result |
| Status | TRACKER_PENDING |
| Artifact revision | **r2** |
| Dependencies | **DDL-2026-068 and DDL-2026-072.** |
| Forward DDL | database/ddl/DDL-2026-080-performance-appraisal-result/forward.sql |
| Manual reset | database/ddl/DDL-2026-080-performance-appraisal-result/drop.sql |
| DML | database/dml/DDL-2026-080-performance-appraisal-result/data-change.sql |
| Concept | plans/kpi/2026-09-01-lapisan-hasil-pa-rancangan.md |
| Forward checksum SHA-256 | 5a8b33a89c89926481328f59e6fc958f9f15353d4723f056ebc5a3e60e769461 |
| DML checksum SHA-256 | 9f12f7bab9932f097acbfbfd949b9f8e9f91241700eaf7bae3e32bec83e2db6d |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

## Scope

`N_PA_Result_Status` (4 rows) and `N_PA_Result_Note_Type` (2 rows) are closed
dictionaries seeded here. `N_PA_Result` is one row per participant, 29 columns.

## The decisions this table encodes

Five business decisions from 1 September 2026 shape it:

1. The KPI figure is taken **YTD** over the cycle's period, through
   `KpiScoringService::accumulatedScore()`.
2. Only KPI submissions with `Status_Pengajuan = 'disetujui'` count.
3. Someone with no KPI is **not** silently given a 0/100 pillar split — the system
   flags it, an admin decides.
4. A score above 100 is **not truncated**.
5. KPI's `Total_Bobot` is always 100.

## Two KPI columns, not one

`accumulatedScore()` returns both `raw_pct` and `final_pct`. Both are stored:
`Nilai_KPI_Sebelum_Cap` and `Nilai_KPI`.

The gap between them is real and large — the cap is `[50, 125]`, and dev data holds a
raw achievement of `177.75` that becomes `125` after capping. Without the raw value, the
question "why is this 125 when he achieved 177" can only be answered by recomputing, and
recomputing is exactly what will no longer be possible once the source data moves on.

## `Final_Score` is deliberately unbounded above

There is **no CHECK capping it at 100**, and that is the point. A staff member on an
80/20 pillar split can legitimately reach `0.8 × 125 + 0.2 × 100 = 120`, and the grade
bands have been open at the top since DML-2026-079.

Putting a 100 ceiling here would create a second source of truth about scale, disagreeing
with the bands the moment either side changes. The only bound enforced is non-negativity.

## A column that was designed and then cancelled

An earlier draft carried `PA_Metode_Nilai_Id` plus a master listing the KPI aggregation
method, on K31 grounds. **The user rejected it, correctly.**

K31 applies to a column whose value *varies* across a fixed list. This one would have
held `AKUMULASI_YTD` on every row forever, because for KPI that is the only method there
is. A column that can only ever hold one value stores no information.

The fallback argument did not survive either: if the method ever changes, everything
computed before that date used the old one, and `Calculated_At` plus the change record
already say so. A method column earns its place only when two methods run side by side.

## Publication lives here

`Published_At` / `Published_By` are on the result, not on the participant. What gets
published is the *result*, and some participants can be held back while others are
released — so the state belongs to the row that is released.

## What is deliberately not enforced

- `Alasan_Tertahan` being present when the status is `TERTAHAN`. The CHECK would have to
  hardcode the business literal `'TERTAHAN'`, which the project rules forbid.
- That `Kode_Grade_Final` may differ from `Kode_Grade_Sistem` only when
  `Flag_Dikalibrasi = 'Y'` — it depends on calibration history, not on this row alone.
- Status ordering. `TERBIT` before `DIHITUNG` is nonsense, but enforcing it at row level
  would harden the workflow into the schema.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- None of the three tables existed.
- `N_PA_Participant` and `N_PA_Hav_Quadrant` were present.
- Change ID 080 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Executed the exact forward.sql in nine batches and the exact data-change.sql in one.
- **The first run failed on the artifact's own guard**, and correctly: the column-count
  assertion said 28 while `N_PA_Result` has 29. The tables were created; only the
  verification block threw. The constant was corrected to 29 and the artifact re-run —
  the guard caught the author's arithmetic, which is what it is for.
- Verified column counts 10/10/29, four status rows, two note-type rows, three enabled
  and trusted foreign keys, eight enabled and trusted CHECK constraints, and eight
  PK/index objects.
- 17 rollback behaviour tests against real participants, all rolled back. Every rejection
  asserts the error came from SQL Server and records which constraint fired:
  - accepted the defaults landing as `BELUM_DIHITUNG`, version 1, not calibrated;
  - rejected a status absent from the master → `FK_PA_Result_Status_Master`;
  - **accepted a final score of 120** — over-target achievement is not truncated;
  - **accepted capped 125 alongside raw 177.75** in the same row;
  - rejected a negative final score → `CK_PA_Result_Nilai_Non_Negatif`;
  - rejected a 70 + 40 pillar split → `CK_PA_Result_Bobot`;
  - accepted a 0/100 split for someone with no KPI;
  - rejected only one of the two weights being present → `CK_PA_Result_Bobot`;
  - rejected a KPI period ending before it starts → `CK_PA_Result_Periode`;
  - rejected raters-completed exceeding raters-expected → `CK_PA_Result_Rater`;
  - rejected a negative pending-KPI count → `CK_PA_Result_KPI_Menggantung`;
  - rejected calculation version 0 → `CK_PA_Result_Calc_Version`;
  - rejected a non-existent HAV quadrant → `FK_PA_Result_Hav_Quadrant`, and accepted a
    valid one;
  - rejected two active results for one participant → `UX_PA_Result_Participant`, and
    accepted a replacement once the earlier row was retired;
  - rejected an orphan result → `FK_PA_Result_Participant`.
- Re-ran the exact artifacts and confirmed a no-op; `N_PA_Result` left empty, masters
  unchanged at 4 and 2, and no fixture rows left behind in `N_PA_Cycle` or
  `N_PA_Participant`.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **080 must follow 068 and 072**.
