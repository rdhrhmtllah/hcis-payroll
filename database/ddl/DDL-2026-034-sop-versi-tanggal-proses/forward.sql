/* Change ID: DDL-2026-034-sop-versi-tanggal-proses */

/* Tanggal proses pada identitas versi.

   Tiga tanggal baru menandai perjalanan versi melewati tahapnya, dan yang
   keempat — Tanggal_Kadaluarsa — SUDAH ADA sejak lama tetapi tak pernah
   tersentuh satu pun jalur SOP: enam dari 76 versi terisi, tanpa form, tanpa
   controller, tanpa penulis. Ia dimunculkan, bukan diduplikasi. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Permintaan')
    ALTER TABLE N_SOP_Item_Versi ADD Tanggal_Permintaan date NULL;
GO
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Respon')
    ALTER TABLE N_SOP_Item_Versi ADD Tanggal_Respon date NULL;
GO
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Item_Versi' AND COLUMN_NAME = 'Tanggal_Akhir_Approval')
    ALTER TABLE N_SOP_Item_Versi ADD Tanggal_Akhir_Approval date NULL;
GO

/* CAP TANGGAL SEBAGAI DATA.

   Tahap mana mencap tanggal mana ditulis di masternya, bukan sebagai cabang di
   kode. Konsekuensinya: admin yang menambah tahap baru bisa sekaligus menyatakan
   tanggal apa yang tercap saat memasukinya, tanpa satu baris kode pun berubah —
   sama seperti Butuh_Berkas dan Status_Teknis di tabel yang sama.

   Nilainya adalah NAMA KOLOM pada N_SOP_Item_Versi. Kosong berarti tahap itu
   tak mencap apa pun, dan itu keadaan mayoritas. */
IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_SOP_Master_Tahap_Versi' AND COLUMN_NAME = 'Cap_Tanggal')
    ALTER TABLE N_SOP_Master_Tahap_Versi ADD Cap_Tanggal varchar(30) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-034-sop-versi-tanggal-proses')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-034-sop-versi-tanggal-proses', 'DDL', 'Tanggal proses pada identitas versi', 'Tiga kolom tanggal pada versi, plus Cap_Tanggal pada master tahap sebagai aturan pencapan.', 'database/ddl/DDL-2026-034-sop-versi-tanggal-proses/forward.sql', 'docs/changes/DDL-2026-034-sop-versi-tanggal-proses/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'tanggal-proses');
GO
