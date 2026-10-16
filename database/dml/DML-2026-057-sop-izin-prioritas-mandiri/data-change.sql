/* Change ID: DML-2026-057-sop-izin-prioritas-mandiri
   Target: Web_HRIS development only.
   Purpose: Menanam tiga baris pendaftaran halaman "Prioritas Dokumen" supaya ia
            berdiri di kunci izinnya sendiri (menuSopPrioritasPage).

   KENAPA SEKARANG
   ---------------
   Halaman Prioritas sudah punya controller, service, halaman Vue, dan rute
   sendiri sejak awal. Yang tak pernah ada adalah tiga baris pendaftarannya di
   database. Karena itu rutenya terpaksa ikut menerima menuSopKategoriPage
   sebagai TONGKAT — kalau tidak, halaman yang sudah jadi itu menolak semua
   orang.

   Utang itu tak terlihat selama Kategori masih hidup. Pekerjaan pencabutan
   Kategori (lihat docs/superpowers/specs/2026-08-28-sop-cabut-kategori-design.md)
   akan mematikan kunci Kategori, dan pada detik itu juga halaman Prioritas
   berubah jadi 403 untuk seluruh organisasi.

   URUTANNYA MENGIKAT DAN TAK BISA DIBALIK
   ---------------------------------------
   Ketiga blok di bawah menyalin dari baris menuSopKategoriPage. Begitu Kategori
   dimatikan (DML-2026-058), skrip ini mandul. Jalankan yang ini LEBIH DULU.

   ASAL-USUL
   ---------
   Isinya diangkat dari plans/sop/sql/37_menu_prioritas_sop.sql yang ditulis
   tapi tak pernah dijalankan. Empat hal disegarkan terhadap keadaan 2026-08-28:

     1. Nama menu "Prioritas SOP" -> "Prioritas Dokumen". Skrip lama mendahului
        standardisasi kosakata DMS (DML-2026-044); seluruh menu tetangga kini
        berbunyi "... Dokumen".
     2. Komentar pemetaan grup fitur menyebut grup 3 (Learning Management
        System). Sejak DML-2026-044 seluruh halaman SOP ada di grup DMS.
        SQL-nya sendiri tak berubah karena ia MENYALIN Id_Grup_Fitur dari baris
        Kategori, bukan menuliskan angkanya.
     3. Sort_Menu tetap 2. Skrip lama beralasan "slot itu kosong (1, 3, 4, 5, 6,
        7, 8)" dan alasan itu sudah tidak berlaku — daftar menu admin DMS kini
        1..11 tanpa lubang, dan slot 2 justru milik Kategori. Nilainya tetap 2
        karena DML-2026-058 mengosongkan slot itu, sehingga urutan akhirnya
        kembali 1..11 tanpa lubang. Di antara kedua perubahan, dua menu memang
        sama-sama bernomor 2 dan urutan relatifnya tak menentu; itu tidak
        merusak apa pun.
     4. Menu_Subtitle diisi, meniru tetangganya yang semuanya punya.

   TENTANG Id_Menu
   ---------------
   Kolom IDENTITY. Sengaja TIDAK disebut di daftar kolom INSERT: SQL Server
   menolak nilai eksplisit ("Cannot insert explicit value for identity column")
   dan seluruh batch-nya gagal. Nomornya ditentukan database sendiri.

   SESUDAH MENJALANKAN
   -------------------
   Buang cache menu sidebar, kalau tidak menunya baru muncul setelah TTL habis:

       php artisan tinker --execute="App\Services\Shell\InertiaShellService::flushMenuCache();"

   AMAN DIJALANKAN BERULANG.
*/

SET XACT_ABORT ON;

/* ── 1. Baris menu ─────────────────────────────────────────────────────
   Disalin dari baris menuSopKategoriPage, bukan ditulis nilainya satu per
   satu: Kode_Perusahaan, Header_Code, Header_Label, Header_Icon, dan
   Sort_Header harus sama persis dengan menu admin DMS lainnya. Satu saja yang
   meleset membuat menunya lahir di kelompok yang salah — atau tak muncul sama
   sekali.
   Lingkup: tepat 1 baris pada N_HRIS_Menus. */
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Menus WHERE Jenis_page = 'menuSopPrioritasPage')
BEGIN
    INSERT INTO dbo.N_HRIS_Menus
        (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
         Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu,
         Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    SELECT
        'menuSopPrioritasPage',
        k.Kode_Perusahaan,
        'Prioritas Dokumen',
        'bi-sliders',
        '/sop/admin/prioritas',
        k.Header_Code,
        k.Header_Label,
        k.Header_Icon,
        k.Sort_Header,
        k.Is_Global_Menu,
        2,
        'self',
        'Y',
        'Tingkat urgensi paket dokumen'
      FROM dbo.N_HRIS_Menus k
     WHERE k.Jenis_page = 'menuSopKategoriPage';

    IF @@ROWCOUNT <> 1
        THROW 50057, 'Baris menu menuSopKategoriPage tidak ditemukan atau ganda, jadi tak ada yang bisa disalin.', 1;

    PRINT 'Menu "Prioritas Dokumen" ditambahkan.';
END
ELSE
    PRINT 'Menu menuSopPrioritasPage sudah ada - dilewati.';

COMMIT TRANSACTION;
GO

/* ── 2. Pemetaan ke grup fitur ─────────────────────────────────────────
   INI YANG PALING MUDAH TERLEWAT, dan tanpanya menu TIDAK AKAN PERNAH MUNCUL
   sekalipun barisnya ada dan hak aksesnya sudah diberikan. Sidebar dibangun
   dari JOIN Grup_Fitur_Map -> Master_Grup_Fitur -> Menus; baris menu yang tak
   terpetakan tersaring habis oleh join pertama, tanpa galat apa pun.

   Id_Grup_Fitur DISALIN dari baris Kategori, bukan ditulis angkanya, supaya
   perpindahan modul di kemudian hari tidak meninggalkan skrip ini di grup yang
   salah.
   Id_Grup_Map IDENTITY, jadi tak disebut.
   Lingkup: tepat 1 baris pada N_HRIS_Grup_Fitur_Map. */
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopPrioritasPage')
BEGIN
    INSERT INTO dbo.N_HRIS_Grup_Fitur_Map
        (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT k.Id_Grup_Fitur, 'menuSopPrioritasPage', k.Sort_Order, 'T', 'Y', 'Y'
      FROM dbo.N_HRIS_Grup_Fitur_Map k
     WHERE k.Jenis_page = 'menuSopKategoriPage';

    IF @@ROWCOUNT <> 1
        THROW 50057, 'Pemetaan menuSopKategoriPage tidak ditemukan atau ganda, jadi tak ada yang bisa disalin.', 1;

    PRINT 'Pemetaan grup fitur untuk menuSopPrioritasPage ditambahkan.';
END
ELSE
    PRINT 'Pemetaan grup fitur sudah ada - dilewati.';

COMMIT TRANSACTION;
GO

/* ── 3. Hak akses ──────────────────────────────────────────────────────
   Diberikan kepada SETIAP pemegang menuSopKategoriPage yang aktif, dan hanya
   kepada yang belum punya barisnya sendiri. Merekalah orang yang sama yang
   mengelola prioritas.
   Lingkup diperkirakan: 2 baris (user 4 dan 622) pada HRIS_Page_Access. */
BEGIN TRANSACTION;

INSERT INTO dbo.HRIS_Page_Access
    (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At, Updated_At)
SELECT
    a.Kode_Perusahaan, a.ID_Level, a.ID_Divisi, 'menuSopPrioritasPage', a.UserID_Web, 'Y', GETDATE(), GETDATE()
  FROM dbo.HRIS_Page_Access a
 WHERE a.Jenis_page = 'menuSopKategoriPage'
   AND a.Flag_Aktif = 'Y'
   AND NOT EXISTS (
        SELECT 1 FROM dbo.HRIS_Page_Access b
         WHERE b.Jenis_page = 'menuSopPrioritasPage'
           AND ISNULL(b.UserID_Web, -1) = ISNULL(a.UserID_Web, -1)
   );

COMMIT TRANSACTION;
GO

/* ── 4. Verifikasi ─────────────────────────────────────────────────────
   Angkanya lewat variabel: PRINT hanya menerima ekspresi skalar, dan subquery
   yang ditempel langsung ke dalamnya ditolak — batch verifikasinya gagal justru
   pada skrip yang tugasnya memverifikasi. */
DECLARE @n INT = (SELECT COUNT(*) FROM dbo.HRIS_Page_Access WHERE Jenis_page = 'menuSopPrioritasPage' AND Flag_Aktif = 'Y');

IF @n = 0
    THROW 50057, 'Tak seorang pun mendapat menuSopPrioritasPage. Periksa apakah ada pemegang menuSopKategoriPage yang aktif.', 1;

PRINT 'Hak akses menuSopPrioritasPage dipegang '+CAST(@n AS VARCHAR(10))+' user.';
GO

/* ── 5. Daftarkan perubahan ────────────────────────────────────────────── */
IF NOT EXISTS (
    SELECT 1 FROM N_SYSTEM_Database_Change
     WHERE Kode_Change = 'DML-2026-057-sop-izin-prioritas-mandiri'
)
BEGIN
    INSERT INTO N_SYSTEM_Database_Change
    (
        Kode_Change, Jenis_Change, Judul_Change, Deskripsi_Change,
        Artifact_Path, Documentation_Path, Target_Development,
        Status_Development, Status_SQL_Tracker, Status_Production,
        Status_Verification, Created_By
    )
    VALUES
    (
        'DML-2026-057-sop-izin-prioritas-mandiri',
        'DML',
        'Halaman Prioritas Dokumen berdiri di kunci izinnya sendiri',
        'Menanam baris N_HRIS_Menus, N_HRIS_Grup_Fitur_Map, dan HRIS_Page_Access untuk menuSopPrioritasPage, yang tak pernah didaftarkan sehingga rutenya terpaksa menumpang menuSopKategoriPage. Prasyarat mutlak DML-2026-058 (pencabutan Kategori): ketiga bloknya menyalin dari baris Kategori, jadi harus dijalankan lebih dulu.',
        'database/dml/DML-2026-057-sop-izin-prioritas-mandiri/data-change.sql',
        'docs/superpowers/specs/2026-08-28-sop-cabut-kategori-design.md',
        'Web_HRIS',
        'PENDING',
        'PENDING',
        'PENDING',
        'PENDING',
        'sop-cabut-kategori'
    );
END;
GO
