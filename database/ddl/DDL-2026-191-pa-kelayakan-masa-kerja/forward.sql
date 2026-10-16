/* Change ID: DDL-2026-191-pa-kelayakan-masa-kerja
   Target: Web_HRIS development; production only through SQL Tracker (sesudah DDL-2026-187).
   Dependencies: N_PA_Cycle.
   Scope: aturan kelayakan peserta PA berdasarkan masa kerja. Master satu baris
          (N_PA_Eligibility_Setting) dan salinan per siklus (N_PA_Cycle.Min_Masa_Kerja_Bulan).
   Dokumentasi: docs/changes/DDL-2026-191-pa-kelayakan-masa-kerja/README.md
*/

IF OBJECT_ID('N_PA_Eligibility_Setting', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Eligibility_Setting (
        Id_PA_Eligibility_Setting int NOT NULL
            CONSTRAINT PK_PA_Eligibility_Setting PRIMARY KEY,
        Min_Masa_Kerja_Bulan int NOT NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Eligibility_Setting_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT CK_PA_Eligibility_Setting_Tunggal CHECK (Id_PA_Eligibility_Setting = 1)
    );
END;
GO

IF COL_LENGTH('N_PA_Cycle', 'Min_Masa_Kerja_Bulan') IS NULL
    ALTER TABLE N_PA_Cycle ADD Min_Masa_Kerja_Bulan int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-191')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-191',
        'DDL',
        'Kelayakan peserta PA berdasarkan masa kerja',
        'Tabel N_PA_Eligibility_Setting (master satu baris: minimal masa kerja dalam bulan) dan kolom N_PA_Cycle.Min_Masa_Kerja_Bulan (salinan per siklus; NULL = aturan tidak berlaku untuk siklus lama).',
        'database/ddl/DDL-2026-191-pa-kelayakan-masa-kerja/forward.sql',
        'docs/changes/DDL-2026-191-pa-kelayakan-masa-kerja/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Cycle. Jalankan sesudah DDL-2026-187.',
        NULL
    );
END;
GO
