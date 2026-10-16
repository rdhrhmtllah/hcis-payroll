# DML-2026-110 — Hasil, Distribusi, HAV Map, dan Kalibrasi lebur ke Pemantauan

| | |
|---|---|
| Change ID | `DML-2026-110-menu-hasil-kalibrasi-lebur-ke-pemantauan` |
| Jenis | DML |
| Artefak | `database/dml/DML-2026-110-menu-hasil-kalibrasi-lebur-ke-pemantauan/data-change.sql` |
| Bergantung pada | DML-2026-107 — **wajib sesudahnya** |
| Status | `TRACKER_PENDING` — dev DONE, production PENDING |

## Masalah yang diselesaikan

Satu siklus yang sedang berjalan dikerjakan dari lima menu berbeda, padahal ia satu
perjalanan. Admin yang menemukan seseorang tertahan di Pemantauan harus pindah ke Hasil
untuk menghitungnya, lalu ke Distribusi dan HAV Map untuk melihat sebarannya, lalu ke
Kalibrasi untuk menggeser gradenya — kehilangan saringan dan posisinya di tiap perpindahan.

Halaman Pemantauan kini tiga tab, dan pembedanya **satuan data**, bukan selera penamaan:

| Tab | Satuan | Menjawab |
|---|---|---|
| Pengisian | penugasan | siapa belum mengisi, siapa telat, pintu mana yang perlu dicetak ulang |
| Hasil & Verifikasi | peserta | kenapa orang ini belum bernilai, dan apa yang harus diperbaiki |
| Kalibrasi | populasi | apakah sebarannya wajar, siapa yang gradenya digeser forum |

Empat menu yang pekerjaannya sudah pindah ke sana disembunyikan; sidebar administrator
menyusut dari 15 menjadi 11 baris.

## Keputusan bentuk

**Disembunyikan, bukan dihapus.** `Is_Show_Sidebar = 'T'`, sama seperti perlakuan
`Master · Paket Soal` pada DML-2026-107 r2. Barisnya tetap ada dan URL-nya tetap hidup,
sehingga tautan lama, bookmark, dan tautan di dalam pemberitahuan tidak mati. Ada penjaga
tersendiri yang menuntut keempat barisnya **tetap lengkap** — beda antara "disembunyikan"
dan "dihapus" baru terasa saat seseorang menekan tautan lama berbulan-bulan kemudian, dan
saat itu tidak ada yang bisa menjelaskan apa pun selain halaman yang tidak ada.

**Tab Kalibrasi masih mengantar, bukan memuat.** Isinya — sebaran nilai, 9-box, pergeseran
grade — untuk sementara tetap di tiga halaman tersendiri, dan tabnya menyatakan itu dengan
jujur sambil membawa siklus yang sedang dibuka. Justru karena jalannya tetap ada,
menyembunyikan menunya aman: yang berpindah pintunya, bukan aksesnya.

**Riwayat tidak ikut.** Ia jejak audit — dibuka ketika seseorang mempertanyakan hasil yang
sudah selesai — bukan pekerjaan yang sedang berjalan. Menaruhnya di dalam layar pemantauan
siklus aktif justru menyembunyikannya dari saat ia paling dicari.

**Nomornya dirapatkan 1–11.** Nomor berlubang tidak salah secara teknis, tetapi baris
berikutnya yang ditambahkan orang lain akan menebak nomornya dari yang terlihat — dan
tebakan itu bertabrakan dengan lubang yang tidak ia ketahui ada.

## Verifikasi di dev

Dijalankan pada `Web_HRIS`. Ketiga penjaga lewat: tidak ada menu lebur yang masih tampil,
keempat barisnya masih lengkap, dan tidak ada nomor urut kembar. Dijalankan dua kali — jalan
kedua tidak mengubah apa pun. `php artisan cache:clear` dijalankan sesudahnya.

Susunan sidebar administrator sesudahnya:

1. Master · Jenis Kompetensi … 7. Master · Paket Soal Baku
8. Siklus Penilaian
9. Pemantauan & Verifikasi
10. Riwayat Penilaian
11. Dashboard PA

Disembunyikan: `Master · Paket Soal`, `Hasil Penilaian`, `Distribusi Nilai`,
`HAV Map (9-Box)`, `Kalibrasi Grade`.

## Yang tersisa

Mengisi tab Kalibrasi dengan isi ketiga halaman itu, lalu — kalau pemilik menghendaki —
menimbang ulang nama menunya: "Pemantauan & Verifikasi" kini kurang mewakili halaman yang
juga menghitung, mengkalibrasi, dan menerbitkan.

## Rollback

Tidak ada skrip pembalik; yang dikembalikan hanya bisa berupa sidebar lama. Bila menu-menu
itu perlu tampil lagi, setel `Is_Show_Sidebar = 'Y'` untuk URL yang bersangkutan dan beri
mereka nomor urut — barisnya masih ada, jadi tidak ada yang perlu dibuat ulang.
