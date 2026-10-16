/* Change ID: DDL-2026-093-pa-pintu-hclearn-nyata — PEMBATALAN.

   TIDAK DIJALANKAN AGEN. Berkas ini hanya jalan pulang bila perubahannya harus dicabut,
   dan pencabutannya dilakukan manusia lewat SQL Tracker.

   PERHATIAN: mencabut kolom-kolom ini MEMBUANG seluruh pintu pengisian yang sudah terbit —
   `Api_Ref`, `Short_Token`, `Akses_Otp`, dan `Link_Ujian` adalah satu-satunya salinan
   tautan penilai di sisi kita. Sesudah dicabut, tidak ada cara mengembalikannya selain
   menerbitkan ulang seluruh pintu lewat HC Learn, dan token lama mati saat itu juga.

   `Process_Id` yang hilang memutus jejak antrean: kiriman yang masih digarap HC Learn tidak
   bisa lagi ditarik hasilnya, dan tidak ada cara mengetahui nasibnya selain mengirim ulang —
   yang berarti pintu KEDUA bagi orang yang sudah punya.

   Urutannya disengaja: indeks dan CHECK dulu, baru kolomnya. SQL Server menolak membuang
   kolom yang masih dirujuk keduanya.
*/

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'IX_PA_AsgIntg_Proses'
)
    DROP INDEX IX_PA_AsgIntg_Proses ON N_PA_Assignment_Integration;
GO

IF EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'UX_PA_AsgIntg_ApiRef'
)
    DROP INDEX UX_PA_AsgIntg_ApiRef ON N_PA_Assignment_Integration;
GO

IF EXISTS (
    SELECT 1 FROM sys.check_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'CK_PA_AsgIntg_Status'
)
    ALTER TABLE N_PA_Assignment_Integration DROP CONSTRAINT CK_PA_AsgIntg_Status;
GO

/* Nilai bawaannya punya nama sendiri dan harus dilepas sebelum kolomnya dibuang. */
IF EXISTS (
    SELECT 1 FROM sys.default_constraints
    WHERE parent_object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = 'DF_PA_AsgIntg_Status'
)
    ALTER TABLE N_PA_Assignment_Integration DROP CONSTRAINT DF_PA_AsgIntg_Status;
GO

DECLARE @kolom TABLE (nama sysname);

INSERT INTO @kolom (nama)
VALUES ('Api_Ref'), ('Short_Token'), ('Akses_Otp'), ('Link_Ujian'),
       ('Process_Id'), ('Status_Integrasi'), ('Pesan_Gagal'),
       ('Waktu_Mulai'), ('Waktu_Akhir');

DECLARE @nama sysname, @sql nvarchar(400);

DECLARE kursor CURSOR LOCAL FAST_FORWARD FOR
    SELECT nama FROM @kolom;

OPEN kursor;
FETCH NEXT FROM kursor INTO @nama;

WHILE @@FETCH_STATUS = 0
BEGIN
    IF EXISTS (
        SELECT 1 FROM sys.columns
        WHERE object_id = OBJECT_ID('N_PA_Assignment_Integration') AND name = @nama
    )
    BEGIN
        SET @sql = N'ALTER TABLE N_PA_Assignment_Integration DROP COLUMN ' + QUOTENAME(@nama) + N';';
        EXEC sp_executesql @sql;
    END;

    FETCH NEXT FROM kursor INTO @nama;
END;

CLOSE kursor;
DEALLOCATE kursor;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-093';
GO
