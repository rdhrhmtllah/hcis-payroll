-- Change ID: DDL-2026-110-cakupan-lokasi-pengumpulan-data
-- ALAT BANTU RESET MANUAL. TIDAK PERNAH DIJALANKAN AGENT.
--
-- Peringatan: membuang kolom ini mengembalikan kampanye ke pembukaan serentak.
-- Kodenya membaca kolom yang hilang sebagai 'seluruh lokasi', jadi tidak ada
-- galat yang muncul — yang terjadi hanya SELURUH karyawan aktif kembali wajib
-- mengisi, termasuk lokasi yang sengaja belum dibuka. Kehilangan itu senyap.

ALTER TABLE N_HRIS_Pengumpulan_Data_Config DROP COLUMN IF EXISTS Cakupan_Lokasi;
GO
