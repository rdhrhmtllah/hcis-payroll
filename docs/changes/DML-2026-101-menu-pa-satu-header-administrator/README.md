# DML-2026-101 — Menu PA jadi satu header "Administrator PA"

| | |
|---|---|
| Change ID | `DML-2026-101-menu-pa-satu-header-administrator` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-101-menu-pa-satu-header-administrator/data-change.sql` |
| Bergantung pada | DML-2026-100 |
| Perubahan kode penyerta | tidak ada — murni penempatan dan nama tampil menu |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Yang diselesaikan

Menu Performance Appraisal tersebar di **lima header** dengan empat `Sort_Header` berbeda:

| Sort | Header | Isi |
|---|---|---|
| 6 | `performance_appraisal` | Penilaian Saya |
| 11 | `pa_ringkasan` | Dashboard Admin, Monitoring Progress |
| 12 | `pa_konfigurasi` | Assessment Cycle, Assignment Penilai |
| 13 | `pa_hasil` | Hasil, Kalibrasi, HAV Map, Distribusi, Riwayat |
| 14 | `pa_master` | delapan butir master data |

Admin yang mencari tempat memverifikasi hasil harus menebak antara **Ringkasan**, **Hasil**,
dan **Konfigurasi Cycle** — ketiganya sama masuk akalnya. Namanya pun campur dua bahasa:
"Assessment Cycle", "Monitoring Progress", "Hasil Performance".

Sesudah: satu header `administrator_pa` berlabel **Administrator PA**, mengikuti pola yang
sudah dipakai modul lain (`administrator_dms`, `administrator_kpi`, `administrator_lms`,
`administrator_visitor`). Urutannya mengikuti **alur kerja**, bukan abjad:

```
1  Dashboard PA              6  HAV Map (9-Box)
2  Siklus Penilaian          7  Distribusi Nilai
3  Pemantauan & Verifikasi   8  Riwayat Penilaian
4  Kalibrasi Grade           9–16  Master · …
5  Hasil Penilaian
```

Delapan butir teratas juga mendapat `Menu_Subtitle` — kolom yang sudah dipakai 36 menu lain
tetapi kosong di seluruh PA.

## Kenapa `Sort_Header` 11

7–10 dipakai Administrator HCIS/KPI/LMS/Visitor, dan 11–14 justru milik header PA yang sedang
dibubarkan. Memakai 11 membuat seluruh kelompok Administrator berdampingan **tanpa satu pun
menu modul lain digeser** — perubahan yang tidak menyentuh modul lain lebih mudah ditinjau dan
lebih mudah dibatalkan.

## "Penilaian Saya" sengaja tidak ikut pindah

`PerformanceAppraisalSayaPage` (`Is_Global_Menu='Y'`) adalah halaman 958 karyawan, bukan
halaman admin. Memasukkannya ke header Administrator akan menyembunyikannya dari orang yang
justru paling membutuhkannya — dan karyawan yang tidak menemukan halaman penilaiannya tidak
akan mengeluh, ia hanya tidak akan pernah mengisi, lalu muncul sebagai penilai yang tidak
merespons pada laporan admin.

Karena itu penyaringnya memakai `Jenis_page IN (...)`, **bukan** `Url_Menu LIKE
'%performance-appraisal%'` — pola itu akan ikut menyeret "Penilaian Saya" ke header
Administrator, persis yang tidak boleh terjadi. Penjaga sebelum COMMIT memeriksanya lagi.

## Yang tidak disentuh

`Jenis_page`, `Url_Menu`, `Is_Global_Menu`, dan seluruh baris `HRIS_Page_Access` tetap apa
adanya. `Jenis_page` adalah kunci **UNIK** yang mengikat menu ke hak akses; mengubahnya berarti
memutus akses orang yang sudah diberi hak.

Tidak ada baris menu **baru**. Hub `/performance-appraisal/master` sempat dipertimbangkan,
tetapi baris baru menuntut `Jenis_page` baru plus baris `HRIS_Page_Access` — melebar ke RBAC,
jauh dari "merapikan menu". Percobaan pertama justru gagal di situ: `UC_JenisPage` menolak
duplikat, dan transaksinya rollback penuh.

## Penjaga di dalam skrip

Tiga pemeriksaan sebelum `COMMIT`, masing-masing menggulung balik bila gagal:

| Kode | Yang dijaga |
|---|---|
| 50512 | header `administrator_pa` berisi tepat 16 butir |
| 50513 | "Penilaian Saya" masih di header `performance_appraisal` |
| 50514 | `Sort_Menu` 1–16 tanpa kembar dan tanpa bolong |

Penjaga ketiga bukan kerapian: dua butir bernomor sama membuat urutannya ditentukan tie-break
yang tidak deterministik, sehingga susunan menu bisa berbeda antar permintaan.

## Sesudah dijalankan

Cache menu **wajib** dibersihkan (`InertiaShellService::flushMenuCache()`), kalau tidak
susunan lama tetap tersaji sampai cache-nya kedaluwarsa sendiri.

## Pekerjaan yang tersisa

- Dicatat di SQL Tracker lalu dijalankan pada production (tugas pengguna).
- `/performance-appraisal/tim` (halaman Leader) **belum punya butir menu sama sekali** —
  leader tidak bisa mencapainya. Menambahkannya menuntut keputusan hak akses, jadi sengaja
  tidak digabungkan ke perubahan ini.
