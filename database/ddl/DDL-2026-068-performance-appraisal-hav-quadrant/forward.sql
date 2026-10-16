/* Change ID: DDL-2026-068-performance-appraisal-hav-quadrant
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: tidak ada tabel lain; berdiri sendiri.
   Scope: kamus sembilan kuadran HAV Map. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50150, 'DDL-2026-068 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

IF OBJECT_ID('N_PA_Hav_Quadrant', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Hav_Quadrant (
        Id_PA_Hav_Quadrant int IDENTITY(1,1) NOT NULL,
        Kode_Kuadran varchar(40) NOT NULL,
        Nama_Kuadran nvarchar(100) NOT NULL,
        Nomor_Box smallint NOT NULL,
        Tingkat_Performa varchar(20) NOT NULL,
        Tingkat_Kompetensi varchar(20) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Warna varchar(20) NULL,
        Urutan_Tampil smallint NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Hav_Quadrant_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Hav_Quadrant_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Hav_Quadrant
            PRIMARY KEY (Id_PA_Hav_Quadrant),
        CONSTRAINT CK_PA_Hav_Quadrant_Nomor_Box
            CHECK (Nomor_Box >= 1),
        CONSTRAINT CK_PA_Hav_Quadrant_Urutan
            CHECK (Urutan_Tampil >= 1)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'UX_PA_Hav_Quadrant_Kode'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Hav_Quadrant_Kode
        ON N_PA_Hav_Quadrant (Kode_Kuadran);
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'UX_PA_Hav_Quadrant_Box_Aktif'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Hav_Quadrant_Box_Aktif
        ON N_PA_Hav_Quadrant (Nomor_Box)
        WHERE Flag_Aktif = 'Y';
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'UX_PA_Hav_Quadrant_Kombinasi_Aktif'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Hav_Quadrant_Kombinasi_Aktif
        ON N_PA_Hav_Quadrant (Tingkat_Performa, Tingkat_Kompetensi)
        WHERE Flag_Aktif = 'Y';
END;

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant') AND name = 'IX_PA_Hav_Quadrant_Lookup'
)
BEGIN
    CREATE INDEX IX_PA_Hav_Quadrant_Lookup
        ON N_PA_Hav_Quadrant (Tingkat_Performa, Tingkat_Kompetensi, Flag_Aktif)
        INCLUDE (Kode_Kuadran, Nama_Kuadran, Nomor_Box, Warna);
END;
GO

IF (
    SELECT COUNT(*) FROM sys.columns
    WHERE object_id = OBJECT_ID('N_PA_Hav_Quadrant')
      AND name IN (
          'Id_PA_Hav_Quadrant', 'Kode_Kuadran', 'Nama_Kuadran', 'Nomor_Box',
          'Tingkat_Performa', 'Tingkat_Kompetensi', 'Deskripsi', 'Warna',
          'Urutan_Tampil', 'Flag_Aktif',
          'Created_At', 'Created_By', 'Updated_At', 'Updated_By'
      )
) <> 14
    THROW 50151, 'DDL-2026-068: kolom HAV Quadrant tidak lengkap.', 1;

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Hav_Quadrant')
      AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50152, 'DDL-2026-068: CHECK HAV Quadrant disabled atau tidak trusted.', 1;
GO
