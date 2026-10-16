/* Change ID: DML-2026-165-sop-form-izin-unduh

   Sejak 10 Okt 2026 izin unduh contoh pengisian FORM (berkas PDF) mengikuti
   Mode_Akses item paket, sama seperti DOKUMEN. Sebelumnya FORM SELALU boleh
   diunduh dan Mode_Akses-nya diabaikan — wizard tak pernah menanyakannya,
   sehingga setiap item FORM tersimpan VIEW_ONLY.

   Keputusan user (10 Okt 2026): item FORM yang SUDAH ADA tetap boleh diunduh.
   Tanpa DML ini, begitu kodenya rilis seluruh contoh pengisian PDF mendadak
   menjadi "hanya lihat".

   Flag_Watermark ikut dimatikan: berkas yang boleh diunduh tak ber-tanda-air
   (SopAksesBerkas::watermarkTersimpan menyimpan 'T' untuk VIEW_UNDUH).

   WAJIB DIJALANKAN DI PRODUCTION SEBELUM PR KODENYA DI-MERGE.

   IDEMPOTEN: hanya baris FORM yang belum (VIEW_UNDUH, 'T') yang diubah.
   Menjalankan ulang = 0 baris. */

SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE N_SOP_Paket_Item
SET Mode_Akses = 'VIEW_UNDUH',
    Flag_Watermark = 'T',
    Updated_At = SYSDATETIME()
WHERE Tipe_Item = 'FORM'
  AND (Mode_Akses IS NULL OR Mode_Akses <> 'VIEW_UNDUH' OR Flag_Watermark IS NULL OR Flag_Watermark <> 'T');

PRINT CONCAT('N_SOP_Paket_Item diubah: ', @@ROWCOUNT);

/* Draft paket yang sedang disunting: tanpa ini, terbit dari draft lama
   menimpa lagi item di atas dengan VIEW_ONLY. */
UPDATE N_SOP_Paket_Draft_Item
SET Mode_Akses = 'VIEW_UNDUH',
    Flag_Watermark = 'T',
    Updated_At = SYSDATETIME()
WHERE Tipe_Item = 'FORM'
  AND (Mode_Akses IS NULL OR Mode_Akses <> 'VIEW_UNDUH' OR Flag_Watermark IS NULL OR Flag_Watermark <> 'T');

PRINT CONCAT('N_SOP_Paket_Draft_Item diubah: ', @@ROWCOUNT);

COMMIT TRANSACTION;

/* Verifikasi (harus 0 baris):
SELECT 'N_SOP_Paket_Item' AS Tabel, COUNT(*) AS Sisa FROM N_SOP_Paket_Item
WHERE Tipe_Item = 'FORM' AND (Mode_Akses IS NULL OR Mode_Akses <> 'VIEW_UNDUH' OR Flag_Watermark <> 'T')
UNION ALL
SELECT 'N_SOP_Paket_Draft_Item', COUNT(*) FROM N_SOP_Paket_Draft_Item
WHERE Tipe_Item = 'FORM' AND (Mode_Akses IS NULL OR Mode_Akses <> 'VIEW_UNDUH' OR Flag_Watermark <> 'T');
*/
