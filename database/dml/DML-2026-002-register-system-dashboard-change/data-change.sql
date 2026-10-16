/* Change ID: DML-2026-002-register-system-dashboard-change */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = '20260816_175007_add-system-module')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('20260816_175007_add-system-module', 'DML', 'Register SYSTEM Feature Group', 'Register SYSTEM in N_HRIS_Master_Grup_Fitur.', 'database/dml/20260816_175007_add-system-module/data-change.sql', 'docs/changes/20260816_175007_add-system-module/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'system-migration');
END;
