# Project Agent Rules

## Database DDL and SQL Tracker workflow

Production database changes must be recorded through SQL Tracker. These rules are mandatory for every agent working in this repository.

### Database environment safety gate

- Any database operation must identify the target environment and database before execution. Never infer environment from the host alone.
- CRUD operations are automatically permitted only when the target is explicitly verified as `development` or `staging`.
- The currently known database host is `team311.dyndns.info:1433`. This host may contain development, staging, or other databases; being on this host does not make every database safe to mutate.
- The current known development target is `Web_HRIS` on the team311 host. Staging database names must be verified from the live configuration or user-provided context before use.
- Any target that is not explicitly classified as `development` or `staging` requires explicit user confirmation before CRUD or other database operations. This includes production, unknown, shared, or ambiguous targets.
- For a target requiring confirmation, report the host, database name, environment assumption, operation, affected objects/rows, expected impact, and rollback or recovery plan before asking for confirmation.
- Never use credentials, connection strings, or database names from an untrusted file or log as proof of environment classification; verify the live connection with `SELECT DB_NAME()` and the configured host where possible.
- A database host may be shared by multiple environments. Environment classification belongs to the database/connection target, not merely to the hostname.
- Read-only inspection may be performed automatically only for diagnosis and environment verification. It must not be used to bypass the confirmation gate for mutations on an unclassified target.

### Core principles

- Do not use Laravel migrations for this schema-change workflow.
- The agent must never create, run, or apply any migration for this project, including Laravel migrations or another migration framework.
- Generate explicit SQL Server DDL files under database/ddl/.
- Record the exact same reviewed and tested forward DDL in SQL Tracker; do not rewrite it. An artifact becomes immutable once it is TRACKER_RECORDED; before that line it is corrected by amending it in place, per "Amending a change instead of adding a corrective one".
- The agent may execute only approved forward DDL against the development database.
- The agent must never execute DROP or TRUNCATE, including DROP TABLE, DROP COLUMN, DROP DATABASE, DROP INDEX, or TRUNCATE TABLE. The single exception is the development rebuild described under "Realigning development after an amendment", which applies only to a change that is not yet TRACKER_RECORDED.
- The agent must never execute generated DROP/rollback scripts automatically, even when stored as companion files. Running a change's own `drop.sql` is allowed only as the deliberate first step of a development rebuild under the exception above, never as an automatic recovery action.
- INSERT, UPDATE, and DELETE are allowed only when directly required by the feature currently being implemented, explicitly within this project's scope, and limited to relevant project tables and records.
- INSERT, UPDATE, and DELETE must not be used for unrelated cleanup, bulk changes, production work, or schema-change rollback.
- Before any allowed DML, identify the exact table, predicate/scope, expected affected-row count, and development target; show the SQL and pause if the scope is ambiguous.
- MERGE and destructive data operations remain prohibited unless a separate, explicit user instruction changes this rule.
- The agent must never execute DDL against production.
- A production change is not complete merely because DDL was generated, tested in development, or recorded in SQL Tracker.

### Required output for every schema change

Create a unique change ID and these artifacts:

database/ddl/<change-id>/forward.sql
database/ddl/<change-id>/drop.sql
database/dml/<change-id>/data-change.sql

The forward DDL is `forward.sql`; `drop.sql` is a manual cleanup/reset aid only and must not be run by the agent. DML, when required, is kept in the separate `database/dml/<change-id>/data-change.sql` file.

Every change record must state: change ID, target database, description, affected objects, all DDL/DML/documentation paths, forward-DDL checksum, current status, agent-completed work, and remaining user work.

### Required workflow statuses

DRAFT
REVIEW_REQUIRED
APPROVED
DEV_EXECUTION_PENDING
DEV_EXECUTED
DEV_FAILED
TRACKER_PENDING
TRACKER_RECORDED
PRODUCTION_PENDING
PRODUCTION_EXECUTED
VERIFICATION_PENDING
COMPLETED

Normal flow:
DRAFT -> REVIEW_REQUIRED -> APPROVED -> DEV_EXECUTION_PENDING -> DEV_EXECUTED -> TRACKER_PENDING -> TRACKER_RECORDED -> PRODUCTION_PENDING -> PRODUCTION_EXECUTED -> VERIFICATION_PENDING -> COMPLETED

Do not collapse these into one done status. PRODUCTION_EXECUTED may only be set after the user explicitly confirms execution from SQL Tracker, or after independently verified production results with user approval. Never infer it from development or SQL Tracker status.

### Amending a change instead of adding a corrective one

TRACKER_RECORDED is the freeze line. It is the point where an artifact stops being
a draft and becomes a promise: production is expected to run exactly that SQL, and
exactly that checksum has been handed over for review.

Before that line, a design error in a change is fixed by editing the change in
place. After that line, it is fixed by a new corrective change.

Amend in place when ALL of the following hold. Verify each one; do not assume:

- the change's status is DRAFT, REVIEW_REQUIRED, APPROVED, DEV_EXECUTION_PENDING,
  DEV_EXECUTED, DEV_FAILED, or TRACKER_PENDING;
- `sql_tracker` is PENDING in `database/ddl/status.json` and nothing for this
  change exists in SQL Tracker;
- `production` is PENDING and the change has never run against production;
- no other environment and no other party depends on the current artifact.

If any condition fails, or if you cannot verify one, do not amend. Create a new
change ID instead.

An amendment is not a silent rewrite. It must:

1. bump an `artifact_revision` (`r1`, `r2`, ...) in `database/ddl/status.json`;
2. record a revision row in `docs/changes/<change-id>/README.md` stating what
   changed and what triggered it;
3. recompute and replace every checksum in the README and the status ledger, and
   state plainly that the superseded checksums must not be recorded;
4. keep the change ID, folder name, and history of *what actually ran in
   development* factual — never rewrite the past into a claim that the earlier
   revision built the new shape;
5. re-run the amended artifact against development and prove it ends as a no-op,
   which is the evidence that development matches what the amendment would build
   from empty.

Do not create a corrective change ID for something the freeze line has not yet
locked. A rename chain such as "064 creates the wrong name, 065 renames it" makes
production carry a mistake it never had, and every corrective change adds an
artifact, a checksum, and an ordering constraint that must be maintained forever.

### Realigning development after an amendment

When development has already executed the earlier revision, its physical state
must be brought in line with the amended artifact.

- Prefer `sp_rename` and other non-destructive `ALTER` operations.
- **Development rebuild exception.** For a change that is still short of
  TRACKER_RECORDED, the agent MAY run the change's own `drop.sql` against the
  development database and rebuild from the amended `forward.sql` and
  `data-change.sql`. This is the standing instruction of 2026-08-30: a change not
  yet in SQL Tracker is still a draft, and rebuilding it in development is faster
  and cleaner than patching names one by one. It also produces a stronger result,
  because the database is then built by the artifact rather than by a sequence of
  corrections.
- The exception is narrow. Every one of these must hold, and each must be
  verified in the same run, not assumed:
  - the target is verified `development` (never staging, never production);
  - the change is short of TRACKER_RECORDED and has never run against production;
  - the tables hold no data beyond what `data-change.sql` re-creates. Guard this
    in the script itself: count the rows first and abort before dropping anything
    if the count is not what the artifact would rebuild;
  - only objects belonging to this change are dropped, using the change's own
    `drop.sql` — never an ad hoc `DROP` written for the occasion.
- Outside that exception, `DROP` and `TRUNCATE` remain prohibited for agent
  execution. In particular, a TRACKER_RECORDED change is never rebuilt this way.
- If a destructive step is needed and the exception does not apply, stop and hand
  it to the user; state exactly what must be dropped and why no non-destructive
  path exists.
- A realignment is a development-only correction. It is NOT an artifact: never
  place it under `database/ddl/` or `database/dml/`, because production runs only
  the amended artifact and must never run a realignment.
- Record the realignment SQL and its verification in
  `docs/changes/<change-id>/README.md` under a clearly separated heading so a
  reader cannot mistake it for part of the change.
- Verify afterwards that no object, index, module definition, or repository file
  still carries the superseded shape, and that the amended artifact re-runs as a
  no-op.

### Safety checks before execution

Before running DDL, inspect the target object and show the exact SQL. DROP, TRUNCATE, and migrations are always prohibited for agent execution. Pause if an ALTER removes or overwrites data. For feature-scoped INSERT, UPDATE, or DELETE, first identify the exact project table, predicate/scope, expected affected-row count, and development target; pause when any part is unclear. DROP statements may be generated in the companion file but never executed by the agent. Before recording in SQL Tracker, verify content and checksum are identical to the tested forward DDL; on failure, record failure and do not mark complete.

### End-of-task report

Always report two sections: Agent completed (files, development execution/verification, tracker recording) and User tasks (review/approval, manual SQL Tracker execution to production, production verification).

At the start of a new DDL task, check and report pending items in database/ddl/ or its status ledger before creating another change. When the task would correct a change that is still short of TRACKER_RECORDED, report that the change is amendable and amend it instead of allocating a new change ID.

### Secrets and environment

- Never print database passwords, access tokens, or full connection strings.
- Unset the unrelated global DATABASE_URL when running Laravel commands for this project.
- Keep production credentials out of repository files.


## Backend, frontend, and transaction standards

For new backend code, agents must follow:

- `docs/architecture/backend-coding-standard.md`
- `docs/architecture/transaction-and-cloud-tasks.md`

For new frontend code and scoped frontend changes, agents must follow:

- `docs/architecture/frontend-architecture.md`

For Git, task scope, testing, commit/push, deployment, reporting, and agent safety, agents must follow:

- `docs/architecture/agent-development-workflow.md`
- `docs/architecture/testing-and-quality-standard.md`

### Scope change control and development checkpoints

Agents must classify every new request that appears during an active feature as `in-scope`, `related change`, or `out-of-scope` before implementing it. Do not silently expand the active feature, add unrelated refactors, change shared components, alter global authorization, or modify unrelated database flow merely because it is convenient.

For a related or out-of-scope request, explain the affected files and boundaries, risk to the active flow, additional testing, time/scope impact, and recommendation. The default recommendation is to finish the active feature first. If the new work may disrupt shared boundaries, database, authorization, transactions, Outbox, providers, global layout, or test baseline, create a development checkpoint and use a separate branch/fase before starting it. Never use `git reset --hard`, `git clean -fd`, `git restore`, or `git stash` to move or discard work without explicit user approval.

At feature-complete, ask the developer whether behavior, validation, permission, UI, or edge cases still need changes. Do not prepare the complete testing package or run full regression until behavior is considered final. After preparing the relevant test package, explain the direct and impacted feature coverage and ask whether to run `feature`, `impacted`, or `full` testing. Focused checks may run during development, but full regression is not automatic for every edit.

The frontend standard includes Vue API choice, Page/API module/component boundaries, multi-device responsive architecture (desktop sticky vs. mobile floating glass dock, zero horizontal overflow, unified single-row headers, automatic card grid on mobile, full-screen mobile dialogs with 50/50 actions, and dual-pane mobile segmented tabs), safe error handling with `error_code` and `request_id`, UI lifecycle states, retry/idempotency behavior, responsive/accessibility requirements, testing, and browser verification. Legacy code that does not yet comply is not changed automatically.

## Release flow and branch guards

There are two long-lived branches, and they are two separate lanes that never meet. `master` is the ordinary lane: working branches (`feat/*`, `fix/*`, `chore/*`) open a pull request straight into it, and a merge deploys to the pre-production Cloud Run service, which is treated as production. `staging` is the lane for features deliberately held out of production; it deploys to a separate Cloud Run service and **nothing is ever merged out of it**, so it may be rebuilt from `master` at any time.

Open your pull request against `master` unless the change belongs to a held feature. Do not merge `staging` into a working branch to bring it up to date — that drags every held feature into your branch, and your `master` pull request is then rejected by the release gate. Update from `master` instead. Full rules, including the GitHub ruleset that must be configured by the repository owner, are in `docs/architecture/alur-rilis-dan-penjaga.md`.

Some features are deliberately held out of production. They are listed in `.github/gerbang-rilis.json`, and CI rejects any pull request that carries them into a branch outside their `boleh_di` list. The guard matches both the feature's own `paths` and its `penanda` strings on newly added lines, so a single provider line added to a shared file such as `config/app.php` is caught as well. Agents must not edit, disable, rename, or work around that manifest, the workflows in `.github/workflows/`, or the hooks in `.githooks/`; promoting a held feature is a separate pull request that the repository owner approves, and that approval is the release decision.

Run `npm run setup:git` once per clone to install the shared hooks, then mark each worktree with `git config --worktree hcis.target "master"` for ordinary work or `"staging"` for a worktree that carries a held feature. Plain `git config` writes to a `.git/config` shared by every worktree, so the `--worktree` flag is required; without it one marking silently changes all worktrees. An unmarked worktree is treated as the most restrictive.

Do not add new Laravel migrations. Schema changes are written as DDL files and executed manually through SQL Tracker, and CI rejects newly added files under `database/migrations/`. A change directory under `database/ddl/` requires `forward.sql` and `drop.sql`, one under `database/dml/` requires `data-change.sql`, and both require an entry in `database/ddl/status.json`. Once a change carries an SQL Tracker reference or a production status of `DONE`, its files are frozen: corrections must be written as a new change.

## Mandatory module structure for every AI agent

All AI agents working in this repository—including Hermes, Claude Code, Codex, OpenCode, delegated agents, and future tools—must follow `docs/architecture/module-registry.md` and `docs/architecture/module-structure-inventory.md`. These files are project rules, not optional suggestions.

The official module/feature registry is database-driven. Agents must read the current active records from `N_HRIS_Master_Grup_Fitur` and resolve page/feature ownership through `N_HRIS_Grup_Fitur_Map` before creating new backend, frontend, route, test, documentation, or database-related files. Role ownership/access scope must be checked through the existing role-functional/user relations when relevant.

Documentation and skills may contain examples or dated snapshots, but they are not authoritative and must never override the current database. If the database cannot be inspected or the mapping is ambiguous, stop and ask; do not infer ownership from a stale list, role name, or folder name.

New files must be placed under the verified owning module, then submodule, then feature. Do not create new business files directly under generic roots or under a role folder such as `Admin`, `User`, or `SuperAdmin`. Roles describe access; module ownership describes placement.

Before creating a file, the agent must state its module, submodule, feature, and layer. If ownership is ambiguous, stop and ask; do not use `Misc`, `General`, or an arbitrary root folder. Existing legacy files are not moved automatically. New work may use the target structure while legacy migration remains a separate reviewed task. If a feature does not fit the registry, the agent must ask whether it extends an existing module or requires a new module; it must not infer a new top-level module. A new module may be created only after explicit approval and after the registry, inventory, agent rules, and relevant backend/frontend/routes/tests documentation are updated. This applies equally to backend and frontend work.

Every agent must inspect the existing implementation and update the inventory/mapping documentation when a new module boundary or legacy mapping is discovered. Such documentation updates record a dated snapshot or mapping note only; they never replace the database master.

For a new feature, the agent must record the verified database basis in its task notes or change documentation: environment, master group identifier/code, page mapping (`Jenis_page`/URL), and the verification date. Do not copy a live database list into source code or treat a previous task note as current authority.

## Structural verification gates

Any change that moves, renames, or deletes files must pass the verification
scripts in `scripts/` before it is committed:

```text
composer dump-autoload -o --strict-psr
php scripts/verify-references.php --max=0
php scripts/verify-frontend-imports.php
php scripts/verify-inertia-pages.php
php scripts/route-snapshot.php FILE     # compare against a baseline taken first
php scripts/console-snapshot.php FILE   # compare against a baseline taken first
```

Take the snapshot baselines before making changes, not after. Every difference
must be intentional and explained in the commit message.

Do not raise `--max` to let a new finding through. Once the threshold rises the
gate can no longer separate old breakage from new breakage, which is exactly the
condition that allowed an entire feature's endpoints to fail undetected.

These gates prove that referenced classes load, routes keep their contract, and
page components exist. They do **not** prove behaviour is correct. Behaviour
verification is a separate step; see
`docs/architecture/testing-and-quality-standard.md`.

Never stage changes with `git add -A` or `git add .` in this repository. Add
files by explicit path. A blanket add has twice swept a Google Cloud service
account key into a commit here, once reaching `master`.

## Database schema conventions

These conventions apply to new tables and columns. Existing legacy tables are not renamed or refactored merely to conform; changes to legacy schema require a separate scoped task and review.

### Table names

- New tables use the format `N_<Modul>_<Fitur>`, with as many descriptive segments as needed.
- Use the module code/prefix verified from the current database master and the project naming convention.
- A new module prefix or a module code not present in the current master requires explicit user confirmation before use; do not infer it from documentation snapshots.
- Use singular, descriptive, continuous names; suffixes such as `Detail`, `Log`, `History`, `Mapping`, and `Setting` are allowed when they describe the table's purpose.
- Do not add a schema prefix such as `dbo.` to new table names unless the task explicitly requires it.
- Constraint and index names carry the same domain prefix as their table, without the `N_` marker: a table `N_PA_Policy` owns `PK_PA_Policy`, `FK_PA_Policy_Level`, `CK_PA_Policy_Versi`, `DF_PA_Policy_Aktif`, `IX_PA_Policy_Level_Period`, and `UX_PA_Policy_Kode_Versi`. Metadata names must not keep a prefix the table no longer uses.

### Column names and identifiers

- Use the project style with capitalized words separated by underscores, such as `Nama_Barang`, `Flag_Aktif`, and `Tanggal_Mulai`.
- Primary keys identify the entity: `Id_<Nama_Entitas>` with an appropriate integer identity type when applicable.
- Foreign key columns use `<Nama_Entitas>_Id`, such as `Dokumen_Id`.
- Common, unambiguous business abbreviations are allowed; do not invent ambiguous abbreviations.

### Flags and data types

- Boolean-like fields use `Flag_<Makna>` with `char(1)`, length 1, and values `Y`/`T`; do not use `Is_* bit` for new schema.
- A table may have multiple flags when each represents a distinct business condition. `Flag_Aktif` is the usual indicator that a row remains active or valid, but context determines whether additional flags are needed.
- Flag defaults follow meaning: for example, `Flag_Aktif` commonly defaults to `Y`, while `Flag_Sudah_Sync` or `Flag_Hapus_Akses` commonly defaults to `T`.
- Do not use enum or rigid database `CHECK` constraints to limit evolving business value lists; validate those values in the backend. Flag semantics must still be documented.
- Use `int` for ordinary identifiers, `bigint` only when scale requires it, `datetime2` for date-time values, and explicit precision/scale for decimals.
- Use `varchar` or `nvarchar` according to the data's character requirements, always with an explicit length unless a large-text type is genuinely required.
- Choose `NULL` or `NOT NULL` from the business context; do not apply either mechanically.

### Audit and relational design

- Add `Created_At`, `Created_By`, `Updated_At`, `Updated_By`, `Cancelled_At`, and `Cancelled_By` only when the table's lifecycle requires them.
- Audit actor columns use `varchar(100)` by default, but before DDL the agent must confirm whether the value is `Kode_Karyawan`, `UserID_Web`, `UserID_Absen`, another identifier, or a system/job actor, and identify its source table/system.
- Unique constraints, foreign key constraints, indexes, and cascade behavior are chosen from the business and query context; do not add them mechanically. Cascade is not the default for operational data.

### Documentation and DDL/DML separation

- Keep SQL files free of long business/documentation comments. Put human-readable table documentation under `docs/database/tables/<modul>/` and change documentation under `docs/changes/<change-id>/`.
- Keep execution artifacts separate: `database/ddl/<change-id>/` for forward/drop SQL and `database/dml/<change-id>/` for data changes. DDL and DML must not be mixed.
- Each new table requires documentation covering purpose, columns, flag meanings/defaults, nullability, audit actor source, relationships, constraints/index rationale, related controllers/models/services/jobs/commands/endpoints, data flow, change ID, and SQL paths.
- The agent must inspect the repository to identify actual application usage; it must not invent controller or endpoint references.
