# Migration Proposal: KPI Form Parameter

Status: `EXECUTED_AND_VERIFIED`

## Scope
Grouped the combined individual/team Form KPI controller, compatibility Team KPI controller, and all local form/team pages under one KPI Performance/Parameter boundary. No controller decomposition or behavior change was performed.

## Paths
```text
app/Http/Controllers/FormKpiController.php
→ app/Http/Controllers/KPI/Performance/Parameter/FormKpiController.php
app/Http/Controllers/TeamKpiController.php
→ app/Http/Controllers/KPI/Performance/Parameter/TeamKpiController.php
resources/js/Pages/KPI/modules/formKPI/*
→ resources/js/Pages/KPI/Performance/Parameter/FormKpi/*
```

## Preserved
All `/form-kpi*` and `/api/v1/form-kpi/*` URLs, middleware, route behavior, team/individual payloads, approval/notification/homepage links, KPI services, and database contracts.

## Verification
Composer autoload regenerated; PHP syntax and class loading passed; frontend build passed (4,398 modules); focused KPI suite passed (35 tests, 89 assertions).

## Rollback
`git revert <migration-commit>`; no database rollback required.
