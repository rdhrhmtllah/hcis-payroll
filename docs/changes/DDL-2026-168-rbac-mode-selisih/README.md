# DDL-2026-168 — Mode keputusan akses per modul & catatan selisih

RBAC Fase 3a. Rencana: `docs/superpowers/plans/2026-10-09-rbac-fase3a-sinkron-bayangan.md`.

| Objek | Arti |
|---|---|
| `N_HRIS_Akses_Mode_Modul` | Satu baris per modul (`Id_Grup_Fitur`): `Mode` = `LAMA` (aturan lama memutuskan), `BAYANGAN` (lama memutuskan, aturan baru dihitung & selisih dicatat), `BARU` (aturan baru memutuskan). Tanpa baris = `LAMA`. Diubah System Admin di tab Katalog (tercatat di `N_HRIS_Akses_Audit`). |
| `N_HRIS_Akses_Selisih` | Satu baris per (user, halaman, aksi) yang keputusannya beda antara LAMA dan BARU selama mode BAYANGAN; `Jumlah` & `Terakhir_At` diperbarui tiap kali terulang. |

Aditif dan idempoten. Kode toleran tabel belum ada (semua modul LAMA).

Verifikasi: `SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.N_HRIS_Akses_Mode_Modul')` → 4; `...N_HRIS_Akses_Selisih` → 9.

Rollback: `drop.sql` (manual).

## Setelah dijalankan di production

Bendera skema RBAC di-cache satu jam (`SkemaRbac`, kunci `rbac:skema`). Agar fitur yang bergantung pada DDL ini langsung aktif tanpa menunggu cache kedaluwarsa, jalankan:

```
php artisan cache:forget rbac:skema
```
