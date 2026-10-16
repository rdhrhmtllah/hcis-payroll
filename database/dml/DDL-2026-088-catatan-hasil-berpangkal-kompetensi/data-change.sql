/* Change ID: DDL-2026-088-catatan-hasil-berpangkal-kompetensi (bagian DML)
   Target: Web_HRIS development; production only through SQL Tracker.
   Dependencies: forward.sql perubahan ini. WAJIB dijalankan SESUDAHNYA — kolom
                 `PA_Competency_Id` belum ada sebelum itu.
   Scope: N_PA_Result_Note — mengisi kolom baru pada baris yang sudah ada.

   YANG DISELESAIKAN

   Memindahkan 88 catatan yang sudah ada dari "jenis catatan" ke "kompetensi", memakai
   padanan yang memang sudah setara namanya:

       STRENGTH          -> QL01 Strength
       DEVELOPMENT_AREA  -> QL02 Development Area

   DICOCOKKAN LEWAT KODE, BUKAN ID

   Id berbeda antara dev dan production. Angka yang ditulis tangan di sini akan menaruh
   catatan orang di bawah kategori yang keliru pada salah satu lingkungan, dan tidak ada
   satu pun galat yang menandainya — kalimatnya tetap tampil, hanya di bawah judul yang
   salah.

   YANG TIDAK DIPETAKAN AKAN TERTANGKAP PENJAGA

   Bila kelak ada jenis catatan yang tidak punya padanan kompetensi, barisnya tersisa
   NULL dan penjaga di akhir skrip menolak. Itu disengaja: catatan tanpa kategori tidak
   bisa ditampilkan di panel mana pun, dan lebih baik ketahuan sekarang daripada muncul
   sebagai panel kosong di layar karyawan.

   KOLOM LAMA DIBIARKAN TERISI

   Nilainya tidak dikosongkan. Selama kolomnya belum dicabut, ia jejak dari mana baris ini
   berasal — dan satu-satunya jalan mundur bila pemetaannya ternyata keliru.
*/

IF DB_NAME() <> 'Web_HRIS'
    THROW 50535, 'DML DDL-2026-088 hanya boleh dijalankan pada Web_HRIS.', 1;
GO

SET NOCOUNT ON;
SET XACT_ABORT ON;

BEGIN TRANSACTION;

UPDATE n
SET n.PA_Competency_Id = k.Id_PA_Competency
FROM N_PA_Result_Note n
JOIN N_PA_Result_Note_Type t ON t.Id_PA_Result_Note_Type = n.PA_Result_Note_Type_Id
JOIN N_PA_Competency k ON k.Kode_Competency = CASE t.Kode_Type
        WHEN 'STRENGTH' THEN 'QL01'
        WHEN 'DEVELOPMENT_AREA' THEN 'QL02'
        ELSE NULL
    END
WHERE n.PA_Competency_Id IS NULL;

COMMIT TRANSACTION;
GO

/* ------------------------------------------------------------------ *
 * Penjaga                                                             *
 * ------------------------------------------------------------------ */

DECLARE @belum int = (SELECT COUNT(*) FROM N_PA_Result_Note WHERE PA_Competency_Id IS NULL);

IF @belum > 0
    THROW 50536, 'Masih ada catatan tanpa kompetensi; jenis catatannya tidak punya padanan.', 1;

/* VERIFIKASI — tiap kompetensi kualitatif beserta jumlah catatannya. */
SELECT k.Kode_Competency, k.Nama_Competency, COUNT(n.Id_PA_Result_Note) AS jumlah_catatan
FROM N_PA_Competency k
LEFT JOIN N_PA_Result_Note n ON n.PA_Competency_Id = k.Id_PA_Competency
JOIN N_PA_Competency_Type t ON t.Id_PA_Competency_Type = k.PA_Competency_Type_Id
WHERE t.Kode_Type = 'KUALITATIF'
GROUP BY k.Kode_Competency, k.Nama_Competency
ORDER BY k.Kode_Competency;
