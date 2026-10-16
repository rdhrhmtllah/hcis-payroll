# DDL-2026-074 — Per-participant rule overrides

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-074-performance-appraisal-participant-overrides |
| Target | team311.dyndns.info:1433 / Web_HRIS development |
| Ownership | KPI / Performance Appraisal / Participant |
| Status | TRACKER_PENDING |
| Artifact revision | r1 |
| Dependencies | **DDL-2026-064, 067, 071, 072.** Must run after all of them. |
| Forward DDL | database/ddl/DDL-2026-074-performance-appraisal-participant-overrides/forward.sql |
| Manual reset | database/ddl/DDL-2026-074-performance-appraisal-participant-overrides/drop.sql |
| DML | database/dml/DDL-2026-074-performance-appraisal-participant-overrides/data-change.sql |
| Concept | docs/superpowers/specs/2026-08-30-performance-appraisal-cycle-participant-snapshot-plan.md |
| Forward checksum SHA-256 | 82abb139c48000e42c0045c92d2e97af536a5f68f29fb0c022c3248d9307fc0e |
| DML checksum SHA-256 | f0f49ebcf764f79a9adf60009ecb848701890d5be2c4c8e2195d91e16f0090c7 |

## Scope

Three sparse tables — `N_PA_Participant_Rater_Override`,
`N_PA_Participant_Pillar_Override`, `N_PA_Participant_Requirement_Override`. A row
exists **only** when one participant departs from the cycle's copy of the rule.
Normally all three are empty. No seed.

## Sparse, not materialised — and why that reversed an earlier decision

The third layer was first designed to materialise a full copy of the rule for every
participant: roughly 6,600 rows per cycle. That was dropped in favour of storing only
the exceptions, and the argument that settled it is worth keeping.

Full materialisation puts the risk in **writing**. Those 6,600 rows are correct only
if the seeding job is correct, and if it is not, wrong data is already persisted, looks
entirely plausible, and repairing it means a data migration for every affected cycle.

Sparse storage puts the risk in **reading** — a single resolver that decides "use the
override if one exists, otherwise the cycle copy". If that resolver is wrong, every
value is wrong at once and obviously so, and the fix is a code deploy that touches no
data at all.

A write bug must be repaired in data; a read bug is repaired in code. That asymmetry
decided it. The reading concern turned out to cut the other way too: because resolution
*must* pass through one place, there is exactly one thing to test rather than logic
scattered across every consumer. Reading 958 participants' rules also becomes a join to
a handful of snapshot rows per level instead of a scan over thousands.

A verification command had been proposed to detect drift between layer 2 and a
materialised layer 3. It is no longer needed: the sparse shape has no drift to detect,
because the default path stores nothing that could disagree.

## Physical decisions

- **Only columns that genuinely vary per person live here.** The rule is set at schema
  level, not per row: anything absent from these tables always comes from the cycle
  snapshot. `Organization_Relationship_Type_Id` and `Flag_Izinkan_Manual` were in the
  draft and were removed — the relationship type only drives *recommendations* while
  rater selection is manual per person anyway (K10), and permitting manual selection is
  a cycle-level setting. Leaving them in would have been worse than redundant: under
  replacement semantics an admin who edited only the weight would have blanked the
  candidate source with no error raised.
- **No inherit-by-NULL.** An earlier draft let an empty column mean "inherit from the
  snapshot". That is ambiguous where NULL already carries meaning — `Maks_Rater` NULL
  means *unbounded*, so an admin lifting a cap would write NULL and the resolver would
  read it as "inherit". Fixing the column split at schema level removes the ambiguity
  entirely.
- **Three kinds of deviation, no kind column.** Changed, added, and removed are all
  readable from two columns that already exist: an empty snapshot trail means the
  category was added, `Flag_Dipakai = 'T'` means it was removed, anything else is a
  change. No override-type master, and no column that can contradict the row it sits on.
- **`Flag_Dipakai`, not `Flag_Berlaku`.** The first name never said what applied. The
  row now reads on its own: category BAWAHAN with `Flag_Dipakai = 'T'` means
  subordinates are not used for this person.
- **`Alasan_Perubahan` is NOT NULL.** A row here exists only because something deviates,
  so a row without a reason has no meaning at all. This differs from
  `N_PA_Participant.Alasan_Dikecualikan`, which is genuinely conditional. The column
  name reuses the vocabulary already established on
  `N_PA_Cycle_Pillar_Weight_Snapshot`.
- **A conditional CHECK, deviating from earlier practice.**
  `CHECK (Flag_Dipakai = 'T' OR (Bobot_Persen IS NOT NULL AND Min_Rater IS NOT NULL))`
  is enforced in the database. Twice before, conditional-required rules were left to the
  application because the CHECK would have hardcoded a business literal such as
  `'DIKECUALIKAN'`. This one is different in kind: the literal is `'Y'`/`'T'` from the
  project's own flag convention, not an evolving business list.
- **`Bobot_Dalam_Kategori` is not here.** It is the weight of one rater *within* a
  category for one person and belongs on `N_PA_Assignment` (K18). These tables hold the
  weight *between* categories.

## What is deliberately not enforced

That the resolved weights across all categories total 100 for a participant spans the
snapshot and the override together — an application gate, as everywhere else in this
module.

Nothing stops an override from being written after the cycle is locked. Whether that is
allowed depends on `N_PA_Cycle.Status_Cycle`, which no row-level constraint can see.

## The resolver is the contract

`PaParticipantRuleResolver` is the only place permitted to combine a cycle snapshot with
these overrides. **No other code may read the cycle snapshot tables for per-person
purposes.** That rule is what concentrates the read risk into one testable place; break
it and the sparse design loses its main advantage.

## Pre-execution audit

- Verified target `Web_HRIS` development.
- None of the three tables existed.
- `N_PA_Participant`, both master tables, and both cycle snapshot tables were present.
- Change ID 074 was unused in the repository and in `N_SYSTEM_Database_Change`.

## Agent completed

- Created and executed the exact forward.sql in seven batches and the exact
  data-change.sql in one batch.
- Verified column counts 15/10/12, seven enabled and trusted foreign keys, eight enabled
  and trusted CHECK constraints, and six PK/index objects.
- Rollback behaviour tests against a real employee, all rolled back and leaving every
  table empty:
  - accepted removing the BAWAHAN category with no values supplied at all;
  - rejected a row marked in use whose weight and minimum were left empty;
  - accepted changing the weight to 30 with a minimum of 1 and no upper bound;
  - rejected a row with no `Alasan_Perubahan`;
  - rejected a maximum below the minimum;
  - rejected two active overrides for the same participant and category;
  - accepted a 0/100 pillar split for someone with no KPI;
  - rejected a pillar split of 70 and 40;
  - rejected two pillar overrides for one participant;
  - accepted removing the MANAJERIAL competency type for one participant;
  - rejected a competency type marked in use with `Flag_Wajib` left empty.
- Re-ran the exact artifacts and confirmed a no-op.

## User tasks

- Review the exact SQL and checksums.
- Record the exact tested artifacts and checksums in SQL Tracker.
- Execute production manually — **074 must follow 064, 067, 071, and 072**.
- Confirm production execution before production verification.
