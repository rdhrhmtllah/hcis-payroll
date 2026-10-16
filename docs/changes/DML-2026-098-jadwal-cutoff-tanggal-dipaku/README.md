# DML-2026-098 — Jadwal cutoff absensi memakai tanggal yang dipaku

**Status:** REVIEW_REQUIRED — belum dieksekusi di mana pun.

## Temuan

Dua baris `N_HRIS_Scheduler_Configs` memakai keterangan yang sama persis,
*"HRIS absensi - plan refresh cutoff harian malam"*. Yang **aktif** bukan yang
menghitung periodenya.

| Id | Aktif | Perintah | Cron |
|---|---|---|---|
| 10 | **Y** | `hris:absen-plan-custom --tgl_awal=2026-05-01 --tgl_akhir=2026-05-31 --refresh_mode=MISSING_OR_DIRTY` | `30 23 * * *` |
| 11 | T | `hris:absen-plan-cutoff --refresh_mode=FORCE` | `* * * * *` |

Baris yang berjalan tiap malam memakai rentang tanggal yang **dipaku pada Mei
2026**. Ia dibuat 13 Mei 2026 dan tidak berubah sejak itu.

Perintah `hris:absen-plan-cutoff` justru ditulis untuk keperluan ini — ia
memanggil `CutoffService::getCurrentPeriod()` dan memakai `Start_Date` serta
`End_Date` periode berjalan. Itu yang dimatikan.

## Dua hal yang memperburuknya

**Rentangnya tidak pernah cocok.** Kebijakan yang berlaku adalah *"Kebijakan
Cutoff Standar 16-15"*; periode berjalan saat catatan ini ditulis 16 Agu – 15
Sep 2026. Rentang 1–31 bukan cuma basi empat bulan, bentuknya pun tidak pernah
sesuai dengan kebijakan cutoff mana pun.

**Baris yang mati membawa cron `* * * * *`.** Selama `Flag_Aktif = 'T'` ia tidak
berjalan, tetapi siapa pun yang menyalakannya tanpa memperhatikan cron-nya akan
menjalankan perencanaan seluruh periode **1.440 kali sehari**.
`withoutOverlapping(15)` dan `onOneServer()` di `Kernel::schedule()` mencegah
penumpukan, bukan frekuensinya.

## Apa yang BELUM diketahui

Semua di atas dibaca dari basis data **pengembangan**. Konfigurasi production
belum diperiksa dan bisa berbeda — **langkah pertama adalah menjalankan BAGIAN 3
(hanya membaca) di production dan membandingkan**, bukan menerapkan BAGIAN 1–2.

`N_HRIS_Absen_Recalc_Queue` di dev juga tidak memuat satu pun baris
ber-`Request_Source` `SCHEDULED_CUTOFF_DAILY` maupun `SCHEDULED_ROLLING_7_DAYS`.
Yang paling mungkin itu berarti `schedule:run` memang tidak berjalan di mesin
dev — **bukan** bukti bahwa penjadwalnya gagal di tempat lain. Antrean di sana
seluruhnya diisi pemicu kejadian (`TRIGGER_CHECKINOUT`, `ABSENSI_SUBMIT_IZIN`,
`CUTI_CONTROLLER_STORE`, `LEMBUR_REQUEST_SERVICE`) dan pemuatan dashboard.

## Dampak yang masuk akal bila konfigurasi production sama

`hris:absen-plan-rolling` tetap berjalan 2× sehari dan mencakup **hari ini
dikurangi enam hari**. Jadi data tujuh hari terakhir tetap terencana ulang.

Yang tidak tercakup adalah hari-hari **lebih tua dari tujuh hari di dalam
periode cutoff berjalan** — persis rentang yang seharusnya ditangani jadwal ini.
Baris absensi di sana tidak pernah masuk antrean `MISSING_OR_DIRTY` dari jalur
terjadwal; ia hanya terencana ulang bila ada kejadian yang memicunya (pengajuan
izin, cuti, lembur) atau bila seseorang membuka dashboard leader.

## Perubahan yang diusulkan

1. **Matikan** baris Id 10 (bertanggal dipaku). Dimatikan, bukan dihapus —
   kalau rentang Mei itu ternyata disengaja untuk sesuatu, barisnya beserta
   `Created_At`-nya masih ada.
2. **Nyalakan** baris Id 11 dengan cron `30 23 * * *` (bukan `* * * * *`) dan
   `--refresh_mode=MISSING_OR_DIRTY`.

### Kenapa MISSING_OR_DIRTY, bukan FORCE

Baris Id 11 tertulis `--refresh_mode=FORCE`. Yang benar-benar berjalan selama
ini `MISSING_OR_DIRTY`. `FORCE` atas periode 31 hari × ~958 karyawan aktif
setiap malam adalah beban yang jauh lebih besar, dan bukan perilaku yang sedang
dipulihkan. **Yang diperbaiki rentang tanggalnya, bukan cakupan kerjanya.**

## Berkas

- `database/dml/DML-2026-098-jadwal-cutoff-tanggal-dipaku/data-change.sql`

## Urutan pelaksanaan

1. Jalankan **BAGIAN 3** di production (hanya `SELECT`) dan bandingkan dengan
   tabel di atas.
2. Bila sama: jalankan BAGIAN 1–2 di development, verifikasi lewat BAGIAN 3.
3. Catat di SQL Tracker, lalu jalankan di production.
4. Sesudah satu malam, periksa `N_HRIS_Absen_Recalc_Queue` memuat baris
   ber-`Request_Source = 'SCHEDULED_CUTOFF_DAILY'` dengan `Tgl_Awal`/`Tgl_Akhir`
   yang mengikuti periode berjalan.

BAGIAN 4 memuat pengembaliannya.
