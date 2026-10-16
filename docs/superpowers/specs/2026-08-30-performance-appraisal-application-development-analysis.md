# Performance Appraisal Application Development Analysis

> **Status:** analysis only after `DDL-2026-064`; no application code, route,
> menu, permission, migration, DDL, DML, SQL Tracker, or production change is
> included in this document.

## 1. Outcome and recommended boundary

The first application increment should manage the **PA policy blueprint** only:
policy header, rater-category rules, versioning, activation, and a read-only
candidate preview. It must not create appraisal cycles, participant assignments,
ratings, or scores yet because no cycle-owned snapshot schema has been approved.

The owning boundary is:

```text
Module: KPI (Id_Grup_Fitur = 2, Kode_Grup_Fitur = KPI)
Submodule: Performance Appraisal
Feature: Policy
Environment verified: Web_HRIS development
Verification date: 2026-08-30
```

The database currently maps `MasterPerformance` to KPI and `/performance-kpi`,
but the implementation behind that route manages legacy table
`KPI_Master_Performance` and displays **Performance Range**. It is not a PA
policy page. Reusing it would mix two unrelated business meanings and one
permission key. The recommended next database change is therefore a new KPI page
mapping, provisionally named `paPolicyPage`, with a new route such as
`/performance-appraisal/policies`. The final `Jenis_page`, menu label, header,
sort order, and initial access-copy source need user review before any DML.

## 2. Grounded current state

### Database

`DDL-2026-064-performance-appraisal-policy-foundation` is executed in development:

- `N_PA_Rater_Policy`: versioned header scoped to `HRIS_Level`;
- `N_PA_Rater_Category`: active seeded categories `ATASAN`, `BAWAHAN`,
  `PEER`, and `SELF`;
- `N_PA_Rater_Policy_Detail`: category weight, candidate relationship type,
  rater-count rules, and manual-selection flag;
- all five foreign keys are enabled and trusted;
- there are no policy or detail sample rows;
- there is no `Metode_Agregasi` or `Metode_Resolusi` column.

Naming: `N_PA_Rater_Policy`, `N_PA_Rater_Category`, and `N_PA_Rater_Policy_Detail` are both
the documented target and the physical as-built names. DDL-2026-064 was amended
in place to revision r2 while still `TRACKER_PENDING`, so no rename change exists
and no transition mapping is needed. Models bind directly to these names.

The organization source is also available in development:

- `N_HRIS_Organization_Relationship_Type` has active explicit types `ATASAN`,
  `BAWAHAN`, and `PEER`;
- `N_HRIS_Organization_Relationship` is effective-dated and references the type
  primary key;
- the organization fact table was empty at the last verification;
- paired `ATASAN`/`BAWAHAN` fact writes and their `Flag_Utama` semantics are not
  yet guaranteed by a writer or schema rule.

### Existing application

- `routes/KPI/MasterData/Target/TargetKpi.php` exposes `/performance-kpi` under
  `check.jabatan:MasterTarget`.
- `PerformanceKpiController` reads and writes `KPI_Master_Performance` directly.
- `masterPerformance.vue` is a legacy score-range screen.
- KPI code already uses `Auth::user()->Id_Users`, Hashids, `ResponseHelper`,
  Inertia, `check.jabatan`, and module-based route/controller/page folders.
- Newer repository examples use page-local API modules that normalize
  `error_code` and `request_id`, explicit loading/error/empty states, thin
  controllers, and services for transactional business rules.

## 3. Proposed application structure

The following are proposed paths, not files created by this analysis:

| Layer | Proposed path | Responsibility |
|---|---|---|
| Route | `routes/KPI/Performance/Appraisal/Policy/PaPolicy.php` | Page and API endpoints behind the new page key. |
| Controller | `app/Http/Controllers/KPI/Performance/Appraisal/Policy/PaPolicyController.php` | HTTP orchestration and stable response codes only. |
| Request | `app/Http/Requests/KPI/Performance/Appraisal/Policy/StorePaPolicyRequest.php` | Validate create-draft payload shape. |
| Request | `app/Http/Requests/KPI/Performance/Appraisal/Policy/UpdatePaPolicyRequest.php` | Validate editable draft payload. |
| Request | `app/Http/Requests/KPI/Performance/Appraisal/Policy/ActivatePaPolicyRequest.php` | Validate activation intent/version token. |
| Model | `app/Models/KPI/Performance/Appraisal/Policy/PaPolicy.php` | Header mapping and relations. |
| Model | `app/Models/KPI/Performance/Appraisal/Policy/PaRaterCategory.php` | Category master read model. |
| Model | `app/Models/KPI/Performance/Appraisal/Policy/PaPolicyDetail.php` | Child-rule mapping and relations. |
| Service | `app/Services/KPI/Performance/Appraisal/Policy/PaPolicyQueryService.php` | Paginated list, detail assembly, options, version history. |
| Service | `app/Services/KPI/Performance/Appraisal/Policy/PaPolicyCommandService.php` | Create/update/version/deactivate transactions. |
| Service | `app/Services/KPI/Performance/Appraisal/Policy/PaPolicyActivationService.php` | Lock and validate a complete immutable version before activation. |
| Service | `app/Services/KPI/Performance/Appraisal/Rater/PaRaterCandidateResolver.php` | Resolve and explain candidate preview from organization relationships. |
| Page | `resources/js/Pages/KPI/Performance/Appraisal/Policy/PaPolicyPage.vue` | Policy list and lifecycle actions. |
| Component | `resources/js/Pages/KPI/Performance/Appraisal/Policy/Components/PaPolicyEditor.vue` | Header and detail editor. |
| Component | `resources/js/Pages/KPI/Performance/Appraisal/Policy/Components/PaPolicyCandidatePreview.vue` | Transparent resolver preview and shortage warnings. |
| API | `resources/js/Pages/KPI/Performance/Appraisal/Policy/api/paPolicyApi.js` | Axios boundary and normalized errors. |

A dedicated repository layer is **not recommended for phase one**. This repo's
established pattern puts query-builder persistence in focused services, and it
does not have a general repository abstraction. Adding interfaces plus concrete
repositories now would create ceremony without a second persistence backend.
If cycle scoring later needs large reusable snapshot queries, a read repository
can be introduced then inside the same feature boundary.

## 4. Proposed routes and API contract

All endpoints require `auth` and the newly registered KPI page key. Mutation
authorization must also be checked server-side; sidebar visibility is not an
authorization decision.

| Method | Path | Purpose |
|---|---|---|
| GET | `/performance-appraisal/policies` | Render the Inertia page. |
| GET | `/api/v1/performance-appraisal/policies` | Filtered/paginated policy list. |
| GET | `/api/v1/performance-appraisal/policies/options` | Active levels, categories, and organization types. |
| GET | `/api/v1/performance-appraisal/policies/{id}` | Header, active details, and version lineage. |
| POST | `/api/v1/performance-appraisal/policies` | Create one DRAFT with details atomically. |
| PUT | `/api/v1/performance-appraisal/policies/{id}` | Update an existing DRAFT only. |
| POST | `/api/v1/performance-appraisal/policies/{id}/new-version` | Copy an existing policy into a new DRAFT version. |
| POST | `/api/v1/performance-appraisal/policies/{id}/activate` | Validate and activate atomically. |
| POST | `/api/v1/performance-appraisal/policies/{id}/deactivate` | End administrative use without deleting history. |
| POST | `/api/v1/performance-appraisal/policies/{id}/candidate-preview` | Resolve candidates for selected sample employees without persisting assignment. |

IDs exposed to the browser should follow the existing custom Hashids convention.
Responses should use `ResponseHelper`; errors should provide stable PA-specific
`error_code` values and the request ID already supported by the frontend error
boundary. Expected status codes are 401/403, 404, 409 for lifecycle/concurrency
conflict, 422 for business validation, and 500 with no database detail leakage.

## 5. CRUD and lifecycle

### Create and update draft

One transaction creates the header and all details. The service resolves category
and relationship codes to current active IDs; it does not trust IDs alone from
the browser. Draft updates use a locked header row and a complete detail payload,
then upsert active rows and mark removed draft details inactive. Physical delete
is unnecessary.

The initial application status contract should be kept deliberately small:

```text
DRAFT -> ACTIVE -> INACTIVE
   |        |
   +-- edit only in DRAFT
   +-- new version copies from DRAFT/ACTIVE/INACTIVE into a new DRAFT
```

The exact allowed status vocabulary still needs approval because the database
intentionally does not use an enum CHECK. `ARCHIVED` should not be added until a
different business behavior from `INACTIVE` is defined.

### Versioning

- `Kode_Policy` identifies the policy family; `(Kode_Policy, Versi)` is unique.
- A new version increments the latest version while holding an application lock
  or a database transaction lock to prevent two users creating the same version.
- `PA_Rater_Policy_Sumber_Id` points to the copied version.
- An ACTIVE version is immutable. Changes require a new DRAFT version.
- Activation should reject overlapping active periods for the same HRIS level
  unless the user explicitly approves multi-policy precedence rules.

### Category master

The four core codes are system vocabulary, not ordinary free-text CRUD. The first
UI should expose them read-only as options. Renaming display text or deactivating a
core code affects all policies and should be a separately authorized governance
operation; hard delete is not supported.

## 6. Activation transaction and validation

`PaPolicyActivationService` should execute one database transaction:

1. lock the policy header and its active details (`UPDLOCK`/Eloquent equivalent);
2. require current status `DRAFT` and active header;
3. verify level, date range, source version, and audit actor;
4. require at least one active detail and exactly one detail per category;
5. require active-detail `Bobot_Persen` total to equal `100.00` exactly at the
   database decimal scale;
6. validate `Min_Rater`, nullable `Maks_Rater`, and nullable
   `Min_Rater_Selesai` again as business errors, despite database CHECKs;
7. require `SELF` to have a NULL organization type and the three other categories
   to reference the matching explicit type code;
8. require all referenced category/type rows active;
9. optionally run candidate-readiness diagnostics, but do not make incomplete
   organization data silently change the policy;
10. set `Status_Policy=ACTIVE`, `Activated_At`, `Activated_By`, and update audit
    fields atomically.

The database deliberately cannot enforce aggregate total weight with a row CHECK.
This transaction is the authoritative activation gate. A second activation of an
already changed version returns a 409 conflict rather than silently succeeding.

## 7. Fixed normalized-average scoring

There is no configurable aggregation mode. For category `c` with valid completed
rater scores `s1..sn`:

```text
CategoryAverage(c) = (s1 + ... + sn) / n
WeightedCategory(c) = CategoryAverage(c) * Bobot_Persen(c) / 100
FinalScore = sum(WeightedCategory(c))
```

Every valid rater inside one category has equal internal weight. For two supervisor
scores 80 and 70, the category average is 75 before category weight. The future
scoring service must define one decimal precision and one final rounding point;
it must not average already rounded intermediate values. Ratings excluded as
invalid/cancelled are omitted from both numerator and denominator, while completion
eligibility follows `Min_Rater_Selesai`.

## 8. Candidate resolver

Resolver input is participant identity, policy detail, and an `as_of_date` from
the future cycle. It reads active/effective relationship rows and returns candidates
plus transparent reason data; it does not create assignments in this phase.

| PA category | Resolution rule |
|---|---|
| `SELF` | Candidate is the participant; organization type must be NULL. |
| `ATASAN` | Subject is participant, related employee is candidate, explicit type code `ATASAN`. |
| `BAWAHAN` | Subject is participant, related employee is candidate, explicit type code `BAWAHAN`. |
| `PEER` | Subject is participant, related employee is candidate, explicit type code `PEER`; deduplicate endpoint pairs. |

Common filters are `Flag_Aktif=Y`, `Berlaku_Dari <= as_of_date`, and
`Berlaku_Sampai IS NULL OR Berlaku_Sampai >= as_of_date`. Order candidates by
`Flag_Utama` first and then `Prioritas`; enforce `Maks_Rater` only through an
explicit deterministic selection or admin choice. `Flag_Izinkan_Manual=Y` permits
an authorized override but must record who selected whom and why in the future
assignment snapshot.

The resolver must not infer an employee's actual supervisor from HRIS level,
division, or job title. Those masters enrich labels and may support policy scope,
but the actual candidate edge comes from Organization Relationship.

### Current organization-data risk

Candidate preview will be empty until relationship facts are populated. More
importantly, the organization design expects mirrored `ATASAN`/`BAWAHAN` rows but
the write contract is not implemented, and the existing primary-open index can
conflict if every mirrored BAWAHAN row is marked primary. Before operational PA
assignment, the organization owner must approve atomic pair-write and primary-side
semantics. The PA resolver should consume explicit rows only and must not quietly
fall back to reverse lookup.

## 9. Cycle and snapshot boundary

The three 064 tables are mutable blueprints. A future cycle must select one policy
version and copy, at minimum:

- policy identity, version, target level, effective dates, and status;
- category code/name, weight, rater limits, manual flag, and display order;
- selected participant identity and organization state relevant to audit;
- every resolved/approved rater assignment, category, source relationship row,
  resolution timestamp, manual reason, and assignment status;
- scoring precision/rule version.

After cycle lock, later edits to a blueprint or Organization Relationship must not
change assignments or historical scores. Candidate preview in the policy screen is
therefore diagnostic only. Creating cycle, participant, assignment, response, and
score tables is a separate design and DDL checkpoint.

## 10. Authorization and audit

- Register a new KPI-owned page key in `N_HRIS_Menus` and
  `N_HRIS_Grup_Fitur_Map` before creating business files.
- Decide initial `HRIS_Page_Access` grants explicitly; do not inherit
  `MasterTarget` merely because the legacy route currently does.
- At minimum distinguish view/edit-draft from activate/deactivate in backend
  policy. If the current page-key system cannot express action permissions,
  activation should be restricted through the existing functional-role/resource
  authorization service rather than frontend button hiding.
- Use authenticated `KPI_Users.Id_Users` for Created/Updated/Activated actor
  columns. System jobs use NULL only when their actor/provenance is logged elsewhere.
- Record lifecycle transitions and manual candidate overrides in an audit log in
  the future assignment phase; do not rely only on mutable `Updated_By`.
- Never expose raw SQL errors, employee data outside authorized scope, or unrestricted
  cross-company candidates. The precise company/employee target scope must be
  resolved through the existing authorization scope services.

## 11. Frontend behavior

The page should use the current Vue 3 Composition API and shared HCIS components.
Recommended layout:

- policy list with level/status/period/version filters;
- editor with header and one card/row per category;
- live total-weight indicator, but server remains authoritative;
- candidate preview showing source type, primary/secondary marker, period, and
  shortages against min/max rules;
- explicit loading, initial-load failure, empty, saving, conflict, validation,
  success, and retry states;
- desktop sticky actions and mobile floating action dock; no horizontal overflow;
- full-screen mobile editor with equal cancel/save actions and accessible labels.

The API module should normalize `{message, errors, error_code, request_id}` and
preserve the request ID in error UI. Mutation buttons are disabled while pending;
retries must not create a duplicate version.

## 12. Test plan

### Focused unit tests

- status transition matrix and ACTIVE immutability;
- version increment and concurrency conflict;
- total weight exactly 100.00;
- SELF/null and category/type matching;
- nullable max and completion-threshold semantics;
- fixed normalized average, excluded ratings, precision, and rounding;
- candidate resolver for ATASAN/BAWAHAN/PEER/SELF, period boundaries,
  primary order, deduplication, and no reverse fallback.

### Feature/API tests

- authenticated page/API access and unauthorized 401/403;
- list/detail/create/update/new-version/activate/deactivate flows;
- validation 422, missing 404, stale lifecycle/version 409;
- active version cannot be edited;
- activation rolls back if any detail or audit update fails;
- Hashids invalid/tampered IDs;
- API error contains `error_code` and `request_id`.

### SQL Server integration tests

- trusted FK behavior for level/category/organization type;
- duplicate active category and `(Kode_Policy, Versi)` conflict;
- concurrent version creation and concurrent activation locking;
- transaction rollback leaves header/detail/audit consistent;
- resolver query uses composite employee identity and effective-period indexes.

### Frontend and browser tests

- API module normalization and editor validation;
- total weight indicator and candidate shortage state;
- loading/empty/error/retry/conflict behavior;
- keyboard navigation, labels, focus restoration, and contrast;
- desktop and mobile widths, modal action layout, and zero horizontal overflow.

Focused checks run during implementation. Once behavior is approved, the user
chooses `feature`, `impacted`, or `full` regression according to the repository
testing workflow.

## 13. Rollout plan

1. **Decision gate:** approve page key/menu/access model, lifecycle vocabulary,
   overlap rule, organization pair-write semantics, and cycle boundary.
2. **Registry change:** create a separate idempotent DML change for menu, KPI map,
   and approved access grants; execute development only and verify ownership.
3. **Backend scaffold:** models, requests, services, thin controller, and routes;
   keep the new menu hidden or inaccessible until focused tests pass.
4. **Draft CRUD:** header/detail editor and read-only category/type options.
5. **Activation:** transactional validation, concurrency handling, and audit.
6. **Resolver preview:** read-only organization candidate diagnostics.
7. **Application UAT:** seed representative development relationship data through
   an approved writer, not ad-hoc production-like SQL.
8. **Cycle design:** separately approve snapshot/assignment/scoring schema before
   operational appraisal execution.

A generic environment feature flag is not currently established for this KPI
feature. The safer rollout control is the database-driven menu/page mapping plus
server authorization; introducing a new global feature-flag mechanism would be
an unrelated architecture change.

## 14. Decisions required before implementation

1. Approve the new page key, URL, menu label/header, sort order, and initial access
   source. Recommendation: new `paPolicyPage`; do not reuse `MasterPerformance`.
2. Approve lifecycle values. Recommendation: `DRAFT`, `ACTIVE`, `INACTIVE` only.
3. Decide whether active policy periods for the same HRIS level may overlap.
   Recommendation: reject overlap until an explicit precedence rule exists.
4. Decide whether one policy must contain all four categories or may omit a
   zero-weight category. Recommendation: omit unused categories; every stored active
   detail should have positive weight and totals must be 100.00.
5. Approve organization paired-write and `Flag_Utama` semantics, then populate
   development relationships for resolver UAT.
6. Define manual-rater authorization and mandatory override reason.
7. Approve rating scale, decimal precision, and final rounding rule before scoring.
8. Approve cycle/snapshot entities before assignment implementation.
9. Confirm company and employee visibility scope for policy administrators.

These decisions do not block reviewing or scaffolding the policy CRUD design, but
items 1 and 2 are required before creating routes/files, and items 5 through 8 are
required before operational appraisal assignment and scoring.
