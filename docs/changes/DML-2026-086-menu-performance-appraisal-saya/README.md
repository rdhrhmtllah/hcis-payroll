# DML-2026-086 — Halaman peserta Performance Appraisal

| | |
|---|---|
| Change ID | `DML-2026-086-menu-performance-appraisal-saya` |
| Jenis | DML |
| Artifact revision | **r1** |
| Artifact | `database/dml/DML-2026-086-menu-performance-appraisal-saya/data-change.sql` |
| Bergantung pada | DML-2026-084 (halaman siklus PA sudah terdaftar) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang dilakukan

Satu baris `N_HRIS_Menus` untuk `PerformanceAppraisalSayaPage` (`/performance-appraisal/saya`),
dipasang **`Is_Global_Menu = 'Y'`**, plus satu baris `N_HRIS_Grup_Fitur_Map` ke grup `KPI`.

**Nol baris `HRIS_Page_Access`.** Itu disengaja, dan menjelaskan seluruh perubahan ini.

## Kenapa membalik pola DML-2026-085

DML-2026-085 memberi akses delapan halaman master lewat `HRIS_Page_Access`, disebut satu
per satu. Itu benar di sana: penggunanya sedikit, dan pita grade — yang mengubah arti nilai
semua orang pada siklus berikutnya — memang harus diberikan kepada orang yang disebut
namanya.

Halaman ini kebalikannya. Ia dipakai **seluruh karyawan** — 958 orang aktif di dev per
1 September 2026. Mendaftarkan mereka satu per satu berarti:

- 958 baris yang harus disisipkan, lalu ditambah lagi setiap ada karyawan baru;
- dan yang terlewat **tidak akan mengeluh**. Ia hanya tidak pernah mengisi penilaiannya,
  lalu muncul di laporan admin sebagai penilai yang tidak merespons.

Kegagalan yang menyamar jadi kelalaian orang adalah kegagalan yang paling lama tidak
diperbaiki, karena tidak ada yang mencarinya.

## Dua jalur yang dibuka `Is_Global_Menu`

Keduanya diperiksa pada kode yang berjalan hari ini, bukan diasumsikan:

| Jalur | Berkas | Perilaku |
|---|---|---|
| Gerbang rute | `User::memilikiJabatanLegacy()` | menggabungkan daftar halaman global ke akses user, sehingga `CheckJabatan` lolos untuk semua orang |
| Sidebar | `InertiaShellService::menuIsVisible()` | mengembalikan `true` lebih awal untuk baris global, tanpa menyentuh `HRIS_Page_Access` |

Delapan halaman lain sudah memakai pola ini (antara lain `menuSopSayaPage` dan
`MyActivity`), jadi ini mengikuti jalan yang sudah ada.

## Verifikasi

Dijalankan **dua kali berturut-turut** pada `Web_HRIS` dev, 1 September 2026. Keduanya OK;
baris menu tetap **1**, map **1**, `HRIS_Page_Access` **0**.

Yang lebih penting, diverifikasi lewat **efek**, bukan lewat isi tabel:

```
uji gerbang, user #594 (karyawan acak): LOLOS
kontrol negatif, PaMasterPitaNilaiPage : DITOLAK (benar)
```

Kontrol negatifnya yang membuat hasil pertama berarti. Tanpa itu, "LOLOS" juga akan muncul
seandainya gerbangnya rusak dan meloloskan segalanya.

Penjaga di dalam artefaknya menolak dua keadaan: halaman tidak terpasang atau tidak global
(`50374`), dan baris menu lebih dari satu (`50375`). Yang kedua ada karena `Jenis_page`
tidak punya unique constraint — dijalankan tanpa penjaga `NOT EXISTS`, hasilnya dua baris
identik di sidebar yang tidak menimbulkan galat apa pun, hanya keraguan.

## Sesudah dijalankan

```
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

Tanpa ini menunya tidak muncul sampai cache kedaluwarsa sendiri.
