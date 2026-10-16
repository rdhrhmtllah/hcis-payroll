# DDL-2026-165 — Model RBAC Fase 2 (role → hak, cakupan, tanggung jawab)

Spec: `docs/superpowers/specs/2026-10-09-rbac-akses-peran-design.md` §4.1. Rencana: `docs/superpowers/plans/2026-10-09-rbac-fase2a-data-konversi.md`.

## Objek terdampak

| Objek | Perubahan | Arti |
|---|---|---|
| `N_HRIS_Role_Hak` | CREATE (PK, UQ role+halaman+aksi, IX halaman+aksi) | Izin sebuah role: satu baris = satu halaman (`Jenis_page`) + satu aksi (`Id_Aksi` → `N_HRIS_Klasifikasi_Aksi`). |
| `N_HRIS_Role_Functional.Jenis_Role` | ADD varchar(20) NOT NULL DEFAULT `AKSES` | Label: `AKSES` (pemakai), `ADMIN` (pengelola akses), `POOL_APPROVAL` (kelompok approver). Tidak memengaruhi hak. |
| `N_HRIS_Role_User.Berlaku_Sampai`, `.Alasan` | ADD NULL | Kedaluwarsa & alasan penugasan role. NULL = tanpa batas. |
| `HRIS_Page_Access.Alasan`, `.Berlaku_Sampai` | ADD NULL | Alasan & kedaluwarsa grant langsung (pengecualian). |
| `N_HRIS_Role_User_Cakupan` | CREATE (PK, IX penugasan) | Cakupan data per penugasan: `Jenis_Cakupan` (`SEMUA`,`ENTITAS`,`LOKASI`,`DIVISI`,`DEPARTEMEN`,`TIM`,`PHL`) + `Nilai`. Tanpa baris = `SEMUA`. |
| `N_HRIS_Role_User_Tanggung_Jawab` | CREATE (PK, UQ penugasan+kode) | Tanggung jawab admin per penugasan: `AKSES`, `APPROVAL`, `KEANGGOTAAN`. |

Aditif dan idempoten. Kolom baru di tabel lama diletakkan di akhir dan nullable/ber-default, jadi `INSERT` lama tetap jalan. View `N_HRIS_VW_Approval_Request_V2` memakai `req.*` pada `HRIS_Approval_Request` (tidak diubah) — aman.

## Alur data

Diisi pertama kali oleh DML-2026-166 (konversi dari kondisi sekarang). Dibaca oleh `KeputusanAkses::bolehBaru()` (Fase 2a, belum memutuskan apa pun) dan layar Akses & Peran (Fase 2b). Ditulis oleh layar admin mulai Fase 3.

## Urutan rilis

DDL ini → DML-2026-166 → kode Fase 2. Kode toleran DDL belum jalan (`bolehBaru` jatuh ke aturan lama).

## Verifikasi

```sql
SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.N_HRIS_Role_Hak');                 -- 9
SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.N_HRIS_Role_User_Cakupan');        -- 6
SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('dbo.N_HRIS_Role_User_Tanggung_Jawab'); -- 5
SELECT COL_LENGTH('dbo.N_HRIS_Role_Functional','Jenis_Role'), COL_LENGTH('dbo.HRIS_Page_Access','Berlaku_Sampai');
```

## Rollback

`drop.sql` (manual; menghapus tabel & kolom baru beserta datanya).

## Setelah dijalankan di production

Bendera skema RBAC di-cache satu jam (`SkemaRbac`, kunci `rbac:skema`). Agar fitur yang bergantung pada DDL ini langsung aktif tanpa menunggu cache kedaluwarsa, jalankan:

```
php artisan cache:forget rbac:skema
```
