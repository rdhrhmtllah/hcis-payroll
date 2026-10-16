# DML-2026-029 — Registrasi menu Master Departemen di grup SYSTEM

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-029-register-system-departemen-menu` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-029-register-system-departemen-menu/data-change.sql` |

## Hasil eksekusi

```text
N_HRIS_Menus          Id_Menu = 92, Jenis_page = systemMasterDepartemenPage
N_HRIS_Grup_Fitur_Map Id_Grup_Fitur = 5 (SYSTEM)
HRIS_Page_Access      0 baris — disengaja
```

Duduk di header `master_data` bersama Master Entitas (`Sort_Menu = 2`).

## Nol baris `HRIS_Page_Access` adalah keputusan, bukan kelalaian

Halaman SYSTEM digerbangi middleware **`system.admin`**
(`EnsureSystemAdmin` → `KPI_Users.Is_System_Admin = 'Y'`), bukan
`check.jabatan`. Grup fitur SYSTEM tidak dipegang satu pun role di
`N_HRIS_Role_Functional`, jadi memasang `check.jabatan` di sini justru
menghasilkan gerbang yang **tak seorang pun** bisa lewati. Preseden yang sama
ada di `DML-2026-021-register-system-master-entitas-menu` dan
`routes/SYSTEM/DatabaseChanges.php`.

## Modul lain tidak membaca lewat route ini

Master Departemen dipakai lintas modul, tetapi Master Dokumen SOP dan Builder
membacanya lewat endpoint bergerbang sendiri:

```text
GET /api/v1/sop/admin/master-data/departemen
    middleware: auth, check.jabatan:menuSopDokumenPage,menuSopBuilderPage
```

Yang berada di balik `system.admin` hanya **pengelolaannya** — menambah kode,
menyunting nama, dan memasang binding. Menggabungkan keduanya di satu gerbang
akan membuat admin SOP tak bisa memilih departemen sama sekali.
