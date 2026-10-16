# DML-2026-108 — Data uji: KPI Arya pada rentang siklus

> **DATA UJI, BUKAN PERBAIKAN.** Jangan direkam ke SQL Tracker, jangan dijalankan di
> production. Menjalankannya di sana berarti mengarang pencapaian seseorang.

| | |
|---|---|
| Change ID | `DML-2026-108-kpi-uji-arya-dalam-rentang-siklus` |
| Jenis | DML — data uji lingkungan pengembangan |
| Artefak | `database/dml/DML-2026-108-kpi-uji-arya-dalam-rentang-siklus/data-change.sql` |
| Status | `DEV_EXECUTED` — tracker `CANCELLED`, production `CANCELLED` |

## Masalah yang diselesaikan

Hasil PA Arya (`C9`) pada siklus `PA-2026-03` berhenti di `TERTAHAN`:

> Tidak ada KPI disetujui pada rentang periode siklus. Sistem menandai, admin yang
> memutuskan — bukan otomatis nol.

Alasannya benar, dan datanya memang begitu. Siklus menilai **Januari–Juni 2026**, sedangkan
seluruh KPI Arya hanya punya satu periode: **September 2026**. Pilar kompetensinya sudah
lengkap — 80,27 dari enam penilai — tetapi pilar KPI-nya kosong, dan mesin menolak
menggantinya dengan nol.

Ini bukan cacat sistem. Ini data pengembangan yang belum lengkap, dan pesan tertahannya
justru bekerja persis seperti yang dirancang.

## Keputusan bentuk

**Mengisi KPI-nya, bukan menggeser periode siklus.** Menggeser rentang siklus supaya memuat
September akan "menyelesaikan" masalah Arya sekaligus mengubah dasar penilaian 858 peserta
lain yang datanya sudah benar. Yang kurang satu orang, jadi yang ditambal satu orang.

**Bentuk barisnya disalin dari baris yang sudah ada.** Baris September (`Id_Update_Performance`
203) menjadi cetakan: kolom yang sama, penanda yang sama, format `No_Transaksi` yang sama.
Baris uji yang bentuknya berbeda dari baris sungguhan akan lolos di skrip lalu gagal di layar
KPI — dan yang tampak salah adalah layarnya, bukan datanya.

**Jangkarnya dicari, bukan ditulis sebagai angka.** `Id_Users`, `Id_Pengajuan`, dan
`Id_Penjadwalan` dicari lewat kode karyawan dan tahun jadwal. Id yang ditulis tangan akan
menempelkan realisasi ke rencana milik orang lain, dan hasilnya tetap tampak wajar.

**Penjaganya menguji keterbacaan, bukan jumlah baris.** Yang diperiksa di akhir bukan
"berapa baris tersisip", melainkan apakah mesin PA sekarang melihatnya — memakai penyaring
yang sama persis dengan yang dipakai perhitungan (`Flag_Aktif`, `Flag_Approval`,
`Flag_Verifikasi` semuanya `Y` dengan `Status` NULL, pada rencana tahun yang sama). Enam
baris yang tersisip tetapi tidak terbaca mesin adalah kegagalan yang terlihat seperti
keberhasilan.

## Yang ditulis

Enam periode pada parameter yang sudah ada — bobotnya sudah 100 dan tidak disentuh:

| Periode | Target | Realisasi |
|---|---|---|
| 2026-01 | 100 | 92 |
| 2026-02 | 100 | 88 |
| 2026-03 | 100 | 95 |
| 2026-04 | 100 | 90 |
| 2026-05 | 100 | 97 |
| 2026-06 | 100 | 93 |

Angkanya sengaja beragam. Enam bulan yang seragam membuat rata-rata terlihat benar walaupun
penjumlahannya salah.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Penjaganya lewat, dan hasil Arya dihitung ulang:

| | Sebelum | Sesudah |
|---|---|---|
| Status hasil | `TERTAHAN` | `DIHITUNG` |
| Nilai KPI | — | 92,50 |
| Nilai kompetensi | 80,27 | 80,27 |
| Final score | — | 87,00 |
| Grade sistem | — | `B_PLUS` |
| Kuadran HAV | — | Future Star |

92,50 adalah 555 dibagi 600: mesin menjumlahkan realisasi dan target sepanjang rentang, lalu
menghitung sendiri — ia tidak memakai `Total_Score` yang tersimpan di kepala transaksi.

**Catatan revisi.** Pada r1, `No_Transaksi` dinomori dengan `ROW_NUMBER` global, sehingga
baris dev untuk Februari–Mei bernomor `0002`–`0005` pada bulan yang sebetulnya masih kosong.
r2 mempartisinya per bulan. Nomornya tetap unik dan sah, jadi baris dev tidak ditulis ulang.

## Rollback

Tidak ada skrip pembalik, dan sebaiknya memang tidak dibuat: membuang baris ini berarti
menghapus realisasi KPI, dan skrip penghapus yang salah sasaran di tabel ini jauh lebih
berbahaya daripada data uji yang tertinggal. Bila lingkungan pengembangan perlu bersih,
hapus lewat `Id_Update_Performance` yang tercatat pada verifikasi di atas, satu per satu,
dengan mata melihat barisnya.
