# DDL-2026-070 — Policy status, score axis, and score usage masters

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-070-performance-appraisal-status-and-axis-masters |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Master |
| Status | TRACKER_PENDING |
| Artifact revision | **r2** |
| Dependencies | **DDL-2026-064 and DDL-2026-067.** Must run after both. |
| Forward DDL | database/ddl/DDL-2026-070-performance-appraisal-status-and-axis-masters/forward.sql |
| Manual reset | database/ddl/DDL-2026-070-performance-appraisal-status-and-axis-masters/drop.sql |
| DML | database/dml/DDL-2026-070-performance-appraisal-status-and-axis-masters/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-29-performance-appraisal-design.md |
| Forward checksum SHA-256 | 333f622bbfc425e09a03832839935fba536783f6c3dc0874667e4515c0137c58 |
| DML checksum SHA-256 | 1060735f7f424b083ca3375ed13257a1226403462a256a9c1329eca6c488654c |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

## Scope

Three lookup tables — `N_PA_Rater_Policy_Status`, `N_PA_Score_Type`,
`N_PA_Score_Usage` — each seeded with three rows, and three foreign keys from the
columns that until now were free `varchar`. Registers the change.

## Why

`N_PA_Cycle_Status` (DDL-2026-069) closed the same gap for the cycle. Three columns in
the foundation layer still had it:

- `N_PA_Rater_Policy.Status_Policy` — the documentation listed values as
  "**misalnya** DRAFT, ACTIVE, INACTIVE, atau ARCHIVED". The word *misalnya* is the
  problem: nothing was ever settled, so the next session to read that line invents its
  own list.
- `N_PA_Score_Range.Score_Type` and `.Score_Usage` — the values were written down in the table
  documentation but nothing enforced them. `COMPETENCY` instead of `KOMPETENSI` would
  have been accepted and would then have silently matched nothing.

These are lookup tables, not enum CHECKs. Project rules forbid rigid CHECKs on
evolving business lists; adding a value here is inserting a row.

## The seeds

**`N_PA_Rater_Policy_Status`** — three, not the four the old note suggested.

| # | Kode | Nama | Arti |
|---|---|---|---|
| 1 | `DRAFT` | Draft | Sedang disusun; total bobot belum wajib 100 dan belum boleh dipakai siklus |
| 2 | `ACTIVE` | Berlaku | Sudah diaktifkan; total bobot kategori aktif wajib 100 sebelum masuk sini |
| 3 | `INACTIVE` | Tidak Berlaku | Digantikan versi lebih baru atau dihentikan |

`ARCHIVED` was dropped. A superseded policy version is `INACTIVE`, and a row created by
mistake is handled by `Flag_Aktif`. A fourth state with no distinct gate is exactly the
kind of thing that drifts between documents — which is what this change exists to stop.

**`N_PA_Score_Type`** — `KPI`, `KOMPETENSI`, `FINAL`.

**`N_PA_Score_Usage`** — `HAVMAP`, `GRADE`, `KONVERSI`, each carrying two flags that
say which optional columns of `N_PA_Score_Range` it actually uses:
`Flag_Pakai_Nilai_Hasil` is `Y` only for `KONVERSI`, and `Flag_Pakai_Target` is `Y` only
for `GRADE`. Those two columns on the range table are nullable precisely because they
belong to one usage each; recording which is which as data means a reader of the schema
can see it without hunting through code.

## A reversal worth recording

The 067 documentation argued `Score_Type` and `Score_Usage` should stay `varchar` because,
unlike competency types, they are fixed by the design rather than curated by HC. That
reasoning was about **whether the list grows**, and it answered the wrong question.

The risk here is not growth. It is that different sessions spell a fixed value
differently, and a free `varchar` accepts every spelling. A lookup table costs three
rows and removes the failure mode entirely. The rollback test below shows `COMPETENCY`
being rejected — under 067 it would have been stored.

## Physical decisions

- **Foreign keys point at the codes, not the ids**, for the same reason as
  DDL-2026-069: `DEFAULT ('DRAFT')` on `Status_Policy` cannot survive an id-based key,
  and code-valued rows stay readable without a join. Each code carries a `UNIQUE`
  constraint, which is what makes it a legal foreign-key target.
- **Seeds live in forward.sql.** The foreign keys are created in the same change and
  refuse to attach while the masters are empty.
- **Guards run before the foreign keys.** Any existing `Status_Policy`, `Score_Type`, or
  `Score_Usage` the masters do not know raises a message telling the operator to fix the
  data, instead of failing on a raw constraint error.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- None of the three tables existed; the three consuming columns did.
- Change ID 070 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in ten batches and the exact
  data-change.sql in one batch.
- Verified column counts 10/10/12, the three seeds in each table, and all three
  foreign keys enabled and trusted.
- Rollback behaviour tests, all rolled back, leaving both consuming tables empty:
  - rejected a `Status_Policy` absent from the master;
  - accepted a policy with no status given, defaulting to `DRAFT`;
  - accepted `ACTIVE` and `INACTIVE`;
  - rejected `Score_Type = 'COMPETENCY'`, the exact misspelling this change exists to stop;
  - rejected an unknown `Score_Usage`;
  - accepted `KOMPETENSI` with `HAVMAP`.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **070 must follow both 064 and 067**.
- Confirm production execution before production verification.
