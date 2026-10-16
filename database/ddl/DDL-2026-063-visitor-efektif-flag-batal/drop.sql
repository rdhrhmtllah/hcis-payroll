-- BANTUAN RESET MANUAL SAJA. Agent tidak boleh menjalankan berkas ini.
-- Change ID: DDL-2026-063-visitor-efektif-flag-batal
--
-- Menjatuhkan kolom ini MENGHAPUS DATA, tetapi datanya bisa dibangun ulang
-- seluruhnya dari Status_Efektif: 'DIBATALKAN' -> 'Y', selain itu 'T'.
-- Default constraint ikut jatuh bersama kolomnya.

IF COL_LENGTH('N_VISITOR_Permit_Efektif', 'Flag_Batal') IS NOT NULL
ALTER TABLE N_VISITOR_Permit_Efektif DROP COLUMN Flag_Batal;
GO

IF COL_LENGTH('N_VISITOR_Tamu_Efektif', 'Flag_Batal') IS NOT NULL
ALTER TABLE N_VISITOR_Tamu_Efektif DROP COLUMN Flag_Batal;
GO
