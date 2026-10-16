SET NOCOUNT ON;
SET XACT_ABORT ON;
GO


IF OBJECT_ID('dbo.[HRIS_Klasifikasi_Aksi]','U') IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM dbo.[HRIS_Klasifikasi_Aksi])
BEGIN
    SET IDENTITY_INSERT dbo.[HRIS_Klasifikasi_Aksi] ON;
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (1, N'CREATE');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (2, N'VIEW');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (3, N'EDIT');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (4, N'DELETE');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (5, N'APPROVE');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (6, N'PRINT');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (7, N'SUMMARY');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (9, N'AKHIRISESI');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (12, N'DETAILJAWABAN');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (13, N'SYSTEM_ADMIN_AKSES');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (14, N'SUPER_ADMIN_AKSES');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (15, N'ADMIN_AKSES');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (16, N'USER_AKSES');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (17, N'CREATE');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (18, N'VIEW');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (19, N'EDIT');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (20, N'DELETE');
    INSERT INTO dbo.[HRIS_Klasifikasi_Aksi] ([Id_Klasifikasi_Actions],[Nama_Aksi]) VALUES (21, N'PRINT');
    SET IDENTITY_INSERT dbo.[HRIS_Klasifikasi_Aksi] OFF;
END;
GO

IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Menus] WHERE [Jenis_page] = N'ActivityMonitor')
    INSERT INTO dbo.[N_HRIS_Menus] ([Jenis_page],[Kode_Perusahaan],[Nama_Menu],[Icon_Menu],[Url_Menu],[Header_Code],[Header_Label],[Header_Icon],[Sort_Header],[Is_Global_Menu],[Sort_Menu],[Active_Match_Pattern],[Route_Name],[Target],[Is_Show_Sidebar],[Menu_Subtitle]) VALUES (N'ActivityMonitor', NULL, N'Activity Monitor', N'mdi-chart-timeline-variant', N'/admin/activity', N'administrator_hcis', N'Administrator HCIS', NULL, NULL, N'T', NULL, NULL, NULL, N'self', N'Y', NULL);
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Menus] WHERE [Jenis_page] = N'menuSopPrioritasPage')
    INSERT INTO dbo.[N_HRIS_Menus] ([Jenis_page],[Kode_Perusahaan],[Nama_Menu],[Icon_Menu],[Url_Menu],[Header_Code],[Header_Label],[Header_Icon],[Sort_Header],[Is_Global_Menu],[Sort_Menu],[Active_Match_Pattern],[Route_Name],[Target],[Is_Show_Sidebar],[Menu_Subtitle]) VALUES (N'menuSopPrioritasPage', N'001', N'Prioritas Dokumen', N'bi-sliders', N'/sop/admin/prioritas', N'administrator_dms', N'Administrator DMS', N'bi bi-gear-fill', N'2', N'T', N'2', NULL, NULL, N'self', N'Y', N'Tingkat urgensi paket dokumen');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Menus] WHERE [Jenis_page] = N'adminPengumpulanDataPage')
    INSERT INTO dbo.[N_HRIS_Menus] ([Jenis_page],[Kode_Perusahaan],[Nama_Menu],[Icon_Menu],[Url_Menu],[Header_Code],[Header_Label],[Header_Icon],[Sort_Header],[Is_Global_Menu],[Sort_Menu],[Active_Match_Pattern],[Route_Name],[Target],[Is_Show_Sidebar],[Menu_Subtitle]) VALUES (N'adminPengumpulanDataPage', N'001', N'Pembaruan Data Admin', N'bi-people-fill', N'/admin/pengumpulan-data', N'administrator_hcis', N'Administrator HCIS', N'bi bi-gear-fill', N'7', N'T', NULL, NULL, NULL, N'self', N'Y', NULL);
GO

IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'PPIC')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'PPIC', N'Production Planning & Inventory Control', N'1', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'IA')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'IA', N'Internal Audit', N'2', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'FIN')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'FIN', N'Finance', N'3', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'ACC')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'ACC', N'Accounting', N'4', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'TAX')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'TAX', N'Tax', N'5', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'PROC')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'PROC', N'Procurement', N'6', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'IT')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'IT', N'Information Technology', N'7', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'LGL')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'LGL', N'Legal', N'8', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'CQM')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'CQM', N'Compliance & Quality Management', N'9', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'SAL')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'SAL', N'Sales', N'10', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'DIS')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'DIS', N'Distribution', N'11', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'MT')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'MT', N'Modern Trade', N'12', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'GT')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'GT', N'General Trade', N'13', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'EXT')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'EXT', N'Export Trade', N'14', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'COPL')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'COPL', N'Corporate Planning', N'15', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'MDEV')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'MDEV', N'Marketing Development', N'16', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'MCOM')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'MCOM', N'Marketing Communication', N'17', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'TDEV')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'TDEV', N'Technical Development', N'18', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'FOR')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'FOR', N'Formulator', N'19', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'RAD')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'RAD', N'Research and Development', N'20', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'QAL')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'QAL', N'Lab & Quality Assurance', N'21', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'KEA')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'KEA', N'Key Account', N'22', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'MTP')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'MTP', N'Modern Trade Sales Promotion', N'23', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'ECO')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'ECO', N'E-Commerce Operation', N'24', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'MTA')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'MTA', N'Modern Trade Administration', N'25', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'HC')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'HC', N'Human Capital', N'26', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'HCS')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'HCS', N'Human Capital Services', N'27', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'TPM')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'TPM', N'Talent & Performance Management', N'28', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'TLD')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'TLD', N'Training & Learning Development', N'29', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'GA')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'GA', N'General Affairs', N'30', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'IER')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'IER', N'Industrial & Employee Relation', N'31', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'PROD')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'PROD', N'Production', N'32', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'WFP')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'WFP', N'Wet Food Production', N'33', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'DFP')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'DFP', N'Dry Food Production', N'34', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'WH')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'WH', N'Warehouse', N'35', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'RWH')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'RWH', N'Raw Material Warehouse', N'36', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'PFWH')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'PFWH', N'Packaging & Finished Good', N'37', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'ENM')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'ENM', N'Engineering & Maintenance', N'38', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'SHE')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'SHE', N'Safety, Health & Environment', N'39', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'CI')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'CI', N'Continuous Improvement', N'40', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'LOG')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'LOG', N'Logistik', N'41', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'GWH')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'GWH', N'General Warehouse', N'42', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'DC')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'DC', N'Distribution Center', N'43', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'BP')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'BP', N'Business Process', N'44', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'LND')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'LND', N'Learning and Development', N'45', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'CC')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'CC', N'Customer Care', N'46', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'MKT')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'MKT', N'Marketing', N'47', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'CSWH')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'CSWH', N'Cold Storage Warehouse', N'48', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'FSCI')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'FSCI', N'Food Safety & Continuous Improvement', N'49', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'WHL')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'WHL', N'Warehouse & Logistic', N'50', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'FM')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'FM', N'Facility Management', N'51', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Departemen] WHERE [Kode_Departemen] = N'EXM')
    INSERT INTO dbo.[N_HRIS_Master_Departemen] ([Kode_Departemen],[Nama_Departemen],[Urutan],[Flag_Aktif]) VALUES (N'EXM', N'Exim', N'52', N'Y');
GO

/* N_HRIS_Master_Entitas — kunci idempoten: Kode_Entitas. Kolom relasi antar-Id sengaja
   dilepas (Sub_Divisi_Id / Induk_Entitas_Id) karena Id-nya beda antar server. */
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Entitas] WHERE [Kode_Entitas] = N'EHG')
    INSERT INTO dbo.[N_HRIS_Master_Entitas] ([Kode_Entitas],[Nama_Entitas],[Nama_Singkat],[Warna],[Urutan],[Flag_Aktif]) VALUES (N'EHG', N'Evo Holding / Group', N'Evo Group', N'#4338CA', N'1', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Entitas] WHERE [Kode_Entitas] = N'ENB')
    INSERT INTO dbo.[N_HRIS_Master_Entitas] ([Kode_Entitas],[Nama_Entitas],[Nama_Singkat],[Warna],[Urutan],[Flag_Aktif]) VALUES (N'ENB', N'PT Evo Nusa Bersaudara', N'Evo Nusa', N'#1D4ED8', N'2', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Entitas] WHERE [Kode_Entitas] = N'EMI')
    INSERT INTO dbo.[N_HRIS_Master_Entitas] ([Kode_Entitas],[Nama_Entitas],[Nama_Singkat],[Warna],[Urutan],[Flag_Aktif]) VALUES (N'EMI', N'PT Evo Manufacturing Indonesia', N'Evo Manufacturing', N'#047857', N'3', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_HRIS_Master_Entitas] WHERE [Kode_Entitas] = N'GMN')
    INSERT INTO dbo.[N_HRIS_Master_Entitas] ([Kode_Entitas],[Nama_Entitas],[Nama_Singkat],[Warna],[Urutan],[Flag_Aktif]) VALUES (N'GMN', N'PT Graha Maju Nusantara', N'Graha Maju', N'#B45309', N'4', N'Y');
GO

/* N_SOP_Master_Jenis_Dokumen — kunci idempoten: Kode_Jenis. Kolom relasi antar-Id sengaja
   dilepas (Sub_Divisi_Id / Induk_Entitas_Id) karena Id-nya beda antar server. */
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'KEBIJAKAN')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'KEBIJAKAN', N'Kebijakan', N'1', N'Pernyataan arah dan komitmen manajemen. Menjawab APA yang dituju dan MENGAPA.', N'1', N'Y', N'PLC', N'24', N'12');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'PROSEDUR')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'PROSEDUR', N'Prosedur', N'4', N'Alur kerja lintas fungsi yang menurunkan kebijakan. Menjawab SIAPA melakukan APA dan KAPAN.', N'4', N'Y', N'SOP', N'24', N'12');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'INSTRUKSI_KERJA')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'INSTRUKSI_KERJA', N'Instruksi Kerja', N'5', N'Langkah teknis rinci pada satu posisi atau satu mesin. Menjawab BAGAIMANA persisnya.', N'5', N'Y', N'IK', N'24', N'12');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'FORMULIR')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'FORMULIR', N'Formulir', N'7', N'Lembar isian yang menjadi bukti pelaksanaan. Dasar piramid.', N'7', N'Y', N'FRM', N'24', N'12');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'SURAT_KEPUTUSAN')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'SURAT_KEPUTUSAN', N'Surat Keputusan', N'3', NULL, N'3', N'Y', N'SK', N'24', N'12');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'STANDAR')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'STANDAR', N'Standar', N'6', NULL, N'6', N'Y', N'STD', N'24', N'12');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'MANUAL')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'MANUAL', N'Manual', N'2', NULL, N'2', N'Y', N'MAN', N'24', N'12');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Jenis_Dokumen] WHERE [Kode_Jenis] = N'CNTH')
    INSERT INTO dbo.[N_SOP_Master_Jenis_Dokumen] ([Kode_Jenis],[Nama_Jenis],[Level_Hirarki],[Deskripsi],[Urutan],[Flag_Aktif],[Kode_Jenis_Dokumen],[Masa_Berlaku_Bulan],[Siklus_Review_Bulan]) VALUES (N'CNTH', N'CONTOH + Revisi', N'1', N'TEST', N'1', N'T', N'CNTH', NULL, NULL);
GO

/* N_SOP_Master_Tahap_Versi — kunci idempoten: Kode_Tahap. Kolom relasi antar-Id sengaja
   dilepas (Sub_Divisi_Id / Induk_Entitas_Id) karena Id-nya beda antar server. */
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'DRAFTING')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'DRAFTING', N'Drafting', N'DRAFT', N'Baru dibuat. Berkas boleh belum ada.', N'#64748B', N'1', N'T', N'T', N'T', N'T', N'Y', NULL, N'1', N'PENGAJUAN');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'REVIEW')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'REVIEW', N'Review', N'DRAFT', N'Isi sedang diperiksa.', N'#0EA5E9', N'2', N'T', N'T', N'T', N'T', N'Y', N'Tanggal_Permintaan', N'2', N'REVIEW');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'APPROVAL')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'APPROVAL', N'Pengesahan', N'DRAFT', N'Pengesah dan legalitasnya dicatat di sini.', N'#A855F7', N'3', N'T', N'T', N'T', N'T', N'Y', N'Tanggal_Akhir_Approval', N'3', N'PENGESAHAN');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'HOLD')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'HOLD', N'Hold', N'DRAFT', N'Ditahan sementara. Bisa dilanjutkan kembali.', N'#F59E0B', N'4', N'T', N'T', N'T', N'T', N'T', NULL, NULL, NULL);
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'CANCEL')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'CANCEL', N'Cancel', N'DRAFT', N'Dibatalkan. Tahap akhir — lanjutkan dengan versi baru.', N'#EF4444', N'5', N'T', N'T', N'Y', N'Y', N'Y', NULL, NULL, NULL);
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'APPROVED')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'APPROVED', N'Pemberkasan', N'DRAFT', N'Sudah disahkan. Berkas final disiapkan, lalu versi diterbitkan.', N'#059669', N'6', N'T', N'Y', N'T', N'T', N'Y', NULL, N'4', N'TERBIT');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'FILING_REGISTERED')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'FILING_REGISTERED', N'Filing and Registered', N'PUBLISHED', N'Terdaftar dan berlaku bagi karyawan.', N'#047857', N'7', N'Y', N'Y', N'T', N'T', N'Y', NULL, N'5', N'TINJAUAN');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_Tahap_Versi] WHERE [Kode_Tahap] = N'OBSOLETE')
    INSERT INTO dbo.[N_SOP_Master_Tahap_Versi] ([Kode_Tahap],[Nama_Tahap],[Status_Teknis],[Deskripsi],[Warna],[Urutan],[Butuh_Berkas],[Butuh_Pengesahan],[Is_Terminal],[Nonaktifkan_Versi],[Flag_Aktif],[Cap_Tanggal],[Langkah],[Kelompok]) VALUES (N'OBSOLETE', N'Obsolete', N'OBSOLETE', N'Digantikan revisi berikutnya.', N'#78716C', N'8', N'T', N'T', N'Y', N'T', N'Y', N'Tanggal_Obsolete', NULL, NULL);
GO

/* N_SOP_Master_APQC — kunci idempoten: Kode_Proses. Kolom relasi antar-Id sengaja
   dilepas (Sub_Divisi_Id / Induk_Entitas_Id) karena Id-nya beda antar server. */
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'01')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'01', N'Develop Vision and Strategy', NULL, N'1', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'02')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'02', N'Develop and Manage Product and Services', NULL, N'2', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'03')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'03', N'Market and Sell Product and Services', NULL, N'3', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'04')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'04', N'Manage Supply Chain for Physical Product', NULL, N'4', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'05')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'05', N'Deliver Services', NULL, N'5', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'06')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'06', N'Manage Customer Service', NULL, N'6', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'07')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'07', N'Develop and Manage Human Capital', NULL, N'7', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'08')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'08', N'Manage Information Technology', NULL, N'8', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'09')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'09', N'Manage Financial Resources', NULL, N'9', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'10')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'10', N'Acquire, Construct, and Manage Assets', NULL, N'10', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'11')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'11', N'Manage Enterprise Risk, Compliance, Remediation, and Resiliency', NULL, N'11', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'12')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'12', N'Manage External Relationship', NULL, N'12', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'13')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'13', N'Develop and Manage Business Capabilities', NULL, N'13', N'Y');
IF NOT EXISTS (SELECT 1 FROM dbo.[N_SOP_Master_APQC] WHERE [Kode_Proses] = N'14')
    INSERT INTO dbo.[N_SOP_Master_APQC] ([Kode_Proses],[Nama_Proses],[Deskripsi],[Urutan],[Flag_Aktif]) VALUES (N'14', N'CONTOH SAJA LE + Revisi', N'CONOTH SAJA LE', N'0', N'T');
GO

/* selesai */
