/* Change ID: DDL-2026-066-performance-appraisal-cycle
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: tidak ada tabel lain; berdiri sendiri.
   Scope: header siklus Performance Appraisal. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50120, 'DDL-2026-066 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle (
        Id_PA_Cycle int IDENTITY(1,1) NOT NULL,
        Kode_Cycle varchar(50) NOT NULL,
        Nama_Cycle nvarchar(150) NOT NULL,
        Tahun smallint NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Periode_Dinilai_Dari date NULL,
        Periode_Dinilai_Sampai date NULL,
        Pengisian_Dari datetime2(0) NULL,
        Pengisian_Sampai datetime2(0) NULL,
        Tanggal_Acuan_Organisasi date NULL,
        PA_Cycle_Sumber_Id int NULL,
        Status_Cycle varchar(20) NOT NULL
            CONSTRAINT DF_PA_Cycle_Status DEFAULT ('DRAFT'),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Aktif DEFAULT ('Y'),
        Locked_At datetime2(0) NULL,
        Locked_By int NULL,
        Published_At datetime2(0) NULL,
        Published_By int NULL,
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle
            PRIMARY KEY (Id_PA_Cycle),
        CONSTRAINT CK_PA_Cycle_Tahun
            CHECK (Tahun >= 2000),
        CONSTRAINT CK_PA_Cycle_Periode_Dinilai
            CHECK (
                Periode_Dinilai_Dari IS NULL
                OR Periode_Dinilai_Sampai IS NULL
                OR Periode_Dinilai_Sampai >= Periode_Dinilai_Dari
            ),
        CONSTRAINT CK_PA_Cycle_Pengisian
            CHECK (
                Pengisian_Dari IS NULL
                OR Pengisian_Sampai IS NULL
                OR Pengisian_Sampai >= Pengisian_Dari
            ),
        CONSTRAINT CK_PA_Cycle_Source_Different
            CHECK (PA_Cycle_Sumber_Id IS NULL OR PA_Cycle_Sumber_Id <> Id_PA_Cycle)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Cycle')
      AND name = 'FK_PA_Cycle_Source'
)
BEGIN
    ALTER TABLE N_PA_Cycle WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Source
        FOREIGN KEY (PA_Cycle_Sumber_Id)
        REFERENCES N_PA_Cycle (Id_PA_Cycle);
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle')
      AND name = 'UX_PA_Cycle_Kode'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Cycle_Kode
        ON N_PA_Cycle (Kode_Cycle);
END;
GO

IF (
    SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Cycle')
      AND name IN (
          'Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Deskripsi',
          'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai',
          'Pengisian_Dari', 'Pengisian_Sampai', 'Tanggal_Acuan_Organisasi',
          'PA_Cycle_Sumber_Id', 'Status_Cycle', 'Flag_Aktif',
          'Locked_At', 'Locked_By', 'Published_At', 'Published_By',
          'Created_At', 'Created_By', 'Updated_At', 'Updated_By'
      )
) <> 21
    THROW 50121, 'DDL-2026-066: kolom PA Cycle tidak lengkap.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Cycle')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50122, 'DDL-2026-066: FK cycle disabled atau tidak trusted.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Cycle')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50123, 'DDL-2026-066: CHECK cycle disabled atau tidak trusted.', 1;
GO
