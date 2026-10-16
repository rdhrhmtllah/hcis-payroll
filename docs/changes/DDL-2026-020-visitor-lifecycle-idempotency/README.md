# DDL-2026-020 - Visitor lifecycle dan idempotency

| Bidang | Nilai |
|---|---|
| Target | `Web_HRIS` development pada `team311.dyndns.info:1433` |
| Status | `TRACKER_PENDING` |
| Deskripsi | Menambah waktu aktual lifecycle tamu dan kunci idempotency pengajuan Visitor. |
| Objek | `N_VISITOR_Tamu_Efektif`, `N_EMI_VISITOR_Transaksi_Permit`, `N_EMI_VISITOR_Transaksi_Tamu` |
| Forward | `database/ddl/DDL-2026-020-visitor-lifecycle-idempotency/forward.sql` |
| Drop manual | `database/ddl/DDL-2026-020-visitor-lifecycle-idempotency/drop.sql` |
| DML | `database/dml/DDL-2026-020-visitor-lifecycle-idempotency/data-change.sql` |
| Checksum SHA-256 | `8d5b4d21e869768188d0f7cff5d2d5ea0e712ab78258ceb22dfc985d120d8abe` |

Agent completed: live target/module verification, forward/drop/DML companion generation, approved development execution, and verification of 4 columns plus 2 filtered unique indexes on 2026-08-23. Remaining user work: record the identical forward SQL in SQL Tracker, execute it manually in production, and verify production.
