/* Change ID: DML-2026-193-menu-monitoring-kehadiran

   Menu "Monitoring Kehadiran" (/admin/monitoring-kehadiran) di header Administrator
   HCIS, grup ESS: admin HC Personel memantau kehadiran hari ini (sudah/belum absen,
   terlambat, izin, sakit, cuti, day off, lembur) dan rekap per periode cut-off.

   Flag_Hibah_Role = 'T': halaman memperlihatkan data kehadiran seluruh karyawan dalam
   cakupan admin, jadi TIDAK ikut terbuka untuk setiap pemegang role grup ESS.
   Akses awal disalin dari pemegang eksplisit Izin Admin (IzinPageAdmin).

   Sesudah dijalankan, bersihkan cache menu: InertiaShellService::flushMenuCache()
   di SEMUA checkout yang melayani vhost. IDEMPOTEN. */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'essMonitoringKehadiranPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('essMonitoringKehadiranPage', '001', 'Monitoring Kehadiran', 'bi bi-activity', '/admin/monitoring-kehadiran', 'administrator_hcis', 'Administrator HCIS', 'bi bi-gear-fill', '7', 'T', '/admin/monitoring-kehadiran*', 'self', 'Y', 'Pantau kehadiran hari ini & rekap per periode cut-off');
END;

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT 1, 'essMonitoringKehadiranPage', NULL, 'T', 'Y', 'Y', 'T'
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'essMonitoringKehadiranPage');

INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT sumber.Kode_Perusahaan, sumber.ID_Level, sumber.ID_Divisi,
       'essMonitoringKehadiranPage', sumber.UserID_Web, sumber.Urutan_Menu, 'Y', SYSDATETIME()
  FROM HRIS_Page_Access sumber
 WHERE sumber.Jenis_page = 'IzinPageAdmin'
   AND sumber.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM HRIS_Page_Access ada
        WHERE ada.Jenis_page = 'essMonitoringKehadiranPage'
          AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
          AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
          AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
   );

COMMIT TRANSACTION;

/* Verifikasi:
SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'essMonitoringKehadiranPage';          -- 1
SELECT COUNT(*) FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'essMonitoringKehadiranPage';  -- 1
SELECT COUNT(*) FROM HRIS_Page_Access WHERE Jenis_page = 'essMonitoringKehadiranPage' AND Flag_Aktif = 'Y';
   -- = jumlah pemegang aktif IzinPageAdmin (tanpa duplikat)
*/
