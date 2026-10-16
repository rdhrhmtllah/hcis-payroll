# DDL-2026-077-visitor-permit-coklat

> **Sebagian digantikan `DDL-2026-103`.** Permit COKLAT tidak lagi ditulis
> langsung ke `N_VISITOR_Permit_Efektif` sebagai baris `AKTIF`: ia kini transaksi
> permit tersendiri yang melewati approval, dan barisnya di proyeksi berkunci
> nomor permit itu — bukan nomor kunjungan. Kolom dan indeks yang dibuat change
> ini tetap berlaku apa adanya.

## Ringkas

Menyiapkan skema untuk **permit COKLAT** — izin akses tamu yang berlaku dalam
hitungan jam dan terbit otomatis untuk setiap tamu yang didaftarkan.

| Objek | Perubahan |
|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu_Detail` | + `Waktu_Mulai_Kunjungan`, `Waktu_Selesai_Kunjungan` (`datetime2(0) NULL`) |
| `N_VISITOR_Permit_Efektif` | + `Waktu_Mulai_Berlaku`, `Waktu_Berakhir` (`datetime2(0) NULL`), + `Sumber_Permit` (`varchar(10) NULL`) |
| `N_VISITOR_Permit_Efektif` | + indeks `IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir` |
| `N_EMI_VISITOR_Master_Kategori_Permit` | + 1 baris `COKLAT` (DML) |
| `N_VISITOR_Permit_Efektif` | backfill `Sumber_Permit = 'KARYAWAN'` (DML) |

## Kenapa muncul sekarang

Permintaan user 2026-09-08: jenis permit baru berbasis jam untuk tamu, terbit
otomatis saat tamu didaftarkan. Desain lengkapnya di
`docs/superpowers/specs/2026-09-08-visitor-permit-coklat-design.md`.

Tiga fakta membuat kolom baru tidak bisa dihindari:

1. `Tanggal_Mulai_Berlaku`/`Tanggal_Kadaluarsa` bertipe `date`. Jam yang ditulis
   ke sana hilang tanpa galat.
2. Registrasi tamu tidak punya jendela jam yang bisa dipakai.
   `Waktu_Estimasi_Kunjungan` adalah `varchar(50)` berisi label kasar hasil
   dropdown (`1-2 jam`, `Seharian`) yang di-hardcode di controller, dan datanya
   sudah bercampur — ada baris berisi `13:30`.
3. Setelah fitur ini, setiap tamu ikut menerbitkan satu baris permit. Tanpa
   `Sumber_Permit`, setiap angka permit di dashboard dan laporan bercampur
   antara permit karyawan dan permit tamu.

## Keputusan desain

**Kolom waktu ditambahkan aditif, tipe kolom `date` yang lama tidak diubah.**
`N_VISITOR_Permit_Efektif` bukan tabel internal: ada aplikasi gate/security di
luar repo ini yang menariknya langsung lewat koneksi SQL Server (baca
`Flag_Sync='T'` → salin ke DB gate → tandai `'Y'`), sesuai
`docs/superpowers/specs/2026-07-30-visitor-efektif-projection-design.md`.
Mengubah tipe kolomnya berarti menuntut rilis serentak dengan aplikasi di luar
kendali repo ini.

Konsekuensinya ada invarian yang **wajib** dijaga di `VisitorEfektifService`:
selama `Waktu_*` diisi, `Tanggal_*` wajib ikut terisi turunan tanggalnya.
Aplikasi gate yang belum mengadopsi kolom jam hanya membaca kolom `Tanggal_*`;
membiarkannya kosong membuat permit coklat tak terlihat di gerbang tanpa satu
pun galat.

**`N_EMI_VISITOR_Transaksi_Permit_Detail` sengaja tidak disentuh.** Permit
coklat tidak pernah membuat baris transaksi permit — ia lahir langsung sebagai
proyeksi dari registrasi tamu, memakai `No_Transaksi` tamunya sendiri
(`VSR-…`) sebagai kunci. Kolom waktu di tabel itu tidak akan pernah ditulis
siapa pun.

**`Sumber_Permit` nullable, dan itu menuntut kehati-hatian di setiap
penyaring.** Semua penyaring wajib ditulis `ISNULL(Sumber_Permit, 'KARYAWAN')`;
`Sumber_Permit <> 'TAMU'` menghasilkan `UNKNOWN` untuk `NULL`, sehingga baris
yang belum ter-backfill akan diam-diam berhenti dikedaluwarsakan sapuan.

**Kategori COKLAT: `Deskripsi_Akses = 'Akses terbatas sesuai jadwal
kunjungan'`.** Ditetapkan user 2026-09-08. Berbeda dari `HIJAU` ("Akses hanya
sampai Area Parkir") dan `MERAH` ("Akses penuh sampai Area Boiler") yang
menyebut area fisik, batas permit coklat ditentukan jendela waktunya.

## Konsekuensi bila tidak dijalankan

Change ini **wajib jalan sebelum kodenya rilis**. Tanpa kolom-kolom ini,
registrasi tamu gagal menyimpan jendela jam (`Invalid column name
'Waktu_Mulai_Kunjungan'`) dan penerbitan permit coklat gagal seluruhnya.

## Perubahan pendamping

`DML-2026-078-visitor-expire-tiap-5-menit` menaikkan cadence
`visitor:expire-efektif` dari `5 0 * * *` menjadi `*/5 * * * *`. Tanpa itu,
permit coklat yang jendelanya habis pukul 12:00 tetap berstatus `AKTIF` di
gerbang sampai lewat tengah malam.

## Berkas

| Peran | Path |
|---|---|
| Forward DDL | `database/ddl/DDL-2026-077-visitor-permit-coklat/forward.sql` |
| Drop (manual saja) | `database/ddl/DDL-2026-077-visitor-permit-coklat/drop.sql` |
| DML | `database/dml/DDL-2026-077-visitor-permit-coklat/data-change.sql` |
| DML pendamping | `database/dml/DML-2026-078-visitor-expire-tiap-5-menit/data-change.sql` |
| Desain | `docs/superpowers/specs/2026-09-08-visitor-permit-coklat-design.md` |
| Rencana | `docs/superpowers/plans/2026-09-08-visitor-permit-coklat.md` |

Checksum forward DDL (SHA-256):
`4a81cb1c0027e5ec663b02f93202c95c624fe756a7040a998edb71d304f20c8e`

## Status

Development: **DEV_EXECUTED** (2026-09-08, `Web_HRIS`).

Diverifikasi langsung dari katalog sistem, bukan dari keluaran skrip:

- `INFORMATION_SCHEMA.COLUMNS` — 5 kolom terbentuk dengan tipe dan nullability
  sesuai deklarasi.
- `sys.indexes` + `sys.index_columns` —
  `IX_VISITOR_Permit_Efektif_Sumber_Waktu_Berakhir` terbentuk dengan kunci
  `(Status_Efektif, Waktu_Berakhir)` dan `INCLUDE (No_Transaksi, Sumber_Permit)`.
- Master `COKLAT` terisi 1 baris.
- Backfill `Sumber_Permit`: 14 baris `KARYAWAN`, 0 baris `NULL` tersisa.
- `N_HRIS_Scheduler_Configs` untuk `visitor:expire-efektif`: `*/5 * * * *`.

Seluruh berkas dijalankan **dua kali**; idempotensinya terbukti, tidak ada efek
ganda.

### Catatan eksekusi

`DML-2026-078` gagal pada percobaan pertama: teks `Keterangan` sepanjang 122
karakter melampaui kolom `varchar(100)`, dan SQL Server menolak **seluruh**
`UPDATE` — bukan memotongnya. Jadwalnya karena itu tidak berubah sama sekali
sampai teksnya dipendekkan ke 98 karakter. Perbaikan dilakukan di tempat karena
change ini belum direkam di SQL Tracker.

## Pekerjaan agent

- Menulis forward DDL, drop, dan kedua DML.
- Mengeksekusi forward DDL + kedua DML ke development dan memverifikasi
  hasilnya lewat katalog sistem.
- Menulis dokumentasi ini dan entri `database/ddl/status.json`.

## Pekerjaan user

- Review berkas DDL/DML.
- Merekam change ini di SQL Tracker.
- Eksekusi manual ke production lewat SQL Tracker, lalu verifikasi production.
- **Konfirmasi ke pemilik aplikasi gate** sebelum rilis: (a) sanggupkah ia
  memproses baris permit tanpa plat nomor — tamu pejalan kaki tetap
  menerbitkan permit; (b) apakah ia membandingkan `Waktu_Mulai_Berlaku` /
  `Tanggal_Mulai_Berlaku` sebelum meloloskan, bukan hanya membaca
  `Status_Efektif`. Permit yang jendelanya baru mulai besok tetap berstatus
  `AKTIF` sejak disetujui — sengaja tidak dibuat status "belum berlaku" karena
  enum `Status_Efektif` adalah kontrak yang dibaca aplikasi gate.
