/* =====================================================================
   SUDAH TIDAK DIPAKAI — DIANGKAT JADI DML RESMI 28 Agu 2026
   =====================================================================
   Isi skrip ini pindah ke:

       database/dml/DML-2026-057-sop-izin-prioritas-mandiri/data-change.sql

   dengan empat penyegaran terhadap keadaan 2026-08-28:
     1. Nama menu "Prioritas SOP" -> "Prioritas Dokumen" (kosakata DMS,
        DML-2026-044).
     2. Komentar grup fitur: grup 3 (LMS) -> grup 7 (DMS).
     3. Alasan Sort_Menu=2 ditulis ulang; slot 2 kini terpakai Kategori dan
        baru kosong setelah DML-2026-058.
     4. Menu_Subtitle diisi, meniru tetangganya.

   JANGAN jalankan berkas ini. Ia disimpan sebagai jejak saja.
   ===================================================================== */

/* =====================================================================
   37 — DML: menu & hak akses halaman "Prioritas SOP"
   =====================================================================

   Halaman master prioritas dipisah dari halaman Kategori SOP. Skrip ini yang
   membuatnya MUNCUL di sidebar dan bisa dibuka.

   TIGA HAL YANG DILAKUKAN, SEMUANYA MENYALIN DARI KATEGORI SOP
   ------------------------------------------------------------
   1. Baris menu, disalin dari baris menuSopKategoriPage yang sudah ada.
      Menyalin, bukan menulis nilainya satu per satu: Kode_Perusahaan,
      Header_Code, Header_Label, Header_Icon, dan Sort_Header harus sama persis
      dengan menu admin SOP lainnya, dan satu saja yang meleset membuat menunya
      lahir di kelompok yang salah — atau tak muncul sama sekali.

   2. PEMETAAN KE GRUP FITUR (N_HRIS_Grup_Fitur_Map). INI YANG PALING MUDAH
      TERLEWAT, dan tanpanya menu TIDAK AKAN PERNAH MUNCUL sekalipun barisnya
      ada dan hak aksesnya diberikan. Sidebar dibangun dari JOIN
      Grup_Fitur_Map → Master_Grup_Fitur → Menus; baris menu yang tak terpetakan
      tersaring habis oleh join pertama, tanpa galat apa pun. Seluruh menu SOP
      dipetakan ke grup 3 (Learning Management System).

   3. Hak akses, untuk SETIAP orang yang hari ini memegang menuSopKategoriPage.
      Merekalah orang yang sama yang mengelola prioritas.

   TENTANG Id_Menu
   ---------------
   Kolom ini IDENTITY. Ia sengaja TIDAK disebut di daftar kolom INSERT — SQL
   Server menolak nilai eksplisit ("Cannot insert explicit value for identity
   column ... when IDENTITY_INSERT is set to OFF") dan seluruh batch-nya gagal.
   Nomornya ditentukan database sendiri.

   URUTANNYA 2, tepat sesudah Kategori SOP. Slot itu memang kosong di daftar
   menu admin SOP (1, 3, 4, 5, 6, 7, 8) sehingga tak ada menu lain yang perlu
   digeser.

   HALAMANNYA SUDAH BISA DIBUKA SEBELUM SKRIP INI DIJALANKAN — rutenya menerima
   menuSopKategoriPage juga. Yang belum ada hanya jalan pintasnya di sidebar.

   AMAN DIJALANKAN BERULANG.
   ===================================================================== */

/* ── 1. Baris menu ──────────────────────────────────────────────────── */
IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Menus WHERE Jenis_page = 'menuSopPrioritasPage')
BEGIN
    INSERT INTO dbo.N_HRIS_Menus
        (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
         Header_Code, Header_Label, Header_Icon, Sort_Header, Is_Global_Menu,
         Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    SELECT
        'menuSopPrioritasPage',
        k.Kode_Perusahaan,
        'Prioritas SOP',
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
        'Tingkat urgensi paket'
      FROM dbo.N_HRIS_Menus k
     WHERE k.Jenis_page = 'menuSopKategoriPage';

    IF @@ROWCOUNT = 0
        RAISERROR ('Baris menu menuSopKategoriPage tidak ditemukan, jadi tak ada yang bisa disalin. Periksa Kode_Perusahaan-nya.', 16, 1);
    ELSE
        PRINT 'Menu "Prioritas SOP" ditambahkan.';
END
ELSE
    PRINT 'Menu menuSopPrioritasPage sudah ada — dilewati.';
GO

/* ── 2. Pemetaan ke grup fitur ──────────────────────────────────────────
   Tanpa baris ini menunya tak pernah sampai ke sidebar. Id_Grup_Map identity,
   jadi tak disebut. */
IF NOT EXISTS (SELECT 1 FROM dbo.N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'menuSopPrioritasPage')
BEGIN
    INSERT INTO dbo.N_HRIS_Grup_Fitur_Map
        (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
    SELECT k.Id_Grup_Fitur, 'menuSopPrioritasPage', k.Sort_Order, 'T', 'Y', 'Y'
      FROM dbo.N_HRIS_Grup_Fitur_Map k
     WHERE k.Jenis_page = 'menuSopKategoriPage';

    IF @@ROWCOUNT = 0
        RAISERROR ('Pemetaan menuSopKategoriPage tidak ditemukan, jadi tak ada yang bisa disalin.', 16, 1);
    ELSE
        PRINT 'Pemetaan grup fitur untuk menuSopPrioritasPage ditambahkan.';
END
ELSE
    PRINT 'Pemetaan grup fitur sudah ada — dilewati.';
GO

/* ── 3. Hak akses ───────────────────────────────────────────────────────
   Diberikan kepada setiap pemegang menuSopKategoriPage yang AKTIF, dan hanya
   yang belum punya barisnya sendiri. */
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
GO

DECLARE @n INT = (SELECT COUNT(*) FROM dbo.HRIS_Page_Access WHERE Jenis_page = 'menuSopPrioritasPage' AND Flag_Aktif = 'Y');

/* Angkanya lewat variabel: PRINT hanya menerima ekspresi skalar, dan subquery
   yang ditempel langsung ke dalamnya ditolak — batch verifikasinya gagal justru
   pada skrip yang tugasnya memverifikasi. */
IF @n = 0
    RAISERROR ('Tak seorang pun mendapat menuSopPrioritasPage. Periksa apakah ada pemegang menuSopKategoriPage yang aktif.', 16, 1);
ELSE
    PRINT 'Hak akses menuSopPrioritasPage dipegang '+CAST(@n AS VARCHAR(10))+' user.';
GO

/* CATATAN
   Menambah pemegang baru di kemudian hari cukup satu baris:

   INSERT INTO dbo.HRIS_Page_Access
       (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At, Updated_At)
   VALUES ('001', <ID_Level>, <ID_Divisi>, 'menuSopPrioritasPage', <Id_Users>, 'Y', GETDATE(), GETDATE());

   Skrip 36 (rename menu Kategori jadi "Master SOP") kini TIDAK PERLU dijalankan
   — panel prioritasnya sudah pindah, jadi nama "Kategori SOP" kembali jujur.

   SESUDAH MENJALANKAN SKRIP INI: buang cache menu sidebar, kalau tidak menunya
   baru muncul setelah TTL 10 menit habis.

       php artisan tinker --execute="App\Services\Shell\InertiaShellService::flushMenuCache();"

   Baris menu di-cache lintas-request per Kode_Perusahaan; menu yang ditanam
   lewat SQL langsung tak melewati kode yang membuang cache itu. */
