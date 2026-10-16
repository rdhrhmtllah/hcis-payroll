/* Change ID: DML-2026-020-seed-hris-entitas */

/* 1. Payung lebih dulu, karena tiga PT di bawahnya menunjuknya lewat
      Induk_Entitas_Id dan FK menolak induk yang belum ada. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Entitas WHERE Kode_Entitas = 'EHG')
INSERT INTO N_HRIS_Master_Entitas (Kode_Entitas, Nama_Entitas, Nama_Singkat, Warna, Induk_Entitas_Id, Urutan, Flag_Aktif)
VALUES ('EHG', N'Evo Holding / Group', N'Evo Group', '#4338CA', NULL, 1, 'Y');

/* 2. Tiga badan hukum di bawah payung. Warnanya sengaja berjauhan di roda warna
      supaya badge tetap terbedakan pada layar kecil dan pada cetakan abu-abu. */
INSERT INTO N_HRIS_Master_Entitas (Kode_Entitas, Nama_Entitas, Nama_Singkat, Warna, Induk_Entitas_Id, Urutan, Flag_Aktif)
SELECT p.Kode, p.Nama, p.Singkat, p.Warna, (SELECT Id_Entitas FROM N_HRIS_Master_Entitas WHERE Kode_Entitas = 'EHG'), p.Urutan, 'Y'
FROM (VALUES
        ('ENB', N'PT Evo Nusa Bersaudara',          N'Evo Nusa',   '#1D4ED8', 2),
        ('EMI', N'PT Evo Manufacturing Indonesia',  N'Evo Manufacturing', '#047857', 3),
        ('GMN', N'PT Graha Maju Nusantara',         N'Graha Maju', '#B45309', 4)
     ) AS p (Kode, Nama, Singkat, Warna, Urutan)
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Entitas x WHERE x.Kode_Entitas = p.Kode);

/* 3. Pendaftaran ke Database Change Dashboard. Mengisi database/ddl/status.json
      saja TIDAK membuat perubahan ini terlihat di sana. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-019-hris-master-entitas')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-019-hris-master-entitas', 'DDL', 'Master entitas perusahaan dan kepemilikan entitas dokumen SOP', 'Tabel master badan hukum lintas modul plus dua kolom entitas pemilik dan ruang lingkup pada N_SOP_Dokumen.', 'database/ddl/DDL-2026-019-hris-master-entitas/forward.sql', 'docs/changes/DDL-2026-019-hris-master-entitas/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-entitas');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-020-seed-hris-entitas')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-020-seed-hris-entitas', 'DML', 'Seed empat entitas perusahaan', 'EHG sebagai payung beserta ENB, EMI, dan GMN di bawahnya.', 'database/dml/DML-2026-020-seed-hris-entitas/data-change.sql', 'docs/changes/DML-2026-020-seed-hris-entitas/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-entitas');
