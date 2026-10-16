/* Change ID: DML-2026-125-batas-z-grade-dan-menu-grup-kurva
   Target: Web_HRIS development; production only through SQL Tracker. Dependencies: DDL-2026-120.
   Scope: (1) Batas_Z enam grade master, hanya bila belum diisi; (2) halaman master Grup Level
   Kurva di menu Master Data PA — N_HRIS_Menus, N_HRIS_Grup_Fitur_Map (grup KPI),
   HRIS_Page_Access untuk dua user pengembang (pola DML-2026-085).
   First-run maximum scope: UPDATE N_PA_Score_Range <= 6, N_HRIS_Menus <= 1,
   N_HRIS_Grup_Fitur_Map <= 1, HRIS_Page_Access <= 2. Aman diulang.
   Sesudah dijalankan: panggil flushMenuCache (lihat README). */
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE r
SET r.Batas_Z = v.Batas_Z, r.Updated_At = SYSDATETIME()
FROM N_PA_Score_Range r
JOIN (VALUES ('A', 2.00), ('B_PLUS', 1.00), ('B', 0.00), ('C_PLUS', -1.00), ('C', -2.00)) AS v(Kode_Range, Batas_Z)
  ON v.Kode_Range = r.Kode_Range
WHERE r.Score_Type = 'FINAL' AND r.Score_Usage = 'GRADE' AND r.Flag_Aktif = 'Y' AND r.Batas_Z IS NULL;

INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
     Header_Code, Header_Label, Header_Icon, Sort_Header,
     Is_Global_Menu, Target, Is_Show_Sidebar, Sort_Menu)
SELECT 'PaMasterGrupKurvaPage', '001', N'Grup Level Kurva', 'bi-diagram-2',
       '/performance-appraisal/master/grup-kurva',
       'pa_master', N'Master Data', 'bi bi-clipboard2-data', 14, 'T', 'self', 'Y', 10
WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'PaMasterGrupKurvaPage');

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT g.Id_Grup_Fitur, 'PaMasterGrupKurvaPage', 'T', 'Y', 'Y'
FROM N_HRIS_Master_Grup_Fitur g
WHERE g.Kode_Grup_Fitur = 'KPI'
  AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x WHERE x.Jenis_page = 'PaMasterGrupKurvaPage');

INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At)
SELECT b.Kode_Perusahaan, b.ID_Level, b.ID_Divisi, 'PaMasterGrupKurvaPage', b.UserID_Web, 'Y', SYSDATETIME()
FROM HRIS_Page_Access b
WHERE b.UserID_Web IN (4, 622)
  AND b.Id_Page_Access = (SELECT MIN(c.Id_Page_Access) FROM HRIS_Page_Access c WHERE c.UserID_Web = b.UserID_Web)
  AND NOT EXISTS (SELECT 1 FROM HRIS_Page_Access d WHERE d.UserID_Web = b.UserID_Web AND d.Jenis_page = 'PaMasterGrupKurvaPage');

COMMIT TRANSACTION;
GO
