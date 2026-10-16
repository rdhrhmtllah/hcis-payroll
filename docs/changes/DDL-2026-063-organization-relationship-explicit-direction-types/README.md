# DDL-2026-063 — Explicit Organization Relationship Types

## Change record

| Field | Value |
|---|---|
| Change ID | DDL-2026-063-organization-relationship-explicit-direction-types |
| Target database | Web_HRIS development |
| Module ownership | ESS / Organization / Relationship |
| Database basis | Id_Grup_Fitur=1 (ESS), page addKaryawanTeam, verified 2026-08-30 |
| Status | TRACKER_PENDING |
| Dependencies | DDL-2026-061, then DDL-2026-062 |
| Forward DDL | database/ddl/DDL-2026-063-organization-relationship-explicit-direction-types/forward.sql |
| Manual rollback | database/ddl/DDL-2026-063-organization-relationship-explicit-direction-types/drop.sql |
| DML | database/dml/DDL-2026-063-organization-relationship-explicit-direction-types/data-change.sql |
| Table docs | docs/database/tables/hris/n-hris-organization-relationship-type.md; docs/database/tables/hris/n-hris-organization-relationship.md |
| Concept | docs/superpowers/specs/2026-08-30-organization-relationship-type-semantics-concept.md |
| Forward checksum SHA-256 | a903b8f659dca4e5defeef1da9582cc9a66585fb57e55fe7960953f21c7c732b |
| DML checksum SHA-256 | ca002bcbad6e9d1dc7f3343d96ae8f82f274621ed89d38e921fcc45ae12f8d0d |

## Scope

- Rename physical Flag_Hubungan_Dua_Arah to Flag_Relasi_Setara.
- Rename its default constraint for readable as-built metadata.
- Seed or normalize active ATASAN, BAWAHAN, and PEER idempotently.
- Keep four legacy types inactive without deleting rows.
- Register the change in N_SYSTEM_Database_Change.

No fact row, context column, PA table, employee master, or production database is changed.

## Audit evidence before execution

- Connection verified as team311.dyndns.info:1433 / Web_HRIS.
- Master has the old char(1) NOT NULL flag and no new-name column.
- Organization Relationship has 0 rows.
- Type, subject, and related FKs are enabled and trusted.
- Repository and SQL module searches found no application consumer of the table or old flag name.
- Active types are ATASAN and PEER; BAWAHAN is absent.
- DDL-2026-061 and 062 are development DONE / SQL Tracker PENDING.

## Pairing decision

This change does not add a paired-mirror constraint. SQL Server CHECK constraints cannot safely require a matching inverse row, and the existing filtered primary index is keyed by type and subject. Copying Flag_Utama=Y to BAWAHAN would incorrectly limit one manager to one open-ended primary subordinate.

Before fact writes begin, the application design must decide whether BAWAHAN rows are persisted mirrors or independently maintained explicit facts, how pairs are identified and written atomically, and how primary is represented on the BAWAHAN side. PEER reverse-duplicate and hierarchy-cycle checks also remain transactional application validation.

## Expected DML scope

On the audited first development run:

- N_HRIS_Organization_Relationship_Type: insert BAWAHAN 1 row; update ATASAN up to 1 and PEER up to 1; legacy normalization up to 4 only if drift exists.
- N_SYSTEM_Database_Change: insert 1 registry row.
- N_HRIS_Organization_Relationship: 0 rows affected.

Rerun must affect no business row when values already match.

## Agent completed

- Verified target as team311.dyndns.info:1433 / Web_HRIS development.
- Verified module basis ESS Id_Grup_Fitur=1 and active page mapping addKaryawanTeam on 2026-08-30.
- Audited 062 checksum, live schema, 0 fact rows, active seeds, trusted FKs/checks, indexes, registry, repository consumers, and SQL module consumers.
- Executed exact forward.sql in 4 batches and exact data-change.sql in 1 batch.
- Renamed the physical column and default constraint to Flag_Relasi_Setara / DF_HRIS_Org_Rel_Type_Relasi_Setara.
- Verified active ATASAN T/Y, BAWAHAN T/Y, and PEER Y/T; four legacy rows remain inactive and were not deleted.
- Verified all three fact FKs and all three CHECK constraints remain enabled/trusted; fact row count remains 0.
- Verified registry DONE/PENDING/PENDING/DEV_DONE.
- Exact rerun changed no type timestamp/count and created no duplicate registry row.
- Transaction rollback validation restored the pre-execution state completely.
- Rollback behavior tests: duplicate open primary ATASAN rejected; reverse PEER duplicate currently accepted; second primary BAWAHAN mirror for the same manager rejected by the existing filtered index. All test rows rolled back and fact count returned to 0.
- Executable artifact destructive scan, deferred-context scope scan, content checks, and git diff --check passed.
- DDL-2026-062 forward/DML checksums remain unchanged.

## User tasks

- Review the forward DDL/DML and pairing boundary.
- Record the exact tested SQL and checksums in SQL Tracker.
- Execute production manually in dependency order 061, 062, 063.
- Confirm production execution before production verification can be marked complete.
- Before any relationship fact writer is released, decide BAWAHAN-side primary semantics, pair identity/atomicity, PEER canonical duplicate validation, and hierarchy-cycle validation.
