# DDL-2026-164 — N_HRIS_Akses_Audit

RBAC Fase 1 (`docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md`): jejak perubahan role/akses dengan nilai sebelum dan sesudah.

## Objek terdampak

- `dbo.N_HRIS_Akses_Audit` (CREATE TABLE, 11 kolom, PK `PK_HRIS_Akses_Audit`)
- `IX_HRIS_Akses_Audit_Target` (`Target_Id_User`, `Waktu`)
- `IX_HRIS_Akses_Audit_Waktu` (`Waktu`)

Aditif saja; tidak mengubah tabel lain. Idempoten (aman dijalankan ulang).

## Urutan rilis

Boleh sebelum atau sesudah kode Fase 1. Kode (`AuditAkses`) toleran tabel belum ada: tanpa tabel, jejak hanya ke log file seperti sebelumnya.

## Verifikasi

```sql
SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.N_HRIS_Akses_Audit'); -- 11
SELECT name FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_HRIS_Akses_Audit');   -- PK + 2 indeks
```

## Rollback

`drop.sql` (manual, menghapus seluruh jejak audit akses).

## Setelah dijalankan di production

Bendera skema RBAC di-cache satu jam (`SkemaRbac`, kunci `rbac:skema`). Agar fitur yang bergantung pada DDL ini langsung aktif tanpa menunggu cache kedaluwarsa, jalankan:

```
php artisan cache:forget rbac:skema
```
