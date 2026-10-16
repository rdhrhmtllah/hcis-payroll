/* Change ID: DML-2026-017-seed-sop-jenis-dokumen */

/* 1. Empat tingkat piramid mutu. Level_Hirarki 1 = puncak. */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Jenis_Dokumen WHERE Kode_Jenis = 'KEBIJAKAN')
INSERT INTO N_SOP_Master_Jenis_Dokumen (Kode_Jenis, Nama_Jenis, Level_Hirarki, Deskripsi, Urutan, Flag_Aktif)
VALUES ('KEBIJAKAN', N'Kebijakan', 1, N'Pernyataan arah dan komitmen manajemen. Menjawab APA yang dituju dan MENGAPA.', 1, 'Y');

IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Jenis_Dokumen WHERE Kode_Jenis = 'PROSEDUR')
INSERT INTO N_SOP_Master_Jenis_Dokumen (Kode_Jenis, Nama_Jenis, Level_Hirarki, Deskripsi, Urutan, Flag_Aktif)
VALUES ('PROSEDUR', N'Prosedur', 2, N'Alur kerja lintas fungsi yang menurunkan kebijakan. Menjawab SIAPA melakukan APA dan KAPAN.', 2, 'Y');

IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Jenis_Dokumen WHERE Kode_Jenis = 'INSTRUKSI_KERJA')
INSERT INTO N_SOP_Master_Jenis_Dokumen (Kode_Jenis, Nama_Jenis, Level_Hirarki, Deskripsi, Urutan, Flag_Aktif)
VALUES ('INSTRUKSI_KERJA', N'Instruksi Kerja', 3, N'Langkah teknis rinci pada satu posisi atau satu mesin. Menjawab BAGAIMANA persisnya.', 3, 'Y');

IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Jenis_Dokumen WHERE Kode_Jenis = 'FORMULIR')
INSERT INTO N_SOP_Master_Jenis_Dokumen (Kode_Jenis, Nama_Jenis, Level_Hirarki, Deskripsi, Urutan, Flag_Aktif)
VALUES ('FORMULIR', N'Formulir', 4, N'Lembar isian yang menjadi bukti pelaksanaan. Dasar piramid.', 4, 'Y');

/* 2. Tipe item yang sah untuk tiap jenis.
      INSTRUKSI_KERJA menerima VIDEO karena instruksi kerja yang direkam adalah
      praktik nyata di lantai produksi. KUIS sengaja tidak dipetakan ke jenis
      mana pun: ia alat verifikasi pemahaman, bukan dokumen terkendali. */
INSERT INTO N_SOP_Master_Jenis_Dokumen_Tipe_Item (Jenis_Dokumen_Id, Tipe_Item, Flag_Aktif)
SELECT j.Id_SOP_Jenis_Dokumen, p.Tipe_Item, 'Y'
FROM (VALUES
        ('KEBIJAKAN',       'DOKUMEN'),
        ('PROSEDUR',        'DOKUMEN'),
        ('INSTRUKSI_KERJA', 'DOKUMEN'),
        ('INSTRUKSI_KERJA', 'VIDEO'),
        ('FORMULIR',        'FORM')
     ) AS p (Kode_Jenis, Tipe_Item)
JOIN N_SOP_Master_Jenis_Dokumen j ON j.Kode_Jenis = p.Kode_Jenis
WHERE NOT EXISTS (
    SELECT 1 FROM N_SOP_Master_Jenis_Dokumen_Tipe_Item x
    WHERE x.Jenis_Dokumen_Id = j.Id_SOP_Jenis_Dokumen AND x.Tipe_Item = p.Tipe_Item
);

/* 3. Pendaftaran DDL-2026-016 dan DML ini ke Database Change Dashboard.
      Mengisi database/ddl/status.json saja TIDAK membuatnya terlihat di sana. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-016-sop-jenis-dokumen-kepemilikan')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-016-sop-jenis-dokumen-kepemilikan', 'DDL', 'Master jenis dokumen SOP dan kepemilikan unit kerja', 'Dua tabel master hirarki mutu plus tiga kolom klasifikasi dan kepemilikan pada N_SOP_Dokumen.', 'database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/forward.sql', 'docs/changes/DDL-2026-016-sop-jenis-dokumen-kepemilikan/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'jenis-dokumen');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-017-seed-sop-jenis-dokumen')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-017-seed-sop-jenis-dokumen', 'DML', 'Seed empat jenis dokumen SOP dan pemetaan tipe item', 'Kebijakan, Prosedur, Instruksi Kerja, Formulir beserta tipe item yang sah bagi masing-masing.', 'database/dml/DML-2026-017-seed-sop-jenis-dokumen/data-change.sql', 'docs/changes/DML-2026-017-seed-sop-jenis-dokumen/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'jenis-dokumen');
