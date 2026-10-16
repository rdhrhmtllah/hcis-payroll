# DML-2026-167 — Menu "Akses & Peran"

RBAC Fase 2b. Menambah baris `N_HRIS_Menus` `systemAksesPeranPage` (`/system/akses-peran`, header "Akses & Peran") dan memetakannya ke grup SYSTEM dengan `Flag_Hibah_Role = 'T'`.

Gerbang: middleware `akses.peran` (System Admin atau pemegang role berjenis ADMIN); sidebar memakai aturan yang sama (`InertiaShellService::menuIsVisible`). Tidak ada grant `HRIS_Page_Access` — sengaja.

Urutan rilis: setelah kode Fase 2b (tanpa kode, menu menunjuk rute yang belum ada). Idempoten.

Rollback:

```sql
DELETE FROM dbo.N_HRIS_Grup_Fitur_Map WHERE Jenis_page = 'systemAksesPeranPage';
DELETE FROM dbo.N_HRIS_Menus WHERE Jenis_page = 'systemAksesPeranPage';
```

Sesudah dijalankan, cache menu perlu dibersihkan (`InertiaShellService::flushMenuCache()` / `php artisan cache:clear`).
