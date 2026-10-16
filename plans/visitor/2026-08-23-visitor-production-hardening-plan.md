# Visitor Production Hardening Implementation Plan

Status: `TESTED_TRACKER_PENDING` (application and development database work complete; production handoff remains manual)

1. [x] Capture route/console baselines and preserve unrelated worktree changes.
2. [x] Add ownership and state-transition services; lock self-service reads and
   mutations to the requester.
3. [x] Retire unsafe/legacy mutation routes and keep the old page redirect.
4. [x] Make Visitor decisions atomic and require final-approver permit validity.
5. [x] Add one-time remediation for approved permits missing validity.
6. [x] Prepare and execute the approved development SQL packages for effective
   lifecycle columns, submission idempotency, and the six-month master correction.
7. [x] Make effective tables the post-approval operational status source.
8. [x] Harden GCS document replacement and safe API errors.
9. [x] Complete focused unit/route/component/database checks and attempt full
   regression; record the unrelated baseline failures, timeouts, and unavailable browser/UAT environment.
10. [x] Stop at feature-complete, confirm behavior, then offer feature/impacted/full
    regression execution.

No Laravel migration, DROP execution, production DDL, blanket staging, or
unrelated refactor is authorized by this plan.
