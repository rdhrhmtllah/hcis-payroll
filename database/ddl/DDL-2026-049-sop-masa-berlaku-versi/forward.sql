/* Change ID: DDL-2026-049-sop-masa-berlaku-versi */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Review_Berikutnya')
    ALTER TABLE N_SOP_Item_Versi ADD Tanggal_Review_Berikutnya date NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Masa_Berlaku_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen ADD Masa_Berlaku_Bulan int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Jenis_Dokumen' AND COLUMN_NAME = 'Siklus_Review_Bulan')
    ALTER TABLE N_SOP_Master_Jenis_Dokumen ADD Siklus_Review_Bulan int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-049-sop-masa-berlaku-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-049-sop-masa-berlaku-versi', 'DDL', 'Masa berlaku dan jadwal review versi', 'Tanggal_Review_Berikutnya pada versi; Masa_Berlaku_Bulan dan Siklus_Review_Bulan sebagai aturan baku per jenis dokumen.', 'database/ddl/DDL-2026-049-sop-masa-berlaku-versi/forward.sql', 'docs/changes/DDL-2026-049-sop-masa-berlaku-versi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'masa-berlaku');
GO
