-- Target: Web_HRIS (development). Production hanya melalui SQL Tracker.
-- Change ID: DDL-2026-062-visitor-permit-efektif-nik
--
-- Mengisi N_VISITOR_Permit_Efektif.NIK untuk baris yang sudah terlanjur
-- tersimpan tanpa nomor induk. Kode yang memperbaiki penyebabnya hanya
-- mengisi kolom itu saat permit di-sync; baris lama tidak akan tersentuh
-- sampai permitnya disetujui atau di-sync ulang.
--
-- JALANKAN SESUDAH forward.sql.
--
-- CAKUPAN
--   Tabel     : N_VISITOR_Permit_Efektif
--   Predikat  : NIK NULL atau kosong, DAN Karyawan.No_Induk_Karyawan terisi
--   Sumber    : Karyawan.No_Induk_Karyawan, dijodohkan lewat
--               Kode_Karyawan_Pemohon
--
-- Baris yang NIK-nya sudah terisi sengaja TIDAK disentuh. Ada dua baris
-- semacam itu di development yang diisi manual di luar aplikasi; menimpanya
-- akan menghapus satu-satunya bukti nilai yang pernah ada di sana.
--
-- DAMPAK TERUKUR DI DEVELOPMENT (dihitung 2026-09-01)
--   12 baris di tabel, 10 di antaranya NIK kosong.
--   Dari 10 itu, 0 yang bisa diisi: semuanya milik karyawan contoh
--   (A3, A4, A5, B6, C9, ...) yang No_Induk_Karyawan-nya memang kosong
--   di master. Jadi di development skrip ini SAH bila menyentuh 0 baris.
--   Angka nyatanya baru muncul di production.
--
-- Idempoten: dijalankan ulang tidak mengubah apa pun, karena baris yang
-- sudah terisi langsung tersaring keluar oleh predikatnya sendiri.

-- Pratinjau. Jalankan lebih dulu, cocokkan jumlahnya, baru jalankan UPDATE.
SELECT
    pe.No_Transaksi,
    pe.Kode_Karyawan_Pemohon,
    pe.Nama_Pemohon,
    k.No_Induk_Karyawan AS NIK_akan_diisi
FROM N_VISITOR_Permit_Efektif AS pe
INNER JOIN Karyawan AS k
    ON k.Kode_Karyawan = pe.Kode_Karyawan_Pemohon
WHERE (pe.NIK IS NULL OR LTRIM(RTRIM(pe.NIK)) = '')
  AND k.No_Induk_Karyawan IS NOT NULL
  AND LTRIM(RTRIM(k.No_Induk_Karyawan)) <> ''
ORDER BY pe.Id_Permit_Efektif;
GO

UPDATE pe
SET pe.NIK = LTRIM(RTRIM(k.No_Induk_Karyawan))
FROM N_VISITOR_Permit_Efektif AS pe
INNER JOIN Karyawan AS k
    ON k.Kode_Karyawan = pe.Kode_Karyawan_Pemohon
WHERE (pe.NIK IS NULL OR LTRIM(RTRIM(pe.NIK)) = '')
  AND k.No_Induk_Karyawan IS NOT NULL
  AND LTRIM(RTRIM(k.No_Induk_Karyawan)) <> '';
GO

-- Verifikasi. Sisa yang masih kosong seharusnya hanya permit milik karyawan
-- yang No_Induk_Karyawan-nya memang belum diisi di master Karyawan.
SELECT
    COUNT(*)                                                              AS total,
    SUM(CASE WHEN NIK IS NULL OR LTRIM(RTRIM(NIK)) = '' THEN 1 ELSE 0 END) AS masih_kosong
FROM N_VISITOR_Permit_Efektif;
GO
