# SQL Server

## Connection policy

Project menggunakan SQL Server eksternal melalui `pdo_sqlsrv`/`sqlsrv`. Credential tidak boleh ditulis ke dokumentasi, source code, atau frontend.

## Environment separation

- Development: dapat dijalankan oleh agent setelah review dan approval.
- Production: perubahan DDL resmi dijalankan melalui SQL Tracker oleh developer.

## Safety

- Jangan menjalankan `DROP`, `TRUNCATE`, atau migration.
- `INSERT`, `UPDATE`, dan `DELETE` hanya untuk fitur aktif, tabel project yang relevan, dan scope yang jelas.
- Sebelum DML, tampilkan tabel, predicate, target environment, dan estimasi affected rows.

## Related notes

- [[../workflows/ddl-sql-tracker|DDL dan SQL Tracker]]
- [[../workflows/agent-safety|Aturan Agent]]
