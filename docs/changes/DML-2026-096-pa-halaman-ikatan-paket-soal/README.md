# DML-2026-096 — Halaman Paket Soal Baku

| | |
|---|---|
| Change ID | `DML-2026-096-pa-halaman-ikatan-paket-soal` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-096-pa-halaman-ikatan-paket-soal/data-change.sql` |
| Bergantung pada | DDL-2026-087 (`N_PA_Question_Binding_Master`), DML-2026-095 |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Pengikatan paket soal ke pasangan (level × kategori penilai) hanya ada per siklus. Setiap
siklus baru, admin mengisi ulang dua puluh sel dari nol — pekerjaan yang sama, dengan peluang
salah yang sama besar tiap kali.

Halaman ini menyimpan pilihan bakunya, dan memeriksa tiap sel terhadap katalog HC Learn
**setiap kali dibuka**: paketnya masih ada, cakupan kompetensinya masih cukup untuk dimensi
yang diwajibkan level itu, dan susunannya belum bergeser.

## Halaman baru wajib tiga baris

Menu, pemetaan grup fitur, dan hak akses. Kurang salah satu, halamannya 403 untuk semua orang.
Haknya ditiru dari pemegang halaman master kompetensi: yang boleh menentukan kompetensi adalah
orang yang sama yang boleh memilih paket soalnya.

## Sesudah dijalankan

**Wajib** diikuti `InertiaShellService::flushMenuCache()`.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Diverifikasi lewat efek: matriks 45 sel tampil, sel yang paketnya
tidak lagi mencakup kompetensi wajib level tampil sebagai tidak bisa dipilih dengan sebabnya
tertulis, dan pemilih paketnya dipakai bersama oleh halaman ini dan tahap 2 wizard lewat satu
komponen — sehingga aturannya tidak bisa berbeda di antara keduanya.
