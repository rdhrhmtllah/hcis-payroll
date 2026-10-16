/* Change ID: DML-2026-024-seed-sop-apqc */

/* 1. Tiga belas kategori APQC PCF tingkat pertama. Kode disimpan sebagai teks
      '01'..'13', bukan angka: nol di depan adalah bagian dari kodenya, dan
      sub-level kelak ditulis '01.1' pada kolom yang sama. */
INSERT INTO N_SOP_Master_APQC (Kode_Proses, Nama_Proses, Urutan, Flag_Aktif)
SELECT p.Kode, p.Nama, p.Urutan, 'Y'
FROM (VALUES
        ('01', N'Develop Vision and Strategy',                                        1),
        ('02', N'Develop and Manage Product and Services',                            2),
        ('03', N'Market and Sell Product and Services',                               3),
        ('04', N'Manage Supply Chain for Physical Product',                           4),
        ('05', N'Deliver Services',                                                   5),
        ('06', N'Manage Customer Service',                                            6),
        ('07', N'Develop and Manage Human Capital',                                   7),
        ('08', N'Manage Information Technology',                                      8),
        ('09', N'Manage Financial Resources',                                         9),
        ('10', N'Acquire, Construct, and Manage Assets',                             10),
        ('11', N'Manage Enterprise Risk, Compliance, Remediation, and Resiliency',   11),
        ('12', N'Manage External Relationship',                                      12),
        ('13', N'Develop and Manage Business Capabilities',                          13)
     ) AS p (Kode, Nama, Urutan)
WHERE NOT EXISTS (SELECT 1 FROM N_SOP_Master_APQC x WHERE x.Kode_Proses = p.Kode);

/* 2. Tipe item yang sah bagi setiap APQC.
      Default: DOKUMEN, FORM, dan VIDEO — tiga tipe yang membawa isi terkendali.
      KUIS sengaja tidak dipetakan, dengan alasan yang sama seperti pada jenis
      dokumen: ia alat verifikasi pemahaman, bukan dokumen yang memetakan proses.

      Ini DEFAULT, bukan aturan tetap. Admin menyesuaikannya lewat halaman
      Master APQC; kewajiban mengisi APQC pada sebuah dokumen diturunkan dari
      pemetaan ini, jadi mencabut seluruh tipe dari satu APQC membuatnya tak
      pernah ditawarkan lagi tanpa perlu satu baris kode pun berubah. */
INSERT INTO N_SOP_Master_APQC_Tipe_Item (APQC_Id, Tipe_Item, Flag_Aktif)
SELECT a.Id_SOP_APQC, t.Tipe, 'Y'
FROM N_SOP_Master_APQC a
CROSS JOIN (VALUES ('DOKUMEN'), ('FORM'), ('VIDEO')) AS t (Tipe)
WHERE NOT EXISTS (
    SELECT 1 FROM N_SOP_Master_APQC_Tipe_Item x
    WHERE x.APQC_Id = a.Id_SOP_APQC AND x.Tipe_Item = t.Tipe
);

/* 3. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-023-sop-master-apqc')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-023-sop-master-apqc', 'DDL', 'Master APQC dan pemetaan proses dokumen SOP', 'Tabel master klasifikasi proses APQC, pemetaan tipe item yang sah, dan kolom APQC_Id pada N_SOP_Dokumen.', 'database/ddl/DDL-2026-023-sop-master-apqc/forward.sql', 'docs/changes/DDL-2026-023-sop-master-apqc/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-apqc');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-024-seed-sop-apqc')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-024-seed-sop-apqc', 'DML', 'Seed 13 kategori APQC beserta pemetaan tipe item', 'Tiga belas kategori APQC PCF tingkat pertama, masing-masing dipetakan ke DOKUMEN, FORM, dan VIDEO sebagai default yang bisa disesuaikan admin.', 'database/dml/DML-2026-024-seed-sop-apqc/data-change.sql', 'docs/changes/DML-2026-024-seed-sop-apqc/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-apqc');
