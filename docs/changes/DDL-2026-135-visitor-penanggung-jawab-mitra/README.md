# DDL-2026-135-visitor-penanggung-jawab-mitra

## Ringkas

Kunjungan dari aplikasi mitra **tidak punya karyawan pendaftar** — dan berhenti
berpura-pura punya.

| | |
|---|---|
| Change ID | `DDL-2026-135-visitor-penanggung-jawab-mitra` |
| Target | `Web_HRIS` (development); production hanya lewat SQL Tracker |
| Status | `DEV_EXECUTED` |

## Sebab

`Kode_Karyawan_PIC` diisi dari `N_HRIS_Api_Aplikasi.Kode_Karyawan_PIC`, yaitu
orang yang **menangani integrasi** — bukan yang mendaftarkan tamunya. Akibatnya
seluruh kunjungan mitra tercatat atas nama satu orang (di development: Frans
Bachtiar), dan dari layar mana pun ia terlihat mendaftarkan ratusan tamu yang
tidak pernah ia sentuh.

Mitra tidak punya kode karyawan, dan **nama bebas tidak bisa dicocokkan**:
600 dari 965 karyawan (62%) berbagi nama dengan orang lain. Salah cocok berarti
tanggung jawab menempel ke orang yang keliru, diam-diam.

## Tiga peran yang dipisahkan

| Kolom | Arti | Pada kunjungan mitra |
|---|---|---|
| `Kode_Karyawan_PIC` | **Pemilik** — karyawan yang mendaftarkan | `NULL` |
| `Created_By` | **Jejak audit** yang dibaca manusia | `Budi Hartono (API#233)` |
| *(penjangkar approval)* | dari siapa rantai persetujuan disusun | PIC registry, **tidak disimpan di header** |
| `Nama_Penanggung_Jawab` | **Nama yang dihubungi** petugas gerbang | wajib dikirim mitra |
| `Keterangan_Tujuan_Detail` (`pic_tujuan`) | **Ke mana** tamu menuju | opsional |

Penjangkar tetap perlu karena `createFlow()` menyusun rantai persetujuan dari
**hierarki seseorang**; tanpanya kunjungan mitra lahir tanpa alur dan
menggantung selamanya. Tetapi ia hanya diteruskan ke pembuat alur — **tidak
ikut tertulis** sebagai pembuat baris, sebab `Created_By` berisi kode karyawan
akan terbaca seolah orang itu yang mengetik pengajuannya.

`Created_By` memuat **nama dan penanda aplikasinya sekaligus**. Namanya lebih
dulu karena itulah yang dicari orang saat membuka barisnya; penandanya ikut
supaya tak ada yang mengira seorang karyawan bernama sama yang mengetiknya.
Kode karyawan sengaja tidak dipakai — itu persis yang membuat kunjungan mitra
dulu terbaca "dibuat oleh" orang yang tak pernah menyentuhnya.

Asal-usul yang bisa dipertanggungjawabkan tetap tersimpan utuh di
`Id_Api_Aplikasi`; kolom ini hanya jejak yang dibaca manusia, dan memang tidak
dibaca kode mana pun. Dipotong pada 100 karakter mengikuti lebar kolomnya —
yang dikorbankan ekor namanya, bukan penandanya.

## Objek

| Objek | Perubahan |
|---|---|
| `N_EMI_VISITOR_Transaksi_Tamu_Detail.Nama_Penanggung_Jawab varchar(150) NULL` | kolom baru |
| `N_VISITOR_Tamu_Efektif.Kode_Karyawan_PIC` | `NOT NULL` → `NULL` |

`N_VISITOR_Tamu_Efektif.Nama_PIC` **tetap NOT NULL**. Nilainya kini
`COALESCE(k.Nama, d.Nama_Penanggung_Jawab)` di `baseTamuQuery()` — gerbang
butuh **nama untuk dihubungi**, bukan kode. Itulah yang membuat kekosongan
kodenya aman.

## Kode

`VisitorPermitQueryService::baseTamuQuery` (COALESCE), `RegistrasiTamuService`
(pemilik vs penjangkar, `cariIdempoten` menerima PIC kosong),
`TamuB2BApiController` (PIC selalu kosong di pintu mitra),
`AjukanTamuB2BApiRequest` (`penanggung_jawab` wajib),
`resources/openapi/hcis-third-party.yaml`.

## Kontrak API yang berubah

`POST /api/v1/hcis/visitor/tamu-b2b` kini **menuntut** `penanggung_jawab`
(string, maks 150). Permintaan tanpa field ini dibalas `422`.

Dokumennya sudah tayang di **`/api/docs`** (disajikan tersaring per konsumen,
membaca `resources/openapi/hcis-third-party.yaml`). Spesifikasinya dinaikkan ke
**v1.1.0** dan diberi bagian **"Riwayat Perubahan Kontrak"** di halaman depan,
sehingga mitra yang membukanya langsung tahu ini perubahan wajib — bukan
sekadar field yang kebetulan `required`. Bagian itu juga menyebut beda
`penanggung_jawab` (siapa) dan `pic_tujuan` (ke mana), sebab kaburnya dua
istilah itulah yang membuat mitra mengisi `pic_tujuan` dengan nama lokasi.

Sekalian diumumkan di sana: **empat field** yang kini opsional selama masa
transisi (`estimasi_masuk`, `estimasi_keluar`, `jenis_kendaraan`,
`no_plat_kendaraan`) akan menjadi wajib saat
`VISITOR_TAMU_B2B_API_WAJIB_LENGKAP` dinyalakan — supaya mitra cukup sekali
mengubah kodenya.

## Riwayat eksekusi development

| Tanggal | Artifact | Hasil |
|---|---|---|
| 2026-09-22 | `forward.sql` | OK — kolom terbentuk, nullability berubah. |

Diuji lewat endpoint sungguhan: tanpa `penanggung_jawab` → 422; dengan
`penanggung_jawab` → 201, `Kode_Karyawan_PIC` NULL, `Created_By` A1, rantai
approval 1 langkah, dan sesudah disetujui proyeksi gerbang terbit dengan
`Nama_PIC` = nama dari mitra. `PenanggungJawabMitraTest` 5 lulus; suite Guest +
Permit 113 lulus.

## Pekerjaan user

- Rekam di SQL Tracker.
- **Beri tahu mitra**: `penanggung_jawab` wajib mulai rilis ini.
- Baris lama tetap memakai PIC registry; tidak di-backfill, sebab tidak ada
  sumber yang tahu siapa penanggung jawab sebenarnya saat itu.
