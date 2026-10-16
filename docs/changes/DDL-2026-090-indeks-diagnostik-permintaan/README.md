# DDL-2026-090 — Indeks penutup untuk N_HRIS_Request_Diagnostics

**Status:** REVIEW_REQUIRED — belum dieksekusi di mana pun.
**Target:** `Web_HRIS` (development) lebih dulu, lalu production lewat SQL Tracker.

## Keluhan yang memicu ini

`SELECT TOP 50 * FROM N_HRIS_Request_Diagnostics ORDER BY Id DESC` lambat di
production, sementara `COUNT(*)` atas tabel yang sama cepat, pada tabel berisi
±71.000 baris (dilaporkan 9 September 2026).

Asimetri itu penting: `COUNT(*)` bisa dilayani indeks sempit
`IX_N_HRIS_Request_Diagnostics_Created`, sedangkan `ORDER BY Id DESC` tidak
punya jalur seek yang berguna sama sekali.

## Yang terukur (development, `Web_HRIS`)

`sys.dm_db_index_usage_stats`:

| indeks | seeks | scans | lookups | updates |
|---|---|---|---|---|
| `PK__N_HRIS_R__3214EC07` (clustered, `Id`) | **0** | 65 | 68 | 2.225 |
| `IX_N_HRIS_Request_Diagnostics_Created` | 92 | 14 | 0 | 2.225 |

Lebar baris: `Slowest_Query_Sql` rata-rata 339 byte (maks 4.000), `Path`
rata-rata 26 byte, 1.190 baris.

Dua kesimpulan:

1. **Indeks clustered tak pernah di-seek.** Tak ada jalur aplikasi yang mencari
   lewat `Id`. Kueri ad-hoc `ORDER BY Id DESC` karena itu memindai seluruh tabel
   lalu menyortir.
2. **65 pemindaian penuh itu dari `/admin/activity` sendiri.**
   `diagnosticsSummary()` meng-agregat seluruh jendela waktunya dan
   `diagnosticsRequests()` menghitung total baris untuk paginasi. Indeks
   `Created_At` tidak punya INCLUDE, jadi tak satu pun kolom yang diagregat
   tersedia di dalamnya — optimizer memindai indeks clustered, yang ikut
   membawa `Slowest_Query_Sql nvarchar(4000)`.

Halaman yang dipakai untuk mencari tahu kenapa sistem lambat memindai tabel yang
paling cepat membesar justru ketika sistem sedang lambat.

## Yang terukur pada skala production

Development hanya 1.190 baris, terlalu kecil untuk membuktikan apa pun. Agregat
`diagnosticsSummary()` yang sama dijalankan atas tabel **coretan** 71.000 baris
(37 MB) di container SQL Server lokal — sebesar production. Tabel dev maupun
production tidak disentuh.

| | logical reads | waktu |
|---|---|---|
| tanpa indeks penutup | 4.757 | 12,4 ms |
| dengan indeks penutup | **16** | 2,1 ms |

Ongkos indeksnya 5 MB. Angka milidetiknya kecil karena mesin lokal tidak sedang
menanggung beban apa pun; yang bertahan lintas mesin adalah jumlah bacanya.

## Dua sebab yang dipisahkan

"Lambatnya karena kolom `Slowest_Query_Sql` yang lebar, atau murni karena tak ada
indeks?" Dipisahkan dengan dua tabel berisi 71.000 baris dan indeks yang sama,
beda **hanya** pada terisi/tidaknya kolom lebar itu (40,4 MB lawan 11,6 MB):

| kueri | kolom SQL terisi | kolom SQL NULL |
|---|---|---|
| `TOP 50 * ORDER BY Id DESC`, indeks `Id` **ada** | **10** baca | 7 baca |
| `TOP 50 * ORDER BY <tanpa indeks> DESC` | 4.825 baca | 1.119 baca |
| agregat `diagnosticsSummary()` | 4.825 baca | 1.119 baca |

**Jawabannya: murni karena indeks.** Dengan indeks di `Id`, kueri yang sama hanya
10 logical reads — kolom lebar itu tidak menghalangi apa pun. Begitu indeksnya
tidak ada, kueri yang sama jadi 4.825: naik **480 kali lipat**.

Kolom lebar itu **pengganda, bukan sebab**. Ia membuat pemindaian yang sudah
terlanjur terjadi jadi 4,3 kali lebih mahal (4.825 lawan 1.119), karena tiap
halaman 8 KB memuat lebih sedikit baris. Menyempitkan kolomnya tanpa memberi
indeks hanya memperbaiki 4,3x dari masalah 480x.

Dua akibatnya untuk perubahan ini: BAGIAN 2 (indeks pada `Id`) bukan kelengkapan,
ia justru yang menjawab keluhan aslinya; dan `Slowest_Query_Sql` tetap **tidak**
di-include di BAGIAN 1, karena memasukkannya memindahkan ongkos 4,3x itu ke dalam
indeks yang justru dibuat supaya murah dipindai.

## Yang dibuat

- `IX_N_HRIS_Request_Diagnostics_Created_Penutup` — kunci `Created_At DESC`,
  INCLUDE kolom sempit yang diagregat dan ditampilkan. `Slowest_Query_Sql`
  sengaja **tidak** di-include (satu-satunya kolom lebar; cukup diambil lewat
  key lookup untuk maks 200 baris per halaman).
- `IX_N_HRIS_Request_Diagnostics_Id` — **bersyarat**, hanya terbuat bila tak ada
  indeks yang berkunci utama `Id`. Di development ini no-op. Ia menutup
  kemungkinan production dibuat tanpa PK.

## Yang TIDAK dikerjakan di sini

`IX_N_HRIS_Request_Diagnostics_Created` menjadi bagian sejati dari indeks baru
dan layak dibuang, tetapi `drop.sql` hanya mencatatnya sebagai langkah manual
setelah indeks penutup terbukti menerima seek di production. Agen tidak
mengeksekusi DROP.

## Perubahan kode yang menyertai (bukan bagian DDL ini)

- `app/Console/Commands/SYSTEM/Application/Maintenance/DiagnostikPrune.php` —
  command `hris:diagnostik-prune`. Sebelum ini **tidak ada apa pun** yang
  menghapus baris dari `N_HRIS_Request_Diagnostics` maupun
  `N_HRIS_DB_Health_Snapshot`; keduanya hanya bertambah sejak dipasang.
- `config/request-diagnostics.php` — `retention_days` (baku 90,
  `REQUEST_DIAGNOSTICS_RETENTION_DAYS`).

Indeks mengurangi biaya **per pemindaian**; pemangkasan mengurangi **jumlah
baris** yang dipindai. Keduanya diperlukan.

## Pekerjaan pengguna

1. Review `forward.sql`; setujui atau tolak.
2. Ambil tiga potret production di BAGIAN 4 `forward.sql` (bentuk indeks, ukuran
   MB, seek/scan). Bila ukurannya ratusan MB, jalankan `hris:diagnostik-prune`
   dulu — membangun indeks atas baris yang sebentar lagi dibuang itu kerja dua
   kali.
3. Setelah disetujui: eksekusi di development, catat di SQL Tracker, lalu
   production lewat SQL Tracker.
4. Daftarkan `hris:diagnostik-prune` di `N_HRIS_Scheduler_Configs` (harian).
   Penjadwal proyek ini dibaca dari tabel, bukan dari `app/Console/Kernel.php`,
   jadi command yang tidak didaftarkan tidak akan pernah jalan.
