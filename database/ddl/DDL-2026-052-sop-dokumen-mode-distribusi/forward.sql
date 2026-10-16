/* Change ID: DDL-2026-052-sop-dokumen-mode-distribusi */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Mode_Distribusi')
    ALTER TABLE N_SOP_Dokumen ADD Mode_Distribusi varchar(10) NOT NULL
        CONSTRAINT DF_N_SOP_Dokumen_Mode_Distribusi DEFAULT ('TERBATAS');
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-052-sop-dokumen-mode-distribusi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-052-sop-dokumen-mode-distribusi', 'DDL', 'Gerbang katalog publik pada dokumen SOP', 'Mode_Distribusi pada N_SOP_Dokumen sebagai penanda keterlihatan dokumen di katalog publik Perpustakaan SOP. NOT NULL dengan bawaan TERBATAS sehingga tidak ada dokumen yang terbuka tanpa keputusan admin.', 'database/ddl/DDL-2026-052-sop-dokumen-mode-distribusi/forward.sql', 'docs/changes/DDL-2026-052-sop-dokumen-mode-distribusi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'katalog-dokumen');
GO
