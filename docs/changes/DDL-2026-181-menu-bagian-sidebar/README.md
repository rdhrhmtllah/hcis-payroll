# DDL-2026-181: Tingkat "bagian" pada menu sidebar

Menambah empat kolom *nullable* di `dbo.N_HRIS_Menus` untuk tingkat **bagian** opsional di atas grup menu (`Header_*`): Modul → **Bagian** → Grup → Menu.

| Kolom | Tipe | Arti |
|---|---|---|
| `Section_Code` | `varchar(100) NULL` | Kunci bagian (mis. `key_performance_indicator`). NULL = menu tanpa bagian. |
| `Section_Label` | `varchar(150) NULL` | Judul bagian di sidebar. |
| `Section_Icon` | `varchar(100) NULL` | Ikon Bootstrap Icons (mis. `bi bi-graph-up-arrow`). |
| `Sort_Section` | `int NULL` | Urutan bagian dalam modul (kecil dulu). |

**Kompatibel ke belakang:** menu yang kolomnya NULL tampil persis seperti sebelumnya (Modul → Grup → Menu). Kode (`InertiaShellService`) hanya membaca kolom ini bila sudah ada, jadi boleh rilis sebelum DDL dijalankan. Hak akses tidak tersentuh — murni tampilan.

- Pemakai: `App\Services\SYSTEM\Application\Shell\InertiaShellService` (`fetchMappedMenuRows`, `transformMenuRow`, `susunBagian`) → `navigation.modules[].sections`; `resources/js/components/Shell/AppSidebar.vue` + `bagianMenu.js`.
- Data pertama: DML-2026-182 (KPI/PA usulan LnD).
- Idempoten. `drop.sql` hanya untuk pembersihan manual (jalankan rollback DML-2026-182 lebih dulu).
- Setelah dijalankan: `InertiaShellService::flushMenuCache()` di setiap checkout.
