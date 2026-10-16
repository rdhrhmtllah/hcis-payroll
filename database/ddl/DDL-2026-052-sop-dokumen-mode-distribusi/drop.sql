/* Change ID: DDL-2026-052-sop-dokumen-mode-distribusi
   Berkas bantu pembersihan manual. JANGAN dijalankan oleh agent. */

IF EXISTS (SELECT 1 FROM sys.default_constraints WHERE name = 'DF_N_SOP_Dokumen_Mode_Distribusi')
    ALTER TABLE N_SOP_Dokumen DROP CONSTRAINT DF_N_SOP_Dokumen_Mode_Distribusi;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Mode_Distribusi')
    ALTER TABLE N_SOP_Dokumen DROP COLUMN Mode_Distribusi;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-052-sop-dokumen-mode-distribusi';
GO
