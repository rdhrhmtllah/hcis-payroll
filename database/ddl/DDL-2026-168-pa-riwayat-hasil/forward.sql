/* Change ID: DDL-2026-168-pa-riwayat-hasil
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-080 (N_PA_Result).
   Scope: potret angka hasil PA setiap kali hitung, hitung ulang, Balancing, atau kurva
          mengubahnya. N_PA_Result ditimpa di tempat; tanpa potret ini angka versi lama hilang.
   Dokumentasi: docs/database/tables/kpi/n-pa-result-history.md
*/

IF OBJECT_ID('N_PA_Result_History', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Result_History (
        Id_PA_Result_History int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Result_History PRIMARY KEY,
        PA_Result_Id int NOT NULL
            CONSTRAINT FK_PA_Result_History_Result REFERENCES N_PA_Result (Id_PA_Result),
        Jenis_Peristiwa varchar(30) NOT NULL,
        Calculation_Version int NOT NULL,
        Status_Hasil varchar(20) NOT NULL,
        Nilai_KPI decimal(6,2) NULL,
        Nilai_KPI_Sebelum_Cap decimal(6,2) NULL,
        Nilai_Kompetensi decimal(6,2) NULL,
        Bobot_KPI_Terpakai decimal(5,2) NULL,
        Bobot_Kompetensi_Terpakai decimal(5,2) NULL,
        Final_Score decimal(6,2) NULL,
        Kode_Grade_Sistem varchar(40) NULL,
        Kode_Grade_Final varchar(40) NULL,
        Flag_Dikalibrasi char(1) NOT NULL,
        PA_Hav_Quadrant_Id int NULL,
        Alasan_Tertahan nvarchar(500) NULL,
        Keterangan nvarchar(500) NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Result_History_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Result_History') AND name = 'IX_PA_Result_History_Result')
    CREATE INDEX IX_PA_Result_History_Result ON N_PA_Result_History (PA_Result_Id, Id_PA_Result_History);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-168')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-168',
        'DDL',
        'Riwayat angka hasil Performance Appraisal',
        'Tabel N_PA_Result_History: potret KPI, kompetensi, final, grade, status, dan kuadran HAV sesudah setiap hitung, hitung ulang, hitung ulang Balancing, serta kunci/buka kurva, beserta waktu dan pelakunya. N_PA_Result tetap ditimpa di tempat dan tidak berubah bentuk.',
        'database/ddl/DDL-2026-168-pa-riwayat-hasil/forward.sql',
        'docs/changes/DDL-2026-168-pa-riwayat-hasil/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Hasil. Kalibrasi, terbit, dan rilis leader tidak dipotret karena sudah tercatat di N_PA_Calibration_History dan kolom Published_* N_PA_Result.',
        NULL
    );
END;
GO
