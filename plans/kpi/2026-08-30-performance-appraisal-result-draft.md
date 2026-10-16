# Draf `N_PA_Result` — disimpan, belum dibangun

> **Status: DRAF, sengaja ditunda.** Dokumen ini bukan izin membuat DDL. Tabel hasil
> adalah atap bangunan: bentuknya harus menyesuaikan seluruh lapisan di bawahnya —
> cetak biru, salinan siklus, salinan per orang, penugasan, dan skor mentah. Kalau
> dibangun sekarang, hampir pasti meleset dan harus diamandemen berkali-kali.
>
> Draf ini disimpan supaya hasil brainstorm 30 Agustus 2026 tidak hilang, dan supaya
> saat gilirannya tiba kita tidak menggali ulang keputusan yang sudah diambil.

## Kapan tabel ini boleh dibangun

Setelah semuanya berikut sudah ada dan bentuknya tidak lagi bergerak:

1. ~~Lapisan 2 — salinan siklus lengkap, termasuk salinan pita nilai.~~ **Selesai**
   (DDL-071, 073).
2. ~~Lapisan 3 — baris konfigurasi per peserta.~~ **Selesai** (DDL-074), dan bentuknya
   berubah: bukan salinan penuh melainkan override yang jarang (K32).
3. ~~`N_PA_Assignment`, `N_PA_Assignment_Payload`, `N_PA_Assignment_Score`.~~ **Selesai**
   (DDL-075 dan DDL-076).
**Tinggal satu prasyarat.** 4. Jawaban T05 dari HC: skala kanonik dan batas pita. Tanpa itu, tipe kolom nilai
   dan makna `Final_Score` masih tebakan.

## Draf kolom

| Kolom | Tipe konseptual | Boleh kosong | Fungsi |
|---|---|:---:|---|
| `Id_PA_Result` | `int identity` | Tidak | Primary key |
| `PA_Participant_Id` | `int` | Tidak | FK ke peserta; unik, satu hasil per peserta |
| `Nilai_KPI` | `decimal(6,2)` | Ya | Skor KPI yang di-snapshot |
| `KPI_Periode_Dari` | `date` | Ya | Rentang KPI yang diakumulasi |
| `KPI_Periode_Sampai` | `date` | Ya | |
| `Nilai_Kompetensi` | `decimal(6,2)` | Ya | Hasil agregasi 360° |
| `Bobot_KPI_Terpakai` | `decimal(5,2)` | Ya | Bukti bobot pilar saat dihitung |
| `Bobot_Kompetensi_Terpakai` | `decimal(5,2)` | Ya | |
| `Final_Score` | `decimal(6,2)` | Ya | Gabungan kedua pilar |
| `Kode_Grade_Sistem` | `varchar(40)` | Ya | Grade hasil hitungan mesin |
| `Kode_Grade_Final` | `varchar(40)` | Ya | Grade setelah kalibrasi |
| `Flag_Dikalibrasi` | `char(1)` | Tidak | Baku `T` |
| `PA_Hav_Quadrant_Id` | `int` | Ya | FK ke `N_PA_Hav_Quadrant` |
| `Status_Hasil` | `varchar(20)` | Tidak | Baku `BELUM_DIHITUNG` |
| `Alasan_Tertahan` | `nvarchar(500)` | Ya | Wajib diisi ketika status `TERTAHAN` |
| `Calculation_Version` | `int` | Tidak | Baku 1, naik tiap hitung ulang |
| `Calculated_At` / `Calculated_By` | waktu / `int` | Ya | |
| `Flag_Aktif` | `char(1)` | Tidak | Baku `Y` |
| kolom audit | | | `Created_At/By`, `Updated_At/By` |

## Keputusan yang sudah diambil

**Baris dibuat sejak awal, bukan saat perhitungan.** Satu baris per peserta lahir
bersamaan dengan peserta itu sendiri, berstatus `BELUM_DIHITUNG`. Kalau barisnya baru
muncul setelah perhitungan berhasil, ketiadaan baris jadi bermakna ganda — belum
dihitung, tidak memenuhi syarat, atau memang tidak diikutkan. Dengan barisnya selalu
ada, `Status_Hasil` yang menjawab, dan halaman monitoring cukup menghitung per status
tanpa membandingkan dua daftar.

Alur statusnya: `BELUM_DIHITUNG` → `DIHITUNG` → `TERBIT`, dengan `TERTAHAN` sebagai
cabang untuk peserta yang penilainya di bawah ambang `Min_Rater`.

**Kuadran memakai FK, grade memakai kode.** Terlihat tidak konsisten tetapi
disengaja. Kuadran adalah kamus yang stabil (K26), jadi FK aman dan sekaligus
memberi nama serta warna tanpa duplikasi. Grade berasal dari `N_PA_Score_Range`
yang **disnapshot per siklus**, jadi yang tahan lama adalah kodenya — batas angkanya
sudah terekam di salinan siklus itu sendiri.

**Publikasi ada di sini, bukan di `N_PA_Participant`.** Sketsa awal menaruh
`Flag_Terbit` pada peserta. Yang diterbitkan adalah hasilnya, dan sebagian peserta
bisa tertahan sementara yang lain terbit, jadi statusnya milik baris hasil. Peserta
tidak perlu menyimpannya lagi.

**Hampir semua kolom nilai boleh kosong.** Peserta yang tertahan tidak diberi angka
yang tampak wajar padahal datanya bolong; kolomnya dibiarkan NULL dan alasannya
tertulis.

**`Calculation_Version` naik tiap hitung ulang.** Bobot pilar boleh berubah setelah
pengisian selesai (K23), jadi menghitung ulang adalah operasi normal, bukan
penyimpangan. Nomor versi bersama `Calculated_At` membuat jelas hasil ini produk
perhitungan keberapa.

## Yang bukan urusan tabel ini

Data mentah tetap di `N_PA_Assignment_Payload` dan `N_PA_Assignment_Score`, keduanya
sisip saja. `N_PA_Result` adalah ringkasan turunan yang boleh dihitung ulang kapan
saja tanpa kehilangan bukti apa pun.

Riwayat perubahan grade oleh kalibrasi tidak disimpan di sini melainkan di
`N_PA_Calibration_History` yang hanya-sisip, supaya nilai sistem tidak terhapus oleh
keputusan manusia.

## Yang masih perlu diputuskan saat gilirannya tiba

1. Apakah hitung ulang menimpa baris ini atau menulis baris berversi. Draf sekarang
   memilih menimpa dengan penghitung versi; kalau audit menuntut lebih, bentuknya
   berubah.
2. Tipe dan rentang `Final_Score` — bergantung pada T05.
3. Apakah `Nilai_KPI` menyimpan nilai sebelum atau sesudah cap 50–125.
4. Apakah peserta tanpa nilai KPI dinormalisasi ke kompetensi saja, dikeluarkan, atau
   KPI-nya dihitung nol. Belum pernah ditanyakan ke HC.
