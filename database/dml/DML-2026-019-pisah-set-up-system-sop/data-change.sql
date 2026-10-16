/* Change ID: DML-2026-019-pisah-set-up-system-sop */

/* Memisahkan halaman "Kebijakan & Otomasi" (/sop/admin/kebijakan) menjadi dua:
     - /sop/admin/kebijakan     -> "Otomasi SOP" (Aturan Penugasan + Riwayat Eksekusi)
     - /sop/admin/setup-system  -> "Kebijakan Global SOP" (Kebijakan Global + Otomasi Terjadwal)

   Keduanya TETAP milik modul SOP (Id_Grup_Fitur = 3 / LMS, diverifikasi dari
   N_HRIS_Master_Grup_Fitur pada 2026-08-22). Yang dipisah adalah halamannya,
   bukan grup fiturnya — gerbang otorisasi aplikasi ini memang per-Jenis_page
   (CheckJabatan -> User::memilikiJabatan), tidak ada konsep izin di bawah
   halaman. */

/* 1. Baris menu untuk halaman baru, di bawah header BARU "Set Up System".
      Sort_Header 12 menaruhnya tepat setelah "Administrator SOP" (11).
      Header di aplikasi ini didenormalisasi per baris menu — kolom
      Header_Code/Header_Label/Header_Icon/Sort_Header ikut di setiap baris,
      tidak ada tabel header terpisah (lihat InertiaShellService::512-528). */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'menuSopSetupSystemPage')
BEGIN
    INSERT INTO N_HRIS_Menus
    (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu, Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu, Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
    ('menuSopSetupSystemPage', '001', 'Kebijakan Global SOP', 'bi-sliders', '/sop/admin/setup-system', 'setup_system', 'Set Up System', 'bi bi-sliders', '12', 'T', '1', 'self', 'Y', 'Guardrail kepatuhan & jadwal otomasi');
END;

/* 2. Pemetaan ke grup fitur LMS. TANPA baris ini menu tidak pernah muncul di
      sidebar dan tidak ada galat apa pun yang menjelaskannya. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopSetupSystemPage')
BEGIN
    INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    VALUES
    (3, 'menuSopSetupSystemPage', 'T', 'Y', 'Y');
END;

/* 3. Hak akses diturunkan dari pemegang menuSopKebijakanPage — halaman yang
      dipecah ini. Siapa pun yang tadinya melihat keempat tab harus tetap
      melihat keempatnya setelah pemisahan; tanpa langkah ini halaman baru
      membalas 403 untuk SEMUA orang.

      Ini bukan kehati-hatian teoretis: pada 2026-08-22 tidak ada satu pun
      penugasan role aktif untuk Id_Grup_Fitur = 3 di N_HRIS_Role_User, sehingga
      SELURUH akses SOP hari ini bergantung pada HRIS_Page_Access eksplisit. */
INSERT INTO HRIS_Page_Access (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Urutan_Menu, Flag_Aktif, Created_At)
SELECT a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, 'menuSopSetupSystemPage', a.UserID_Web, a.Urutan_Menu, 'Y', SYSDATETIME()
FROM HRIS_Page_Access a
WHERE a.Jenis_page = 'menuSopKebijakanPage'
  AND a.Flag_Aktif = 'Y'
  AND NOT EXISTS (
      SELECT 1 FROM HRIS_Page_Access b
      WHERE b.Jenis_page = 'menuSopSetupSystemPage'
        AND ISNULL(b.UserID_Web, -1) = ISNULL(a.UserID_Web, -1)
        AND ISNULL(b.ID_Level, -1) = ISNULL(a.ID_Level, -1)
        AND ISNULL(b.ID_Divisi, -1) = ISNULL(a.ID_Divisi, -1)
  );

/* 4. Halaman lama berganti nama. URL dan Jenis_page sengaja TIDAK diubah:
      keduanya kunci akses yang sudah dipegang, dan /sop/admin/kebijakan masih
      ditautkan dari contentDashboard.vue. */
UPDATE N_HRIS_Menus
SET Nama_Menu = 'Otomasi SOP',
    Menu_Subtitle = 'Aturan penugasan & riwayat eksekusi'
WHERE Jenis_page = 'menuSopKebijakanPage';

/* 5. Pendaftaran ke Database Change Dashboard. Mengisi database/ddl/status.json
      saja TIDAK membuat perubahan ini terlihat di sana — keduanya catatan yang
      terpisah. Lihat DML-2026-011. */
IF NOT EXISTS (SELECT 1 FROM N_SYSTEM_Database_Change WHERE Kode_Change = 'DML-2026-019-pisah-set-up-system-sop')
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change, Artifact_Path, Documentation_Path, Target_Development, Status_Development, Status_SQL_Tracker, Status_Production, Status_Verification, Created_By)
    VALUES
    ('DML-2026-019-pisah-set-up-system-sop', 'DML', 'Pisah halaman Kebijakan & Otomasi SOP menjadi Otomasi SOP dan Set Up System', 'Menu baru menuSopSetupSystemPage di header Set Up System, pemetaan ke grup LMS, hak akses diturunkan dari pemegang menuSopKebijakanPage, dan penggantian nama menu halaman lama menjadi Otomasi SOP.', 'database/dml/DML-2026-019-pisah-set-up-system-sop/data-change.sql', 'docs/changes/DML-2026-019-pisah-set-up-system-sop/README.md', 'Web_HRIS', 'DONE', 'PENDING', 'PENDING', 'DEV_DONE', 'setup-system');
END;
