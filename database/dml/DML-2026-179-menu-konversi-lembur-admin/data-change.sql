/* Change ID: DML-2026-179-menu-konversi-lembur-admin

   Menu "Konversi Lembur Admin" (/admin/konversi-lembur) di header Administrator HCIS:
   admin HC memantau seluruh pengajuan Konversi Lembur dan membatalkannya.

   Flag_Hibah_Role = 'T' dengan sengaja: halaman ini bisa membatalkan pengajuan yang
   sudah diterapkan dan menarik kembali saldo cuti, jadi TIDAK ikut terbuka untuk
   setiap pemegang role grup ESS. Akses awal disalin dari pemegang eksplisit Lembur
   Admin (OvertimeManagementAdmin) — dev 13 Okt 2026: 8 baris aktif.

   Sesudah dijalankan, bersihkan cache menu: InertiaShellService::flushMenuCache().
   IDEMPOTEN. */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'konversiLemburAdmin')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('konversiLemburAdmin', '001', 'Konversi Lembur Admin', 'bi bi-arrow-left-right', '/admin/konversi-lembur', 'administrator_hcis', 'Administrator HCIS', 'bi bi-gear-fill', '7', 'T', '/admin/konversi-lembur*', 'self', 'Y', 'Pantau & batalkan pengajuan konversi lembur');
END;

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT 1, 'konversiLemburAdmin', NULL, 'T', 'Y', 'Y', 'T'
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'konversiLemburAdmin');

INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT sumber.Kode_Perusahaan, sumber.ID_Level, sumber.ID_Divisi,
       'konversiLemburAdmin', sumber.UserID_Web, sumber.Urutan_Menu, 'Y', SYSDATETIME()
  FROM HRIS_Page_Access sumber
 WHERE sumber.Jenis_page = 'OvertimeManagementAdmin'
   AND sumber.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM HRIS_Page_Access ada
        WHERE ada.Jenis_page = 'konversiLemburAdmin'
          AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
          AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
          AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
   );

COMMIT TRANSACTION;

/* Verifikasi:
SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'konversiLemburAdmin';          -- 1
SELECT COUNT(*) FROM HRIS_Page_Access WHERE Jenis_page = 'konversiLemburAdmin' AND Flag_Aktif = 'Y';
*/
