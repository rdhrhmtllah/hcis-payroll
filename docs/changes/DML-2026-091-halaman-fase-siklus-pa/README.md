# DML-2026-091 — Halaman fase siklus PA: pemantauan dan hasil

| | |
|---|---|
| Change ID | `DML-2026-091-halaman-fase-siklus-pa` |
| Jenis | DML |
| Artifact revision | **r1** |
| Artifact | `database/dml/DML-2026-091-halaman-fase-siklus-pa/data-change.sql` |
| Bergantung pada | DML-2026-084 (halaman siklus PA sudah terdaftar) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang dilakukan

Menambah dua halaman fase beserta akses dan peta grup fiturnya:

| Kunci halaman | Alamat | Menu |
|---|---|---|
| `PaPemantauanPage` | `/performance-appraisal/pemantauan` | Pemantauan Pengisian (urut 3) |
| `PaHasilPage` | `/performance-appraisal/hasil` | Hasil dan Penerbitan (urut 4) |

`PerformanceAppraisalPage` yang sudah ada tetap, menjadi fase **Penyiapan**.

## Kenapa dipecah

`siklus.vue` berukuran **1.578 baris** dan menampung sembilan dialog serta delapan
composable — menyiapkan siklus, menugaskan penilai, memantau pengisian, menghitung,
mengkalibrasi, dan menerbitkan, semuanya di satu layar.

Ketiganya dikerjakan pada **waktu** yang berbeda dan sering oleh **orang** yang berbeda:
menyiapkan sekali di awal, memantau berminggu-minggu, memutuskan angka sesudah semuanya
tutup. Yang memantau tidak perlu melihat tombol "kunci siklus" yang tak akan ia sentuh
sepanjang tahun.

Alasan yang lebih tajam: **kalibrasi dan penerbitan mengubah angka yang sampai ke
karyawan.** Menaruhnya satu klik dari "siapkan peserta" membuat tindakan paling
berkonsekuensi di modul ini berada di tempat yang sama dengan pekerjaan rutin.

## Pembagiannya bukan karangan

Mengikuti `PaCycleGate::IZIN` yang sudah ada — operasi apa yang diizinkan pada status apa:

| Fase | Status | Operasi gerbang |
|---|---|---|
| Penyiapan | DRAFT, PREPARED | `SUNTING_*`, `BUAT_PENUGASAN`, `KUNCI_SIKLUS` |
| Pengisian | LOCKED, OPEN | `BUKA_PENGISIAN`, `TERIMA_PAYLOAD`, `TUTUP_PENGISIAN` |
| Hasil | CLOSED, PUBLISHED | `HITUNG_HASIL`, `KALIBRASI`, `TERBITKAN` |

`SUNTING_BOBOT_PILAR` sengaja melintasi ketiganya (K23) dan ditaruh di fase Penyiapan,
karena ia menyunting salinan — bukan memutuskan angka akhir siapa pun.

## Keputusan lama yang DIHORMATI, bukan dibatalkan

Berkas rutenya dulu menulis:

> "Memecahnya menjadi beberapa kunci akan melahirkan kombinasi hak yang tak seorang pun
> berniat memberikannya — misalnya orang yang boleh menghitung hasil tetapi tak boleh
> melihat peserta yang dihitungnya."

Kekhawatiran itu **sah dan tetap berlaku**. Yang berubah adalah cara mendamaikannya:

- Yang berbahaya adalah memecah hak **MEMBACA**. Karena itu seluruh endpoint `GET`
  digerbangi ketiga kunci sekaligus (logika salah-satu) — siapa pun yang memegang salah
  satu fase bisa melihat seluruh keadaan siklus. Kombinasi yang ditakutkan itu **tetap
  mustahil**.
- Yang berguna adalah memecah hak **MENULIS**, dan hanya itu yang dipecah.

## Tidak seorang pun kehilangan kemampuan

Aksesnya **ditiru** dari pemegang `PerformanceAppraisalPage`, bukan didaftar manual —
daftar manual akan berselisih dengan kenyataan begitu ada orang ditambahkan ke halaman
siklus.

Skripnya menuntut **jumlah pemegang ketiga kunci sama persis**. Kalau ada yang tertinggal,
transaksinya dibatalkan: orang yang terlewat tidak akan mengeluh, ia hanya menemukan
tombolnya hilang tanpa penjelasan.

Yang bertambah adalah **kemampuan membedakannya**. Kalau kelak HC ingin memisahkan "yang
memantau" dari "yang menerbitkan", cukup cabut satu baris akses — tanpa menyentuh kode.

## Sesudah dijalankan

```
php artisan tinker --execute="\App\Services\SYSTEM\Application\Shell\InertiaShellService::flushMenuCache();"
```

## Verifikasi lewat EFEK

1. Menu PA menjadi 5 baris: siklus, Penilaian Saya (1), Penilaian Tim (2), Pemantauan (3),
   Hasil (4).
2. Akses ketiga kunci fase **sama persis** — diperiksa di dalam skrip.
3. H45 (user 4) **LOLOS** ketiganya; karyawan acak (user 594) **DITOLAK** pada
   `PaHasilPage` sebagai kontrol negatif.
4. Regresi 43 uji hijau: `CycleGateTest` 10, `RantaiStatusTest` 3, `ResultPublishTest` 10,
   `AdminDashboardTest` 11, `KontrakLaporanTest` 9.

Seluruhnya dijalankan pada dev 3 September 2026 dan lolos.

## Catatan: satu cacat yang dibuat lalu ditutup saat mengerjakan ini

Draf pertama berkas rutenya memakai `const PA_KUNCI_SEMUA_FASE` di ruang berkas. Itu
**mematikan 38 uji sekaligus** dengan `Constant already defined` — Laravel mem-boot
aplikasi baru untuk tiap uji, sehingga deklarasi kedua fatal.

Gejalanya menyesatkan: tepat **satu** uji lulus di tiap berkas, uji yang sama **lulus bila
dijalankan sendiri** dengan `--filter`, dan yang merah termasuk uji yang tidak menyentuh
rute sama sekali. Diganti nilai sebaris; jangan pernah mendeklarasikan `const`, `function`,
atau `class` di ruang berkas route.
