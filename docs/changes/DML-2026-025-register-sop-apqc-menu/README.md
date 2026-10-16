# DML-2026-025 — Registrasi menu Master APQC di bawah LMS

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-025-register-sop-apqc-menu` |
| Jenis | DML |
| Target development | `Web_HRIS` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-025-register-sop-apqc-menu/data-change.sql` |

## Basis registry yang diverifikasi

```text
Module        : LMS
Id_Grup_Fitur : 3            (diverifikasi 2026-08-22)
Jenis_page    : menuSopApqcPage
Source URL    : /sop/admin/apqc
Middleware    : auth, check.jabatan:menuSopApqcPage
Header menu   : administrator_sop
```

Berbeda dari Master Entitas yang berada di SYSTEM dengan gerbang `system.admin`,
Master APQC **tetap di Administrator SOP** dengan gerbang `check.jabatan` —
keputusan pemilik: APQC dipakai dan dirawat oleh tata kelola SOP.

## Dampak yang diperkirakan dan yang terjadi

| Tabel | Perkiraan | Hasil |
|---|---|---|
| `N_HRIS_Menus` | 1 baris | 1 baris (`Id_Menu` 91) |
| `N_HRIS_Grup_Fitur_Map` | 1 baris | 1 baris (`Id_Grup_Fitur` 3) |
| `HRIS_Page_Access` | sebanyak pemegang `menuSopJenisDokumenPage` aktif | 2 baris |
| `N_SYSTEM_Database_Change` | 1 baris | 1 baris |

Hak akses diturunkan dari pemegang `menuSopJenisDokumenPage`: yang mengelola
master jenis adalah orang yang sama yang akan mengelola klasifikasi proses.

## Sisa pekerjaan pengguna

1. Konfirmasi daftar pemegang akses di production.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
