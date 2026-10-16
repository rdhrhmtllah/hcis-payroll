/* =====================================================================
   DML-2026-120-reset-dokumen-sop-dev
   ---------------------------------------------------------------------
   KHUSUS DEVELOPMENT (Web_HRIS). BUKAN untuk production, tidak masuk
   SQL Tracker.

   Mengosongkan seluruh dokumen SOP di dev (53 dokumen, isinya demo/uji:
   "[DEMO] ...", "CONTOH ...", "dasdasda") sebelum register dokumen nyata
   EMI diimpor dari Excel.

   Jumlah baris per 2026-09-17 (dibaca sebelum dijalankan):
     N_SOP_Item_Versi_Review_Lampiran   12
     N_SOP_Item_Versi_Review            17
     N_SOP_Item_Versi_Pengesah          98
     N_SOP_Item_Versi_Berkas             0
     N_SOP_Progress                   1161   (progres baca karyawan)
     N_SOP_Coverage_Rollup              28
     N_SOP_Paket_Draft_Item_Versi        0
     N_SOP_Paket_Draft_Item             13
     N_SOP_Paket_Item                   31
     N_SOP_Paket_Versi_Item             31
     N_SOP_Paket_Versi                   8   (+ pointer di 8 Master_Paket)
     N_SOP_Item_Versi                   59
     N_SOP_Dokumen                      53

   DIBIARKAN: master (jenis, APQC, tahap, kategori, prioritas, setting),
   N_SOP_Master_Paket & section-nya, penjadwalan, Kepatuhan_Riwayat, dan
   N_SOP_Log (jejak; Id-nya menunjuk baris yang sudah tiada, tanpa FK).
   Berkas lampiran review di GCS TIDAK ikut terhapus (menjadi yatim).

   Tanpa TRUNCATE/DROP. Satu transaksi: gagal satu, batal semua.
   ===================================================================== */

SET XACT_ABORT ON;
SET NOCOUNT OFF;

IF DB_NAME() <> 'Web_HRIS'
BEGIN
    RAISERROR('DML-2026-120 hanya untuk development Web_HRIS.', 16, 1);
    RETURN;
END;

BEGIN TRANSACTION;

DELETE l FROM N_SOP_Item_Versi_Review_Lampiran l
    JOIN N_SOP_Item_Versi_Review r ON r.Id_Review = l.Id_Review;
DELETE FROM N_SOP_Item_Versi_Review;
DELETE FROM N_SOP_Item_Versi_Pengesah;
DELETE FROM N_SOP_Item_Versi_Berkas;
DELETE FROM N_SOP_Progress;
DELETE FROM N_SOP_Coverage_Rollup;

DELETE FROM N_SOP_Paket_Draft_Item_Versi;
DELETE FROM N_SOP_Paket_Draft_Item;
DELETE FROM N_SOP_Paket_Item;

UPDATE N_SOP_Master_Paket SET Id_SOP_Paket_Versi_Aktif = NULL
    WHERE Id_SOP_Paket_Versi_Aktif IS NOT NULL;
DELETE FROM N_SOP_Paket_Versi_Item;
DELETE FROM N_SOP_Paket_Versi;

UPDATE N_SOP_Dokumen SET Id_Versi_Aktif = NULL WHERE Id_Versi_Aktif IS NOT NULL;
DELETE FROM N_SOP_Item_Versi;
DELETE FROM N_SOP_Dokumen;

COMMIT TRANSACTION;
