/* Change ID: DDL-2026-069-performance-appraisal-cycle-status
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: DDL-2026-066 (N_PA_Cycle harus sudah ada).
   Scope: master status siklus dan foreign key dari N_PA_Cycle.Status_Cycle. */

IF DB_NAME() <> 'Web_HRIS'
    THROW 50160, 'DDL-2026-069 hanya boleh dijalankan pada Web_HRIS.', 1;

IF OBJECT_ID('N_PA_Cycle', 'U') IS NULL
   OR COL_LENGTH('N_PA_Cycle', 'Status_Cycle') IS NULL
    THROW 50161, 'DDL-2026-069 membutuhkan N_PA_Cycle.Status_Cycle dari DDL-2026-066.', 1;
GO

IF OBJECT_ID('N_PA_Cycle_Status', 'U') IS NULL
BEGIN
    CREATE TABLE N_PA_Cycle_Status (
        Id_PA_Cycle_Status int IDENTITY(1,1) NOT NULL,
        Kode_Status varchar(20) NOT NULL,
        Nama_Status nvarchar(100) NOT NULL,
        Deskripsi nvarchar(500) NULL,
        Urutan_Alur smallint NOT NULL,
        Kode_Badge varchar(20) NOT NULL,
        Flag_Aktif char(1) NOT NULL
            CONSTRAINT DF_PA_Cycle_Status_Aktif DEFAULT ('Y'),
        Created_At datetime2(0) NOT NULL
            CONSTRAINT DF_PA_Cycle_Status_Created DEFAULT (SYSDATETIME()),
        Created_By int NULL,
        Updated_At datetime2(0) NULL,
        Updated_By int NULL,
        CONSTRAINT PK_PA_Cycle_Status
            PRIMARY KEY (Id_PA_Cycle_Status),
        CONSTRAINT UQ_PA_Cycle_Status_Kode
            UNIQUE (Kode_Status),
        CONSTRAINT CK_PA_Cycle_Status_Urutan
            CHECK (Urutan_Alur >= 1)
    );
END;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Cycle_Status')
      AND name = 'UX_PA_Cycle_Status_Urutan_Aktif'
)
BEGIN
    CREATE UNIQUE INDEX UX_PA_Cycle_Status_Urutan_Aktif
        ON N_PA_Cycle_Status (Urutan_Alur)
        WHERE Flag_Aktif = 'Y';
END;
GO
/* Seed status ditempatkan di forward.sql, bukan data-change.sql, karena foreign key
   di bawahnya menolak dipasang selama N_PA_Cycle memuat status yang belum terdaftar.
   Master ini kamus tertutup milik struktur, bukan data bisnis yang tumbuh. */

IF NOT EXISTS (SELECT 1 FROM N_PA_Cycle_Status WHERE Kode_Status = 'DRAFT')
    INSERT INTO N_PA_Cycle_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge)
    VALUES ('DRAFT', N'Draft',
            N'Baru dibuat. Tanggal boleh masih kosong dan belum ada yang dapat dijalankan.', 1, 'DRAFT');

IF NOT EXISTS (SELECT 1 FROM N_PA_Cycle_Status WHERE Kode_Status = 'PREPARED')
    INSERT INTO N_PA_Cycle_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge)
    VALUES ('PREPARED', N'Disiapkan',
            N'Peserta dan seluruh salinan sudah dibentuk dan masih boleh diubah. Penetapan penilai berjalan di sini.', 2, 'ACTIVE');

IF NOT EXISTS (SELECT 1 FROM N_PA_Cycle_Status WHERE Kode_Status = 'LOCKED')
    INSERT INTO N_PA_Cycle_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge)
    VALUES ('LOCKED', N'Dikunci',
            N'Salinan aturan penilai dibekukan dan penilai tidak dapat diubah lagi. Keranjang kasus tertahan wajib bersih sebelum masuk keadaan ini.', 3, 'ACTIVE');

IF NOT EXISTS (SELECT 1 FROM N_PA_Cycle_Status WHERE Kode_Status = 'OPEN')
    INSERT INTO N_PA_Cycle_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge)
    VALUES ('OPEN', N'Pengisian Dibuka',
            N'Jendela pengisian terbuka dan penilai boleh mengisi. Berbeda dari Dikunci walaupun sering berdekatan waktunya.', 4, 'ACTIVE');

IF NOT EXISTS (SELECT 1 FROM N_PA_Cycle_Status WHERE Kode_Status = 'CLOSED')
    INSERT INTO N_PA_Cycle_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge)
    VALUES ('CLOSED', N'Pengisian Ditutup',
            N'Tidak ada nilai baru yang masuk. Perhitungan, kalibrasi, dan review berjalan di sini; kemajuannya dibaca dari status hasil dan mesin approval, bukan dari status siklus.', 5, 'CLOSED');

IF NOT EXISTS (SELECT 1 FROM N_PA_Cycle_Status WHERE Kode_Status = 'PUBLISHED')
    INSERT INTO N_PA_Cycle_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge)
    VALUES ('PUBLISHED', N'Terbit',
            N'Hasil sudah diterbitkan dan karyawan boleh melihat kesimpulan akhirnya.', 6, 'CLOSED');

IF NOT EXISTS (SELECT 1 FROM N_PA_Cycle_Status WHERE Kode_Status = 'ARCHIVED')
    INSERT INTO N_PA_Cycle_Status (Kode_Status, Nama_Status, Deskripsi, Urutan_Alur, Kode_Badge)
    VALUES ('ARCHIVED', N'Diarsipkan',
            N'Siklus selesai dan disimpan sebagai riwayat. Hanya-baca dan hilang dari daftar aktif.', 7, 'ARCHIVED');
GO

IF EXISTS (
    SELECT 1 FROM N_PA_Cycle c
    WHERE NOT EXISTS (
        SELECT 1 FROM N_PA_Cycle_Status s WHERE s.Kode_Status = c.Status_Cycle
    )
)
    THROW 50162, 'DDL-2026-069: terdapat N_PA_Cycle.Status_Cycle yang tidak terdaftar di master. Perbaiki datanya sebelum foreign key dipasang.', 1;
GO

IF NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE parent_object_id = OBJECT_ID('N_PA_Cycle')
      AND name = 'FK_PA_Cycle_Status'
)
BEGIN
    ALTER TABLE N_PA_Cycle WITH CHECK
        ADD CONSTRAINT FK_PA_Cycle_Status
        FOREIGN KEY (Status_Cycle)
        REFERENCES N_PA_Cycle_Status (Kode_Status);
END;
GO

IF (SELECT COUNT(*) FROM sys.columns WHERE object_id = OBJECT_ID('N_PA_Cycle_Status')) <> 11
    THROW 50163, 'DDL-2026-069: kolom Cycle Status tidak lengkap.', 1;

IF (SELECT COUNT(*) FROM N_PA_Cycle_Status WHERE Flag_Aktif = 'Y') <> 7
    THROW 50164, 'DDL-2026-069: tujuh status siklus tidak lengkap.', 1;

IF EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE name = 'FK_PA_Cycle_Status' AND (is_disabled = 1 OR is_not_trusted = 1)
)
    THROW 50165, 'DDL-2026-069: FK status siklus disabled atau tidak trusted.', 1;
GO
