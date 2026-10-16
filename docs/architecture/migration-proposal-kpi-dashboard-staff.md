# Migration Proposal: KPI Staff Performance Dashboard

Status: `EXECUTED_AND_VERIFIED`

## Scope
Path alignment only for the staff dashboard controller and page. The shared KPI route file remains in place; only the staff controller import and Inertia page key changed.

## Paths
```text
app/Http/Controllers/DashboardStaffController.php
→ app/Http/Controllers/KPI/Performance/Dashboard/StaffPerformanceDashboardController.php
resources/js/Pages/KPI/modules/dashboard/staff/dashboardStaff.vue
→ resources/js/Pages/KPI/Performance/Dashboard/Staff/StaffPerformanceDashboardPage.vue
```

## Preserved
`/dashboard-my-performance`, `/api/v1/dashboard-ceo/my-performance`, middleware `auth, check.jabatan:dashboardStaff`, route behavior, API payloads, cache keys, database reads, `KpiScoringService`, and CEO dashboard boundaries.

## Verification
PHP syntax passed; Composer class loading passed; frontend build passed (4,401 modules); old active references absent; `git diff --check` passed. Related `KpiLifecycleE2ETest`: 8 passed, 4 skipped, 1 failed due an existing HTTP 500 in the unrelated temporary draft bypass flow. No business logic was changed.

## Rollback
`git revert <migration-commit>`; no database rollback required.
