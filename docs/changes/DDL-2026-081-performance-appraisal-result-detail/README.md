# DDL-2026-081 — The four result detail tables

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-081-performance-appraisal-result-detail |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Result |
| Status | TRACKER_PENDING |
| Artifact revision | **r3** |
| Dependencies | **DDL-2026-064, 067, 068, 075, 080.** |
| Forward DDL | database/ddl/DDL-2026-081-performance-appraisal-result-detail/forward.sql |
| Manual reset | database/ddl/DDL-2026-081-performance-appraisal-result-detail/drop.sql |
| DML | database/dml/DDL-2026-081-performance-appraisal-result-detail/data-change.sql |
| Concept | plans/kpi/2026-09-01-lapisan-hasil-pa-rancangan.md |
| Forward checksum SHA-256 | 68d5ee5555170d8715a7a9e022b364ad5b8ea58235a8f3a0d140c8db2493a1b3 |
| DML checksum SHA-256 | ca8e3a22ca7d2b1efb1f71835dcf4c7cf9d4b98b8cdedc38447fc90c4adc6e78 |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |
| r3 | 2026-09-01 | Uji menemukan kalibrasi gagal total lewat API | Menambah `Performed_By_Nama nvarchar(150) NULL`. `Performed_By` adalah `int NOT NULL` berisi Id_User; jalur aplikasi menulis NAMA ke sana, galat konversinya tertutup pesan rollback sehingga muncul sebagai 422 yang menyesatkan. Nama kini punya kolomnya sendiri, mengikuti pola `Nama_Penilai_Saat_Cycle`. |

## Scope

Four children of `N_PA_Result`: `N_PA_Result_Rater_Category` (13 columns),
`N_PA_Result_Competency` (12), `N_PA_Calibration_History` (9), `N_PA_Result_Note` (11).
No seed — all four are filled by the calculation engine and by admins.

## `Bobot_Terpakai` is the weight actually used, not the weight on the rule

The two differ whenever a rater category comes back empty and its weight is
redistributed across the categories that did respond. Without this column, that
redistribution cannot be audited: the result would show a competency score that no
combination of the *documented* weights can reproduce.

This is what answers "why is his competency score that number".

## An empty rater category means the aggregate

On `N_PA_Result_Competency`, `PA_Rater_Category_Id` is nullable, and **NULL means the
figure aggregated across every category** — the number the radar chart draws. A filled
category means the same competency as seen by that one category alone.

SQL Server's unique index treats NULLs as equal, so `UX_PA_Result_Competency_Aktif`
limits the aggregate row to exactly one per (result, competency) without any extra rule.
That was verified rather than assumed: inserting a second aggregate row for the same
competency is rejected.

## Calibration history has no way back

`N_PA_Calibration_History` deliberately carries **no `Flag_Aktif` and no `Updated_At` /
`Updated_By`**. A history that can be deactivated or edited is not a history.

Two more things are enforced rather than trusted:

- **`Alasan` is required and may not be whitespace.** A row here only exists because a
  person overrode the engine, and an override with no reason cannot be defended.
- **`Performed_By` is `NOT NULL`.** Calibration is a human act; a calibration with no
  actor is not auditable.
- **A calibration that changes nothing is rejected** — either the grade or the quadrant
  must move. Otherwise the history fills with rows that record no decision.

There is also a **schema guard**: the artifact throws if `Flag_Aktif`, `Updated_At`, or
`Updated_By` is ever found on that table. If someone later adds one out of habit, the
next run of this artifact will say so.

## Notes remember where they came from

`N_PA_Result_Note.PA_Assignment_Id` is empty when an admin typed the note and filled
when it came from a rater's qualitative answer. The distinction matters to the employee
reading it: "your manager wrote this" and "this is what your peers said" do not carry
the same weight.

## Recalculation follows the mapper's pattern

All three versioned children carry `Calculation_Version` and `Flag_Aktif`. Recalculating
retires the old rows and inserts new ones beside them — the same shape used when
`N_PA_Assignment_Score` is re-mapped, and for the same reason: an old result must stay
explainable after the method changes.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- None of the four tables existed.
- `N_PA_Result`, `N_PA_Result_Note_Type`, `N_PA_Rater_Category`, `N_PA_Competency`,
  `N_PA_Hav_Quadrant`, and `N_PA_Assignment` were present.
- Change ID 081 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Executed the exact forward.sql in eight batches and the exact data-change.sql in one.
- Verified column counts 13/12/9/11, eleven enabled and trusted foreign keys, eleven
  enabled and trusted CHECK constraints, and nine PK/index objects.
- Confirmed independently that `N_PA_Calibration_History` carries none of
  `Flag_Aktif`, `Updated_At`, `Updated_By`.
- 17 rollback behaviour tests against real participants, all rolled back. Every
  rejection asserts the error came from SQL Server and records the constraint:
  - accepted a category trace whose `Bobot_Terpakai` differs from the rule's weight;
  - rejected two active traces for one category → `UX_PA_Result_Rater_Cat_Aktif`;
  - accepted a version-2 trace after the version-1 row was retired, leaving both;
  - rejected `Bobot_Terpakai = 150` → `CK_PA_Result_Rater_Cat_Bobot`;
  - rejected raters-completed above raters-expected → `CK_PA_Result_Rater_Cat_Jumlah`;
  - accepted an aggregate competency row beside a per-category one for the same
    competency;
  - **rejected two aggregate rows for one competency** → `UX_PA_Result_Competency_Aktif`,
    proving NULL is treated as equal;
  - rejected a non-existent competency → `FK_PA_Result_Competency_Comp`;
  - accepted a calibration that moves the grade, and one that moves only the quadrant;
  - rejected a calibration that changes nothing → `CK_PA_Calibration_Ada_Perubahan`;
  - rejected a calibration whose reason is whitespace → `CK_PA_Calibration_Alasan`;
  - rejected a calibration with no actor → NOT NULL on `Performed_By`;
  - accepted both an admin note and a rater-sourced note on one result;
  - rejected a whitespace-only note → `CK_PA_Result_Note_Isi`;
  - rejected an unknown note type → `FK_PA_Result_Note_Type`;
  - rejected an orphan child row → `FK_PA_Result_Competency_Result`.
- Re-ran the exact artifacts and confirmed a no-op; all four tables empty and no fixture
  rows left in `N_PA_Result`, `N_PA_Cycle`, or `N_PA_Participant`.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **081 must follow 064, 067, 068, 075, and 080**.
