/* Change ID: DML-2026-035-cap-tanggal-tahap */

/* Tahap mana mencap tanggal mana.

   Dicap SAAT MEMASUKI tahapnya, dan hanya bila kolomnya masih kosong. Aturan
   "hanya bila kosong" melindungi dua hal sekaligus: tanggal asli saat versi
   bolak-balik antar tahap, dan koreksi manual admin — surat yang ditandatangani
   minggu lalu tapi baru dicatat hari ini tak boleh tertimpa oleh klik.

   Seluruh perpindahan tetap tercatat utuh di N_SOP_Log, jadi jejaknya tak
   hilang meski kolomnya hanya menyimpan yang pertama. */

UPDATE N_SOP_Master_Tahap_Versi SET Cap_Tanggal = 'Tanggal_Permintaan',     Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'REVIEW'   AND Cap_Tanggal IS NULL;
UPDATE N_SOP_Master_Tahap_Versi SET Cap_Tanggal = 'Tanggal_Respon',         Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'APPROVAL' AND Cap_Tanggal IS NULL;
UPDATE N_SOP_Master_Tahap_Versi SET Cap_Tanggal = 'Tanggal_Akhir_Approval', Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'APPROVED' AND Cap_Tanggal IS NULL;

/* Tahap lain sengaja TIDAK mencap apa pun. Hold, Cancel, dan Obsolete adalah
   keadaan, bukan peristiwa yang punya tanggal resmi di dokumen; Drafting adalah
   titik awal; dan Filing and Registered sudah punya Tanggal_Publish sendiri
   yang ditulis jalur terbit. */

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-035-cap-tanggal-tahap')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-035-cap-tanggal-tahap', 'DML', 'Aturan pencapan tanggal per tahap', 'Review mencap Permintaan, Approval mencap Respon, Approved mencap Akhir Approval.', 'database/dml/DML-2026-035-cap-tanggal-tahap/data-change.sql', 'docs/changes/DML-2026-035-cap-tanggal-tahap/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'tanggal-proses');
