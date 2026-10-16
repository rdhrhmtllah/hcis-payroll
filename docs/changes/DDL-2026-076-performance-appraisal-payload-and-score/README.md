# DDL-2026-076 — Raw HC Learn bundle and projected per-item scores

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-076-performance-appraisal-payload-and-score |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Assignment |
| Status | TRACKER_PENDING |
| Artifact revision | **r3** |
| Dependencies | **DDL-2026-067 and DDL-2026-075.** Must run after both. |
| Forward DDL | database/ddl/DDL-2026-076-performance-appraisal-payload-and-score/forward.sql |
| Manual reset | database/ddl/DDL-2026-076-performance-appraisal-payload-and-score/drop.sql |
| DML | database/dml/DDL-2026-076-performance-appraisal-payload-and-score/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-29-performance-appraisal-design.md |
| Forward checksum SHA-256 | 39135bde914c3d52042607705a61279a317db1cac283047fa59cd8c8f30870d0 |
| DML checksum SHA-256 | ae69882eb7d2a9eec3bdd574b0a91f6528debfebeff8dca3277667b5042ba563 |

## Revision history

| Revision | Date | Trigger | Change |
|---|---|---|---|
| r1 | 2026-08-31 | Initial reviewed design | Payload referenced `N_PA_Assignment` directly. This exact revision ran in development and ended as a no-op on rerun. |
| r2 | 2026-09-01 | Service-path audit before SQL Tracker | Payload now references the exact `N_PA_Assignment_Integration` row that received the callback. This preserves the originating token and question package when an inactive historical token submits after a package replacement. |
| r3 | 2026-09-01 | Penyeragaman penamaan yang disetujui pemilik proyek | Nama kolom, constraint, index, dan tabel diselaraskan dengan prinsip: Inggris untuk yang struktural (waktu, pelaku, versi), Indonesia untuk nilai bisnis. Lihat `plans/kpi/pa-penamaan-2026-09-01.md`. |

The r1 forward checksum `a20d313fea4e8a1fb377a492b0006d0dc8a81749814f452348f151a388b1b8a9`
is superseded and **must not be recorded in SQL Tracker**. Development still reflects
r1 until the reviewed r2 rebuild is executed and verified.

## Scope

`N_PA_Assignment_Payload` stores the callback bundle exactly as it arrived and points
to the exact integration/token row that delivered it.
`N_PA_Assignment_Score` stores what the mapper projected out of it, one row per source
question. No seed — both are filled by the callback and the mapper.

This closes the third prerequisite for `N_PA_Result`.

## Insert-only applies to the payload, not to the whole row

The design has always said this table is insert-only. Taken literally that is
impossible: "already projected" is a state that changes *after* the row is born, and it
has to live somewhere.

So the rule is stated precisely. `Payload_Json`, `Payload_Hash`, `Id_Ujian_Nilai_Akhir`,
`Api_Version`, and `Diterima_At` are never updated — the evidence of what HC Learn
actually sent stays untouched. `Flag_Terpeta` and `Mapper_Version` are the only mutable
columns, and they describe our own processing, not their data. Stating it this way is
better than a rule that would be broken on day one.

`Diterima_At` replaces `Created_At` rather than joining it. They name the same event,
and DDL-073 already removed a duplicate pair for exactly that reason.

## Score does not carry the assignment

The sketch had both `Id_Assignment` and `Id_Payload` on the score row. Only the payload
survives: a payload belongs to exactly one assignment, so the assignment is already
determined. This is the same reasoning that removed `Id_Cycle` from `N_PA_Assignment` in
DDL-075 — 75,000 rows are cheap to join, and two sources of one truth are never cheap.

The same rule now applies one level higher: the payload does not repeat
`PA_Assignment_Id`. It references `PA_Assignment_Integration_Id`; that integration row
already determines both the assignment and the question package. This matters when a
historical inactive token submits after an administrator has replaced the active
package. Mapping through the current active integration would apply the wrong contract.

## Re-mapping deletes nothing

The mapper has a version. When v2 replaces v1, the old rows are set
`Flag_Aktif = 'T'` and the new ones are inserted alongside, so the earlier projection
stays auditable — including *why* an old result looked the way it did.
`UX_PA_Assignment_Score_Butir` is filtered on active rows so the same source code can
exist twice in one payload as long as only one is live. Tested end to end.

## `Status_Interpretation` was dropped

"Not yet recognised" is already readable from `PA_Competency_Id IS NULL`. A second
column saying the same thing only adds a way for the two to contradict each other — the
same argument that kept an override-type column out of DDL-074.

## Physical decisions

- **`Skor_Maksimal` is stored per item and read from the data.** The sample payload
  returns 100 while the PIC deck shows a 1–5 scale, and T05 is still open. A 5-point
  item and a 100-point item inside one payload were tested together and accepted.
- **A score above its own maximum is rejected** (`CK_PA_Assignment_Score_Batas`). This
  was deliberate rather than lenient: normalising such a value produces above 100% and
  quietly corrupts the final score, whereas a loud failure costs nothing — the raw
  payload is already stored, so nothing is lost and the row stays visible for repair.
- **Idempotency hangs on `Id_Ujian_Nilai_Akhir`, not on the hash.** A filtered unique
  index over that key stops the same result being projected twice. `Payload_Hash` gets a
  plain index instead: making it unique risked rejecting a genuine retake that happened
  to produce an identical bundle, which would lose real data to prevent a duplicate that
  the proper key already prevents.
- **Two payloads per integration are legal**, which is what a resend needs. NULL keys do
  not collide with each other, so a bundle that arrived before the key existed is still
  storable. Both were tested.
- **`Flag_Terpeta = 'Y'` requires `Mapper_Version`.** The literal in that CHECK is `'Y'`/`'T'`
  from the project's own flag convention, not a business value list, so enforcing it in
  the database is consistent with DDL-074.

## What is deliberately not enforced

That every question in the cycle's question-package contract actually arrived. That
spans `N_PA_Cycle_Question_Package_Item` and is computed, not constrained — a missing
item sends the participant to the held bucket.

Nothing stops a payload arriving after the cycle is closed; that depends on
`N_PA_Cycle.Status_Cycle`, which no row-level constraint can see.

## Pre-execution audit — r1

- Verified target `Web_HRIS` development.
- Neither table existed.
- `N_PA_Assignment` and `N_PA_Competency` were present.
- Change ID 076 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in six batches and the exact
  data-change.sql in one batch.
- Verified column counts 11/16, three enabled and trusted foreign keys, nine enabled and
  trusted CHECK constraints, and eight PK/index objects.
- 19 rollback behaviour tests, all rolled back, leaving every table empty. Each rejection
  asserts the error came from SQL Server and records which constraint fired.

  Payload:
  - accepted a raw bundle with a 64-character hash, not yet projected;
  - rejected a hash that is not 64 characters → `CK_PA_Assignment_Payload_Hash`;
  - rejected `Flag_Terpeta = 'Y'` with no mapper version
    → `CK_PA_Assignment_Payload_Terpeta`;
  - accepted the same row once the mapper version was supplied;
  - rejected a duplicate `Id_Ujian_Nilai_Akhir` across two assignments
    → `UX_PA_Assignment_Payload_Nilai_Akhir`;
  - accepted many payloads with a NULL key, confirming NULLs do not collide;
  - accepted two payloads for one assignment under different keys (the resend case);
  - rejected an orphan payload → `FK_PA_Assignment_Payload_Assignment`.

  Score:
  - accepted an item mapped to a competency;
  - accepted an unrecognised source code with a NULL competency;
  - accepted a 5-point item and a 100-point item inside one payload;
  - rejected `Skor_Maksimal = 0` → `CK_PA_Assignment_Score_Maksimal`;
  - rejected a score above its own maximum → `CK_PA_Assignment_Score_Batas`;
  - rejected `Bobot_Sumber = 150` → `CK_PA_Assignment_Score_Bobot`;
  - rejected a duplicate active source code in one payload
    → `UX_PA_Assignment_Score_Butir`;
  - accepted a re-map that retires the v1 row and inserts a v2 row beside it,
    leaving both;
  - rejected `Mapper_Version = 0` → `CK_PA_Assignment_Score_Mapper`;
  - rejected a non-existent competency → `FK_PA_Assignment_Score_Competency`;
  - rejected a score with no parent payload → `FK_PA_Assignment_Score_Payload`.
- **One test was green for the wrong reason on the first run** and the guard caught it:
  the duplicate-key test reported `UX_PA_Cycle_Kode` instead of the payload index,
  because the fixture inserted the same hardcoded cycle code twice inside one
  transaction. The fixture now generates a unique cycle code and asserts its own
  uniqueness before the test proceeds, and the whole suite was re-run.
- Re-ran the exact artifacts and confirmed a no-op; both tables empty and no fixture
  rows left behind in `N_PA_Cycle`, `N_PA_Participant`, or `N_PA_Assignment`.

## r2 development realignment — 2026-09-01

The target was verified in the same run as `team311.dyndns.info / Web_HRIS`
development. The ledger still showed SQL Tracker and production `PENDING`, and row
guards proved `N_PA_Assignment_Payload = 0` and `N_PA_Assignment_Score = 0`.

The realignment used only this change's own `drop.sql`. Its parsed DROP targets were
guarded to exactly `N_PA_Assignment_Score` and `N_PA_Assignment_Payload`. It then ran
the exact r2 `forward.sql`, exact `data-change.sql`, and exact r2 `forward.sql` once
more as the no-op proof.

Final verification: payload 11 columns, score 16 columns, three enabled/trusted foreign
keys, and 0/0 rows. The new payload foreign key points to
`N_PA_Assignment_Integration`; no object belonging to another change was dropped.

## User tasks

- Review the exact r2 SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **076 must follow 067 and 075**.
- Confirm production execution before production verification.
