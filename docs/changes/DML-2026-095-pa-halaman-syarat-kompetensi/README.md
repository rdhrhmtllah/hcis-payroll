# DML-2026-095 — Halaman Dimensi per Level

| | |
|---|---|
| Change ID | `DML-2026-095-pa-halaman-syarat-kompetensi` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-095-pa-halaman-syarat-kompetensi/data-change.sql` |
| Bergantung pada | DML-2026-094 |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

`N_PA_Competency_Requirement` menentukan dimensi apa yang muncul di formulir penilaian
seseorang — Pramukarya hanya Behavior, Staff ditambah Manajerial dan Core Value, Leaders
ditambah Qualitative. Tabelnya sudah terisi 33 baris dan **sudah dipakai**.

Tetapi tidak ada satu pun layar untuk membacanya, apalagi mengubahnya. Satu-satunya cara HC
menyesuaikannya adalah meminta orang menulis SQL langsung ke produksi, dan perubahan
sepenting ini berjalan tanpa jejak siapa yang memutuskannya.

## Halaman baru wajib tiga baris

Menu, pemetaan grup fitur, dan hak akses. Kurang salah satu, halamannya **403 untuk semua
orang** — termasuk yang membuatnya. Haknya ditiru dari pemegang halaman master kompetensi,
karena orang yang boleh mengubah daftar kompetensi adalah orang yang sama yang boleh
menentukan kompetensi mana berlaku di level mana.

## Sesudah dijalankan

**Wajib** diikuti `InertiaShellService::flushMenuCache()`. Tanpa itu menu yang ter-cache tetap
tidak memuat butirnya, dan pemegang hak akan melaporkan "menunya tidak ada" atas hak yang
sebenarnya sudah aktif.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`, lalu diverifikasi lewat efek: pemegang halaman master kompetensi
melihat butir baru di grup Master Data dan bisa membukanya; karyawan biasa tidak melihatnya.
