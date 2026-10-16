# DDL-2026-014 — Sebab, catatan, dan pelaku pada riwayat kepatuhan SOP

Empat kolom baru dan dua indeks pada `N_SOP_Kepatuhan_Riwayat`. Tidak ada kolom,
baris, indeks, atau constraint yang dihapus maupun ditimpa.

| Kolom | Tipe | Isi |
|---|---|---|
| `Sebab` | `varchar(40) NULL` | Sebab kejadian yang dipilih manusia, mis. `PINDAH_UNIT` |
| `Catatan` | `nvarchar(500) NULL` | Penjelasan bebas dari admin |
| `Aktor_Id` | `int NULL` | `KPI_Users.Id_Users` pelaku; `NULL` = bukan tindakan manusia |
| `Aktor_Nama` | `varchar(100) NULL` | Nama pelaku, **disalin saat kejadian** |

## Masalah yang diselesaikan

Halaman detail karyawan menampilkan riwayat kepatuhan berisi deretan "Dicabut"
yang tak menjelaskan apa pun. Penelusuran menemukan tiga sebab terpisah, dan dua
di antaranya ada di skema:

1. `cabutBanyakDetail()` menulis `'Alasan' => null` secara harfiah. Pada
   development, **135 dari 135** baris `DICABUT` beralasan kosong.
2. Daftar alasan yang sah dikunci `CK_N_SOP_Rwt_Alasan` ke
   `MANUAL, ONBOARDING, STRUKTUR, VERSI_BARU, ITEM_BARU` — kelimanya menjelaskan
   *kenapa penugasan dibuka lagi*. Tak satu pun bisa menjelaskan pencabutan.
3. Tabelnya **tidak punya kolom pelaku sama sekali**, sehingga "dicabut oleh
   siapa" mustahil dijawab.

## Kenapa kolom baru, bukan melonggarkan CHECK yang ada

`docs/database/table-design-rules.md` melarang mengunci daftar nilai bisnis yang
dapat berkembang di skema; validasinya milik backend. Melonggarkan
`CK_N_SOP_Rwt_Alasan` berarti membuang lalu membuat ulang constraint — sebuah
pernyataan `DROP` yang, menurut `AGENTS.md`, tidak boleh dijalankan agen. Setiap
sebab baru pun akan menuntut DDL lagi.

Karena itu `Sebab` dibuat **tanpa CHECK** dan gerbangnya ada di
`App\Services\LMS\SOP\Support\SopSebabCabut`, dengan cermin di
`resources/js/Pages/LMS/SOP/Shared/support/constants.js`.

Konsekuensinya jujur disebutkan: untuk sementara ada dua kolom sebab. `Alasan`
tetap milik jalur "dibuka lagi" yang ditulis mesin; `Sebab` milik pilihan
manusia. UI membaca `Sebab` lebih dulu dan jatuh ke `Alasan` sebagai cadangan,
sehingga baris lama tetap bercerita. Bila kelak keduanya hendak disatukan,
pernyataan yang dibutuhkan — dan **tidak** dijalankan agen — adalah:

```sql
ALTER TABLE N_SOP_Kepatuhan_Riwayat DROP CONSTRAINT CK_N_SOP_Rwt_Alasan;
```

## Kenapa nama pelaku disalin, bukan di-join

Akun bisa berganti nama atau dinonaktifkan. Catatan audit yang ikut berubah
setiap kali master user disunting bukan catatan audit. `Aktor_Id` sengaja tanpa
foreign key dengan alasan yang sama: baris audit harus tetap terbaca meski
akunnya kelak dihapus.

Menumpang `N_SOP_Penjadwalan_Detail.Updated_By` sempat dipertimbangkan dan
ditolak: baris detail ikut terhapus bersama penjadwalannya — pada development
hanya **2 dari 135** baris `DICABUT` yang detailnya masih ada.

## Yang sudah dikerjakan agen

- `database/ddl/DDL-2026-014-riwayat-kepatuhan-sebab-aktor/forward.sql` dan
  `drop.sql` dibuat.
- `forward.sql` dijalankan ke **Web_HRIS (development)**, 6 batch, seluruhnya
  berhasil.
- Diverifikasi: keempat kolom ada dengan tipe yang benar, dua indeks terbentuk,
  jumlah baris tetap **225** (tidak ada data tersentuh).
- Checksum `forward.sql` (SHA-256):
  `696700a3b609469360391b15851df806738e62df773e7c8bef387703abc99e3b`
- Diuji lewat efek: pencabutan sungguhan dijalankan di dalam transaksi lalu
  di-*rollback*; ledger menerima `Sebab='PINDAH_UNIT'`, catatan terpangkas
  spasinya, `Aktor_Id=1`, `Aktor_Nama='Steven'`.

## Yang masih menjadi tugas pengguna

1. Tinjau dan setujui `forward.sql`.
2. Rekam DDL yang sama persis di SQL Tracker (jangan ditulis ulang).
3. Jalankan ke production lewat SQL Tracker.
4. Verifikasi di production: keempat kolom ada, dua indeks ada, jumlah baris
   `N_SOP_Kepatuhan_Riwayat` tidak berubah.

`drop.sql` adalah bantuan pembersihan manual dan **tidak pernah** dijalankan
agen — menjalankannya menghapus data audit yang tak dapat dipulihkan.
