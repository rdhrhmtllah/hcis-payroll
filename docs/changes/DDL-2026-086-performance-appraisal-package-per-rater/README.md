# DDL-2026-086 — Paket soal PA per kategori penilai

| | |
|---|---|
| Change ID | `DDL-2026-086-performance-appraisal-package-per-rater` |
| Jenis | DDL |
| Forward | `database/ddl/DDL-2026-086-performance-appraisal-package-per-rater/forward.sql` |
| Rollback | `.../drop.sql` |
| Bergantung pada | DDL-2026-073 (`N_PA_Cycle_Question_Package`), DDL-2026-075 (`N_PA_Rater_Category`) |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Paket soal terkunci pada pasangan (siklus, level). Satu level berarti satu susunan soal, dan
siapa pun yang menilai membaca susunan yang sama.

HC menegaskan itu bukan yang mereka mau: soal berbeda per **(level × kategori penilai)**.
Manager yang menilai dirinya sendiri membaca susunan lain dari yang dibaca atasannya, dan
lain lagi dari yang dibaca bawahan dan rekan kerjanya. Tanpa kolom ini kebutuhan itu tidak
bisa diwakili sama sekali.

## Keputusan bentuk

**`PA_Rater_Category_Id` nullable, dan NULL punya arti.** NULL berarti "berlaku untuk semua
kategori" — paket bawaan level itu. Ini bukan kompromi malas: tiga paket yang sudah berjalan
di dev lahir sebelum kolom ini ada, dan memaksa mereka memilih satu kategori berarti menebak
keputusan yang tidak pernah dibuat siapa pun.

**Urutan pencariannya mengikat.** Cocokkan kategori tepat lebih dulu, baru jatuh ke NULL.
Dibalik, paket bawaan akan selalu menang dan paket khusus kategori tidak pernah terpakai
walau sudah dibuat — tanpa satu pun pesan galat.

**Indeks uniknya diganti, bukan ditambah.** `UX_PA_Cycle_Question_Package_Aktif` mengunci
(siklus, level) dan harus dibuang: selama ia berdiri, satu level tidak akan pernah bisa punya
paket kategori kedua. SQL Server memperlakukan NULL sebagai nilai yang sama di indeks unik,
sehingga baris bawaan tetap dijamin tunggal per (siklus, level).

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Tiga paket lama tetap hidup dengan `PA_Rater_Category_Id` NULL
dan tetap terbaca sebagai paket bawaan levelnya. Simulasi HC Learn menghasilkan varian per
penilai (3 paket → 15) tanpa menabrak indeks unik yang baru.

## Rollback

`drop.sql` mengembalikan indeks unik lama dan membuang kolomnya. Baris yang sudah memilih
kategori penilai akan kehilangan pembedaannya — jadi rollback hanya aman selama belum ada
paket per kategori yang dipakai siklus berjalan.
