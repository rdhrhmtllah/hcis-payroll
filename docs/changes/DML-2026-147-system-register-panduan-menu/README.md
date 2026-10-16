# DML-2026-147 — Registrasi menu Manajemen Panduan di grup SYSTEM

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-147-system-register-panduan-menu` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Artifact | `database/dml/DML-2026-147-system-register-panduan-menu/data-change.sql` (sha256 `f3eee98081bee1909f412369d84664bb1e322b69027598713b009730dde3c031`) |
| Bergantung pada | `DDL-2026-146-system-panduan-manual` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |

## Isi

```text
N_HRIS_Menus          Jenis_page = systemPanduanPage, header baru `aplikasi` (Sort_Header 3)
N_HRIS_Grup_Fitur_Map Id_Grup_Fitur = 5 (SYSTEM)
HRIS_Page_Access      0 baris — disengaja
N_SYSTEM_Database_Change 1 baris
```

## Hasil eksekusi development (2026-09-29)

```text
N_HRIS_Menus          Id_Menu = 139, Jenis_page = systemPanduanPage
N_HRIS_Grup_Fitur_Map Id_Grup_Map = 293, Id_Grup_Fitur = 5 (SYSTEM)
HRIS_Page_Access      0 baris
```

Dijalankan ulang: tanpa duplikat. `InertiaShellService::flushMenuCache()`
dijalankan sesudahnya; lakukan hal yang sama di production.

## Nol baris `HRIS_Page_Access`

Sama dengan `DML-2026-029`: halaman SYSTEM digerbangi middleware
`system.admin` (`KPI_Users.Is_System_Admin = 'Y'`), bukan `check.jabatan`.

Endpoint pengguna (`/panduan/halaman/{jenisPage}`, `/panduan/{id}/berkas`)
hanya butuh login; penyaringnya hak akses halaman yang dipetakan.

## Idempoten

Setiap INSERT dijaga `IF NOT EXISTS`; dijalankan ulang tidak menambah baris.
