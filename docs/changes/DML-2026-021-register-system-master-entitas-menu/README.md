# DML-2026-021 — Registrasi menu Master Entitas di bawah SYSTEM

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-021-register-system-master-entitas-menu` |
| Jenis | DML |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-021-register-system-master-entitas-menu/data-change.sql` |

## Basis registry yang diverifikasi

```text
Master module : N_HRIS_Master_Grup_Fitur
Module        : SYSTEM
Id_Grup_Fitur : 5
Flag_Aktif    : Y
Diverifikasi  : 2026-08-22 (kueri langsung ke Web_HRIS)

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : systemMasterEntitasPage
Source URL    : /system/master-data/entitas
Middleware    : auth, system.admin        <- BUKAN check.jabatan
```

## Kenapa tanpa satu pun baris `HRIS_Page_Access`

Ini bagian yang paling mudah salah ditiru, jadi dicatat eksplisit.

Halaman SYSTEM di repo ini **tidak memakai gerbang `check.jabatan`**. Buktinya
terukur pada 2026-08-22:

- `N_HRIS_Role_Functional` punya role untuk ESS, KPI, LMS, dan VISITOR —
  **nol role** memegang `Id_Grup_Fitur = 5`.
- `systemDatabaseChangeDashboardPage`, satu-satunya halaman SYSTEM yang sudah
  ada, punya **nol baris** di `HRIS_Page_Access`.
- Gerbangnya `routes/SYSTEM/DatabaseChanges.php` adalah
  `Route::middleware(['auth', 'system.admin'])`.

Di repo ini "System Admin" bukan role, melainkan flag `KPI_Users.Is_System_Admin`.
Per 2026-08-22 hanya **1 dari 945** user memilikinya.

Karena itu menyisipkan grant `HRIS_Page_Access` untuk halaman ini justru salah:
ia akan membuka master lintas modul kepada pemegang menu modul tertentu, padahal
gerbang sebenarnya ada di middleware.

`Is_Global_Menu = 'T'` mengikuti Database Change Dashboard: tautannya tidak
dibagikan ke semua orang.

## Dampak yang diperkirakan dan yang terjadi

| Tabel | Perkiraan | Hasil |
|---|---|---|
| `N_HRIS_Menus` | 1 baris | 1 baris (`Id_Menu` 89) |
| `N_HRIS_Grup_Fitur_Map` | 1 baris | 1 baris (`Id_Grup_Fitur` 5) |
| `HRIS_Page_Access` | 0 baris (disengaja) | 0 baris |
| `N_SYSTEM_Database_Change` | 1 baris | 1 baris |

Idempoten. Tidak ada baris lama yang di-update atau dihapus.

## Diverifikasi di browser

```text
NIK A4  (Is_System_Admin kosong) -> 403
NIK H45 (Is_System_Admin = 'Y')  -> 200, halaman terender, nol galat konsol
```

## Sisa pekerjaan pengguna

1. Pastikan daftar pemegang `Is_System_Admin` di production sudah benar —
   di development hanya satu orang.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
