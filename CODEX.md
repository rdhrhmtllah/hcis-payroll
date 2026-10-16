# Project Instructions

Read and follow `AGENTS.md` before making changes.

For project context, read `docs/README.md` and the relevant notes under `docs/`, plus any applicable files under `plans/` and `database/ddl/`.

The database safety rules, DDL workflow, SQL Tracker workflow, and status requirements in `AGENTS.md` are mandatory. Do not duplicate or override them here.

## Mandatory module structure

Before creating or moving any file, read `docs/architecture/module-registry.md` and `docs/architecture/module-structure-inventory.md`. The registry applies to this agent and any delegated coding process. New work must belong to `ESS`, `KPI`, `LMS`, `VISITOR`, or `SYSTEM`, followed by submodule, feature, and technical layer. These five are the active groups verified from `N_HRIS_Master_Grup_Fitur` on `Web_HRIS` (development) on 2026-08-18. There is no `HRIS` group and no `SOP` group; SOP is a submodule of `LMS`, and all nine SOP pages resolve to the `LMS` group through `N_HRIS_Grup_Fitur_Map`. Verify against the database again rather than trusting this dated snapshot. Do not place new business files in generic roots or role folders. Stop and ask if ownership is ambiguous.
