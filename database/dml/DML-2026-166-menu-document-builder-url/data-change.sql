/* Change ID: DML-2026-166-menu-document-builder-url

   Menu sidebar "Document Builders" (N_HRIS_Menus) menunjuk ke
   /sop/admin/builders (jamak), padahal rutenya /sop/admin/builder
   (sop.admin.builder, routes/LMS/SOP/Governance/SopGovernance.php). Setiap klik
   dari sidebar berbalas 404. Seluruh tautan lain di aplikasi sudah memakai
   bentuk tunggal.

   Terukur di development Web_HRIS (baca saja, 10 Okt 2026): satu baris,
   Id_Menu 71, Kode_Perusahaan 001. Tak ada tabel menu/halaman lain yang
   menyimpan URL ini; hak akses terikat ke Id_Menu, bukan ke URL.

   Dicocokkan lewat URL, bukan Id_Menu: identitas baris bisa berbeda antara
   development dan production.

   Sesudah dijalankan, sidebar baru berubah setelah cache menu
   (shell.menu-rows.v1:{Kode_Perusahaan}, TTL 600 detik) kedaluwarsa atau
   dibersihkan lewat InertiaShellService::flushMenuCache().

   IDEMPOTEN: menjalankan ulang = 0 baris. */

SET NOCOUNT ON;
SET XACT_ABORT ON;
BEGIN TRANSACTION;

UPDATE N_HRIS_Menus
SET Url_Menu = '/sop/admin/builder'
WHERE Url_Menu = '/sop/admin/builders';

PRINT CONCAT('N_HRIS_Menus diubah: ', @@ROWCOUNT);

COMMIT TRANSACTION;

/* Verifikasi (harus 0):
SELECT COUNT(*) AS Sisa FROM N_HRIS_Menus WHERE Url_Menu = '/sop/admin/builders';
*/
