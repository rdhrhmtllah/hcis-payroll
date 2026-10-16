# DDL dan SQL Tracker

## Tujuan

Setiap perubahan struktur database harus dapat ditelusuri dari project sampai production tanpa memakai Laravel migration.

## Alur

```text
DRAFT
 -> REVIEW_REQUIRED
 -> APPROVED
 -> DEV_EXECUTION_PENDING
 -> DEV_EXECUTED
 -> TRACKER_PENDING
 -> TRACKER_RECORDED
 -> PRODUCTION_PENDING
 -> PRODUCTION_EXECUTED
 -> VERIFICATION_PENDING
 -> COMPLETED
```

## Output setiap perubahan

```text
database/ddl/<change-id>/forward.sql
database/ddl/<change-id>/drop.sql
database/dml/<change-id>/data-change.sql
```

File `forward.sql` yang diuji di development harus sama persis dengan yang dicatat ke SQL Tracker. File `drop.sql` hanya dokumentasi/reset manual dan tidak boleh dijalankan agent. DML disimpan terpisah dan tidak dicampur ke DDL.

## Pembagian tugas

### Agent

- Membuat forward DDL dan drop script.
- Menunggu review/approval.
- Menjalankan forward DDL yang disetujui hanya di development.
- Memverifikasi hasil development.
- Mencatat DDL yang sama ke SQL Tracker.

### Developer

- Mereview dan menyetujui DDL.
- Menjalankan DDL dari SQL Tracker ke production.
- Mengonfirmasi hasil dan melakukan verifikasi production.

## Related notes

- [[../database/sql-server|SQL Server]]
- [[../workflows/dev-to-production|Development ke Production]]
- [[../decisions/ADR-002-ddl-no-migration|ADR-002 DDL tanpa migration]]
