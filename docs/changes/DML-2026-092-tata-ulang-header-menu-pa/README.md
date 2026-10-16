# DML-2026-092 — Tata ulang header menu PA

| | |
|---|---|
| Change ID | `DML-2026-092-tata-ulang-header-menu-pa` |
| Jenis | DML |
| Artifact revision | **r1** |
| Artifact | `database/dml/DML-2026-092-tata-ulang-header-menu-pa/data-change.sql` |
| Bergantung pada | DML-2026-091 (dua halaman fase sudah terdaftar) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalahnya

Header `performance_appraisal` memuat **campuran** yang membuat satu header berisi hal
yang sama sekali berbeda tergantung siapa yang membukanya:

| Menu | Akses | Yang melihat |
|---|---|---|
| Penilaian Saya | `Is_Global_Menu='Y'` | 958 karyawan |
| Penilaian Tim | `Is_Global_Menu='Y'` | 958 karyawan |
| Performance Appraisal | eksplisit | 2 orang |
| Pemantauan Pengisian | eksplisit | 2 orang |
| Hasil dan Penerbitan | eksplisit | 2 orang |

Karyawan biasa melihat dua butir; admin melihat lima — dengan nama header yang sama. Orang
yang membandingkan layarnya dengan rekan akan mengira ada yang rusak.

Kejanggalan kedua, lebih kecil tetapi menular: halaman siklus bernama **"Performance
Appraisal" di dalam header bernama "Performance Appraisal"**.

## Sesudah perubahan

```
Performance Appraisal        (global — semua karyawan)
  1. Penilaian Saya
  2. Penilaian Tim

Administrator PA             (akses eksplisit)
  1. Siklus dan Penyiapan    <- pindah, berganti nama
  2. Pemantauan Pengisian    <- pindah
  3. Hasil dan Penerbitan    <- pindah
  4. Jenis Kompetensi
  5. Kompetensi
  6. Aturan Penilai
  7. Bobot Pilar
  8. Pita Nilai
  9. Paket Soal
```

Fase ditaruh lebih dulu karena itulah **alur kerjanya** — menyiapkan, memantau, memutuskan.
Master menyusul karena konfigurasi jarang disentuh.

## Urutannya ikut dirapikan, dan itu bukan kosmetik

Nomor master sempat berlubang (1, 2, 4, 5, 6, 8) karena dua halaman dicabut DML-2026-090.
Lubang membuat penyisipan berikutnya menebak-nebak, dan urutan kembar membuat susunan
sidebar bergantung pada urutan baris — yang tidak dijamin SQL Server.

## Tidak ada hak yang berubah

Yang diubah hanya `Header_Code`, `Header_Label`, `Nama_Menu`, dan `Sort_Menu`.
**`HRIS_Page_Access` tidak disentuh sama sekali**, jadi tidak seorang pun mendapat atau
kehilangan akses karena perubahan ini.

## Sesudah dijalankan

```
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

## Verifikasi lewat EFEK, bukan isi tabel

Yang diperiksa bukan "barisnya berubah" melainkan **apa yang benar-benar dilihat orang**:

| Siapa | Melihat |
|---|---|
| Admin H45 (user 4) | **11 butir dalam DUA header** — 2 di Performance Appraisal, 9 di Administrator PA |
| Karyawan biasa (user 594) | **2 butir dalam SATU header** |

Tidak ada lagi header yang isinya berubah tergantung pembukanya.

Skripnya sendiri juga menuntut, dan membatalkan transaksi bila tidak terpenuhi:

1. Header karyawan berisi **tepat 2** halaman, dan semuanya `Is_Global_Menu='Y'`.
2. Header admin berisi **tepat 9**, dan **tak satu pun** global — halaman admin yang
   diam-diam global akan tampil bagi 958 orang yang tidak bisa memakainya.
3. Urutan 1..9 tanpa kembar.

## Catatan: satu kesalahan yang tertangkap penjaganya sendiri

Draf pertama menyetel kolom `Updated_At`, yang **tidak ada** di `N_HRIS_Menus` — asumsi
yang tidak diperiksa. Transaksinya batal dengan benar, sehingga tidak ada perubahan
separuh jalan yang tertinggal: keadaan menu sebelum dan sesudah percobaan gagal itu sama
persis. Kolomnya dibuang, lalu dijalankan ulang.
