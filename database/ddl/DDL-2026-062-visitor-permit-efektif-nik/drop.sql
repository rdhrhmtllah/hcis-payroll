-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Change ID: DDL-2026-062-visitor-permit-efektif-nik
--
-- PERINGATAN: menjatuhkan kolom ini MENGHAPUS DATA. Nilainya bisa dibangun
-- ulang dari Karyawan.No_Induk_Karyawan lewat data-change.sql, tetapi hanya
-- untuk pemohon yang nomor induknya belum berubah sejak permit disetujui.

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'NIK') IS NOT NULL
ALTER TABLE N_VISITOR_Permit_Efektif DROP COLUMN NIK;
GO
