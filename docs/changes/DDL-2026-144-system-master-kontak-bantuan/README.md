# DDL-2026-144-system-master-kontak-bantuan

- **Target:** Web_HRIS development & production (SQL Tracker)
- **Artefak:** `database/ddl/DDL-2026-144-system-master-kontak-bantuan/forward.sql` (sha256 `d88fecddbbeaaf1ed242cca1fb46cf91db4ccb4d35a6f7066e8ea535a074a7b2`), `drop.sql` (pembersihan manual, tidak dijalankan agent)
- **Objek:** `dbo.N_HRIS_Master_Kontak_Bantuan` + indeks `IX_HRIS_Kontak_Bantuan_Lingkup`
- **Pemilik:** SYSTEM › MasterData › KontakBantuan (`App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService`)

## Tujuan

Kontak bantuan resmi **global lintas modul**. Satu baris = satu kontak untuk satu fitur di satu
modul, dilingkupi `Kode_Grup_Fitur` (ESS/KPI/LMS/VISITOR/SYSTEM) + `Kode_Fitur` (PERMIT, TAMU,
PA, ...). Keputusan user 28 Sep 2026: tabel global (bukan per fitur), kolom `Kode_Fitur`, kontak
RESMI yang ditetapkan admin (bukan nomor pribadi pemegang role).

## Pemakai pertama

- VISITOR/PERMIT — dikelola di **Master Data Permit › Kontak Bantuan** (katalog master VISITOR
  memasang lingkup tetap `VISITOR/PERMIT`: halaman itu hanya membaca & menyunting barisnya
  sendiri), ditampilkan di halaman pengajuan permit dan detail permit karyawan
  (`resources/js/components/SYSTEM/KontakBantuan/KontakBantuanCard.vue`).

Modul lain memakai service & komponen yang sama dengan `Kode_Fitur` miliknya.

## Perilaku bila tabel belum ada

Halaman pemakai tetap berjalan; kartu bantuan tidak tampil (daftar kosong, dicatat di log).

## Tugas user

1. Jalankan `forward.sql` lewat SQL Tracker di production.
2. Isi minimal satu kontak di Master Data Permit › Kontak Bantuan.
