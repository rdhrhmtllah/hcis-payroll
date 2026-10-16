/* Change ID: DML-2026-011-backfill-dashboard-changes */

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = '20260816_175007_add-system-module')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('20260816_175007_add-system-module', 'DML', 'Register SYSTEM in N_HRIS_Master_Grup_Fitur', 'Register SYSTEM in N_HRIS_Master_Grup_Fitur', 'database/dml/20260816_175007_add-system-module/data-change.sql', 'docs/changes/20260816_175007_add-system-module/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-001-create-system-database-change')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-001-create-system-database-change', 'DDL', 'Create SYSTEM database change workflow table', 'Create SYSTEM database change workflow table', 'database/ddl/DDL-2026-001-create-system-database-change/forward.sql', 'docs/changes/DDL-2026-001-create-system-database-change/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-002-register-system-dashboard-change')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-002-register-system-dashboard-change', 'DML', 'Register existing SYSTEM change in dashboard', 'Register existing SYSTEM change in dashboard', 'database/dml/DML-2026-002-register-system-dashboard-change/data-change.sql', 'docs/changes/DML-2026-002-register-system-dashboard-change/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-003-add-system-database-change-menu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-003-add-system-database-change-menu', 'DML', 'Add SYSTEM Database Change Dashboard sidebar menu', 'Add SYSTEM Database Change Dashboard sidebar menu', 'database/dml/DML-2026-003-add-system-database-change-menu/data-change.sql', 'docs/changes/DML-2026-003-add-system-database-change-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-004-restrict-system-database-change-menu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-004-restrict-system-database-change-menu', 'DML', 'Restrict SYSTEM Database Change Dashboard menu to System Admin', 'Restrict SYSTEM Database Change Dashboard menu to System Admin', 'database/dml/DML-2026-004-restrict-system-database-change-menu/data-change.sql', 'docs/changes/DML-2026-004-restrict-system-database-change-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-005-create-system-task-outbox')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-005-create-system-task-outbox', 'DDL', 'Create durable SYSTEM task outbox for critical asynchronous work', 'Create durable SYSTEM task outbox for critical asynchronous work', 'database/ddl/DDL-2026-005-create-system-task-outbox/forward.sql', 'docs/changes/DDL-2026-005-create-system-task-outbox/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-006-add-provider-result-to-task-outbox')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-006-add-provider-result-to-task-outbox', 'DDL', 'Add provider result fields to SYSTEM task outbox', 'Add provider result fields to SYSTEM task outbox', 'database/ddl/DDL-2026-006-add-provider-result-to-task-outbox/forward.sql', 'docs/changes/DDL-2026-006-add-provider-result-to-task-outbox/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-007-create-pengumpulan-data-tables')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-007-create-pengumpulan-data-tables', 'DDL', 'Create Pengumpulan Data Karyawan tables', 'Create Pengumpulan Data Karyawan tables', 'database/ddl/DDL-2026-007-create-pengumpulan-data-tables/forward.sql', 'docs/changes/DDL-2026-007-create-pengumpulan-data-tables/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-008-register-pengumpulan-data-menu')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-008-register-pengumpulan-data-menu', 'DML', 'Register Pengumpulan Data menus under ESS', 'Register Pengumpulan Data menus under ESS', 'database/dml/DML-2026-008-register-pengumpulan-data-menu/data-change.sql', 'docs/changes/DML-2026-008-register-pengumpulan-data-menu/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-009-create-report-template-tables')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DDL-2026-009-create-report-template-tables', 'DDL', 'Create cross-module report template tables', 'Create cross-module report template tables', 'database/ddl/DDL-2026-009-create-report-template-tables/forward.sql', 'docs/changes/DDL-2026-009-create-report-template-tables/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-010-register-report-template-change')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-010-register-report-template-change', 'DML', 'Register DDL-2026-009 in Database Change Dashboard', 'Register DDL-2026-009 in Database Change Dashboard', 'database/dml/DML-2026-010-register-report-template-change/data-change.sql', 'docs/changes/DML-2026-010-register-report-template-change/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-011-backfill-dashboard-changes')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-011-backfill-dashboard-changes', 'DML', 'Backfill Database Change Dashboard from ledger', 'Mendaftarkan seluruh perubahan di database/ddl/status.json yang belum terdaftar di N_SYSTEM_Database_Change, termasuk dirinya sendiri.', 'database/dml/DML-2026-011-backfill-dashboard-changes/data-change.sql', 'docs/changes/DML-2026-011-backfill-dashboard-changes/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'backfill-ledger');
END;
