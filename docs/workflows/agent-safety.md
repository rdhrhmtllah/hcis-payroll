# Aturan Agent

Aturan lengkap berada di [[../../AGENTS.md|AGENTS.md]].

## Selalu dilarang

- Menjalankan `DROP`.
- Menjalankan `TRUNCATE`.
- Membuat atau menjalankan migration.
- Menjalankan DDL ke production.
- Menjalankan DML di luar scope fitur aktif.

## DML terbatas

`INSERT`, `UPDATE`, dan `DELETE` hanya boleh dilakukan bila langsung diperlukan fitur yang sedang dikerjakan, terbatas pada tabel/record project yang relevan, dan targetnya development. SQL, predicate, dan estimasi affected rows harus ditampilkan sebelum eksekusi.
