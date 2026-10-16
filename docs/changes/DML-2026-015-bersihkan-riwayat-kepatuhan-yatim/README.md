# DML-2026-015 — Bersihkan riwayat kepatuhan SOP yang paketnya sudah lenyap

Menghapus baris `N_SOP_Kepatuhan_Riwayat` yang menunjuk paket SOP yang tidak ada
lagi di `N_SOP_Master_Paket`.

## Temuan

Pada database development, 20 Agustus 2026:

| | |
|---|---|
| Total baris riwayat | 225 |
| Menunjuk paket yang tak ada | **133** |
| Peristiwa | seluruhnya `DICABUT` |
| Rentang waktu | 14 Agu 2026, **15:32 – 16:47** (75 menit) |
| Karyawan terdampak | 50, semuanya karyawan sungguhan (`A1`, `A2`, `A3`, …) |
| `Id_SOP_Paket` yang dirujuk | 838, 839, 840, 842, 844, 845, 858, 923 |
| Baris penjadwalannya | sudah tidak ada satu pun |

Id 838–923 tidak ada di `N_SOP_Master_Paket` maupun di `N_SOP_Paket_Draft`,
sementara master hanya memuat id 1–7, 18–24, 924, dan 1213. Ciri puing uji
beban: penjadwalannya sudah dibersihkan, ledger-nya tertinggal.

## Kenapa perlu dibersihkan

`Nama_Paket` diambil lewat `leftJoin`, sehingga ke-133 baris ini muncul di
halaman detail karyawan sebagai deretan "Dicabut" **dengan keterangan kosong** —
sumber keluhan "riwayatnya kurang jelas". Baris ini juga ikut terhitung sebagai
peristiwa pada grafik tren kepatuhan.

UI sudah dipasangi penjaga permanen (`SopProgressReadService::riwayat()` kini
menyaring baris tanpa paket dan **melaporkan jumlah yang disembunyikan**), jadi
pembersihan ini memperbaiki datanya, bukan menutupi gejalanya. Keduanya
dibutuhkan: penjaga UI melindungi dari kasus serupa di masa depan, pembersihan
menghilangkan puing yang sudah ada.

## Bentuk berkas

`data-change.sql` disusun dalam lima langkah dan **tidak bisa dijalankan sekali
tekan**: langkah 1 dan 2 menghitung dan menampilkan apa yang akan terhapus,
langkah 3 mencadangkan ke `ZZ_Backup_SOP_Rwt_Yatim_20260820`, langkah 4
menghapus di dalam `BEGIN TRAN` dengan `COMMIT`/`ROLLBACK` sengaja
dikomentari, langkah 5 memastikan bersih.

Tabel cadangan sengaja **tidak** ikut dihapus di akhir — itu satu-satunya jalan
pulang bila ternyata ada baris yang masih dibutuhkan.

## Yang sudah dikerjakan agen

- Berkas DML dan dokumentasi ini dibuat.
- Predikat diverifikasi dengan `SELECT` di development: 133 baris terjaring,
  seluruhnya `DICABUT`, tak ada peristiwa lain yang ikut.
- **DML ini TIDAK dijalankan**, bahkan di development. Penjaga UI sudah membuat
  barisnya tak terlihat, sehingga tak ada alasan mendesak untuk menghapus data
  sebelum Anda meninjaunya sendiri.

## Yang masih menjadi tugas pengguna

1. Jalankan langkah 1 dan 2 di production, **baca hasilnya**. Angka production
   boleh berbeda dari 133; bila yang terjaring jauh lebih banyak dari perkiraan,
   berhenti dan tinjau ulang.
2. Jalankan langkah 3 (cadangan) dan pastikan jumlahnya cocok.
3. Jalankan langkah 4, periksa `@@ROWCOUNT`, lalu `COMMIT` bila cocok dan
   `ROLLBACK` bila tidak.
4. Jalankan langkah 5 untuk memastikan tak ada sisa.
5. Rekam di SQL Tracker.
