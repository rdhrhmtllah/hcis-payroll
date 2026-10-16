-- DDL-2026-091 — pembatalan MANUAL. TIDAK BOLEH DIJALANKAN OLEH AGEN.
--
-- Membuang kolom akan MENGHAPUS datanya. Di production kolomnya baru dibuat dan
-- seluruhnya NULL, jadi tak ada yang hilang — tetapi di development kolom ini
-- sudah ada sejak sebelum perubahan ini dan berisi data (1 baris terisi per
-- 9 September 2026). Jangan pernah menjalankan berkas ini di development.
--
-- Membuangnya juga mengembalikan dua widget dashboard cuti ke keadaan 500.

IF DB_NAME() NOT IN ('Web_HRIS', 'Web_HRIS_Cut_Off')
BEGIN
    RAISERROR('drop.sql DDL-2026-091 dijalankan pada basis data yang salah.', 16, 1);
    SET NOEXEC ON;
END;
GO

-- ALTER TABLE dbo.Karyawan DROP COLUMN Tanggal_Akhir_Probation;
GO

SET NOEXEC OFF;
GO
