# DML-2026-109 — Mengosongkan siklus uji PA

> **PENGHAPUSAN PERMANEN, LINGKUNGAN PENGEMBANGAN SAJA.** Jangan direkam ke SQL Tracker,
> jangan dijalankan di production. Di sana skrip ini menghapus penilaian orang sungguhan,
> termasuk hasil yang sudah mereka baca, dan tidak ada jalan pulih selain pemulihan basis
> data.

| | |
|---|---|
| Change ID | `DML-2026-109-kosongkan-siklus-uji-pa` |
| Jenis | DML — penghapusan data uji |
| Artefak | `database/dml/DML-2026-109-kosongkan-siklus-uji-pa/data-change.sql` |
| Status | `DEV_EXECUTED` — tracker `CANCELLED`, production `CANCELLED` |

## Kenapa ada

Diminta pemilik pada 7 September 2026: menguji ulang alur admin dari awal, dari layar
pembuatan siklus. Tiga siklus percobaan dihapus beserta seluruh isinya.

| Id | Kode | Nama | Status | Isi |
|---|---|---|---|---|
| 4900 | PA-2026-01 | Test1 | CLOSED | 958 peserta · 3.574 penugasan · 958 hasil |
| 5013 | PA-2026-02 | Uji Cetak Biru Otomatis | DRAFT | kosong, sudah nonaktif |
| 5053 | PA-2026-03 | Test2 - Skala & Esai | OPEN | 958 peserta · 3.578 penugasan · 958 hasil |

Dua di antara hasil itu sudah berstatus `TERBIT`. Di lingkungan pengembangan itu memang
hasil percobaan, bukan penilaian siapa pun.

## Keputusan bentuk

**Master tidak disentuh** — keputusan pemilik. Kompetensi, jenis kompetensi, syarat per
level, kategori dan aturan penilai, bobot pilar, pita nilai, kuadran HAV, serta ikatan paket
soal baku tetap utuh, sehingga siklus berikutnya langsung menyalin cetak biru yang sudah
benar dan pengujian bisa dimulai dari langkah membuat siklus.

**Dihapus, bukan dinonaktifkan.** Di hampir semua perubahan lain di modul ini
`Flag_Aktif = 'T'` adalah jawaban yang benar; di sini tidak. Baris yang sekadar dinonaktifkan
tetap memegang nomor siklusnya, sehingga siklus berikutnya akan bernama `PA-2026-04` — bukan
lingkungan yang bersih, hanya lingkungan yang tampak bersih.

**Satu transaksi, dari daun ke akar.** Penghapusan yang berhenti di tengah meninggalkan
penugasan tanpa peserta dan hasil tanpa penugasan — keadaan yang tidak bisa dibersihkan lewat
layar mana pun. Urutannya mengikuti ketergantungan: jawaban → pintu → hasil dan turunannya →
penugasan → peserta → paket soal → cetak biru → siklus.

**Catatan hasil dihapus lebih dulu dari keduanya.** `N_PA_Result_Note` menunjuk dua arah
sekaligus, ke hasil dan ke penugasan. Menyisakannya berarti salah satu dari keduanya menolak
dihapus, dan pesannya menuduh tabel yang salah.

**Penjaga untuk arah yang berlawanan.** Yang pertama memastikan tidak ada sisa: nol baris
pada empat belas tabel berlingkup siklus. Yang kedua memastikan penghapusannya **tidak
kebablasan** ke master — kekebablasan itu tidak berbunyi sebagai galat, dan baru ketahuan
berminggu-minggu kemudian ketika siklus baru lahir tanpa satu pun kompetensi.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Kedua penjaga lewat.

| Pemeriksaan | Hasil |
|---|---|
| Tabel berlingkup siklus yang masih berisi | tidak ada |
| `N_PA_Cycle` | 0 baris |
| Master kompetensi / jenis / syarat | 24 / 4 / 33 |
| Kategori / aturan / detail aturan penilai | 4 / 14 / 45 |
| Bobot pilar / pita nilai / kuadran HAV | 13 / 12 / 9 |
| Ikatan paket soal baku | 45 |
| Kode siklus berikutnya | `PA-2026-01` |

**Catatan revisi.** Pada r1, `PRINT CONCAT(...)` memuat sub-kueri, dan SQL Server menolaknya
saat **kompilasi batch** — seluruh blok gagal sebelum satu baris pun terhapus. Kegagalan yang
menguntungkan: kalau penolakannya terjadi saat eksekusi, sebagian data sudah hilang. r2
menghitungnya ke variabel lebih dulu.

## Rollback

Tidak ada, dan tidak bisa ada. Data yang dihapus permanen hanya bisa kembali lewat pemulihan
basis data. Itulah sebabnya skrip ini menolak berjalan di luar `Web_HRIS` dan diberi
peringatan di baris pertamanya.
