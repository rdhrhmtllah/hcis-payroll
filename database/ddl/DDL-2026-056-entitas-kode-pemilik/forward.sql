/* Change ID: DDL-2026-056-entitas-kode-pemilik

   Kode cetak entitas SAAT BERPERAN SEBAGAI PEMILIK, terpisah dari kode
   identitasnya.

   MASALAHNYA. Nomor dokumen memuat entitas pada DUA posisi dengan arti yang
   berbeda:

       IK / ENB / PROD / EHG / 09 / 03 / 01
            ^pemilik          ^ruang lingkup

   Keduanya membaca Kode_Entitas dari master yang sama, sehingga dokumen milik
   holding tercetak "EHG" di kedua posisi. Padahal artinya tak sama: di posisi
   ruang lingkup, EHG berarti "berlaku di seluruh entitas" (payung, dihitung
   dari Induk_Entitas_Id) — bukan "perusahaan holding". Standar penomoran
   perusahaan memakai "HOL" untuk pemilik-holding dan "EHG" untuk lingkup
   seluruh-entitas.

   KENAPA BUKAN BARIS MASTER BARU. Menambah baris 'HOL' memecah satu badan
   hukum menjadi dua. Hirarkinya langsung rusak: ENB/EMI/GMN menunjuk
   Induk_Entitas_Id = 1 (EHG), sehingga 'HOL' menjadi entitas yatim tanpa anak
   dan Cakup_Semua tak menganggapnya payung. Sesudah itu "berapa dokumen milik
   holding" harus menjumlah dua baris selamanya, dan setiap laporan baru yang
   lupa menjumlahkannya melapor lebih kecil tanpa terlihat salah.

   Kolom ini berlaku UMUM, bukan khusus EHG. Biayanya sama, dan bila kelak ada
   PT lain yang kode penomorannya berbeda dari kode identitasnya, tak perlu DDL
   lagi.

   NULL berarti "pakai Kode_Entitas". Bawaan itu membuat tiga entitas lain tak
   perlu disentuh sama sekali, dan membuat kolom yang lupa diisi jatuh ke
   perilaku hari ini — bukan ke nomor yang kosong. */

IF NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'N_HRIS_Master_Entitas' AND COLUMN_NAME = 'Kode_Sebagai_Pemilik')
    ALTER TABLE N_HRIS_Master_Entitas ADD Kode_Sebagai_Pemilik varchar(10) NULL;
GO

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-056-entitas-kode-pemilik')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-056-entitas-kode-pemilik', 'DDL', 'Kode cetak entitas sebagai pemilik', 'Kode_Sebagai_Pemilik pada N_HRIS_Master_Entitas: kode yang dicetak pada segmen PEMILIK nomor dokumen, terpisah dari Kode_Entitas yang tetap dipakai segmen ruang lingkup. NULL berarti pakai Kode_Entitas. Menutup ambiguitas EHG yang berarti "holding" sebagai pemilik dan "seluruh entitas" sebagai lingkup, tanpa memecah satu badan hukum menjadi dua baris master.', 'database/ddl/DDL-2026-056-entitas-kode-pemilik/forward.sql', 'docs/changes/DDL-2026-056-entitas-kode-pemilik/README.md', 'Web_HRIS', 'PENDING', 'PENDING', 'PENDING', 'PENDING', 'entitas-kode-pemilik');
GO
