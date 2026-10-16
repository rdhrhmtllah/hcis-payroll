/* Change ID: DML-2026-184-pa-master-level-isi-hris
   Target: Web_HRIS development; production only through SQL Tracker. Setelah DDL-2026-183.
   Scope: menyalin seluruh level HRIS ke N_PA_Level dengan ID yang SAMA (Sumber HRIS, Urutan =
          Level_Hierarchy). Idempoten: level yang sudah ada dilewati. Level HRIS baru di masa depan
          disalin aplikasi (PaLevelMasterService::sinkronHris), bukan oleh trigger.
*/
IF DB_NAME() <> 'Web_HRIS'
    THROW 50684, 'DML-2026-184 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

INSERT INTO N_PA_Level (ID_Level, Nama_Level, Sumber, ID_Level_HRIS_Dasar, Urutan, Flag_Aktif)
SELECT h.ID_Level, LTRIM(RTRIM(h.Keterangan)), 'HRIS', h.ID_Level, CAST(ISNULL(h.Level_Hierarchy, 0) AS decimal(6,2)), 'Y'
FROM HRIS_Level h
WHERE NOT EXISTS (SELECT 1 FROM N_PA_Level p WHERE p.ID_Level = h.ID_Level);

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-184')
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Catatan, Created_By)
    VALUES ('DML-2026-184', 'DML', 'Isi Master Level PA dari HRIS_Level',
        'Salinan seluruh HRIS_Level ke N_PA_Level dengan ID sama (Sumber HRIS, Urutan = Level_Hierarchy).',
        'database/dml/DML-2026-184-pa-master-level-isi-hris/data-change.sql',
        'docs/changes/DML-2026-184-pa-master-level-isi-hris/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'KPI/Performance Appraisal/Master Data. Setelah DDL-2026-183, sebelum DDL-2026-185.', NULL);

COMMIT TRANSACTION;
GO
