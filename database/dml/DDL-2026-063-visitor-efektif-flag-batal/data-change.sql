-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-063-visitor-efektif-flag-batal
--
-- Meluruskan Flag_Batal agar cocok dengan Status_Efektif.
--
-- JALANKAN SESUDAH forward.sql.
--
-- KENAPA PERLU
--   Isi kolom ini sekarang TIDAK BISA DIPERCAYA. Tidak ada kode yang pernah
--   menulisnya, tetapi nilainya tidak seragam, jadi ada yang mengubahnya di
--   luar aplikasi. Keadaan development per 2026-09-01:
--
--     N_VISITOR_Permit_Efektif   AKTIF      Flag_Batal='T'    1 baris
--                                AKTIF      Flag_Batal='Y'    8 baris  <-- salah
--                                DIBATALKAN Flag_Batal='T'    3 baris  <-- salah
--                                DIBATALKAN Flag_Batal='Y'    2 baris
--
--     N_VISITOR_Tamu_Efektif     AKTIF      Flag_Batal='T'    1 baris
--                                AKTIF      Flag_Batal='Y'   76 baris  <-- salah
--
--   Delapan permit dan 76 kunjungan yang MASIH AKTIF bertanda "dibatalkan".
--   Kalau kolomnya mulai dibaca tanpa diluruskan lebih dulu, akses yang sah
--   akan terbaca sebagai dicabut.
--
-- CAKUPAN
--   Tabel    : N_VISITOR_Permit_Efektif, N_VISITOR_Tamu_Efektif
--   Predikat : setiap baris yang Flag_Batal-nya tidak cocok dengan
--              Status_Efektif, termasuk yang NULL
--   Sumber   : Status_Efektif pada baris yang sama. Tidak ada tabel lain yang
--              dibaca, tidak ada baris yang dihapus.
--
--   Perkiraan tersentuh di development: 11 baris permit + 76 baris tamu.
--   Angkanya wajib dicocokkan dengan hasil pratinjau di bawah sebelum UPDATE
--   dijalankan; bila jauh berbeda, berhenti dan periksa dulu.
--
-- Idempoten: dijalankan ulang menyentuh 0 baris karena predikatnya menyaring
-- baris yang sudah benar.

-- Pratinjau. Jalankan lebih dulu, cocokkan jumlahnya, baru jalankan UPDATE.
SELECT 'N_VISITOR_Permit_Efektif' AS tabel, Status_Efektif,
       ISNULL(Flag_Batal, '(null)') AS flag_sekarang,
       CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END AS flag_seharusnya,
       COUNT(*) AS baris
FROM N_VISITOR_Permit_Efektif
WHERE Flag_Batal IS NULL
   OR Flag_Batal <> CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END
GROUP BY Status_Efektif, Flag_Batal
UNION ALL
SELECT 'N_VISITOR_Tamu_Efektif', Status_Efektif,
       ISNULL(Flag_Batal, '(null)'),
       CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END,
       COUNT(*)
FROM N_VISITOR_Tamu_Efektif
WHERE Flag_Batal IS NULL
   OR Flag_Batal <> CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END
GROUP BY Status_Efektif, Flag_Batal
ORDER BY tabel, Status_Efektif;
GO

UPDATE N_VISITOR_Permit_Efektif
SET Flag_Batal = CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END
WHERE Flag_Batal IS NULL
   OR Flag_Batal <> CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END;
GO

UPDATE N_VISITOR_Tamu_Efektif
SET Flag_Batal = CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END
WHERE Flag_Batal IS NULL
   OR Flag_Batal <> CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END;
GO

-- Verifikasi. Kedua angka sisa_selisih harus 0.
SELECT 'N_VISITOR_Permit_Efektif' AS tabel, COUNT(*) AS sisa_selisih
FROM N_VISITOR_Permit_Efektif
WHERE Flag_Batal IS NULL
   OR Flag_Batal <> CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END
UNION ALL
SELECT 'N_VISITOR_Tamu_Efektif', COUNT(*)
FROM N_VISITOR_Tamu_Efektif
WHERE Flag_Batal IS NULL
   OR Flag_Batal <> CASE WHEN Status_Efektif = 'DIBATALKAN' THEN 'Y' ELSE 'T' END;
GO
