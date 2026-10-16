/* Change ID: DDL-2026-055-sop-dokumen-nonaktif

   Penonaktifan Dokumen SOP direkam pada kolomnya SENDIRI, bukan dengan
   membalik Flag_Aktif.

   Alasannya bukan selera. Flag_Aktif dokumen ditanyakan oleh 28 kueri, tiga di
   antaranya jalur baca karyawan (SopProgressReadService, SopRepositoryReadService,
   SopJangkauanPublikService). Membalik Flag_Aktif akan mencabut dokumen dari
   paket yang SEDANG berjalan — padahal keputusan produknya justru sebaliknya:
   dokumen yang dinonaktifkan hanya tertutup bagi pemakaian BARU, sedangkan
   kewajiban yang sudah terbit tetap berjalan sampai adminnya melepas sendiri.
   Dengan kolom terpisah, tak satu pun pembaca perlu disunting.

   Nonaktif_At merangkap penanda status: NULL berarti aktif. Tidak ada kolom
   "Status" tersendiri karena keadaannya hanya dua, dan dua keadaan yang
   disimpan di dua tempat pada akhirnya akan berselisih.

   Nonaktif_Paket & Nonaktif_Orang adalah CUPLIKAN, bukan turunan. Justru karena
   paket tetap berjalan setelah dokumen dinonaktifkan, angkanya terus berubah
   sesudahnya; "kondisi terakhir saat dinonaktifkan" hanya bisa benar bila
   dibekukan pada detik itu. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_At')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_At datetime NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_By')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_By int NULL;
GO

/* Catatan dibiarkan NULL-able di tingkat skema semata-mata karena baris lama
   tak punya catatan; aplikasi mewajibkannya untuk setiap penonaktifan baru.
   NOT NULL di sini akan menuntut nilai bohongan bagi seluruh baris yang sudah
   ada, dan nilai bohongan itu tak bisa dibedakan dari catatan sungguhan. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Catatan')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_Catatan nvarchar(500) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Paket')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_Paket int NULL;
GO

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Dokumen' AND COLUMN_NAME = 'Nonaktif_Orang')
    ALTER TABLE N_SOP_Dokumen ADD Nonaktif_Orang int NULL;
GO

/* Indeks TERFILTER. Pil "Dibatalkan" hanya pernah meminta baris yang
   Nonaktif_At-nya terisi, dan baris itu segelintir; indeks penuh akan
   menyimpan seluruh dokumen aktif hanya untuk tak pernah dibaca. Urutannya
   menurun karena satu-satunya urutan yang diminta layar adalah "paling
   terakhir dinonaktifkan lebih dulu". */
IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_N_SOP_Dokumen_Nonaktif_At' AND object_id = OBJECT_ID('N_SOP_Dokumen'))
    CREATE INDEX IX_N_SOP_Dokumen_Nonaktif_At
        ON N_SOP_Dokumen (Nonaktif_At DESC)
        INCLUDE (Nonaktif_By)
        WHERE Nonaktif_At IS NOT NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-055-sop-dokumen-nonaktif')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-055-sop-dokumen-nonaktif', 'DDL', 'Jejak penonaktifan dokumen SOP', 'Lima kolom jejak penonaktifan pada N_SOP_Dokumen (Nonaktif_At/By/Catatan/Paket/Orang) plus indeks terfilter. Nonaktif_At merangkap penanda status sehingga Flag_Aktif tidak perlu dibalik dan tidak satu pun jalur baca karyawan berubah; Nonaktif_Paket & Nonaktif_Orang membekukan kondisi sebaran pada detik penonaktifan.', 'database/ddl/DDL-2026-055-sop-dokumen-nonaktif/forward.sql', 'docs/changes/DDL-2026-055-sop-dokumen-nonaktif/README.md', 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'sop-nonaktif-dokumen');
GO
