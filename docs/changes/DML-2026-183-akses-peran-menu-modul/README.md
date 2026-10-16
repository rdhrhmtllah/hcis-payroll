# DML-2026-183 — Menu "Akses & Peran" per modul

Mendaftarkan lima menu `{ess,kpi,lms,visitor,dms}AksesPeranPage` (`/admin/{modul}/akses-peran`) di bawah header admin masing-masing modul — header disalin dari menu Menu & Matrix modul itu (DMS: dari Monitoring Dokumen). Peta `N_HRIS_Grup_Fitur_Map` ke modulnya dengan `Flag_Hibah_Role = 'T'`: menu ini TIDAK diberikan lewat hibah, grant, atau matriks. Yang melihat & membukanya: admin (Super Admin/Admin) modul itu dan System Admin — gerbang `akses.peran` + sidebar `InertiaShellService`. SYSTEM → Akses & Peran (`systemAksesPeranPage`) sejak perubahan ini khusus System Admin (kode).

Idempoten. Target `Web_HRIS`. Urutan rilis: bersama kode branch `feat/system/rbac-akses-peran-modul`. Sesudahnya bersihkan cache menu (`InertiaShellService::flushMenuCache` / `php artisan cache:clear`).

Rollback: `UPDATE dbo.N_HRIS_Menus SET Is_Show_Sidebar = 'T' WHERE Jenis_page LIKE '%AksesPeranPage' AND Jenis_page <> 'systemAksesPeranPage';`
