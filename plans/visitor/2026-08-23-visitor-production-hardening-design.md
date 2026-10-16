# Visitor Production Hardening Design

## Registry basis

- Environment: development
- Database: `Web_HRIS`
- Verification date: 2026-08-23
- Master group: `Id_Grup_Fitur=4`, `Kode_Grup_Fitur=VISITOR`
- Pages: `visitorPermitPage`, `visitorTamuPage`, `visitorApprovalPage`,
  `visitorVerifikatorPage`, `visitorLaporanPage`, and
  `menuApprovalMatrixVisitorPage`

The database registry is authoritative. The hierarchy used by this work is:

- `VISITOR/Guest/Visit`
- `VISITOR/Permit/VehiclePermit`
- `VISITOR/Workflow/Approval`
- `VISITOR/Workflow/Verification`
- `VISITOR/Operations/Expiration`

## Decisions

- Self-service data is private to the submitting PIC/requester.
- A requester may cancel only a pending request before any approval is processed.
- Approved access may only be revoked by the Visitor verifier/SHE path.
- Approval expiry is terminal; the requester submits a new transaction.
- The final approver chooses the permit duration.
- Permit validity starts on final approval. A one-month permit starting on
  23 August ends on 22 September.
- Existing approved permits without validity require a one-time SHE remediation.
- The gate application writes guest check-in, check-out, and pre-check-in
  cancellation to `N_VISITOR_Tamu_Efektif` through its separate SQL connection.
- Gate application code and credentials are outside this repository's scope.
- Mutating legacy `approvalvisitor*` endpoints are retired; the page redirect is
  retained temporarily.

## Status model

Request tables remain the approval history. Effective tables become the source
of operational status after final approval.

```text
Guest request: PENDING -> APPROVED | REJECTED | EXPIRED | CANCELLED
Guest effective: AKTIF -> CHECKED_IN -> CHECKED_OUT
                         \-> DIBATALKAN (only before check-in)
                         \-> EXPIRED

Permit request: PENDING -> APPROVED | REJECTED | EXPIRED | CANCELLED
Permit effective: VALIDITY_REQUIRED -> AKTIF -> DIBATALKAN | EXPIRED
```

`Flag_Sync='T'` means an HCIS-originated effective change still needs to be
pulled. The gate acknowledges it using `Flag_Sync='Y'` and `Synced_At`. Until an
operator identity contract exists, gate-originated updates use the technical
actor `GATE_EXTERNAL`.

## Scope boundaries

In scope: Visitor ownership, state transitions, approval atomicity, permit
validity, effective lifecycle reads, document safety, idempotency, tests, and SQL
Tracker artifacts.

Related changes requiring their own checkpoint: the Windows frontend-import
verifier defect and shared transaction-prefix recognition.

Out of scope: global RBAC redesign, gate application implementation, production
database execution, and unrelated legacy file moves.
