# DDL-2026-104-visitor-kategori-permit-akses

## Ringkas

Menandai kategori permit mana yang **muncul di form pengajuan permit karyawan**
(`/transaksipermit`, tab "Buat Baru").

| Objek | Perubahan |
|---|---|
| `N_EMI_VISITOR_Master_Kategori_Permit` | + `Flag_Muncul_Pengajuan char(1) NULL` |
| `N_EMI_VISITOR_Master_Kategori_Permit` | backfill `'Y'`, lalu `COKLAT` → `'T'` (DML) |

## Kenapa muncul sekarang

Permintaan user 2026-09-15. Sejak kategori COKLAT lahir untuk izin tamu
(`DDL-2026-077`), form pengajuan permit menawarkan **tiga** kategori aktif —
HIJAU, MERAH, dan COKLAT — padahal COKLAT tidak pernah diajukan karyawan. Ia
terbit dari registrasi tamu dan endpoint mitra, lewat mesin izin tamu.

Tanpa penanda, satu-satunya cara menyembunyikannya adalah menyebut namanya di
dalam kode. Kategori izin tamu berikutnya akan menuntut perubahan kode lagi —
dan sampai itu dikerjakan, ia ditawarkan ke karyawan.

## Keputusan desain

**Penanda tampil, bukan cakupan akses.** Sempat dirancang memakai kolom `Akses`
(`ALL` / `PERMIT` / `TAMU`) meniru `N_EMI_VISITOR_Master_Vehicle_Type`, tetapi
user menolak: yang dibutuhkan hanya penentu tampil-atau-tidak di satu layar.
Kolom yang menjanjikan lebih dari itu akan dibaca sebagai aturan yang ternyata
tidak pernah ditegakkan di tempat lain — penerbitan permit COKLAT tidak membaca
kolom ini sama sekali.

**NULL dibaca sebagai boleh tampil.** Kolomnya nullable tanpa DEFAULT, dan
kodenya memperlakukan NULL seperti `'Y'`. Arah bawaannya sengaja begitu:
kategori yang lahir dari jalur lain — impor, penyisipan manual, kategori yang
dibuat sebelum penanda ini ada — tidak boleh hilang diam-diam dari form hanya
karena kolomnya terlewat diisi. Pemohon akan menyimpulkan permitnya tidak bisa
diajukan, dan tidak ada galat yang menjelaskan sebabnya.

**Penjaganya di server, bukan hanya dropdown.** `store()` memakai penyaring yang
sama persis dengan yang menyusun daftar pilihannya. Penyaring yang hanya ada di
dropdown dilewati siapa pun yang mengirim `id_kategori_permit` langsung ke
endpoint-nya — dan permit COKLAT pun terbit dari form karyawan, melewati seluruh
mesin izin tamu.

**Satu definisi penyaring.** Keduanya memanggil
`VehiclePermitController::terapkanKategoriPengajuan()`. Dua pemanggil yang
menyusun penyaringnya sendiri adalah pola "dua pintu" yang sudah berulang di
repo ini; penjaganya di
`tests/Unit/VISITOR/Permit/VehiclePermit/KategoriPengajuanSatuPenyaringTest.php`.

**Bisa ditandai admin sendiri.** Kolomnya masuk ke Master Data Visitor sebagai
pilihan "Muncul di Pengajuan Permit" (Ya/Tidak) beserta kolom tabelnya, jadi
kategori baru kelak tidak perlu developer untuk ditandai.

## Konsekuensi bila tidak dijalankan

Setiap pembukaan form pengajuan permit gagal dengan
`Invalid column name 'Flag_Muncul_Pengajuan'`, karena penyaringnya menyebut
kolom itu di kueri master data.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-104-visitor-kategori-permit-akses/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-104-visitor-kategori-permit-akses/drop.sql` |
| DML | `database/dml/DDL-2026-104-visitor-kategori-permit-akses/data-change.sql` |

## Status

Development: **DEV_EXECUTED** (2026-09-15, `Web_HRIS`).

Diverifikasi dari katalog sistem dan dari keluaran endpoint-nya, bukan dari
keluaran skrip: kolom `Flag_Muncul_Pengajuan char(1) NULL` terbentuk tanpa
DEFAULT; kelima kategori bertanda (`COKLAT` = `'T'`, sisanya `'Y'`);
`getMasterData()` memulangkan `["HIJAU","MERAH"]` saja.

Uji sudah ditulis tetapi **belum dijalankan** atas permintaan user.

## Pekerjaan user

- Merekam change ini di SQL Tracker, lalu mengeksekusinya ke production dan
  memverifikasinya.
- Menandai kategori lain bila ada yang juga tidak ingin ditawarkan ke karyawan —
  cukup lewat Master Data Visitor, tanpa perubahan kode.
