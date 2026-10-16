# DDL-2026-071 — Cycle rule snapshots

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-071-performance-appraisal-cycle-rule-snapshots |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Cycle |
| Status | TRACKER_PENDING |
| Artifact revision | **r2** |
| Dependencies | **DDL-2026-064, 065, 066, 067, 070.** Must run after all of them. |
| Forward DDL | database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/forward.sql |
| Manual reset | database/ddl/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/drop.sql |
| DML | database/dml/DDL-2026-071-performance-appraisal-cycle-rule-snapshots/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md |
| Forward checksum SHA-256 | 06b1aa17bf935b1e8dbca5b3b7704836c25cea43f05c3992054db26c2fcc241c |
| DML checksum SHA-256 | 1a23f1967259beba80ffc4331d503601938571830ab381d94210eeeb45d4c162 |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | — | Rancangan awal | Revisi ini benar-benar berjalan di development. |
| r2 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

## Scope

The second layer: five tables holding one cycle's own copy of the rules.

| Tabel | Isi | Butiran |
|---|---|---|
| `N_PA_Cycle_Rater_Policy_Snapshot` | Salinan header Rater Policy | per (siklus, level) |
| `N_PA_Cycle_Rater_Policy_Detail_Snapshot` | Salinan bobot dan aturan jumlah penilai | per (salinan header, kategori) |
| `N_PA_Cycle_Pillar_Weight_Snapshot` | Salinan pembagian pilar KPI vs kompetensi | per (siklus, level) |
| `N_PA_Cycle_Competency_Requirement_Snapshot` | Salinan kebutuhan jenis kompetensi | per (siklus, level, jenis) |
| `N_PA_Cycle_Score_Range_Snapshot` | Salinan pita nilai yang berlaku | per (siklus, sumbu, kegunaan, kode) |

No seed. All five are filled by the application when a cycle is prepared.

## Why copies at all

The blueprints in layer one keep changing — a policy gets a new version, a weight is
corrected, a grade boundary moves. A cycle that has already been locked must not
follow those changes, or someone promoted mid-cycle would silently land under a
different rule than the raters assessed them under.

The copy also carries the values inline, not just a foreign key to the source. The
foreign keys here record *where it came from*; the `*_Salinan` columns record *what it
said at the time*. That is the difference between a link and a snapshot.

## One of the five deliberately does not freeze

Four of these tables stop changing when the cycle is locked. `N_PA_Cycle_Pillar_Weight_Snapshot`
does not, and that is K23.

Rater weights must be settled before filling starts, because they also decide who gets
assigned through `Min_Rater`, `Maks_Rater`, and the relationship type. Pillar weights
decide nothing about who fills what; they apply only in the final arithmetic, after
every raw score already exists. Freezing them would trap an admin who wants to change
60/40 to 70/30 once everyone has finished rating — the only way through would be to
break the guarantee that rule changes cannot cross the lock line.

That is why this one table carries `Alasan_Perubahan` and no `Flag_Aktif`: it is
edited in place rather than superseded, and a change after filling closes must say why.

## Physical decisions

- **`Flag_Aktif` on four, absent on one.** A snapshot that turns out wrong is retired
  and replaced, so the uniqueness on those four is filtered to active rows. The pillar
  snapshot has exactly one row per (cycle, level) with no notion of an inactive
  weight, so its uniqueness is unfiltered and covering.
- **Jejak asal is nullable where the row may be created by hand.**
  `PA_Pillar_Weight_Id` and `PA_Score_Range_Id` are nullable because an admin may enter
  a value the blueprint never held. The rater policy trail is NOT NULL — a rater policy
  snapshot without a source policy would have nothing to be a copy of.
- **`Score_Type` and `Score_Usage` carry foreign keys here too**, to the same masters from
  DDL-2026-070. A snapshot is not an excuse to accept a spelling the master rejects.
- All CHECK constraints from the source tables are repeated on the copies. A snapshot
  that can hold a weight above 100 or a reversed range is not a faithful copy.

## What is deliberately not enforced

Total active detail weight of 100 per snapshot header, and total grade target of 100
per cycle, both span rows and remain application gates — same as on the blueprints.

Whether a snapshot may still be edited depends on `N_PA_Cycle.Status_Cycle`, which no
row-level constraint can see. Freezing at `LOCKED` is an application rule.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- None of the five tables existed.
- All twelve prerequisite tables from DDL-064 through 070 were present.
- Change ID 071 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in eleven batches and the exact
  data-change.sql in one batch.
- Verified column counts 13/20/11/13/18, eighteen enabled and trusted foreign keys,
  eleven enabled and trusted CHECK constraints, and eleven PK/index objects.
- Rollback behaviour tests, all rolled back, leaving every table empty:
  - rejected a snapshot pointing at a non-existent cycle;
  - accepted a complete policy snapshot together with its detail row;
  - rejected two active snapshots for the same cycle and level;
  - accepted retiring one snapshot and adding its replacement;
  - rejected pillar weights of 60 and 30;
  - accepted 80/20 entered by hand with no source row and a stated reason;
  - rejected duplicate pillar weights for the same cycle and level;
  - accepted a competency requirement snapshot;
  - rejected an unknown `Score_Type` on a score range snapshot;
  - accepted a grade band carrying a 10% target.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **071 must follow 064, 065, 066, 067, and 070**.
- Confirm production execution before production verification.
