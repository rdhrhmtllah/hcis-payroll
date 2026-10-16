/* Change ID: DML-2026-033-seed-tahap-versi */

/* Delapan tahap proses versi, transisinya, dan backfill 75 versi yang lahir
   sebelum kolomnya ada.

   Status_Teknis adalah JEMBATAN ke seluruh pembaca lama. Empat nilai itu yang
   dipahami rilis paket, progres karyawan, monitoring, dan ekspor; delapan tahap
   di sini hanya memberi nama manusiawi pada keadaan di antaranya. */

/* 1. Master tahap. */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi)
INSERT INTO N_SOP_Master_Tahap_Versi
(Kode_Tahap, Nama_Tahap, Status_Teknis, Deskripsi, Warna, Urutan, Butuh_Berkas, Butuh_Pengesahan, Is_Terminal, Nonaktifkan_Versi, Flag_Aktif, Created_By)
VALUES
/* Berkas SENGAJA belum dituntut sampai Approved: seluruh maksud tahapan ini
   adalah versi boleh lahir dan bergerak sebelum berkasnya ada. */
('DRAFTING',          N'Drafting',              'DRAFT',     N'Baru dibuat. Berkas boleh belum ada.',                        '#64748B', 1, 'T', 'T', 'T', 'T', 'Y', NULL),
('REVIEW',            N'Review',                'DRAFT',     N'Isi sedang diperiksa.',                                       '#0EA5E9', 2, 'T', 'T', 'T', 'T', 'Y', NULL),
('APPROVAL',          N'Approval',              'DRAFT',     N'Menunggu tanda tangan pengesah.',                             '#A855F7', 3, 'T', 'T', 'T', 'T', 'Y', NULL),
('HOLD',              N'Hold',                  'DRAFT',     N'Ditahan sementara. Bisa dilanjutkan kembali.',                 '#F59E0B', 4, 'T', 'T', 'T', 'T', 'Y', NULL),
('CANCEL',            N'Cancel',                'DRAFT',     N'Dibatalkan. Tahap akhir — lanjutkan dengan versi baru.',       '#EF4444', 5, 'T', 'T', 'Y', 'Y', 'Y', NULL),
/* Approved menuntut berkas DAN pengesahan: sejak titik ini isinya dianggap
   final, hanya pendaftarannya yang belum.

   Status teknisnya tetap DRAFT, BUKAN READY. CK_N_SOP_Versi_Status hanya
   mengenal DRAFT/PUBLISHED/OBSOLETE, dan nol baris READY pernah ada di tabel
   ini — 'READY' hanya punya arti pada salinan kerja paket terbit
   (N_SOP_Paket_Draft_Item_Versi), yang tak dijaga constraint itu. Yang
   membedakan Approved dari Drafting adalah Tahap_Id, bukan Status. */
('APPROVED',          N'Approved',              'DRAFT',     N'Sah dan lengkap, belum terdaftar sebagai yang berlaku.',      '#059669', 6, 'Y', 'Y', 'T', 'T', 'Y', NULL),
('FILING_REGISTERED', N'Filing and Registered', 'PUBLISHED', N'Terdaftar dan berlaku bagi karyawan.',                        '#047857', 7, 'Y', 'Y', 'T', 'T', 'Y', NULL),
('OBSOLETE',          N'Obsolete',              'OBSOLETE',  N'Digantikan revisi berikutnya.',                               '#78716C', 8, 'T', 'T', 'Y', 'T', 'Y', NULL);

/* 2. Transisi yang diizinkan.

      Hold BISA KEMBALI ke tiga tahap penyusunan; Cancel dan Obsolete tak punya
      transisi keluar sama sekali — itulah arti Is_Terminal, dan tabel ini yang
      menegakkannya. */
IF NOT EXISTS (SELECT 1 FROM N_SOP_Master_Tahap_Versi_Transisi)
INSERT INTO N_SOP_Master_Tahap_Versi_Transisi (Dari_Tahap_Id, Ke_Tahap_Id, Flag_Aktif, Created_By)
SELECT d.Id_Tahap, k.Id_Tahap, 'Y', NULL
FROM (VALUES
    ('DRAFTING',  'REVIEW'),
    ('DRAFTING',  'CANCEL'),
    ('REVIEW',    'APPROVAL'),
    ('REVIEW',    'DRAFTING'),          /* dikembalikan untuk diperbaiki */
    ('REVIEW',    'HOLD'),
    ('REVIEW',    'CANCEL'),
    ('APPROVAL',  'APPROVED'),
    ('APPROVAL',  'REVIEW'),            /* ditolak pengesah */
    ('APPROVAL',  'HOLD'),
    ('APPROVAL',  'CANCEL'),
    ('HOLD',      'DRAFTING'),
    ('HOLD',      'REVIEW'),
    ('HOLD',      'APPROVAL'),
    ('HOLD',      'CANCEL'),
    ('APPROVED',  'FILING_REGISTERED'),
    ('APPROVED',  'REVIEW'),            /* ternyata masih ada yang keliru */
    ('APPROVED',  'HOLD'),
    ('APPROVED',  'CANCEL'),
    ('FILING_REGISTERED', 'OBSOLETE')
) AS t(dari, ke)
JOIN N_SOP_Master_Tahap_Versi d ON d.Kode_Tahap = t.dari
JOIN N_SOP_Master_Tahap_Versi k ON k.Kode_Tahap = t.ke;

/* 3. Backfill 75 versi lama, DITURUNKAN dari Status yang sudah ada.

      Bukan menebak: pemetaannya persis kebalikan dari Status_Teknis, sehingga
      tak ada versi yang berpindah keadaan hanya karena kolom baru muncul.
      READY tak ikut dipetakan: constraint tabel ini tak pernah mengizinkannya. */
UPDATE v SET v.Tahap_Id = t.Id_Tahap
FROM N_SOP_Item_Versi v
JOIN N_SOP_Master_Tahap_Versi t
  ON t.Kode_Tahap = CASE v.Status
        WHEN 'DRAFT'     THEN 'DRAFTING'
        WHEN 'PUBLISHED' THEN 'FILING_REGISTERED'
        WHEN 'OBSOLETE'  THEN 'OBSOLETE'
     END
WHERE v.Tahap_Id IS NULL;

/* 4. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-033-seed-tahap-versi')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-033-seed-tahap-versi', 'DML', 'Seed delapan tahap proses versi', 'Tahap, transisi, dan backfill versi lama dari Status teknisnya.', 'database/dml/DML-2026-033-seed-tahap-versi/data-change.sql', 'docs/changes/DML-2026-033-seed-tahap-versi/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'tahap-versi');
