/* Change ID: DML-2026-051-langkah-tinjauan-akhir */

UPDATE N_SOP_Master_Tahap_Versi
SET Langkah = 5,
    Kelompok = 'TINJAUAN',
    Updated_At = SYSDATETIME()
WHERE Kode_Tahap = 'FILING_REGISTERED';
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-051-langkah-tinjauan-akhir')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-051-langkah-tinjauan-akhir', 'DML', 'Tinjauan Akhir sebagai langkah kelima', 'FILING_REGISTERED dipindah dari langkah 4 ke langkah 5 dengan kelompok TINJAUAN, sehingga rekap sebelum terbit berdiri sebagai langkah tersendiri.', 'database/dml/DML-2026-051-langkah-tinjauan-akhir/data-change.sql', 'docs/changes/DML-2026-051-langkah-tinjauan-akhir/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'tinjauan-akhir');
GO
