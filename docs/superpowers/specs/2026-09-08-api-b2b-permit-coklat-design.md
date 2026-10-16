# Desain: API B2B untuk Penerbitan Permit Coklat

Tanggal: 2026-09-08
Modul: `VISITOR`
Change ID DDL: `DDL-2026-079-visitor-permit-api-b2b`
Prasyarat: `DDL-2026-077-visitor-permit-coklat` (kategori COKLAT dan kolom waktu di proyeksi) sudah dieksekusi.

## Konteks & Masalah

Ada aplikasi mitra B2B yang **sudah mendaftarkan tamunya sendiri** di sistem mereka. Yang mereka butuhkan dari HCIS hanya satu: izin akses kendaraan/orang di gerbang kita — permit COKLAT. Mereka tidak mendaftar ulang tamunya ke HCIS, dan tidak punya karyawan di sini.

Repo ini sudah punya lapisan API pihak ketiga yang matang dan tidak perlu dibangun ulang:

- Middleware `hris.api.guard` — HMAC-SHA512 atas `method\npath\nquery\ntimestamp\nnonce\nbodyHash\npublicKey`, nonce sekali pakai (anti-replay), whitelist endpoint per aplikasi, dan pencatatan tiap percobaan.
- Tabel `N_HRIS_Api_Aplikasi` / `_Endpoint` / `_Akses_Endpoint` / `_Nonce` / `_Log`.
- Dokumentasi OpenAPI di `resources/openapi/hcis-third-party.yaml`, tersaring per konsumen, disajikan `ApiDocsController`.
- Perintah terjadwal `hris:api-nonce-prune`.
- Preseden yang sudah jalan: API Kalender Operasional untuk Web Careers.

**Yang baru dan menentukan: ini endpoint TULIS pertama di lapisan itu.** Seluruh konsumen yang ada hanya `GET`. Menulis membawa hal yang tidak dipunyai membaca — idempotensi, pembatalan, isolasi antar penyewa, dan pertanggungjawaban atas izin akses fisik yang diterbitkan.

## Keputusan Desain

Disepakati bersama user; **bukan** untuk ditawar ulang saat implementasi.

| # | Keputusan | Alasan |
|---|-----------|--------|
| 1 | API-nya berbentuk "terbitkan permit", bukan "daftarkan tamu". | Tamu sudah hidup di sistem mitra. |
| 2 | PIC karyawan **melekat pada aplikasi**, bukan dikirim di payload. | Payload mitra bersih dari data internal; kode karyawan kita tidak bocor keluar; PIC tidak bisa dikarang pemanggil. |
| 3 | **Tidak** membuat baris registrasi tamu. Permit saja. | Diminta user. Konsekuensinya diterima sadar dan ditangani di §Kedaluwarsa. |
| 4 | Permit **menunggu persetujuan**, tidak langsung aktif. | Diminta user. Ada manusia yang memeriksa tiap izin akses dari luar. |
| 5 | Mitra mengetahui keputusan lewat **polling** endpoint status, bukan webhook. | Yang ditunggu manusia menekan tombol (menit–jam), jadi keunggulan latensi webhook nyaris nol. Webhook menuntut penyimpanan URL pihak luar, penandatanganan panggilan keluar, coba-ulang, dan worker antrean yang hidup — lapisan API ini sampai sekarang murni masuk. Panggilan HTTP keluar pernah menahan lock dan menyebabkan login timeout di repo ini. Bisa ditambahkan di atas endpoint status kelak tanpa membongkar apa pun. |
| 6 | Pendekatan **A**: permit B2B adalah pengajuan permit sungguhan di `N_EMI_VISITOR_Transaksi_Permit`, ditandai `Sumber_Pengajuan='API'`. | Memakai ulang approval, meja verifikator, pencabutan, proyeksi, dan monitoring yang sudah terbukti. Alternatifnya menggandakan lima mesin (tabel terpisah) atau mengotori tabel yang dibaca gerbang (tulis langsung ke proyeksi). |
| 7 | Permit COKLAT B2B berlaku **satu hari kalender** pada `tanggal_berlaku`; jam tidak dipilih mitra maupun approver. Batas permit aktif per aplikasi tetap konstanta di service. | Dikoreksi user 2026-09-12: durasi COKLAT adalah satu hari, setara konsep pilihan durasi permit lain tetapi dipaku oleh sistem. |
| 8 | Keunikan `Idempotency-Key` diperluas mencakup `Id_Api_Aplikasi`. | Tanpa itu, dua mitra yang berbagi PIC bisa bertabrakan kuncinya, dan mitra kedua menerima nomor permit milik mitra pertama — kebocoran data antar pelanggan yang tidak menyalakan galat apa pun. |
| 9 | Pengajuan B2B tetap di **satu antrean** verifikator, diberi lencana mitra dan panel penjelas. | Satu kebiasaan kerja. Antrean kedua adalah antrean yang terlupakan. Tanpa penanda, verifikator membuka pengajuan tanpa foto STNK/SIM dan menyimpulkan pemohonnya lalai lalu menolaknya — kegagalan yang paling mungkin terjadi di hari pertama. |

## Lingkup

**Termasuk:** tiga endpoint API terjaga, kolom penanda dan batas waktu sistem pada transaksi permit, identitas tamu sampai ke proyeksi gerbang, pengecualian tiga mesin permit karyawan, masa berlaku satu hari pada status dan sapuan kedaluwarsa, penanda mitra di meja verifikator, pendaftaran aplikasi + endpoint, dan dokumentasi OpenAPI.

**Tidak termasuk:** webhook keluar; registrasi tamu lewat API; layar admin untuk mendaftarkan aplikasi mitra (pendaftaran lewat DML seperti preseden Kalender Operasional); apa pun yang terjadi di pos gerbang.

## Kontrak API

Prefix `v1/hcis/visitor/permit-tamu`, seluruhnya di belakang `hris.api.guard`. Bentuk respons mengikuti API Kalender: `{sukses, message, result}` — kuncinya **`sukses`**, bukan `success`; itu yang benar-benar dipakai `KalenderOperasionalApiController` dan yang ditegaskan uji kontraknya.

### `POST /` — ajukan permit

Header wajib: `X-HC-Public`, `X-HC-Signature`, `X-HC-Timestamp`, `X-HC-Nonce`, body hash, dan `Idempotency-Key` (maks 100 karakter).

| Field | Wajib | Keterangan |
|---|---|---|
| `nama_tamu` | ya | maks 150 |
| `instansi` | ya | maks 150 |
| `no_identitas`, `jenis_identitas` | tidak | |
| `jenis_kendaraan` | tidak | nama tipe; divalidasi ke master yang `Flag_Aktif='Y'` dan berakses `TAMU`/`ALL` |
| `plat_prefix`, `plat_angka`, `plat_suffix` | tidak | **boleh kosong** — konsisten dengan keputusan tamu pejalan kaki tetap berpermit |
| `tanggal_berlaku` | **ya** | `Y-m-d`; permit berlaku satu hari kalender, 00:00 tanggal ini sampai 00:00 hari berikutnya (akhir eksklusif) |
| `keperluan` | ya | maks 250 |
| `referensi_mitra` | tidak | id tamu di sistem mitra; korelasi dua arah saat ada sengketa |

Tidak ada kode karyawan di payload — PIC melekat pada aplikasi.

Balasan `201`:

```json
{ "sukses": true, "message": "Pengajuan permit diterima",
  "result": { "no_transaksi": "PMT2609-0042", "status": "PENDING",
              "tanggal_berlaku": "2026-09-10", "durasi": "1 Hari" } }
```

Kiriman ulang dengan `Idempotency-Key` yang sama membalas `200` dengan permit yang sama dan `"idempotent_replay": true` — pola yang sudah dipakai registrasi tamu dan permit karyawan.

### `GET /{no_transaksi}` — status

Balas `PENDING` / `APPROVED` / `REJECTED` / `REVOKED` / `EXPIRED`, disertai `alasan` bila ditolak atau dicabut dan masa berlaku bila sudah disetujui. Inilah yang di-polling mitra.

### `POST /{no_transaksi}/batal` — mitra membatalkan

Bekerja di dua keadaan: selagi `PENDING` ia menutup alur approval-nya; setelah `APPROVED` ia mencabut izin efektifnya lewat `revokePermitEfektif()` yang sudah ada. Perlu karena tamu mitra bisa batal datang — tanpa ini pengajuan menggantung di antrean verifikator selamanya, atau izin yang sudah disetujui tetap hidup untuk orang yang tidak jadi datang.

### Isolasi antar mitra

`GET` dan `batal` **wajib** menyaring `Id_Api_Aplikasi` milik penandatangan permintaan. Permit milik mitra lain dibalas `404`, bukan `403` — `403` mengonfirmasi bahwa nomornya ada.

## Model Data

Change `DDL-2026-079-visitor-permit-api-b2b`: `database/ddl/DDL-2026-079-visitor-permit-api-b2b/{forward,drop}.sql`, DML pendaftaran di `database/dml/DDL-2026-079-visitor-permit-api-b2b/data-change.sql`, entri di `database/ddl/status.json`.

### `N_HRIS_Api_Aplikasi`

| Kolom baru | Tipe | Keterangan |
|---|---|---|
| `Kode_Karyawan_PIC` | `varchar(20) NULL` | PIC penanggung jawab permit dari aplikasi ini. Wajib terisi untuk aplikasi yang diberi akses endpoint permit; nullable karena aplikasi lain (mis. Kalender Operasional) tidak memakainya. |

### `N_EMI_VISITOR_Transaksi_Permit`

| Kolom baru | Tipe | Keterangan |
|---|---|---|
| `Sumber_Pengajuan` | `varchar(10) NULL` | `'HCIS'` / `'API'`. Dasar seluruh pengecualian. |
| `Id_Api_Aplikasi` | `int NULL` | Aplikasi penerbit; dasar batas per aplikasi, audit, dan isolasi antar mitra. |
| `Referensi_Mitra` | `varchar(100) NULL` | Id tamu di sistem mitra. |

Baris permit yang sudah ada di-backfill `Sumber_Pengajuan='HCIS'` lewat DML, dan `store()` mulai menulisnya. Setiap penyaring **wajib** `ISNULL(Sumber_Pengajuan,'HCIS')` — kolomnya nullable, dan perbandingan terhadap `NULL` menghasilkan `UNKNOWN`.

Indeks unik `UX_VISITOR_Permit_Requester_Idempotency` diganti agar mencakup `Id_Api_Aplikasi` (keputusan #8).

### `N_EMI_VISITOR_Transaksi_Permit_Detail`

| Kolom baru | Tipe | Keterangan |
|---|---|---|
| `Waktu_Mulai_Berlaku`, `Waktu_Berakhir` | `datetime2(0) NULL` | Batas sistem satu hari: 00:00 tanggal berlaku sampai 00:00 hari berikutnya (akhir eksklusif). Bukan input jam mitra. |
| `Nama_Tamu`, `Instansi_Tamu` | `varchar(150) NULL` | Identitas pemegang permit. |
| `No_Identitas_Tamu` | `varchar(100) NULL` | Opsional. |
| `Jenis_Identitas_Tamu` | `varchar(20) NULL` | Opsional. |

### `N_VISITOR_Permit_Efektif`

| Kolom baru | Tipe | Keterangan |
|---|---|---|
| `Nama_Pemegang`, `Instansi_Pemegang` | `varchar(150) NULL` | Identitas tamu **di proyeksi**. Tanpa ini satpam melihat izin atas nama karyawan SHE dan tidak punya petunjuk siapa yang mengemudi — proyeksi adalah satu-satunya tabel yang dibaca aplikasi gerbang. |

`Sumber_Permit` bertambah satu nilai: `'API'`. Aditif dan nullable, jadi aplikasi gerbang yang belum diperbarui tetap berjalan seperti sekarang.

**Dua kolom bernama mirip, dan itu disengaja.** `Sumber_Pengajuan` di tabel TRANSAKSI menjawab "siapa yang mengajukan" (`HCIS` / `API`) dan dipakai untuk mencabangkan aturan pengajuan. `Sumber_Permit` di PROYEKSI menjawab "izin ini milik siapa" (`KARYAWAN` / `TAMU` / `API`) dan dibaca gerbang serta laporan. Keduanya tidak selalu sepadan: permit coklat tamu HCIS bersumber pengajuan `HCIS` tetapi bersumber permit `TAMU`, dan ia bahkan tidak punya baris transaksi sama sekali.

## Alur

1. **`POST`** → validasi payload dan `tanggal_berlaku` → periksa batas permit aktif aplikasi → resolve identitas pemohon dari pendaftaran aplikasi → insert header + detail satu transaksi, `Flag_Approval = NULL`, `Sumber_Pengajuan='API'`, `Id_Api_Aplikasi` terisi, durasi dipaku `1 Hari` → `createFlow('permit', $noTransaksi, $picKode)`.
2. **Verifikator** melihatnya di antrean permit yang sama (`basePermitQuery()` + `permitStatusSql()` = `PENDING`), dengan lencana nama mitra; tidak memilih durasi atau jam.
3. **Approval final** → `approvePermit()` memvalidasi masa berlaku satu hari yang sudah dipaku → proyeksi ditulis dengan `Sumber_Permit='API'`, batas 00:00 hingga 00:00 hari berikutnya, dan identitas pemegang.
4. **Penolakan** → `Flag_Approval='T'`; mitra membacanya lewat endpoint status beserta alasannya.
5. **Pembatalan mitra** → sesuai §Kontrak API.
6. **Kedaluwarsa** → §berikut.

### Mesin permit karyawan yang tidak berlaku

Tiga mesin **dilewati** dan satu **diganti sumbernya**. Semuanya dicabang **berdasarkan `Sumber_Pengajuan='API'`**, bukan berdasarkan kategori permit — kategori COKLAT juga dipakai permit tamu HCIS yang bukan berasal dari API.

| Mesin | Kenapa dilewati | Penggantinya |
|---|---|---|
| Kuota per karyawan (`VisitorPermitQuotaService`, maks 2 aktif) | Semua permit satu mitra memakai satu PIC, jadi permit ketiga akan ditolak "Jatah permit Anda sudah penuh". | Batas permit aktif **per aplikasi**, konstanta di service. "Aktif" = berstatus `PENDING` atau `APPROVED`, sejalan dengan `VisitorPermitQuotaService::STATUS_MEMAKAI_KUOTA`; `REJECTED`/`EXPIRED`/`REVOKED` membebaskan jatah. |
| Tiga foto wajib (kendaraan, STNK, SIM) | Mitra tidak memilikinya. | — (panel verifikator menjelaskan ketidakhadirannya) |
| Checklist verifikasi (`checklist_required`) | Pertanyaannya menyangkut dokumen kendaraan karyawan. | — |
| Durasi bulanan saat approval final (`resolveDuration()`) | Permit COKLAT B2B dipaku satu hari oleh sistem saat pengajuan. | `1 Hari` dari `tanggal_berlaku`; approver tidak memilih. Penjaga `MONTH` **tetap utuh** untuk permit HCIS. |

## Kedaluwarsa dan Status Sadar Jam

Dua perbaikan yang ikut karena tanpanya fitur ini salah sejak hari pertama:

- **`permitStatusSql()`** menghitung `EXPIRED` dari `d.Tanggal_Kadaluarsa < CAST(GETDATE() AS date)` — ketelitian hari. Permit yang jendelanya habis pukul 12:00 tetap terbaca `APPROVED` di meja verifikator dan monitoring sampai lewat tengah malam. Diubah: bila `Waktu_Berakhir` terisi, bandingkan dengannya; bila tidak, jatuh kembali ke perbandingan tanggal.
- **Sapuan `visitor:expire-efektif`** cabang jam saat ini menyaring `Sumber_Permit='TAMU'`. Diubah menjadi **siapa pun yang punya `Waktu_Berakhir` dan sudah lewat** — tidak ada daftar sumber yang harus dijaga tetap sinkron setiap kali muncul sumber baru. Cabang "tamu tercatat keluar" tidak menyentuh permit API dan itu memang benar: tidak ada baris tamu untuk diperiksa.

**Konsekuensi keputusan #3 yang diterima sadar:** karena tidak ada baris tamu, permit API tidak bisa diputus lewat check-out. Ia berakhir otomatis pada batas eksklusif 00:00 hari setelah `tanggal_berlaku`; pembatalan eksplisit tetap tersedia lewat endpoint batal.

`Sisa_Hari` di `basePermitQuery()` (`DATEDIFF(day, ...)`) bernilai `0` pada tanggal permit satu hari. Dibiarkan apa adanya; tampilan mitra memakai `tanggal_berlaku` dan label `1 Hari`, bukan menafsirkan angka itu sebagai durasi pilihan.

## Meja Verifikator

Pengajuan B2B tetap di antrean yang sama, dengan:

- **Lencana** berisi nama mitra pada baris daftarnya.
- **Panel dokumen diganti keterangan**: "Diajukan lewat API oleh `<mitra>`; dokumen kendaraan tidak berlaku untuk pengajuan mitra," disertai identitas tamu, tanggal berlaku satu hari, dan `referensi_mitra`.

## Pendaftaran dan Dokumentasi

DML mendaftarkan: aplikasi di `N_HRIS_Api_Aplikasi` (`Arah` masuk, `Kode_Karyawan_PIC` terisi, `Flag_Aktif='Y'`), tiga baris di `N_HRIS_Api_Endpoint`, dan izinnya di `N_HRIS_Api_Akses_Endpoint`.

**Jebakan yang wajib diikuti:** `Url_Route` harus berisi **pola rute**, termasuk `{no_transaksi}` — bukan nomor konkret. Whitelist dicocokkan ke `route()->uri()`, dan pola yang salah membuat rute berparameter ditolak `401` tanpa petunjuk penyebabnya. Dua dari tiga endpoint di sini berparameter.

Ketiga endpoint wajib masuk `resources/openapi/hcis-third-party.yaml` beserta tag dan komponennya; tanpa itu uji dokumentasi merah dan mitra tidak punya acuan untuk menandatangani permintaannya. Kunci rahasia tidak pernah diletakkan di URL.

## Rencana Pengujian

Yang diuji bukan "endpoint-nya jalan", melainkan hal yang kalau salah membuat orang lolos gerbang atau data mitra tertukar. Tiap penjaga harus pernah dilihat **merah**.

| # | Uji | Membuktikan |
|---|---|---|
| 1 | Rute berparameter tembus sesudah didaftarkan dengan pola yang benar | jebakan `401` bisu pada `route()->uri()` |
| 2 | Permintaan tanpa tanda tangan / kedaluwarsa / nonce terpakai ulang ditolak | guard bekerja pada endpoint tulis |
| 3 | Endpoint yang tidak di-whitelist dibalas `403` | izin per aplikasi |
| 4 | Mitra A tidak bisa membaca maupun membatalkan permit mitra B (`404`) | isolasi antar penyewa |
| 5 | Kunci idempotensi sama dua kali → satu permit; dua mitra kunci sama → dua permit berbeda | keputusan #8 |
| 6 | Permit API terbit tanpa foto, tanpa checklist, tanpa kena kuota karyawan | ketiga pengecualian |
| 7 | **Cermin:** permit HCIS tetap menuntut foto, checklist, kuota, dan durasi bulanan | pengecualian tidak bocor ke jalur karyawan |
| 8 | Approval final permit API berhasil tanpa `id_durasi_permit`; permit HCIS tetap gagal tanpanya | penjaga `MONTH` utuh |
| 9 | `tanggal_berlaku` kosong atau bukan `Y-m-d` ditolak `422`; field jam lama tidak memengaruhi hasil | kontrak tanggal tunggal |
| 10 | Permit API mati pada batas eksklusif 00:00 hari berikutnya; permit karyawan berbulan tidak tersentuh sapuan | durasi satu hari tepat |
| 11 | Status permit yang tanggalnya sudah lewat terbaca `EXPIRED` | status mengikuti masa berlaku sistem |
| 12 | Pembatalan mitra menutup alur saat `PENDING` dan mencabut izin saat `APPROVED` | dua keadaan pembatalan |
| 13 | Ketiga endpoint ada di `hcis-third-party.yaml` | dokumentasi tidak tertinggal |

Penjaga baru menyentuh service dan kueri bersama, jadi verifikasi akhir menjalankan suite penuh modul Visitor, bukan hanya berkas yang berubah.

## Risiko yang Perlu Keputusan Manusia

- **Aplikasi mitra menerbitkan izin akses fisik.** Kunci rahasianya harus diperlakukan setara kunci gerbang; pencabutan aplikasi (`Flag_Aktif='N'`) harus jadi prosedur yang diketahui, bukan penemuan saat darurat.
- **Permit API tidak punya check-out.** Kalau kelak dianggap perlu mati saat tamunya benar-benar keluar, itu menuntut baris tamu — yaitu membatalkan keputusan #3, bukan menambal sapuan.
