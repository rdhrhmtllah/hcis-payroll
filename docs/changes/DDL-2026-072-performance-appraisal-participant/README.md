# DDL-2026-072 — Cycle participants and their status master

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-072-performance-appraisal-participant |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Participant |
| Status | TRACKER_PENDING |
| Artifact revision | r2 |
| Dependencies | **DDL-2026-066 and DDL-2026-071.** Must run after both. |
| Forward DDL | database/ddl/DDL-2026-072-performance-appraisal-participant/forward.sql |
| Manual reset | database/ddl/DDL-2026-072-performance-appraisal-participant/drop.sql |
| DML | database/dml/DDL-2026-072-performance-appraisal-participant/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md |
| Forward checksum SHA-256 | eb250f3cfe0e6a7616fe756de10a48db028fc21e662edf3924043f0382507e7c |
| DML checksum SHA-256 | 623a9fe9e4e97af474460417fcf14f7b7914349d6ae4404fdd0a96b73dc05b0a |

## Amendment history

| Rev | Date | Trigger | Change |
|---|---|---|---|
| r1 | 2026-08-30 | first authoring | 20 columns; division held as one combined id and one combined name; no location. |
| r2 | 2026-08-31 | layer-2 completeness check against the mockup | Split division into parent and child with their own ids and names, added `Lokasi_Saat_Cycle`, and carried all three into the monitoring index. 24 columns. |

The monitoring screen filters participants by Departemen, Divisi, Jabatan, and Lokasi,
and groups its heatmap by Departemen. Under r1 none of that could be answered from the
snapshot: the division was a single combined name, and the location was not stored at
all. Monitoring would have had to join live `Karyawan`, which defeats the freeze —
someone who changes division mid-cycle would move on the historical heatmap too.

**Naming trap worth knowing before wiring the filter.** In the mockup, Departemen is
the parent and Divisi its child (Human Capital → HC Learning & Development). In HCIS
the hierarchy is the other way round: `nama_divisi` is the parent (20 values such as
SALES & DISTRIBUTION, HC-GA, IT) and `nama_sub_divisi` its child, while
`N_HRIS_Master_Departemen` hangs *below* sub-division. So the mockup's "Departemen" is
this database's `nama_divisi`. Wiring them the obvious way produces a filter that looks
right and groups the wrong level.

Both name columns are `varchar(100)`, matching `View_Divisi_Sub_Divisi`, and
`Lokasi_Saat_Cycle` is `varchar(15)`, matching `Karyawan.Lokasi` — measured on
development as DISTRIBUTOR 601 and INDUK 356, with one NULL.

Development was rebuilt from the r2 artifacts under the development rebuild exception
in `AGENTS.md`; the change is still `TRACKER_PENDING` and the table held zero rows,
asserted before anything was dropped.

## Scope

`N_PA_Participant_Status` seeded with three statuses, and `N_PA_Participant` which
freezes an employee's organisational state at the moment a cycle is prepared. No
participant seed.

## The three statuses

| # | Kode | Arti |
|---|---|---|
| 1 | `TERTUNDA` | Baris ada, tetapi level atau aturan penilainya belum teresolusi |
| 2 | `DIIKUTKAN` | Teresolusi dan ikut dinilai |
| 3 | `DIKECUALIKAN` | Sengaja tidak dinilai, dengan alasan tertulis |

`TERTUNDA` is the default, deliberately. A row nobody has classified must not quietly
count as participating. It is also what makes the stuck-case bucket a single query:
counting `TERTUNDA` rows per cycle is the gate that blocks locking (K17).

## Physical decisions

- **`ID_Level_Saat_Cycle` is NOT NULL** even though the chain that produces it can
  fail. `Karyawan` has no `ID_Level` column at all; the value is resolved through
  `Karyawan.ID_Level_Jabatan` into `View_Golongan_Sub_Golongan_Level_Jabatan` (K19).
  If that resolution fails, the row must not be created half-formed — the failure is
  what feeds the bucket, and guard G10 blocks the lock until it clears. Measured on
  development, all 958 active employees resolve.
- **Both the raw and the derived level are stored.** `ID_Level_Jabatan_Saat_Cycle` is
  what actually sits on the employee record; `ID_Level_Saat_Cycle` is what the policy
  lookup uses. Keeping only the derived value would lose the ability to re-check the
  resolution later; keeping only the raw one would force every later query to re-walk
  the view.
- **Composite foreign key to `Karyawan`.** `Karyawan` carries a primary key on
  `(Kode_Perusahaan, Kode_Karyawan)`, so employee identity is enforced rather than
  trusted. Employee identity in HCIS is composite; a single-column key would be a
  latent bug the moment a second company appears.
- **Names are copied, identifiers are kept.** `Nama_Karyawan_Saat_Cycle`,
  `Nama_Level_Saat_Cycle`, and the rest exist so a historical report reads correctly
  after someone is renamed or moved. The identifiers stay for traceability.
- **Publication is not here.** An earlier sketch put `Flag_Terbit` on the participant.
  What gets published is the *result*, and some participants may be held back while
  others are released, so that state belongs to `N_PA_Result.Status_Hasil`.
- **`Alasan_Dikecualikan` is required by the application, not by a CHECK.** A row-level
  CHECK could express it, but only by hardcoding the literal `'DIKECUALIKAN'` into the
  schema — exactly what the project rules forbid for business value lists, and exactly
  what the status master exists to avoid.

## What is deliberately not enforced

Which employees belong in a cycle at all — active only, contract type, resignation
cut-off — is a preparation rule, not a constraint. So is the requirement that a
`DIIKUTKAN` participant must point at a rater policy snapshot.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- Neither table existed.
- `Karyawan` primary key confirmed as `(Kode_Perusahaan, Kode_Karyawan)`, which is what
  makes the composite foreign key legal.
- `N_PA_Cycle`, `N_PA_Cycle_Rater_Policy_Snapshot`, and `HRIS_Level` were present.
- Change ID 072 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in seven batches and the exact
  data-change.sql in one batch.
- Verified column counts 10/24, three seeded statuses, five enabled and trusted foreign
  keys, and three index objects.
- Rollback behaviour tests using a real employee resolved through the golongan view
  (`001/A1`, level 2 STAFF), all rolled back and leaving zero participants:
  - accepted a real participant and confirmed the default status is `TERTUNDA`;
  - rejected an employee absent from `Karyawan`;
  - rejected an `ID_Level` absent from `HRIS_Level`;
  - rejected an unknown participant status;
  - accepted all three seeded statuses;
  - rejected the same employee twice in one cycle while both rows are active;
  - accepted the same employee in two different cycles;
  - rejected a pointer to a non-existent rater policy snapshot.
- After the r2 amendment, stored one participant assembled entirely from live sources
  — level through the golongan view, division through ,
  location from  — and confirmed a monitoring grouping by parent
  division and location runs **without touching **.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **072 must follow 066 and 071**.
- Confirm production execution before production verification.
