/* Change ID: DML-2026-010-register-report-template-change */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-009-create-report-template-tables')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-009-create-report-template-tables', 'DDL', 'Create cross-module report template tables', 'Dua tabel Export Studio: N_HRIS_Report_Template dan N_HRIS_Report_Template_Akses.', 'database/ddl/DDL-2026-009-create-report-template-tables/forward.sql', 'docs/changes/DDL-2026-009-create-report-template-tables/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'export-studio');
END;
