/* Change ID: DDL-2026-168-rbac-mode-selisih */
/* RBAC Fase 3a: sakelar mode keputusan akses per modul + catatan selisih
   aturan LAMA vs BARU. Aditif & idempoten. Tanpa baris mode = LAMA. */
IF OBJECT_ID('dbo.N_HRIS_Akses_Mode_Modul', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Akses_Mode_Modul
    (
        Id_Grup_Fitur int NOT NULL,
        Mode varchar(10) NOT NULL CONSTRAINT DF_HRIS_Akses_Mode_Modul_Mode DEFAULT ('LAMA'),
        Updated_At datetime2 NULL,
        Updated_By int NULL,
        CONSTRAINT PK_HRIS_Akses_Mode_Modul PRIMARY KEY (Id_Grup_Fitur),
        CONSTRAINT CK_HRIS_Akses_Mode_Modul_Mode CHECK (Mode IN ('LAMA', 'BAYANGAN', 'BARU'))
    );
END;
GO
IF OBJECT_ID('dbo.N_HRIS_Akses_Selisih', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Akses_Selisih
    (
        Id_Akses_Selisih bigint IDENTITY(1,1) NOT NULL,
        Id_User int NOT NULL,
        Jenis_page varchar(100) NOT NULL,
        Aksi varchar(20) NOT NULL,
        Keputusan_Lama char(1) NOT NULL,
        Keputusan_Baru char(1) NOT NULL,
        Jumlah int NOT NULL CONSTRAINT DF_HRIS_Akses_Selisih_Jumlah DEFAULT (1),
        Pertama_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Akses_Selisih_Pertama DEFAULT (sysdatetime()),
        Terakhir_At datetime2 NOT NULL CONSTRAINT DF_HRIS_Akses_Selisih_Terakhir DEFAULT (sysdatetime()),
        CONSTRAINT PK_HRIS_Akses_Selisih PRIMARY KEY (Id_Akses_Selisih),
        CONSTRAINT UQ_HRIS_Akses_Selisih UNIQUE (Id_User, Jenis_page, Aksi)
    );
END;
GO
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Akses_Selisih_Halaman' AND object_id = OBJECT_ID('dbo.N_HRIS_Akses_Selisih'))
    CREATE INDEX IX_HRIS_Akses_Selisih_Halaman ON dbo.N_HRIS_Akses_Selisih (Jenis_page, Terakhir_At);
GO
