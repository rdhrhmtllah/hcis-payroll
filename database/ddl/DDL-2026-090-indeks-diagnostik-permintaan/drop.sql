-- DDL-2026-090-indeks-diagnostik-permintaan — pembatalan / pembersihan MANUAL
--
-- BERKAS INI TIDAK BOLEH DIJALANKAN OLEH AGEN. Ia alat bantu reset bagi manusia.
--
-- Menghapus indeks tidak menghilangkan data apa pun; yang hilang hanya
-- percepatannya. Halaman /admin/activity akan kembali memindai indeks clustered
-- pada tiap agregat.

IF DB_NAME() NOT IN ('Web_HRIS', 'Web_HRIS_Cut_Off')
BEGIN
    RAISERROR('drop.sql DDL-2026-090 dijalankan pada basis data yang salah.', 16, 1);
    SET NOEXEC ON;
END;
GO

DROP INDEX IF EXISTS IX_N_HRIS_Request_Diagnostics_Created_Penutup
    ON dbo.N_HRIS_Request_Diagnostics;
GO

DROP INDEX IF EXISTS IX_N_HRIS_Request_Diagnostics_Id
    ON dbo.N_HRIS_Request_Diagnostics;
GO

SET NOEXEC OFF;
GO

-- ── PEMBERSIHAN LANJUTAN, SETELAH indeks penutup terbukti dipakai ────────────
--
-- IX_N_HRIS_Request_Diagnostics_Created berkunci sama persis dengan indeks
-- penutup di BAGIAN 1 (Created_At) tetapi tanpa satu pun INCLUDE, jadi ia jadi
-- bagian sejati dari indeks baru: tiap kueri yang bisa dilayaninya bisa dilayani
-- indeks penutup juga. Ia tetap dibiarkan hidup oleh forward.sql supaya
-- penerapan bisa dibatalkan tanpa meninggalkan tabel tanpa indeks waktu sama
-- sekali.
--
-- Buang HANYA setelah dipastikan indeks penutup benar-benar menerima seek di
-- production (user_seeks > 0 pada kueri (c) di forward.sql BAGIAN 4):
--
--   DROP INDEX IF EXISTS IX_N_HRIS_Request_Diagnostics_Created
--       ON dbo.N_HRIS_Request_Diagnostics;
