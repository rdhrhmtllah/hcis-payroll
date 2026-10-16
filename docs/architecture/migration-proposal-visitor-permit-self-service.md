# Migration Proposal: VISITOR Permit Self-Service

Status: `EXECUTED_AND_VERIFIED`

## Scope
Path alignment for the self-service permit controller, route, page, local components, and existing storage test namespace. Approval, verification, reporting, query, and effective projection services remain shared.

## Paths
```text
app/Http/Controllers/TransaksiPermit/TransaksiPermitController.php
→ app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php
routes/TransaksiPermit/TransaksiPermitWeb.php
→ routes/VISITOR/Permit/VehiclePermit/VehiclePermit.php
resources/js/Pages/VISITOR/modules/permit/permit.vue
→ resources/js/Pages/VISITOR/Permit/VehiclePermit/VehiclePermitPage.vue
resources/js/Pages/VISITOR/modules/permit/components/*
→ resources/js/Pages/VISITOR/Permit/VehiclePermit/components/*
```

## Preserved
`/transaksipermit*`, middleware `auth, check.jabatan:visitorPermitPage`, route/API behavior, GCS storage behavior, shared Visitor services, related approval/verification/reporting callers, disabled API stub, and database contracts.

## Verification
PHP syntax passed; Composer class loading passed; `TransaksiPermitDocumentStorageTest` passed (1 test, 3 assertions); frontend build passed (4,401 modules); old active references absent; `git diff --check` passed.

## Rollback
`git revert <migration-commit>`; no database rollback required.
