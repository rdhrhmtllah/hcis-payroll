# DDL-2026-102-visitor-tamu-api-b2b

## Ringkas

Menyiapkan skema dan pendaftaran endpoint untuk **registrasi tamu B2B yang
diajukan aplikasi mitra lewat API**.

| Objek | Perubahan |
|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu` | + `Sumber_Pengajuan`, `Id_Api_Aplikasi`, `Referensi_Mitra` |
| `UX_VISITOR_Tamu_Requester_Idempotency` | diganti: kuncinya kini mencakup `Id_Api_Aplikasi` |
| `IX_VISITOR_Tamu_Api_Aplikasi` | indeks baru, penopang penyaring isolasi antar mitra |
| `N_EMI_VISITOR_Transaksi_Tamu` | backfill `Sumber_Pengajuan='HCIS'` (DML) |
| `N_HRIS_Api_Endpoint` | + 2 baris endpoint tamu B2B (DML) |

## Kenapa muncul sekarang

Permintaan user 2026-09-14: mitra tidak hanya ingin meminta izin gerbang, tetapi
**mendaftarkan tamunya** ke HCIS. Endpoint `v1/hcis/visitor/permit-tamu` yang ada
sengaja tidak membuat baris kunjungan (keputusan `DDL-2026-079`), jadi kebutuhan
ini dilayani endpoint terpisah `v1/hcis/visitor/tamu-b2b` — diminta terpisah agar
mitra bebas memilih salah satu tanpa mengubah integrasi yang sudah jalan.

## Keputusan desain

**Cermin persis DDL-2026-079, kini pada tabel tamu.** Sebabnya sama: tanpa
`Id_Api_Aplikasi`, tidak ada satu pun kolom yang memisahkan tamu milik mitra A
dari milik mitra B, dan endpoint status akan menyajikan data satu pelanggan
kepada pelanggan lain tanpa menyalakan galat apa pun.

**Indeks idempotensi diperluas.** Kunci lamanya hanya unik per
`Kode_Karyawan_PIC`. Seluruh tamu satu mitra memakai satu PIC aplikasi, sehingga
dua mitra yang berbagi PIC bisa bertabrakan kuncinya — dan mitra kedua menerima
nomor kunjungan milik mitra pertama sebagai "sudah pernah diterima". Sifat unik
dan filternya dipertahankan persis; yang berubah hanya penambahan
`Id_Api_Aplikasi` ke kunci. Pada registrasi HCIS kolom itu `NULL`, dan SQL Server
memperlakukan `NULL` sebagai nilai yang sama di indeks unik, sehingga
perlindungan bagi registrasi karyawan tidak berubah sedikit pun.

**PIC aplikasi WAJIB karyawan nyata pada pintu ini.** Berbeda dari permit mitra
yang boleh berpemohon aplikasi (`API#<id>`), proyeksi `N_VISITOR_Tamu_Efektif`
menuntut `Kode_Karyawan_PIC` dan `Nama_PIC` NOT NULL, dan `Nama_PIC` di-resolve
lewat JOIN ke tabel `Karyawan`. Penanda `API#<id>` tidak menghasilkan baris JOIN,
sehingga tamunya gagal terbit ke gerbang — jauh setelah pengajuannya diterima.
Karena itu aplikasi tanpa `Kode_Karyawan_PIC` ditolak `422` di depan, bukan gagal
diam-diam di belakang.

**Satu penulis untuk dua pintu.** Layar HCIS dan API mitra kini sama-sama menulis
lewat `RegistrasiTamuService`. Selama keduanya menyusun insert-nya sendiri, setiap
aturan baru hanya akan terpasang di salah satunya — pola kegagalan yang sudah
berulang di repo ini. Penjaganya ada di
`tests/Unit/VISITOR/Guest/Visit/RegistrasiTamuSatuPenulisTest.php`.

## Jebakan yang wajib diikuti

`Url_Route` di `N_HRIS_Api_Endpoint` **harus berisi POLA rute**, termasuk
`{no_transaksi}` — bukan nomor konkret. Whitelist guard dicocokkan ke
`route()->uri()`, dan pola yang salah membuat rute berparameter ditolak `401`
tanpa petunjuk penyebabnya.

## Konsekuensi bila tidak dijalankan

Change ini **wajib jalan sebelum kodenya rilis**. Tanpa kolomnya, setiap
registrasi tamu gagal dengan `Invalid column name 'Sumber_Pengajuan'` — termasuk
registrasi dari layar HCIS, karena kedua pintu kini menulis kolom penanda yang
sama. Tanpa baris `N_HRIS_Api_Endpoint`-nya, setiap panggilan mitra ditolak `403`
karena endpointnya tidak ada dalam whitelist.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-102-visitor-tamu-api-b2b/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-102-visitor-tamu-api-b2b/drop.sql` |
| DML | `database/dml/DDL-2026-102-visitor-tamu-api-b2b/data-change.sql` |

## Status

Development: **DEV_EXECUTED** (2026-09-14, `Web_HRIS`).

Diverifikasi dari katalog sistem, bukan dari keluaran skrip: tiga kolom terbentuk
nullable; `UX_VISITOR_Tamu_Requester_Idempotency` terbentuk ulang dengan kunci
`(Kode_Karyawan_PIC, Id_Api_Aplikasi, Idempotency_Key)`, tetap unik dan tetap
terfilter `[Idempotency_Key] IS NOT NULL`; `IX_VISITOR_Tamu_Api_Aplikasi`
terbentuk; backfill menyisakan 0 baris `Sumber_Pengajuan` NULL; dua endpoint
tamu B2B terdaftar berpola `{no_transaksi}`.

## Pekerjaan user

- Merekam change ini di SQL Tracker, lalu mengeksekusinya ke production dan
  memverifikasinya.
- **Menunjuk `Kode_Karyawan_PIC` pada aplikasi mitra** yang akan memakai endpoint
  tamu B2B. Tanpa itu, registrasi ditolak `422`.
- **Memberi akses endpoint** pada `N_HRIS_Api_Akses_Endpoint` untuk kedua rute
  tamu B2B; tanpa itu panggilan mitra dijawab `403`.
