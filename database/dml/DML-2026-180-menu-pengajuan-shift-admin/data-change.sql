/* Change ID: DML-2026-180-menu-pengajuan-shift-admin

   Menu "Pengajuan Shift Admin" (/admin/pengajuan-shift) di header Administrator
   HCIS: admin HC memantau seluruh pengajuan perubahan jadwal — Ganti Shift
   (karyawan, leader, admin), Tukar Hari, Status Hari — dan membatalkan tanggal
   yang belum diterapkan.

   Flag_Hibah_Role = 'T' dengan sengaja: halaman ini bisa membatalkan pengajuan
   karyawan mana pun, jadi TIDAK ikut terbuka untuk setiap pemegang role grup ESS.
   Akses awal disalin dari pemegang eksplisit Shift Admin (ShiftManagementAdmin)
   — dev 13 Okt 2026: 8 baris aktif.

   Amandemen 13 Okt 2026 (sebelum SQL Tracker): versi pertama bernama
   selfShiftAdmin (/admin/self-shift) sempat dijalankan di dev lalu di-rollback.

   Sesudah dijalankan, bersihkan cache menu: InertiaShellService::flushMenuCache().
   IDEMPOTEN. */
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'pengajuanShiftAdmin')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('pengajuanShiftAdmin', '001', 'Pengajuan Shift Admin', 'bi bi-calendar2-week', '/admin/pengajuan-shift', 'administrator_hcis', 'Administrator HCIS', 'bi bi-gear-fill', '7', 'T', '/admin/pengajuan-shift*', 'self', 'Y', 'Pantau & batalkan ganti shift, tukar hari, dan status hari');
END;

INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT 1, 'pengajuanShiftAdmin', NULL, 'T', 'Y', 'Y', 'T'
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'pengajuanShiftAdmin');

INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT sumber.Kode_Perusahaan, sumber.ID_Level, sumber.ID_Divisi,
       'pengajuanShiftAdmin', sumber.UserID_Web, sumber.Urutan_Menu, 'Y', SYSDATETIME()
  FROM HRIS_Page_Access sumber
 WHERE sumber.Jenis_page = 'ShiftManagementAdmin'
   AND sumber.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM HRIS_Page_Access ada
        WHERE ada.Jenis_page = 'pengajuanShiftAdmin'
          AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
          AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
          AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
   );

COMMIT TRANSACTION;

/* Verifikasi:
SELECT COUNT(*) FROM N_HRIS_Menus WHERE Jenis_page = 'pengajuanShiftAdmin';          -- 1
SELECT COUNT(*) FROM HRIS_Page_Access WHERE Jenis_page = 'pengajuanShiftAdmin' AND Flag_Aktif = 'Y';
*/
