/* Change ID: DDL-2026-181-rbac-tingkat-peran */
/* RBAC: master tingkat peran (System Admin > Super Admin > Admin > Pengguna) dan
   tingkat setiap peran. Aditif & idempoten. Tanpa tabel/kolom ini aplikasi memakai
   nilai bawaan yang sama (TingkatPeran). */
IF OBJECT_ID('dbo.N_HRIS_Tingkat_Peran', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Tingkat_Peran
    (
        Kode_Tingkat varchar(20) NOT NULL,
        Nama_Tingkat varchar(50) NOT NULL,
        Peringkat int NOT NULL,
        Boleh_Angkat char(1) NOT NULL CONSTRAINT DF_HRIS_Tingkat_Peran_Angkat DEFAULT ('T'),
        Boleh_Ubah_Matriks char(1) NOT NULL CONSTRAINT DF_HRIS_Tingkat_Peran_Matriks DEFAULT ('T'),
        Boleh_Tugaskan char(1) NOT NULL CONSTRAINT DF_HRIS_Tingkat_Peran_Tugaskan DEFAULT ('T'),
        Keterangan nvarchar(300) NULL,
        Flag_Aktif char(1) NOT NULL CONSTRAINT DF_HRIS_Tingkat_Peran_Aktif DEFAULT ('Y'),
        Updated_At datetime2 NULL,
        Updated_By int NULL,
        CONSTRAINT PK_HRIS_Tingkat_Peran PRIMARY KEY (Kode_Tingkat),
        CONSTRAINT UQ_HRIS_Tingkat_Peran_Peringkat UNIQUE (Peringkat),
        CONSTRAINT CK_HRIS_Tingkat_Peran_Flag CHECK (Boleh_Angkat IN ('Y', 'T') AND Boleh_Ubah_Matriks IN ('Y', 'T')
            AND Boleh_Tugaskan IN ('Y', 'T') AND Flag_Aktif IN ('Y', 'T'))
    );
END;
GO
IF COL_LENGTH('dbo.N_HRIS_Role_Functional', 'Kode_Tingkat') IS NULL
    ALTER TABLE dbo.N_HRIS_Role_Functional ADD Kode_Tingkat varchar(20) NULL;
GO
IF NOT EXISTS (SELECT 1 FROM sys.foreign_keys WHERE name = 'FK_HRIS_Role_Functional_Tingkat')
    ALTER TABLE dbo.N_HRIS_Role_Functional
        ADD CONSTRAINT FK_HRIS_Role_Functional_Tingkat FOREIGN KEY (Kode_Tingkat) REFERENCES dbo.N_HRIS_Tingkat_Peran (Kode_Tingkat);
GO
