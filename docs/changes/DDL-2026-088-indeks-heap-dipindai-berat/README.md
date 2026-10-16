# DDL-2026-088 — Lima tabel heap yang dipindai puluhan ribu kali tanpa satu pun seek

**Status:** REVIEW_REQUIRED — belum dieksekusi di mana pun.

## Temuan

Diukur dari `sys.dm_db_index_usage_stats` pada `Web_HRIS` (development), jendela
**2026-08-18 03:26** (`sqlserver_start_time`) sampai **2026-09-08 16:27**:

| tabel | baris | `user_scans` | `user_seeks` | indeks |
|---|---:|---:|---:|---:|
| `Transaksi_Cuti_Det` | 184 | **127.654** | 0 | 0 |
| `N_HRIS_Transaksi_Shift_Det` | 268 | **72.582** | 0 | 0 |
| `HRIS_Page_Access` | 3.026 | **50.330** | 0 | 1 † |
| `N_HRIS_Grup_Fitur_Map` | 103 | **43.601** | 0 | 0 |
| `HRIS_Karyawan_Team` | 108 | 5.382 | 0 | 0 |

† satu unique index pada kolom surrogate `Id_Page_Access`, yang tidak dipakai
satu pun jalur penyaringan nyata — **1 seek** sepanjang jendela di atas.

Nol seek bukan kebetulan. Tanpa indeks yang cocok, SQL Server memang tidak punya
pilihan selain memindai seluruh tabel, termasuk untuk mencari **satu** baris.

## Kenapa ini tidak boleh dinilai dari jumlah baris development

184 baris memang murah dipindai. Tiga hal membuat angka itu menyesatkan:

1. **Development bukan production.** `Transaksi_Cuti_Det` di sini 184 baris
   karena datanya sebagian; di production ia menampung rincian cuti
   bertahun-tahun.
2. **RCSI mati di basis data ini.** Pemindaian penuh mengambil shared lock atas
   lebih banyak baris daripada seek, jadi biayanya bukan hanya CPU melainkan
   juga antrean penulis.
3. **Frekuensinya yang jadi soal.** 127.654 pemindaian dalam tiga minggu berarti
   tabel itu disapu habis rata-rata sekali tiap **14 detik**.

## Kolom penyaringnya dari mana

Bukan tebakan. Setiap klausa `where`/`join` di `app/` dikumpulkan, lalu setiap
kolomnya dicocokkan ke `sys.columns` supaya salah-baca alias tidak ikut terbawa —
pemeriksaan itu membuang tujuh kandidat yang ternyata bukan kolom tabelnya.

| tabel | kolom (jumlah pemakaian di kode) |
|---|---|
| `Transaksi_Cuti_Det` | `No_Transaksi` 37, `Kode_Karyawan` 16, `Tanggal_Cuti` 6 |
| `N_HRIS_Transaksi_Shift_Det` | `Id_Detail` 5, `Status` 5, `Tanggal_Shift` 4 |
| `HRIS_Page_Access` | `Jenis_page` 43, `UserID_Web` 41, `Flag_Aktif` 35 |
| `N_HRIS_Grup_Fitur_Map` | `Id_Grup_Fitur` 22, `Jenis_page` 20, `Flag_Aktif` 12 |
| `HRIS_Karyawan_Team` | `Kode_Karyawan` 23, `Kode_Karyawan_Team` 21 |

## Keputusan yang perlu dijelaskan

**`Kode_Perusahaan` tidak dijadikan kolom pertama** pada `Transaksi_Cuti_Det`,
walaupun ia bagian dari identitas baris. Tidak satu pun jalur di `app/` menyaring
dengannya, dan indeks `(Kode_Perusahaan, No_Transaksi)` **tidak bisa di-seek**
oleh kueri yang hanya tahu `No_Transaksi`.

**`Flag_Aktif` jadi kolom kunci kedua, bukan saringan indeks.** Indeks tersaring
`WHERE Flag_Aktif = 'Y'` akan lebih ramping, tetapi kolomnya NULL-able dan
sebagian jalur membaca baris non-aktif dengan sengaja — jalur itu akan kembali
memindai.

**Dua arah untuk `HRIS_Page_Access` dan `N_HRIS_Grup_Fitur_Map`.** Keduanya dibaca
dari dua sisi yang sama-sama nyata: "halaman apa saja yang boleh dibuka user ini"
dan "siapa saja yang boleh membuka halaman ini". Satu indeks tidak bisa melayani
keduanya.

## Hubungannya dengan DDL-2026-087

`DDL-2026-087` menambahkan `PK_HRIS_Page_Access` (CLUSTERED, `Id_Page_Access`).
Itu menghapus bentuk heap-nya, tetapi **tidak menolong satu pun kueri di atas**:
tidak ada jalur yang menyaring lewat `Id_Page_Access`. Kedua perubahan saling
melengkapi, tidak bertabrakan, dan boleh diterapkan dalam urutan apa pun.

## Yang harus dilakukan di production lebih dulu

Seluruh angka di atas dari development. **BAGIAN 7** `forward.sql` memuat blok
hanya-baca yang wajib dijalankan di production dulu. Cara yang bisa diulang:

```bash
# di production (hanya membaca katalog sistem)
php artisan db:inventaris-skema --pemakaian --keluaran=prod.json

# di mana saja
php artisan db:inventaris-skema --banding=dev.json,prod.json
```

Kalau di production kelima tabel itu sudah punya indeks setara, skrip ini tidak
perlu dijalankan sama sekali.

## Risiko

Rendah, dan dapat dibatalkan penuh. Tidak ada `DROP`, tidak ada perubahan kolom,
tidak ada perubahan data — yang dibuat hanya sepuluh indeks non-klaster, dan
`drop.sql` membuang persis kesepuluhnya.

Biayanya nyata tetapi kecil: setiap `INSERT`/`UPDATE`/`DELETE` pada kelima tabel
itu harus memelihara indeks baru. Pada tabel yang jarang ditulis (`user_updates`
tertinggi di antara kelimanya: 610) itu sepadan dengan puluhan ribu pemindaian
yang hilang.
