# DML-2026-107-pisah-master-data

## Ringkas

Master Data Visitor dipecah per domain, melengkapi pemecahan Monitoring,
Verifikator (`DML-2026-105`), dan Export Studio (`DML-2026-106`).

| Halaman lama | Halaman baru | URL |
|---|---|---|
| `visitorMasterDataPage` | `visitorMasterDataPermitPage` | `/admin/visitor/master-data/permit` |
| | `visitorMasterDataTamuPage` | `/admin/visitor/master-data/tamu` |

## Pembagian masternya

Diturunkan dari pemakaian **nyata** di kode, bukan dari namanya:

| Master | Dipakai | Halaman |
|---|---|---|
| Keperluan Tamu | `GuestRegistrationController` saja | Tamu |
| Kategori Permit, Durasi Permit, Pertanyaan Verifikasi, Merek/Model, Warna | pengajuan permit & checklist verifikasinya | Permit |
| Kode Plat Kendaraan | registrasi tamu **dan** pengajuan permit | Keduanya |
| Tipe Kendaraan | registrasi tamu **dan** pengajuan permit | Keduanya |

Dua master terakhir muncul di kedua halaman atas keputusan user: barisnya sama
persis, yang berbeda hanya pintu masuknya, dan admin tamu tidak perlu membuka
halaman permit sekadar menambah kode plat baru. Konsekuensinya disadari —
menyunting dari halaman mana pun memengaruhi kedua domain, dan itu sifat
datanya, bukan kelalaian pemisahan.

## Keputusan desain

**Pemetaannya hidup di katalog, bukan di rute.** `VisitorMasterDataCatalog::DOMAIN`
adalah satu-satunya tempat pembagian ini ditulis; rute, gerbang API, dan katalog
yang dikirim ke layar semuanya membacanya lewat `keysDomain()`. Daftar yang
disalin ke salah satunya akan menua sendiri dan menghasilkan halaman yang
menampilkan master yang endpointnya justru menolaknya.

**Katalognya disaring di SERVER.** Mengirim katalog utuh lalu menyembunyikan
sebagian di layar tetap memberi tahu halaman tamu seluruh bentuk master permit,
dan satu tab yang terlewat disembunyikan akan memanggil endpoint yang menolaknya.

**Tiap master didaftarkan sebagai ruas rute LITERAL**, bukan satu pola
`{master}` yang dibatasi `where()` per grup. Koleksi rute berkunci metode + pola
URI; dua grup berparameter sama akan terdaftar dengan pola identik dan yang
belakangan **menimpa** yang pertama. Itu benar-benar terjadi saat Export Studio
dipecah (`DML-2026-106`) dan baru ketahuan karena setiap URL diuji resolusinya.

**Master bersama mendapat SATU rute dengan dua kunci (makna ATAU)**, bukan dua
rute — URI-nya sama, dan yang kedua akan menimpa yang pertama.

Sisanya mengikuti `DML-2026-105`: hak akses diturunkan dari data, baris akses
kunci lama tidak dihapus, menu lama disembunyikan lewat `Is_Show_Sidebar`.

## Berkas

| Peran | Path |
|---|---|
| DML | `database/dml/DML-2026-107-pisah-master-data/data-change.sql` |

Tidak ada DDL: seluruhnya memakai tabel yang sudah ada.

## Status

Development: **DONE** (2026-09-15, `Web_HRIS`).

Diverifikasi dari basis data dan dari resolusi rute: dua baris menu baru
`Is_Show_Sidebar='Y'` urutan 10/15; dua pemetaan grup fitur aktif; hak akses tiap
kunci baru **1 baris**, sama dengan pemegang kunci lamanya; menu gabungan
`Is_Show_Sidebar='T'`; cache menu di-flush. Delapan URL diuji resolusinya — dua
halaman dan enam endpoint mewakili master tamu, permit, dan bersama — dan
seluruhnya mendarat pada kunci serta master yang benar. `keysDomain()`
memulangkan 3 master untuk tamu dan 7 untuk permit.

Uji sudah ditulis tetapi **belum dijalankan** atas permintaan user.

## Pekerjaan user

- Merekam change ini di SQL Tracker bersama `DML-2026-105` dan `DML-2026-106`,
  lalu mengeksekusi ke production dan memverifikasinya. Jalankan **sesudah**
  kodenya rilis.
- Menetapkan siapa yang berhak atas tiap domain. Saat ini kedua kunci diberikan
  kepada seluruh pemegang kunci lama.
