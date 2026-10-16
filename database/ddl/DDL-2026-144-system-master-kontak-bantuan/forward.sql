-- Change ID: DDL-2026-144-system-master-kontak-bantuan
-- Kontak bantuan GLOBAL lintas modul (milik SYSTEM). Satu baris = satu kontak
-- untuk satu fitur di satu modul, mis. VISITOR/PERMIT, VISITOR/TAMU, KPI/PA.
-- Dikelola dari halaman master masing-masing modul, yang hanya melihat
-- barisnya sendiri (lingkup Kode_Grup_Fitur + Kode_Fitur).

IF OBJECT_ID('dbo.N_HRIS_Master_Kontak_Bantuan', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.N_HRIS_Master_Kontak_Bantuan (
        Id_Kontak_Bantuan int IDENTITY(1,1) NOT NULL
            CONSTRAINT PK_HRIS_Kontak_Bantuan PRIMARY KEY,

        -- Lingkup: modul + fitur di dalamnya
        Kode_Grup_Fitur varchar(20) NOT NULL,   -- ESS / KPI / LMS / VISITOR / SYSTEM
        Kode_Fitur      varchar(50) NOT NULL,   -- PERMIT / TAMU / PA / SOP / ... (huruf besar)

        -- Isi kontak
        Nama_Kontak nvarchar(100) NOT NULL,
        Keterangan  nvarchar(150) NULL,         -- bagian / peran
        No_HP       varchar(20)   NULL,         -- juga untuk WhatsApp
        Email       varchar(150)  NULL,
        Jam_Layanan nvarchar(100) NULL,

        -- Tampilan & status
        Urutan_Tampil int NOT NULL
            CONSTRAINT DF_HRIS_Kontak_Bantuan_Urutan DEFAULT (0),
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_HRIS_Kontak_Bantuan_Aktif DEFAULT ('Y'),

        -- Jejak
        Created_At datetime2 NOT NULL
            CONSTRAINT DF_HRIS_Kontak_Bantuan_Created DEFAULT (sysdatetime()),
        Created_By varchar(100) NULL,
        Updated_At datetime2 NULL,
        Updated_By varchar(100) NULL,

        CONSTRAINT CK_HRIS_Kontak_Bantuan_Aktif CHECK (Flag_Aktif IN ('Y', 'T')),
        CONSTRAINT CK_HRIS_Kontak_Bantuan_Kontak CHECK (No_HP IS NOT NULL OR Email IS NOT NULL)
    );
END;
GO

IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_HRIS_Kontak_Bantuan_Lingkup')
BEGIN
    CREATE INDEX IX_HRIS_Kontak_Bantuan_Lingkup
        ON dbo.N_HRIS_Master_Kontak_Bantuan (Kode_Grup_Fitur, Kode_Fitur, Flag_Aktif, Urutan_Tampil)
        INCLUDE (Nama_Kontak, Keterangan, No_HP, Email, Jam_Layanan);
END;
GO
