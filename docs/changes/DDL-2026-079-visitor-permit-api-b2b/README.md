# DDL-2026-079-visitor-permit-api-b2b

## Ringkas

Menyiapkan skema dan pendaftaran endpoint untuk **permit COKLAT yang diajukan
aplikasi mitra B2B lewat API**.

| Objek | Perubahan |
|---|---|
| `N_HRIS_Api_Aplikasi` | + `Kode_Karyawan_PIC` (`varchar(20) NULL`) |
| `N_EMI_VISITOR_Transaksi_Permit` | + `Sumber_Pengajuan`, `Id_Api_Aplikasi`, `Referensi_Mitra` |
| `N_EMI_VISITOR_Transaksi_Permit_Detail` | + `Waktu_Mulai_Berlaku`, `Waktu_Berakhir`, `Nama_Tamu`, `Instansi_Tamu`, `No_Identitas_Tamu`, `Jenis_Identitas_Tamu` |
| `N_VISITOR_Permit_Efektif` | + `Nama_Pemegang`, `Instansi_Pemegang` |
| `UX_VISITOR_Permit_Requester_Idempotency` | diganti: kuncinya kini mencakup `Id_Api_Aplikasi` |
| `IX_VISITOR_Permit_Api_Aplikasi` | indeks baru, penopang penyaring isolasi antar mitra |
| `N_EMI_VISITOR_Transaksi_Permit` | backfill `Sumber_Pengajuan='HCIS'` (DML) |
| `N_HRIS_Api_Endpoint` | + 3 baris endpoint permit mitra (DML) |

## Kenapa muncul sekarang

Permintaan user 2026-09-08: ada aplikasi mitra B2B yang sudah mendaftarkan
tamunya sendiri dan hanya butuh permit COKLAT dari HCIS. Desain lengkapnya di
`docs/superpowers/specs/2026-09-08-api-b2b-permit-coklat-design.md`, rencana
implementasinya di `docs/superpowers/plans/2026-09-08-api-b2b-permit-coklat.md`.

**Ini endpoint TULIS pertama di lapisan `N_HRIS_Api_*`.** Seluruh konsumen yang
ada sebelumnya (Kalender Operasional untuk Web Careers, callback kuis SOP)
hanya membaca atau menerima callback. Menulis membawa hal yang tidak dipunyai
membaca: idempotensi, pembatalan, isolasi antar penyewa, dan pertanggungjawaban
atas izin akses fisik yang diterbitkan.

## Keputusan desain

**Permit mitra adalah pengajuan permit sungguhan, bukan bentuk data baru.** Ia
duduk di `N_EMI_VISITOR_Transaksi_Permit` seperti pengajuan karyawan, dan
memakai ulang alur approval, meja verifikator, endpoint pencabutan, proyeksi ke
gerbang, dan monitoring yang sudah terbukti. Yang membedakannya cuma satu
penanda: `Sumber_Pengajuan='API'`. Dari penanda itulah tiga mesin permit
karyawan dilewati — kuota per karyawan, tiga foto wajib, dan checklist
verifikasi — karena ketiganya tidak masuk akal bagi pengajuan mitra.

**Tidak ada baris registrasi tamu yang dibuat.** Diminta user secara eksplisit.
Konsekuensinya diterima sadar: tanpa baris tamu, tidak ada check-out yang bisa
memutus permit lebih awal. Kontrak akhirnya dipaku menjadi **satu hari kalender**
berdasarkan `tanggal_berlaku`: sistem menulis 00:00 pada tanggal tersebut sampai
00:00 hari berikutnya (batas akhir eksklusif), tanpa pilihan jam dari mitra atau
approver.

**PIC melekat pada aplikasi, bukan dikirim di payload.** Kolom
`Kode_Karyawan_PIC` di `N_HRIS_Api_Aplikasi` menjawab siapa yang bertanggung
jawab atas permit yang diterbitkan aplikasi itu. Payload mitra jadi bersih dari
data internal, kode karyawan kita tidak bocor keluar, dan PIC tidak bisa
dikarang pemanggil.

**Identitas pemegang ikut ke proyeksi.** `N_VISITOR_Permit_Efektif` adalah
satu-satunya tabel yang dibaca aplikasi gerbang. Tanpa `Nama_Pemegang` dan
`Instansi_Pemegang`, satpam melihat izin atas nama karyawan PIC dan tidak punya
petunjuk siapa yang sebenarnya mengemudi. Kolomnya aditif dan nullable, jadi
aplikasi gerbang yang belum diperbarui tetap berjalan seperti sekarang.

**Indeks idempotensi diperluas.** Kunci lamanya hanya unik per
`Kode_Karyawan_Pemohon`. Karena seluruh permit satu mitra memakai satu PIC, dua
mitra yang berbagi PIC bisa bertabrakan kuncinya — dan mitra kedua menerima
nomor permit milik mitra pertama sebagai "sudah pernah diterima". Itu kebocoran
data antar pelanggan yang tidak menyalakan galat apa pun. Sifat unik dan
filternya dipertahankan persis; yang berubah hanya penambahan `Id_Api_Aplikasi`
ke kunci. Pada permit HCIS kolom itu `NULL`, dan SQL Server memperlakukan `NULL`
sebagai nilai yang sama di indeks unik, sehingga perlindungan bagi permit
karyawan tidak berubah sedikit pun.

## Jebakan yang wajib diikuti

`Url_Route` di `N_HRIS_Api_Endpoint` **harus berisi POLA rute**, termasuk
`{no_transaksi}` — bukan nomor konkret. Whitelist guard dicocokkan ke
`route()->uri()`, dan pola yang salah membuat rute berparameter ditolak `401`
tanpa petunjuk penyebabnya. Dua dari tiga endpoint di sini berparameter.

## Konsekuensi bila tidak dijalankan

Change ini **wajib jalan sebelum kodenya rilis**. Tanpa kolomnya, setiap
pengajuan permit mitra gagal dengan `Invalid column name 'Sumber_Pengajuan'`,
dan tanpa baris `N_HRIS_Api_Endpoint`-nya setiap panggilan mitra ditolak `403`
karena endpointnya tidak ada dalam whitelist.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-079-visitor-permit-api-b2b/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-079-visitor-permit-api-b2b/drop.sql` |
| DML | `database/dml/DDL-2026-079-visitor-permit-api-b2b/data-change.sql` |
| Desain | `docs/superpowers/specs/2026-09-08-api-b2b-permit-coklat-design.md` |
| Rencana | `docs/superpowers/plans/2026-09-08-api-b2b-permit-coklat.md` |

Checksum forward DDL (SHA-256):
`8b6c6137ba467564ed305a06d1b1225a5b671ae47c6b6404986082e9a94bb100`

## Status

Development: **DEV_EXECUTED** (2026-09-09, `Web_HRIS`). Prasyarat
`DDL-2026-077-visitor-permit-coklat` sudah `DEV_EXECUTED` sejak 2026-09-08.

Diverifikasi dari katalog sistem, bukan dari keluaran skrip:

- `INFORMATION_SCHEMA.COLUMNS` — 12 kolom terbentuk dengan tipe dan nullability
  sesuai deklarasi.
- `sys.indexes` + `sys.index_columns` — `UX_VISITOR_Permit_Requester_Idempotency`
  terbentuk ulang dengan kunci `(Kode_Karyawan_Pemohon, Id_Api_Aplikasi,
  Idempotency_Key)`, tetap unik dan tetap terfilter
  `[Idempotency_Key] IS NOT NULL`; `IX_VISITOR_Permit_Api_Aplikasi` terbentuk.
- Backfill: 24 baris `Sumber_Pengajuan='HCIS'`, 0 baris `NULL` tersisa.
- Tiga endpoint terdaftar berpola `{no_transaksi}`, `Flag_Aktif='Y'`.

Suite modul Visitor sesudahnya: 10 kegagalan, **identik dengan baseline
`master`** (6 `VerifikatorDecisionNotifiesRequesterTest`, 2
`VisitorSubmissionNotifiesAdminTest`, 2 `PermitEfektifFlagBatalTest` — yang
terakhir gagal karena fixture-nya memakai kode karyawan karangan). Nol regresi.

### Cacat yang ditemukan uji

Status internal `NO_FLOW` bocor ke kontrak publik: permit yang belum punya satu
pun langkah approval mengembalikan nilai itu ke mitra, padahal yang dijanjikan
hanya `PENDING`/`APPROVED`/`REJECTED`/`REVOKED`/`EXPIRED`. Ditambal dengan
pemeta di `PermitTamuApiController::statusUntukMitra()` plus dua uji penjaga.

## Pekerjaan agent

- Menulis forward DDL, drop, dan DML (backfill + pendaftaran endpoint).
- Mengeksekusi forward DDL + DML ke development dan memverifikasi hasilnya lewat
  katalog sistem.
- Menjalankan suite modul Visitor dan membandingkannya dengan baseline.
- Menulis dokumentasi ini dan entri `database/ddl/status.json`.

## Pekerjaan user

- Merekam change ini di SQL Tracker, lalu eksekusi ke production dan verifikasi.
- **Mendaftarkan aplikasi mitra.** Tidak bisa diambil alih agent karena
  menyangkut kredensial pihak luar:
  1. Baris di `N_HRIS_Api_Aplikasi`: nama aplikasi, `Api_Public`, `Api_Secret`,
     `Arah='IN'`, `Id_Grup_Fitur=4`, `Flag_Aktif='Y'`, dan **`Kode_Karyawan_PIC`
     yang benar-benar ada di tabel `Karyawan`** — tanpa itu penerbitan permit
     ditolak `422` dengan pesan yang menyebutkan hal ini.
  2. Baris di `N_HRIS_Api_Akses_Endpoint` untuk ketiga endpoint permit mitra.
- **Membagikan `Api_Secret` lewat kanal aman.** Kunci itu setara kunci gerbang:
  siapa pun yang memegangnya dapat menerbitkan izin akses fisik.
- **Menetapkan prosedur pencabutan aplikasi** (`Flag_Aktif='N'` pada
  `N_HRIS_Api_Aplikasi`) sebelum darurat, bukan menemukannya saat darurat.
