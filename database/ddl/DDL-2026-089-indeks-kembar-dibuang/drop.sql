-- DDL-2026-089-indeks-kembar-dibuang — PEMBATALAN
-- Target: Web_HRIS (development), lalu production lewat SQL Tracker
-- BELUM DIEKSEKUSI.
--
-- Membangun kembali ketujuh indeks yang dibuang forward.sql, persis seperti
-- aslinya. Definisi di bawah bukan tulisan tangan: ia dibaca dari sys.indexes
-- dan sys.index_columns pada Web_HRIS sebelum satu pun DROP disusun, jadi nama,
-- urutan kolom kunci, dan sifat uniknya sama dengan yang berdiri hari ini.
--
-- Tidak satu pun dari indeks ini unik, sehingga membangunnya kembali tidak
-- bisa gagal karena data kembar.

IF DB_NAME() NOT IN ('Web_HRIS')
BEGIN
    RAISERROR('Skrip ini hanya untuk Web_HRIS. Basis data aktif: %s', 16, 1, DB_NAME());
    SET NOEXEC ON;
END
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.HRIS_KANDIDAT_Jawaban_Soal') AND name = 'idx_jawaban_soal_pk')
    CREATE NONCLUSTERED INDEX idx_jawaban_soal_pk ON dbo.HRIS_KANDIDAT_Jawaban_Soal (Id_Jawaban_Soal);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.HRIS_KANDIDAT_Master_Soal') AND name = 'idx_master_soal_pk')
    CREATE NONCLUSTERED INDEX idx_master_soal_pk ON dbo.HRIS_KANDIDAT_Master_Soal (Id_Master_Soal);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_WEB_CAREERS_Lamaran_Tahap') AND name = 'IX_N_WEB_CAREERS_Lamaran_Tahap_Lamaran')
    CREATE NONCLUSTERED INDEX IX_N_WEB_CAREERS_Lamaran_Tahap_Lamaran ON dbo.N_WEB_CAREERS_Lamaran_Tahap (Lamaran_Id, Urutan);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.HRIS_Rekrutmen_Karyawan') AND name = 'idx_rekrutmen_kode_calon')
    CREATE NONCLUSTERED INDEX idx_rekrutmen_kode_calon ON dbo.HRIS_Rekrutmen_Karyawan (Kode_Calon);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_WEB_CAREERS_Sub_Divisi_Informations') AND name = 'Idx_DeptInfo_Sub_Divisi')
    CREATE NONCLUSTERED INDEX Idx_DeptInfo_Sub_Divisi ON dbo.N_WEB_CAREERS_Sub_Divisi_Informations (Id_Sub_Divisi);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_WEB_CAREERS_Division_Informations') AND name = 'Idx_DivInfo_Divisi')
    CREATE NONCLUSTERED INDEX Idx_DivInfo_Divisi ON dbo.N_WEB_CAREERS_Division_Informations (Id_Divisi);
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('dbo.N_ESIGN_Revoked_Certificates') AND name = 'IX_Revoked_SerialNumber')
    CREATE NONCLUSTERED INDEX IX_Revoked_SerialNumber ON dbo.N_ESIGN_Revoked_Certificates (Serial_Number);
GO

SET NOEXEC OFF;
GO
