/* Change ID: DML-2026-028-seed-hris-departemen */

/* 1. Lima puluh dua kode departemen. Empat ejaan diperbaiki dari daftar asal
      atas konfirmasi pengguna, karena nilai ini menjadi SEGMEN NOMOR DOKUMEN
      dan akan dikutip di audit:

        IT   "Information Technolog"   -> "Information Technology"
        TPM  "Performance Managent"    -> "Performance Management"
        SHE  "Enviroment"              -> "Environment"
        FSCI "Continues Improvement"   -> "Continuous Improvement"
*/
INSERT INTO N_HRIS_Master_Departemen (Kode_Departemen, Nama_Departemen, Urutan, Flag_Aktif)
SELECT p.Kode, p.Nama, p.Urutan, 'Y'
FROM (VALUES
        ('PPIC', N'Production Planning & Inventory Control', 1),
        ('IA', N'Internal Audit', 2),
        ('FIN', N'Finance', 3),
        ('ACC', N'Accounting', 4),
        ('TAX', N'Tax', 5),
        ('PROC', N'Procurement', 6),
        ('IT', N'Information Technology', 7),
        ('LGL', N'Legal', 8),
        ('CQM', N'Compliance & Quality Management', 9),
        ('SAL', N'Sales', 10),
        ('DIS', N'Distribution', 11),
        ('MT', N'Modern Trade', 12),
        ('GT', N'General Trade', 13),
        ('EXT', N'Export Trade', 14),
        ('COPL', N'Corporate Planning', 15),
        ('MDEV', N'Marketing Development', 16),
        ('MCOM', N'Marketing Communication', 17),
        ('TDEV', N'Technical Development', 18),
        ('FOR', N'Formulator', 19),
        ('RAD', N'Research and Development', 20),
        ('QAL', N'Lab & Quality Assurance', 21),
        ('KEA', N'Key Account', 22),
        ('MTP', N'Modern Trade Sales Promotion', 23),
        ('ECO', N'E-Commerce Operation', 24),
        ('MTA', N'Modern Trade Administration', 25),
        ('HC', N'Human Capital', 26),
        ('HCS', N'Human Capital Services', 27),
        ('TPM', N'Talent & Performance Management', 28),
        ('TLD', N'Training & Learning Development', 29),
        ('GA', N'General Affairs', 30),
        ('IER', N'Industrial & Employee Relation', 31),
        ('PROD', N'Production', 32),
        ('WFP', N'Wet Food Production', 33),
        ('DFP', N'Dry Food Production', 34),
        ('WH', N'Warehouse', 35),
        ('RWH', N'Raw Material Warehouse', 36),
        ('PFWH', N'Packaging & Finished Good', 37),
        ('ENM', N'Engineering & Maintenance', 38),
        ('SHE', N'Safety, Health & Environment', 39),
        ('CI', N'Continuous Improvement', 40),
        ('LOG', N'Logistik', 41),
        ('GWH', N'General Warehouse', 42),
        ('DC', N'Distribution Center', 43),
        ('BP', N'Business Process', 44),
        ('LND', N'Learning and Development', 45),
        ('CC', N'Customer Care', 46),
        ('MKT', N'Marketing', 47),
        ('CSWH', N'Cold Storage Warehouse', 48),
        ('FSCI', N'Food Safety & Continuous Improvement', 49),
        ('WHL', N'Warehouse & Logistic', 50),
        ('FM', N'Facility Management', 51),
        ('EXM', N'Exim', 52)
     ) AS p (Kode, Nama, Urutan)
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Master_Departemen x WHERE x.Kode_Departemen = p.Kode);

/* 2. Binding OTOMATIS hanya untuk nama yang COCOK PERSIS dengan sub-divisi
      HRIS. Delapan belas dari lima puluh dua.

      Dicocokkan lewat NAMA, bukan id yang di-hardcode: ID_Sub_Divisi berbeda
      antar environment, dan menuliskan id development ke dalam seed berarti
      memasangkan departemen ke sub-divisi yang salah begitu dijalankan di
      production.

      Alias yang jelas sepadan tapi namanya berbeda -- IT, QAL, SHE, PPIC --
      SENGAJA TIDAK dipasangkan di sini. Begitu seed mulai menebak alias, tak
      ada garis jelas di mana tebakan berhenti, dan pemetaan yang salah akan
      tercetak di nomor dokumen. Admin memasangkannya sendiri di halaman
      Master Departemen. */
UPDATE d
SET d.Sub_Divisi_Id = s.ID_Sub_Divisi
FROM N_HRIS_Master_Departemen d
JOIN HRIS_Sub_Divisi s
  ON UPPER(LTRIM(RTRIM(s.Keterangan))) = UPPER(LTRIM(RTRIM(d.Nama_Departemen)))
WHERE d.Sub_Divisi_Id IS NULL;

/* 3. Pendaftaran ke Database Change Dashboard. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DDL-2026-027-hris-master-departemen')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DDL-2026-027-hris-master-departemen', 'DDL', 'Master kode departemen dan kepemilikan departemen dokumen SOP', 'Tabel master kode departemen lintas modul beserta binding opsional ke sub-divisi HRIS, plus kolom Departemen_Id pada N_SOP_Dokumen.', 'database/ddl/DDL-2026-027-hris-master-departemen/forward.sql', 'docs/changes/DDL-2026-027-hris-master-departemen/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-departemen');

IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-028-seed-hris-departemen')
INSERT INTO N_SYSTEM_Database_Change
(Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
VALUES
('DML-2026-028-seed-hris-departemen', 'DML', 'Seed 52 kode departemen dan binding nama persis', 'Lima puluh dua kode; binding ke sub-divisi HRIS hanya untuk nama yang cocok persis, sisanya dipasangkan admin.', 'database/dml/DML-2026-028-seed-hris-departemen/data-change.sql', 'docs/changes/DML-2026-028-seed-hris-departemen/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'master-departemen');
