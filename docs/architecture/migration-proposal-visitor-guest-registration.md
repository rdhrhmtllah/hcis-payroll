# Migration Proposal: VISITOR Guest Registration

Status: `EXECUTED_AND_VERIFIED`

## Scope
Path alignment for the guest registration controller, route, page, and two local components. Registration and visit lifecycle remain one feature. Shared approval/effective services remain unchanged.

## Paths
```text
app/Http/Controllers/TransaksiTamu/TransaksiTamuController.php
→ app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php
routes/TransaksiTamu/TransaksiTamuWeb.php
→ routes/VISITOR/Guest/Visit/GuestRegistration.php
resources/js/Pages/VISITOR/modules/tamu/tamu.vue
→ resources/js/Pages/VISITOR/Guest/Visit/GuestRegistrationPage.vue
resources/js/Pages/VISITOR/modules/tamu/components/*
→ resources/js/Pages/VISITOR/Guest/Visit/components/*
```

## Preserved
`/transaksitamu*`, `transaksitamu-stats`, `transaksitamu-list`, `transaksitamu-master`, middleware `auth, check.jabatan:visitorTamuPage`, route behavior, frontend API calls, approval/effective services, database contracts, and disabled API stub.

## Verification
PHP syntax passed; Composer class loading passed; frontend build passed (4,401 modules); old active references absent; `git diff --check` passed. No dedicated visitor guest test was found.

## Rollback
`git revert <migration-commit>`; no database rollback required.
