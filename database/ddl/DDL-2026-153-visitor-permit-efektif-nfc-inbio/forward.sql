-- Change ID: DDL-2026-153-visitor-permit-efektif-nfc-inbio
-- Nomor kartu ID Card (N_EMI_Master_NFC.nfc_inbio) pemegang izin, dibaca
-- aplikasi palang dari N_VISITOR_Permit_Efektif.
--
-- Diisi VisitorEfektifService saat baris efektif ditulis (akses inspeksi dan
-- sinkron setelah approval) dari kartu aktif karyawan pemohon:
--   N_EMI_Binding_NFC.kode_karyawan -> id_nfc -> N_EMI_Master_NFC.nfc_inbio
-- Hanya izin yang dipegang karyawan sendiri (Sumber_Permit KARYAWAN); izin
-- tamu/mitra NULL. varchar, bukan bigint: nomor kartu adalah pengenal, bukan
-- angka untuk dihitung, dan palang membacanya sebagai teks.

IF COL_LENGTH('dbo.N_VISITOR_Permit_Efektif', 'NFC_Inbio') IS NULL
BEGIN
    ALTER TABLE dbo.N_VISITOR_Permit_Efektif ADD NFC_Inbio varchar(30) NULL;
END;
GO
