# DML-2026-097 — Menu Relasi Organisasi

| | |
|---|---|
| Change ID | `DML-2026-097-menu-relasi-organisasi` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-097-menu-relasi-organisasi/data-change.sql` |
| Bergantung pada | DDL-2026-061 (`N_HRIS_Organization_Relationship`) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

`N_HRIS_Organization_Relationship` berisi **nol baris**, dan sampai halaman ini dibuat tidak
ada satu pun jalur tulis ke sana di seluruh aplikasi: tak ada layar, tak ada perintah, tak ada
seed. Yang membacanya hanya `PaAssignmentBuilderService`.

Akibatnya usulan penilai untuk ATASAN, PEER, dan BAWAHAN selalu kosong, dan penugasan selain
penilaian diri ditolak karena wajib menunjuk relasi asal. Pada siklus berjalan itu terlihat
sebagai **859 peserta dengan nol penugasan**.

## Kenapa header Master Data, bukan header PA

Relasi organisasi adalah master **lintas modul** — Performance Appraisal hanya salah satu
pembacanya. Menaruhnya di header PA menyiratkan ia milik PA, dan orang berikutnya yang
membutuhkannya tidak akan mencarinya di sana.

## Koreksi: bagian "kenapa tidak ada baris HRIS_Page_Access" sudah tidak berlaku

Artefak ini memuat alasan bahwa hak per halaman tidak perlu disisipkan karena halamannya
digerbangi `system.admin` dan tidak ada yang membaca hak itu. Waktu itu benar.

Sejak **DML-2026-098** gerbangnya berubah menjadi ATAU
(`system.admin.atau:systemRelasiOrganisasiPage`) dan hak per halaman benar-benar diperiksa.
Artefak 097 sengaja **tidak disunting** — ia sudah berjalan di dev dan checksumnya tercatat;
koreksinya hidup di 098.

## Sesudah dijalankan

**Wajib** diikuti `InertiaShellService::flushMenuCache()`.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Penjaganya menuntut: butir menu terbentuk, pemetaan grup fitur
terbentuk, satu `Header_Code` tidak berjalan pada dua `Sort_Header` (kalau terbelah, grupnya
tampil dua kali di sidebar dengan nama sama dan isi berbeda tanpa satu pun galat), tidak ada
urutan kembar, dan halamannya tidak ditandai global.
