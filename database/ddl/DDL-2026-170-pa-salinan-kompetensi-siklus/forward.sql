/* Change ID: DDL-2026-170-pa-salinan-kompetensi-siklus
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-067 (N_PA_Competency), DDL-2026-066 (N_PA_Cycle).
   Scope: salinan per siklus atas atribut kompetensi yang memengaruhi hitung dan tampilan
          (Flag_Berskor, Flag_Radar, Nilai_Ambang), dibekukan saat siklus dikunci.
   Dokumentasi: docs/database/tables/kpi/n-pa-cycle-competency-snapshot.md
*/

IF OBJECT_ID('N_PA_Cycle_Competency_Snapshot', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Competency_Snapshot (
        Id_PA_Cycle_Competency_Snapshot int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Cycle_Competency_Snapshot PRIMARY KEY,
        PA_Cycle_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Competency_Snapshot_Cycle REFERENCES N_PA_Cycle (Id_PA_Cycle),
        PA_Competency_Id int NOT NULL
            CONSTRAINT FK_PA_Cycle_Competency_Snapshot_Competency REFERENCES N_PA_Competency (Id_PA_Competency),
        Flag_Berskor char(1) NOT NULL,
        Flag_Radar char(1) NOT NULL,
        Nilai_Ambang decimal(5,2) NULL,
        Snapshot_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Competency_Snapshot_Snapshot_At DEFAULT (sysdatetime()),
        Snapshot_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Cycle_Competency_Snapshot') AND name = 'UX_PA_Cycle_Competency_Snapshot_Cycle_Competency')
    CREATE UNIQUE INDEX UX_PA_Cycle_Competency_Snapshot_Cycle_Competency
        ON N_PA_Cycle_Competency_Snapshot (PA_Cycle_Id, PA_Competency_Id);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-170')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-170',
        'DDL',
        'Salinan atribut kompetensi per siklus Performance Appraisal',
        'Tabel N_PA_Cycle_Competency_Snapshot: Flag_Berskor, Flag_Radar, dan Nilai_Ambang tiap kompetensi dibekukan per siklus saat siklus dikunci, supaya perubahan master tidak menggeser hitung dan tampilan hasil siklus yang sudah berjalan atau terbit. Siklus tanpa salinan tetap membaca master.',
        'database/ddl/DDL-2026-170-pa-salinan-kompetensi-siklus/forward.sql',
        'docs/changes/DDL-2026-170-pa-salinan-kompetensi-siklus/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Penyiapan & Hasil. Tanpa DML: siklus lama tetap membaca master.',
        NULL
    );
END;
GO
