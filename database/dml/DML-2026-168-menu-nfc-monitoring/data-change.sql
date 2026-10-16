/* Change ID: DML-2026-168-menu-nfc-monitoring
   Mendaftarkan menu Monitoring ID Card / NFC ke sidebar Administrator Visitor,
   pemetaan grup fitur VISITOR, dan hibah akses bagi pemegang hak verifikator permit.
*/
SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

/* ── 1. N_HRIS_Menus ─────────────────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'visitorNfcMonitoringPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Active_Match_Pattern, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('visitorNfcMonitoringPage', '001', 'Monitoring ID Card / NFC', 'bi bi-credit-card-2-front-fill', '/admin/visitor/nfc', 'administrator_visitor', 'Administrator Visitor', 'bi bi-gear-fill', '10', 'T', '23', '/admin/visitor/nfc*', 'self', 'Y', 'Monitoring master ID Card & pairing NFC karyawan / mitra');
END;

/* ── 2. N_HRIS_Grup_Fitur_Map ─────────────────────────────────────────── */
INSERT INTO N_HRIS_Grup_Fitur_Map (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif, Flag_Hibah_Role)
SELECT 4, 'visitorNfcMonitoringPage', 23, 'T', 'Y', 'Y', 'T'
 WHERE NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'visitorNfcMonitoringPage');

/* ── 3. HRIS_Page_Access (diturunkan dari verifikator permit / master permit) ── */
INSERT INTO HRIS_Page_Access
(Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT DISTINCT sumber.Kode_Perusahaan, sumber.ID_Level, sumber.ID_Divisi,
       'visitorNfcMonitoringPage', sumber.UserID_Web, 23, 'Y', SYSDATETIME()
  FROM HRIS_Page_Access sumber
 WHERE sumber.Jenis_page IN ('visitorVerifikatorPermitPage', 'visitorMasterDataPermitPage')
   AND sumber.Flag_Aktif = 'Y'
   AND NOT EXISTS (
       SELECT 1 FROM HRIS_Page_Access ada
        WHERE ada.Jenis_page = 'visitorNfcMonitoringPage'
          AND ISNULL(ada.UserID_Web, -1) = ISNULL(sumber.UserID_Web, -1)
          AND ISNULL(ada.ID_Level, -1) = ISNULL(sumber.ID_Level, -1)
          AND ISNULL(ada.ID_Divisi, -1) = ISNULL(sumber.ID_Divisi, -1)
   );

COMMIT TRANSACTION;
