# ADR-001: SQL Server sebagai database eksternal

- Status: Accepted
- Context: Project menggunakan SQL Server eksternal dan driver `pdo_sqlsrv`.
- Decision: Laravel tetap memakai koneksi SQL Server; database tidak dibuat sebagai container lokal.
- Consequence: Semua query dan DDL harus kompatibel dengan SQL Server.
