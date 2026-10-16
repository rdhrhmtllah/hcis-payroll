# DML-2026-018 — Registrasi menu Jenis Dokumen SOP di bawah LMS

| Bidang | Nilai |
|---|---|
| Change ID | `DML-2026-018-register-sop-jenis-dokumen-menu` |
| Jenis | DML |
| Target development | `Web_HRIS` pada `team311.dyndns.info:1433` |
| Status | `DEV_EXECUTED` / `TRACKER_PENDING` |
| Artifact | `database/dml/DML-2026-018-register-sop-jenis-dokumen-menu/data-change.sql` |

## Basis registry yang diverifikasi

```text
Master module : N_HRIS_Master_Grup_Fitur
Module        : LMS
Id_Grup_Fitur : 3
Flag_Aktif    : Y
Diverifikasi  : 2026-08-22 (kueri langsung ke Web_HRIS)

Page mapping  : N_HRIS_Grup_Fitur_Map
Jenis_page    : menuSopJenisDokumenPage
Source URL    : /sop/admin/jenis-dokumen
Middleware    : auth, check.jabatan:menuSopJenisDokumenPage
```

## Isi

1. Baris menu di `N_HRIS_Menus`, header `administrator_sop`, `Sort_Menu = 2`.
   Posisi itu memang kosong: Kategori SOP = 1 dan Master Dokumen = 3, jadi master
   jenis duduk di antara dua master lain, bukan di ekor daftar.
2. Pemetaan `Jenis_page` ke `Id_Grup_Fitur = 3`. Tanpa baris ini menu tidak
   pernah muncul di sidebar dan tidak ada galat apa pun yang menjelaskannya.
3. Hak akses diturunkan dari pemegang `menuSopKategoriPage`, **bukan** dari
   `menuSopMonitoringPage`: yang mengelola taksonomi kategori adalah orang yang
   sama yang akan mengelola tingkat hirarki. Menurunkannya dari Monitoring akan
   memberi hak ubah master kepada orang yang tugasnya hanya membaca kepatuhan.
4. Pendaftaran diri ke Database Change Dashboard.

## Dampak yang diperkirakan dan yang terjadi

| Tabel | Perkiraan | Hasil |
|---|---|---|
| `N_HRIS_Menus` | 1 baris | 1 baris (`Id_Menu` 88) |
| `N_HRIS_Grup_Fitur_Map` | 1 baris | 1 baris (`Id_Grup_Fitur` 3) |
| `HRIS_Page_Access` | sebanyak pemegang `menuSopKategoriPage` aktif | 2 baris |
| `N_SYSTEM_Database_Change` | 1 baris | 1 baris |

Idempoten. Tidak ada baris lama yang di-update atau dihapus.

## Sisa pekerjaan pengguna

1. Konfirmasi bahwa penurunan hak akses dari `menuSopKategoriPage` sesuai
   kebijakan peran di production — daftar pemegangnya bisa berbeda dari
   development.
2. Catat ke SQL Tracker, jalankan ke production, verifikasi.
