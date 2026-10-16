/* Change ID: DDL-2026-055-sop-dokumen-nonaktif
   Berkas bantu pembersihan manual. JANGAN dijalankan oleh agent.

   Perhatikan: menjatuhkan kolom-kolom ini MENGHAPUS jejak penonaktifan —
   siapa, kapan, catatannya, dan kondisi sebaran saat itu. Tidak ada tempat
   lain yang menyimpannya selain SopLog, dan SopLog tidak menyimpan angka
   cuplikannya. Salin isinya dulu bila jejaknya masih dibutuhkan. */

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Dokumen_Nonaktif_At' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
    DROP INDEX IX_N_SOP_Dokumen_Nonaktif_At ON N_SOP_Dokumen;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Orang')
    ALTER TABLE N_SOP_Dokumen DROP COLUMN Nonaktif_Orang;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Paket')
    ALTER TABLE N_SOP_Dokumen DROP COLUMN Nonaktif_Paket;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Catatan')
    ALTER TABLE N_SOP_Dokumen DROP COLUMN Nonaktif_Catatan;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_By')
    ALTER TABLE N_SOP_Dokumen DROP COLUMN Nonaktif_By;
GO

IF EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_At')
    ALTER TABLE N_SOP_Dokumen DROP COLUMN Nonaktif_At;
GO

DELETE FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-055-sop-dokumen-nonaktif';
GO
