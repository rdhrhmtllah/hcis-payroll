/* Change ID: DDL-2026-169-pa-waktu-kirim-penilai
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-075 (N_PA_Assignment).
   Scope: tiga kolom pada N_PA_Assignment yang mencatat kapan, oleh siapa, dan lewat jalur
          apa sebuah penugasan dilepas menjadi SELESAI. Sebelumnya hanya Updated_At yang
          tertimpa setiap perubahan berikutnya.
   Dokumentasi: docs/database/tables/kpi/n-pa-assignment.md
*/

IF COL_LENGTH('N_PA_Assignment', 'Submitted_At') IS NULL
    ALTER TABLE N_PA_Assignment ADD Submitted_At datetime2(0) NULL;
GO

IF COL_LENGTH('N_PA_Assignment', 'Submitted_By') IS NULL
    ALTER TABLE N_PA_Assignment ADD Submitted_By int NULL;
GO

IF COL_LENGTH('N_PA_Assignment', 'Submitted_Via') IS NULL
    ALTER TABLE N_PA_Assignment ADD Submitted_Via varchar(20) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-169')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-169',
        'DDL',
        'Waktu kirim penilaian Performance Appraisal',
        'Kolom Submitted_At, Submitted_By (KPI_Users.Id_Users), dan Submitted_Via (PENILAI, ADMIN, PENUTUPAN) pada N_PA_Assignment: kapan dan oleh siapa lembar dilepas menjadi SELESAI. Penugasan yang selesai sebelum perubahan ini tetap NULL.',
        'database/ddl/DDL-2026-169-pa-waktu-kirim-penilai/forward.sql',
        'docs/changes/DDL-2026-169-pa-waktu-kirim-penilai/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Pengisian. Tanpa DML: waktu kirim lama tidak bisa direkonstruksi dari Updated_At.',
        NULL
    );
END;
GO
