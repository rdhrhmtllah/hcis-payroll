/* Change ID: DDL-2026-177-pa-level-pa-karyawan
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: N_PA_Participant (DDL-2026-066 dst.), HRIS_Level.
   Scope: koreksi Level PA per karyawan (khusus PA, HRIS tidak diubah) + kolom jejak level HRIS
          asli di peserta siklus.
   Dokumentasi: docs/changes/DDL-2026-177-pa-level-pa-karyawan/README.md
*/

IF OBJECT_ID('N_PA_Karyawan_Level_PA', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Karyawan_Level_PA (
        Id_PA_Karyawan_Level_PA int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_PA_Karyawan_Level_PA PRIMARY KEY,
        Kode_Perusahaan varchar(3) NOT NULL,
        Kode_Karyawan varchar(255) NOT NULL,
        ID_Level_PA bigint NOT NULL,
        ID_Level_HRIS_Saat_Dibuat bigint NULL,
        ID_Level_Jabatan_Saat_Dibuat bigint NULL,
        Alasan nvarchar(500) NOT NULL,
        Berlaku_Dari date NOT NULL,
        Berlaku_Sampai date NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Karyawan_Level_PA_Flag_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Karyawan_Level_PA_Created_At DEFAULT (sysdatetime()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        Cancelled_At datetime2(0) NULL,
        Cancelled_By int NULL,
        Alasan_Cabut nvarchar(500) NULL
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE object_id = OBJECT_ID('N_PA_Karyawan_Level_PA') AND name = 'UX_PA_Karyawan_Level_PA_Karyawan_Aktif')
    CREATE UNIQUE INDEX UX_PA_Karyawan_Level_PA_Karyawan_Aktif
        ON N_PA_Karyawan_Level_PA (Kode_Perusahaan, Kode_Karyawan) WHERE Flag_Aktif = 'Y';
GO

IF COL_LENGTH('N_PA_Participant', 'ID_Level_HRIS_Saat_Cycle') IS NULL
    ALTER TABLE N_PA_Participant ADD ID_Level_HRIS_Saat_Cycle bigint NULL;
GO

IF COL_LENGTH('N_PA_Participant', 'Nama_Level_HRIS_Saat_Cycle') IS NULL
    ALTER TABLE N_PA_Participant ADD Nama_Level_HRIS_Saat_Cycle varchar(100) NULL;
GO

IF COL_LENGTH('N_PA_Participant', 'PA_Karyawan_Level_PA_Id') IS NULL
    ALTER TABLE N_PA_Participant ADD PA_Karyawan_Level_PA_Id int NULL
        CONSTRAINT FK_PA_Participant_Karyawan_Level_PA REFERENCES N_PA_Karyawan_Level_PA (Id_PA_Karyawan_Level_PA);
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-177')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path,
        Target_Development, Status_Development, Status_SQL_Tracker,
        Status_Production, Status_Verification, Catatan, Created_By
    )
    VALUES (
        'DDL-2026-177',
        'DDL',
        'Koreksi Level PA per karyawan Performance Appraisal',
        'Tabel N_PA_Karyawan_Level_PA (level PA pengganti level HRIS untuk karyawan tertentu, wajib alasan, berlaku dari/sampai, level HRIS saat dibuat untuk deteksi basi) dan kolom jejak di N_PA_Participant: ID_Level_HRIS_Saat_Cycle, Nama_Level_HRIS_Saat_Cycle, PA_Karyawan_Level_PA_Id.',
        'database/ddl/DDL-2026-177-pa-level-pa-karyawan/forward.sql',
        'docs/changes/DDL-2026-177-pa-level-pa-karyawan/README.md',
        'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING',
        'KPI/Performance Appraisal/Peserta. Kolom peserta nullable; peserta lama tetap tanpa jejak (berarti level HRIS).',
        NULL
    );
END;
GO
