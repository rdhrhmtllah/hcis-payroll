/* Change ID: DML-2026-040-seed-langkah-versi */

/* Pemetaan tahap ke langkah.

   Empat langkah, dan satu langkah boleh diwakili lebih dari satu tahap:
   Approval dan Approved sama-sama langkah Pengesahan — yang pertama berarti
   langkah itu sedang berjalan, yang kedua berarti sudah tuntas.

   HOLD, CANCEL, dan OBSOLETE sengaja TANPA langkah. Ketiganya bukan tahapan
   perjalanan melainkan keadaan yang menempel: Hold menahan langkah yang sedang
   berjalan, Cancel dan Obsolete mengakhirinya. Memberi mereka langkah sendiri
   akan menampilkan kotak kosong yang tak pernah punya isian. */

UPDATE N_SOP_Master_Tahap_Versi SET Langkah = 1, Kelompok = 'PENGAJUAN',  Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'DRAFTING'          AND Langkah IS NULL;
UPDATE N_SOP_Master_Tahap_Versi SET Langkah = 2, Kelompok = 'REVIEW',     Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'REVIEW'            AND Langkah IS NULL;
UPDATE N_SOP_Master_Tahap_Versi SET Langkah = 3, Kelompok = 'PENGESAHAN', Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'APPROVAL'          AND Langkah IS NULL;
UPDATE N_SOP_Master_Tahap_Versi SET Langkah = 3, Kelompok = 'PENGESAHAN', Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'APPROVED'          AND Langkah IS NULL;
UPDATE N_SOP_Master_Tahap_Versi SET Langkah = 4, Kelompok = 'TERBIT',     Updated_At = SYSDATETIME() WHERE Kode_Tahap = 'FILING_REGISTERED' AND Langkah IS NULL;

/* Backfill Jenis pengesah.

   Tiga puluh enam baris yang ada seluruhnya REALISASI — itu memang artinya
   selama ini, dan default kolomnya sudah menuliskannya. UPDATE ini hanya
   memastikan baris yang lahir sebelum default terpasang ikut benar. */
UPDATE N_SOP_Item_Versi_Pengesah SET Jenis = 'REALISASI'
WHERE Jenis IS NULL OR LTRIM(RTRIM(Jenis)) = '' OR Jenis = 'RENCANA';

/* Syarat nyata per tahap.

   Review TIDAK menuntut apa pun lagi — pengesahan sepenuhnya milik langkah
   Pengesahan, bukan rencana terpisah di Pengajuan (keputusan 2026-08-24).
   Approval menuntut minimal satu catatan review. Approved menuntut tanda
   tangan nyata. Filing menuntut berkas final + tanda tangan nyata.

   Semua sebagai data — layar dan service membaca tabel yang sama. */
INSERT INTO N_SOP_Master_Tahap_Versi_Syarat
(Tahap_Id, Kode_Syarat, Nama_Syarat, Urutan, Flag_Aktif, Created_By)
SELECT t.Id_Tahap, s.kode, s.nama, s.urut, 'Y', NULL
FROM (VALUES
    ('APPROVAL',          'CATATAN_REVIEW',     N'Catatan review',       1),
    ('APPROVED',          'REALISASI_PENGESAH', N'Realisasi pengesah',   1),
    ('FILING_REGISTERED', 'BERKAS',              N'Berkas final',         1),
    ('FILING_REGISTERED', 'REALISASI_PENGESAH', N'Realisasi pengesah',   2)
) s(tahap, kode, nama, urut)
JOIN N_SOP_Master_Tahap_Versi t ON t.Kode_Tahap = s.tahap
WHERE NOT EXISTS (
    SELECT 1 FROM N_SOP_Master_Tahap_Versi_Syarat x
    WHERE x.Tahap_Id = t.Id_Tahap AND x.Kode_Syarat = s.kode
);

/* Hapus syarat lama yang sudah tidak berlaku — konsep rencana pengesah
   terpisah dibatalkan, pengesahan cukup satu blok di langkah Pengesahan. */
DELETE FROM N_SOP_Master_Tahap_Versi_Syarat WHERE Kode_Syarat = 'RENCANA_PENGESAH';

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-040-seed-langkah-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-040-seed-langkah-versi', 'DML', 'Pemetaan langkah dan syarat tahap', 'Empat langkah; Hold/Cancel/Obsolete tanpa langkah. Approval menuntut review, Approved menuntut pengesah, Filing menuntut berkas+pengesah.', 'database/dml/DML-2026-040-seed-langkah-versi/data-change.sql', 'docs/changes/DML-2026-040-seed-langkah-versi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'langkah-versi');

UPDATE N_SYSTEM_Database_Change
SET Judul_Change = 'Pemetaan langkah dan syarat tahap',
    Deskripsi_Change = 'Empat langkah; Hold/Cancel/Obsolete tanpa langkah. Approval menuntut review, Approved menuntut pengesah, Filing menuntut berkas+pengesah.'
WHERE Kode_Change = 'DML-2026-040-seed-langkah-versi';
