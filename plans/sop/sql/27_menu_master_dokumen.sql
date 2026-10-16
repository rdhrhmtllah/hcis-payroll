/* =====================================================================
   27 — Menu "Master Dokumen SOP"

   Dijalankan MANUAL di SSMS. Akses halaman SOP dikendalikan baris di
   N_HRIS_Menus + grant peran, bukan kode — jadi tanpa skrip ini halaman
   /sop/admin/dokumen tetap ditolak 403 dan menunya tak muncul.

   -------------------------------------------------------------------
   POSISINYA SEBELUM BUILDER, DAN ITU DISENGAJA.

   Alur yang dituju: admin mendeklarasikan SOP di Master Dokumen LEBIH
   DULU, lalu memakainya di banyak Paket SOP lewat Builder. Menempatkan
   menunya setelah Builder membalik urutan yang justru ingin diajarkan
   oleh susunan menu itu sendiri.

   Karena itu Sort_Menu Builder dan sesudahnya digeser +1.
   ===================================================================== */

BEGIN TRANSACTION;

/* 1. Geser menu setelah Kategori supaya ada tempat di urutan ke-2. */
UPDATE N_HRIS_Menus
   SET Sort_Menu = Sort_Menu + 1
 WHERE Header_Code = 'administrator_sop'
   AND Kode_Perusahaan = '001'
   AND CAST(Sort_Menu AS INT) >= 2;

/* 2. Sisipkan menu baru. Idempoten: tak menulis dua kali bila sudah ada. */
IF NOT EXISTS (SELECT 1 FROM N_HRIS_Menus WHERE Jenis_page = 'menuSopDokumenPage' AND Kode_Perusahaan = '001')
BEGIN
    INSERT INTO N_HRIS_Menus
        (Jenis_page, Kode_Perusahaan, Nama_Menu, Icon_Menu, Url_Menu,
         Header_Code, Header_Label, Header_Icon, Sort_Header,
         Is_Global_Menu, Sort_Menu, Target, Is_Show_Sidebar, Menu_Subtitle)
    VALUES
        ('menuSopDokumenPage', '001', 'Master Dokumen SOP', 'bi-journal-bookmark-fill', '/sop/admin/dokumen',
         'administrator_sop', 'Administrator SOP', 'bi bi-gear-fill', '11',
         'T', '2', 'self', 'Y', 'Deklarasikan SOP sebelum dipakai di paket');

    PRINT '27: menu Master Dokumen SOP dibuat pada urutan ke-2.';
END
ELSE
BEGIN
    PRINT '27: menu Master Dokumen SOP sudah ada — tidak ada perubahan.';
END

COMMIT;
GO

/* Pemeriksaan pasca-jalan: urutan menu Administrator SOP. */
SELECT Sort_Menu, Nama_Menu, Jenis_page, Url_Menu
  FROM N_HRIS_Menus
 WHERE Header_Code = 'administrator_sop' AND Kode_Perusahaan = '001'
 ORDER BY CAST(Sort_Menu AS INT);
GO

/* =====================================================================
   LANGKAH KEDUA — PETAKAN KE GRUP FITUR. WAJIB AGAR MENU MUNCUL.

   Sidebar dibangun InertiaShellService::fetchMappedMenuRows() dari
   N_HRIS_Grup_Fitur_Map JOIN N_HRIS_Menus. Baris di N_HRIS_Menus saja
   TIDAK cukup: tanpa pasangannya di Grup_Fitur_Map, menu itu tak pernah
   ikut terambil, berapa pun izin yang dimiliki user.

   INI BERBEDA TUJUAN dari LANGKAH KETIGA, dan keduanya wajib:
     - Grup_Fitur_Map  -> menentukan menu MUNCUL di sidebar
     - HRIS_Page_Access -> menentukan user BOLEH membukanya
   Memperlakukan keduanya sebagai alternatif membuat menu tetap hilang
   meski semua pemeriksaan izin tampak hijau.
   ===================================================================== */

INSERT INTO N_HRIS_Grup_Fitur_Map
    (Id_Grup_Fitur, Jenis_page, Sort_Order, Is_Default_Landing, Is_Show_Sidebar, Flag_Aktif)
SELECT gfm.Id_Grup_Fitur, 'menuSopDokumenPage', gfm.Sort_Order, 'T', 'Y', 'Y'
  FROM N_HRIS_Grup_Fitur_Map gfm
 WHERE gfm.Jenis_page = 'menuSopBuilderPage'
   AND NOT EXISTS (SELECT 1 FROM N_HRIS_Grup_Fitur_Map x
                    WHERE x.Jenis_page = 'menuSopDokumenPage');
GO

/* =====================================================================
   LANGKAH KETIGA — GRANT AKSES. WAJIB, dan inilah yang paling mudah
   terlewat: baris menu saja TIDAK membuat menunya muncul.

   check.jabatan:menuSopDokumenPage memeriksa apakah user punya kunci itu.
   Di lingkungan ini jalurnya sudah ditelusuri: user admin SOP yang dipakai
   sehari-hari (aji, UserID_Web 622) memetakan ke Grup Fitur 1 dan 2,
   sedangkan menuSopBuilderPage ada di Grup Fitur 3. Artinya IZIN-nya tidak
   datang dari jalur grup fitur melainkan dari baris eksplisit
   HRIS_Page_Access.

   Itu TIDAK membuat Langkah Kedua tak perlu: grup fitur mengurus
   penampilan menu, page access mengurus izin masuk. Keduanya wajib.
   ===================================================================== */

INSERT INTO HRIS_Page_Access
    (Kode_Perusahaan, ID_Level, ID_Divisi, Jenis_page, UserID_Web, Flag_Aktif, Created_At, Updated_At)
SELECT pa.Kode_Perusahaan, pa.ID_Level, pa.ID_Divisi, 'menuSopDokumenPage', pa.UserID_Web, 'Y', GETDATE(), GETDATE()
  FROM HRIS_Page_Access pa
 WHERE pa.Jenis_page = 'menuSopBuilderPage'
   AND pa.Flag_Aktif = 'Y'
   AND NOT EXISTS (
        SELECT 1 FROM HRIS_Page_Access x
         WHERE x.UserID_Web = pa.UserID_Web
           AND x.Jenis_page = 'menuSopDokumenPage');
GO

/* Menyalin dari menuSopBuilderPage disengaja: siapa pun yang boleh
   menyusun paket SOP adalah orang yang sama yang mendeklarasikan
   dokumennya. NOT EXISTS membuatnya aman dijalankan ulang.

   PERIKSA hasilnya — jumlah kedua baris ini harus SAMA: */
SELECT Jenis_page, COUNT(*) AS jumlah_user
  FROM HRIS_Page_Access
 WHERE Jenis_page IN ('menuSopBuilderPage', 'menuSopDokumenPage')
   AND Flag_Aktif = 'Y'
 GROUP BY Jenis_page;
GO

/* =====================================================================
   LANGKAH KEEMPAT — BERSIHKAN CACHE MENU. WAJIB.

   InertiaShellService menyimpan baris menu di cache untuk menekan jumlah
   query per halaman, dan membersihkannya otomatis HANYA bila menu diubah
   lewat aplikasi (flushMenuCache). Menambah baris lewat SQL langsung tak
   memicu apa pun — sidebar akan tetap menyajikan daftar lama meski baris
   menu dan grant-nya sudah benar sepenuhnya.

   Ini penyebab paling membingungkan dari ketiganya, karena semua
   pemeriksaan di database tampak hijau sementara layar tak berubah.

   Jalankan dari akar proyek:

     php -r "require 'vendor/autoload.php'; \$a=require 'bootstrap/app.php';
       \$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
       App\Services\Shell\InertiaShellService::flushMenuCache();"

     php artisan cache:clear

   Lalu refresh browser. Logout tidak diperlukan.
   ===================================================================== */

/* ---------------------------------------------------------------------
   ROLLBACK — jalankan HANYA bila halaman ini dibatalkan.

     DELETE FROM N_HRIS_Menus WHERE Jenis_page = 'menuSopDokumenPage';
     UPDATE N_HRIS_Menus SET Sort_Menu = Sort_Menu - 1
      WHERE Header_Code = 'administrator_sop' AND Kode_Perusahaan = '001'
        AND CAST(Sort_Menu AS INT) >= 3;

   Tak ada data SOP yang hilang: N_SOP_Dokumen tetap utuh dan tetap dipakai
   Builder lewat pemilih Dokumen. Yang hilang hanya pintunya.
   --------------------------------------------------------------------- */
