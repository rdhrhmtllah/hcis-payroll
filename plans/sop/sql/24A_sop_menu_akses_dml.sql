/* ============================================================================
   SOP 24A - DML Menutup Akses Global Halaman Admin SOP

   Masalah:
   Ketujuh halaman admin SOP ditandai Is_Global_Menu = 'Y' di N_HRIS_Menus.
   Di User::memilikiJabatanLegacy(), flag itu memberi akses ke SEMUA pengguna
   tanpa syarat — tanpa memeriksa role, level, maupun divisi:

       $globalAccess = DB::table('N_HRIS_Menus')
           ->where('Is_Global_Menu','Y')->pluck('Jenis_page');

   Akibatnya seluruh 45 pengguna dapat menyusun, menerbitkan, dan MENGHAPUS
   paket SOP mana pun, menugaskan SOP ke karyawan mana pun, melihat data
   kepatuhan seluruh karyawan, serta mengubah pengaturan otomasi global.

   Tindakan:
   Mencabut flag global pada ENAM halaman ADMIN. Dua halaman konsumsi karyawan
   (menuSopSayaPage, menuSopRepositoryPage) SENGAJA tetap global — itu halaman
   yang memang harus bisa dibuka semua karyawan.

   Prasyarat: SOP 22A, 23A, 23B sudah dijalankan.
   Aman dijalankan berulang kali. Tidak menyentuh data paket SOP sama sekali.

   PENTING - baca STEP 0 dulu. Menjalankan STEP 2 tanpa memastikan ada admin
   yang masih berhak akan mengunci semua orang dari modul SOP.
   ============================================================================ */

SET NOCOUNT ON;
GO

/* ============================================================================
   STEP 0 - PRA-PERIKSA (hanya membaca, tidak mengubah apa pun)
   Jalankan blok ini lebih dulu dan baca hasilnya.
   ============================================================================ */

-- 0.1 Keadaan flag global saat ini
SELECT  Jenis_page,
        Is_Global_Menu,
        CASE WHEN Jenis_page IN ('menuSopSayaPage','menuSopRepositoryPage')
             THEN 'KONSUMSI - tetap global'
             ELSE 'ADMIN - akan dicabut'
        END AS Rencana
FROM    N_HRIS_Menus
WHERE   Jenis_page LIKE 'menuSop%'
ORDER BY Rencana, Jenis_page;

-- 0.2 Siapa yang MASIH berhak setelah flag dicabut (jalur hak per-pengguna).
--     Kalau hasilnya KOSONG, JANGAN lanjut ke STEP 2 - semua orang akan terkunci.
--     Jalankan STEP 1 lebih dulu untuk memberi hak kepada admin yang dituju.
SELECT   pa.UserID_Web,
         u.Username,
         ISNULL(k.Nama, '(tanpa data karyawan)') AS Nama,
         COUNT(*)                               AS Jumlah_Halaman_SOP
FROM     HRIS_Page_Access pa
LEFT JOIN KPI_Users u ON u.Id_Users    = pa.UserID_Web
LEFT JOIN Karyawan  k ON k.UserID_Web  = pa.UserID_Web
WHERE    pa.Jenis_page LIKE 'menuSop%'
  AND    pa.Flag_Aktif = 'Y'
GROUP BY pa.UserID_Web, u.Username, k.Nama
ORDER BY pa.UserID_Web;

-- 0.3 Jalur role (N_HRIS_Role_User -> N_HRIS_Role_Functional -> Grup Fitur 3).
--     Saat skrip ini ditulis hasilnya KOSONG: tidak ada pengguna yang memegang
--     role ber-Grup Fitur 3, sehingga jalur role belum bisa diandalkan.
SELECT   ru.UserId_Web, rf.Id_Role, rf.Role_Code, rf.Role_Name
FROM     N_HRIS_Role_User ru
JOIN     N_HRIS_Role_Functional rf ON rf.Id_Role = ru.Id_Role
WHERE    ru.Flag_Aktif = 'Y'
  AND    rf.Id_Grup_Fitur = 3;
GO

/* ============================================================================
   STEP 1 - (OPSIONAL) Beri hak admin SOP kepada pengguna tertentu

   Jalankan HANYA bila STEP 0.2 belum memuat semua orang yang seharusnya tetap
   bisa mengelola SOP. Lewati bila daftar di 0.2 sudah benar.

   Cara pakai: ubah daftar di dalam VALUES menjadi Id_Users yang dituju.
   Blok ini idempoten - menjalankannya dua kali tidak menggandakan baris.

   Catatan: hak diberikan PER-PENGGUNA lewat HRIS_Page_Access, bukan lewat role.
   Alasannya, role yang memetakan ke Grup Fitur 3 adalah role LMS
   (LMS_MENU_ADMINISTRATOR), sehingga memberikannya juga membuka seluruh
   halaman admin LMS - hak yang lebih luas dari yang diminta.
   ============================================================================ */

/*  -- HILANGKAN TANDA KOMENTAR BLOK INI BILA PERLU MENAMBAH ADMIN

DECLARE @AdminSop TABLE (Id_Users INT PRIMARY KEY);

INSERT INTO @AdminSop (Id_Users) VALUES
    (4),        -- NOIR / Ridho  (sudah punya hak, aman diulang)
    (622);      -- Aji           (sudah punya hak, aman diulang)
    -- (624),   -- arya / Arya   <- contoh penambahan
    -- (1),     -- Steven        <- contoh penambahan

DECLARE @HalamanAdmin TABLE (Jenis_page VARCHAR(100) PRIMARY KEY);

INSERT INTO @HalamanAdmin (Jenis_page) VALUES
    ('menuSopBuilderPage'),
    ('menuSopKategoriPage'),
    ('menuSopKontenPage'),
    ('menuSopPenjadwalanPage'),
    ('menuSopMonitoringPage'),
    ('menuSopKebijakanPage');

BEGIN TRANSACTION;

    -- Id_Page_Access adalah IDENTITY, jadi tidak disebut di daftar kolom.
    -- ID_Level dan ID_Divisi NOT NULL, diambil dari KPI_Users bila tersedia.
    INSERT INTO HRIS_Page_Access
        (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web,
         Created_At, Updated_At, Flag_Aktif)
    SELECT  '001',
            ISNULL(TRY_CAST(u.Level_Id    AS INT), 0),
            ISNULL(TRY_CAST(u.Division_Id AS INT), 0),
            h.Jenis_page,
            a.Id_Users,
            SYSDATETIME(),
            SYSDATETIME(),
            'Y'
    FROM    @AdminSop a
    CROSS JOIN @HalamanAdmin h
    LEFT JOIN KPI_Users u ON u.Id_Users = a.Id_Users
    WHERE   NOT EXISTS (
                SELECT 1 FROM HRIS_Page_Access pa
                WHERE  pa.UserID_Web = a.Id_Users
                  AND  pa.Jenis_page = h.Jenis_page
                  AND  pa.Flag_Aktif = 'Y'
            );

    -- Hidupkan kembali baris yang pernah dinonaktifkan
    UPDATE  pa
    SET     pa.Flag_Aktif = 'Y', pa.Updated_At = SYSDATETIME()
    FROM    HRIS_Page_Access pa
    JOIN    @AdminSop     a ON a.Id_Users   = pa.UserID_Web
    JOIN    @HalamanAdmin h ON h.Jenis_page = pa.Jenis_page
    WHERE   ISNULL(pa.Flag_Aktif,'T') <> 'Y';

COMMIT TRANSACTION;

*/
GO

/* ============================================================================
   STEP 2 - Cabut flag global pada halaman ADMIN

   JANGAN jalankan sebelum STEP 0.2 memperlihatkan minimal satu pengguna.
   ============================================================================ */

BEGIN TRANSACTION;

    -- Pengaman: batalkan bila tidak ada seorang pun yang akan tetap berhak.
    IF NOT EXISTS (
        SELECT 1 FROM HRIS_Page_Access
        WHERE  Jenis_page = 'menuSopBuilderPage' AND Flag_Aktif = 'Y'
    )
    BEGIN
        ROLLBACK TRANSACTION;
        RAISERROR ('DIBATALKAN: tidak ada pengguna yang berhak atas menuSopBuilderPage. Jalankan STEP 1 lebih dulu, kalau tidak semua orang akan terkunci dari Builder.', 16, 1);
    END
    ELSE
    BEGIN
        UPDATE  N_HRIS_Menus
        SET     Is_Global_Menu = 'T'          -- 'T' = konvensi "tidak global" di tabel ini
        WHERE   Jenis_page IN (
                    'menuSopBuilderPage',
                    'menuSopKategoriPage',
                    'menuSopKontenPage',
                    'menuSopPenjadwalanPage',
                    'menuSopMonitoringPage',
                    'menuSopKebijakanPage'
                )
          AND   ISNULL(Is_Global_Menu,'T') <> 'T';

        PRINT 'Selesai. Halaman admin SOP tidak lagi global.';
        COMMIT TRANSACTION;
    END
GO

/* ============================================================================
   STEP 3 - VERIFIKASI
   ============================================================================ */

-- 3.1 Harapan: enam halaman admin bernilai 'T', dua halaman konsumsi tetap 'Y'
SELECT  Jenis_page,
        Is_Global_Menu,
        CASE WHEN Jenis_page IN ('menuSopSayaPage','menuSopRepositoryPage')
             THEN CASE WHEN Is_Global_Menu = 'Y' THEN 'BENAR' ELSE 'PERIKSA' END
             ELSE CASE WHEN Is_Global_Menu = 'T' THEN 'BENAR' ELSE 'PERIKSA' END
        END AS Status
FROM    N_HRIS_Menus
WHERE   Jenis_page LIKE 'menuSop%'
ORDER BY Jenis_page;

-- 3.2 Tidak boleh kosong: daftar pengguna yang masih dapat membuka Builder
SELECT   pa.UserID_Web, u.Username, ISNULL(k.Nama,'(tanpa data karyawan)') AS Nama
FROM     HRIS_Page_Access pa
LEFT JOIN KPI_Users u ON u.Id_Users   = pa.UserID_Web
LEFT JOIN Karyawan  k ON k.UserID_Web = pa.UserID_Web
WHERE    pa.Jenis_page = 'menuSopBuilderPage'
  AND    pa.Flag_Aktif = 'Y';
GO

/* ============================================================================
   ROLLBACK - kembalikan seperti semula bila diperlukan

   Menjadikan keenam halaman admin global lagi. Setelah ini seluruh pengguna
   kembali dapat mengelola SOP, jadi gunakan hanya sebagai jalan keluar darurat.
   ============================================================================ */

/*
UPDATE  N_HRIS_Menus
SET     Is_Global_Menu = 'Y'
WHERE   Jenis_page IN (
            'menuSopBuilderPage',
            'menuSopKategoriPage',
            'menuSopKontenPage',
            'menuSopPenjadwalanPage',
            'menuSopMonitoringPage',
            'menuSopKebijakanPage'
        );
*/
