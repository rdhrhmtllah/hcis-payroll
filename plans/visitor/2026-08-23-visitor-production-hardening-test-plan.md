# Visitor Production Hardening Test Plan

Risk: `HIGH` (authorization, PII, approval, shared database, external handoff).

## Direct feature coverage

- requester A cannot list, inspect, cancel, upload to, or mutate requester B's
  Permit/Tamu;
- pending cancellation is idempotent and rejected after approval processing;
- expired approval cannot be resumed;
- only the current approver can change checklist or decide a request;
- final Permit approval requires an active duration and atomically creates an
  effective row with calculated dates;
- transaction rollback leaves approval, header, and projection consistent;
- legacy mutation endpoints return 410;
- effective guest lifecycle is displayed consistently across history,
  verification, dashboard, and reporting;
- repeated submission with the same actor/idempotency key returns one
  transaction;
- GCS and metadata failures leave neither dangling metadata nor newly uploaded
  orphan files.

## Impacted coverage

- shared approval service current/future step behavior;
- transaction number generation;
- scheduler expiry;
- route and Inertia contracts;
- frontend import resolution and production build;
- SQL integrity queries for duplicate transactions, missing projections,
  validity gaps, and invalid lifecycle timestamps.

## Execution policy

Use focused checks during implementation. At feature-complete, confirm behavior
before preparing the complete package and asking whether to run `feature`,
`impacted`, or `full` testing.

## Execution result (2026-08-23)

- PASS: full backend Unit suite, 201 tests and 600 assertions.
- PASS: Visitor focused Unit/Feature/Integration package, 19 tests and 34 assertions.
- PASS: development database contract verifies 4 columns, 2 filtered unique indexes,
  lifecycle source precedence, per-requester idempotency, and permit validity remediation.
- PASS: strict PSR optimized autoload, backend reference gate, Inertia page gate.
- PASS: direct Vue SFC compilation and Prettier check for the three changed components.
- PASS: route snapshot differences are limited to the planned Visitor contract changes; console snapshot unchanged.
- PASS: Vite production build, 4,828 modules transformed.
- PASS: DDL-2026-020 and DML-2026-021 executed and verified on the explicitly
  approved `Web_HRIS` development target.
- LIMITATION: frontend import verifier exits 0 but checks zero paths on Windows due a pre-existing path-concatenation defect.
- BASELINE FAILURE: global Feature and Integration suites contain unrelated 404
  route-contract failures in ESS, KPI, LMS, and SYSTEM. `LemburSubmitSmokeTest`,
  `AllTransactionsReadParityTest`, and the LMS/SOP suite also hit existing 300-second execution limits.
- BASELINE FAILURE: Vitest completed with 823 passed and 88 failed tests across
  335 suites; failures are outside the changed Visitor components.
- BLOCKED ENVIRONMENT: Playwright regression cannot start because `.env.uat` is
  absent, so the target database cannot be verified; interactive browser verification
  is unavailable because no browser is connected to this session.
