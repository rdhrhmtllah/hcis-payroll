-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-104-visitor-kategori-permit-akses
--
-- Menandai kategori permit mana yang MUNCUL di form pengajuan permit karyawan
-- (/transaksipermit, tab "Buat Baru").
--
-- Sejak kategori COKLAT lahir untuk izin tamu, form itu menawarkan tiga
-- kategori aktif — dan salah satunya memang tidak pernah diajukan karyawan.
-- Tanpa penanda, satu-satunya cara menyembunyikannya adalah menyebut namanya
-- di dalam kode, dan kategori baru kelak menuntut perubahan kode lagi.
--
-- Sengaja BUKAN kolom cakupan akses ('boleh dipakai tamu / permit'): yang
-- dibutuhkan hanya penentu tampil-atau-tidak di satu layar, dan kolom yang
-- menjanjikan lebih dari itu akan dibaca sebagai aturan yang tidak pernah
-- ditegakkan di tempat lain.
--
-- Nullable tanpa DEFAULT, dan NULL DIBACA SEBAGAI 'Y' oleh kodenya: baris yang
-- lahir dari jalur lain tidak boleh hilang diam-diam dari form hanya karena
-- kolom ini terlewat diisi.

IF COL_LENGTH('N_EMI_VISITOR_Master_Kategori_Permit', 'Flag_Muncul_Pengajuan') IS NULL
BEGIN
    ALTER TABLE N_EMI_VISITOR_Master_Kategori_Permit ADD Flag_Muncul_Pengajuan char(1) NULL;
END
GO
