# Design: Master Jenis Dokumen SOP + Kepemilikan Unit Kerja

Tanggal: 2026-08-22
Status: `APPROVED_FOR_IMPLEMENTATION`
Basis registry: `Web_HRIS` (development), `N_HRIS_Master_Grup_Fitur` → `LMS` = `Id_Grup_Fitur` 3, diverifikasi langsung 2026-08-22.
Modul/submodul/fitur: `LMS` / `SOP` / `Governance — Master Data` / Jenis Dokumen.

## Masalah

Dokumen SOP hari ini hanya punya satu sumbu klasifikasi teknis: `N_SOP_Dokumen.Tipe`
(DOKUMEN, FORM, VIDEO, KUIS) — yang menjawab "berkasnya apa dan apa yang diminta dari
karyawan". Tidak ada yang menjawab dua pertanyaan tata kelola mutu yang berbeda:

1. **Dokumen ini tingkat apa dalam hirarki mutu?** Kebijakan → Prosedur → Instruksi
   Kerja → Formulir adalah piramid yang dipakai auditor, tapi sistem tidak menyimpannya.
2. **Siapa pemiliknya?** Tidak ada unit kerja yang bertanggung jawab atas sebuah dokumen,
   sehingga pertanyaan "SOP milik Produksi ada berapa dan yang mana yang usang" tidak
   bisa dijawab tanpa membaca judul satu per satu.

`N_SOP_Kategori` (Keuangan, HSE, HR, IT, QA…) **bukan** jawabannya: itu kategori *topik*,
melekat pada **paket** (`N_SOP_Master_Paket.Id_Kategori_SOP`), bukan pada dokumen. Ia
sumbu yang berbeda dan tetap berdiri sendiri.

## Keputusan yang mengunci desain

| # | Pertanyaan | Keputusan |
|---|---|---|
| 1 | Jenis dokumen melekat di mana? | Di master dokumen (`N_SOP_Dokumen`). Item paket mewarisi, tidak menyimpan sendiri. |
| 2 | Tipe Item × Jenis Dokumen | Dibatasi, aturannya **data** di tabel pemetaan — bukan enum di kode, bukan CHECK constraint. |
| 3 | Granularitas kepemilikan | `Divisi_Id` wajib + `Sub_Divisi_Id` opsional. Sub-divisi kosong = milik seluruh divisi. |
| 4 | Daya kerja kepemilikan (fase 1) | Metadata: tampil, filter, export. **Tidak** menyentuh gerbang otorisasi. |
| 5 | Data lama (41 dokumen) | Kolom nullable. Wajib hanya di pintu manusia (form), bukan di service. |
| 6 | Bentuk master | Tabel + halaman admin CRUD sendiri. |
| 7 | Kedalaman hirarki | Cukup `Level_Hirarki` 1–4. Tanpa tautan induk antar dokumen. |
| 8 | Penomoran | Tidak disentuh. `Nomor_SOP_Resmi` tetap manual + unik seperti sekarang. |

## Bentuk data

### `N_SOP_Master_Jenis_Dokumen`

Master tingkat hirarki. Empat baris seed, dirancang untuk tumbuh (mis. "Manual Mutu"
di puncak, "Rekaman" di dasar) tanpa DDL baru.

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `Id_SOP_Jenis_Dokumen` | `int IDENTITY` | NO | PK. Penamaan mengikuti presedennya: `N_SOP_Master_Paket` → `Id_SOP_Paket`. |
| `Kode_Jenis` | `varchar(30)` | NO | `KEBIJAKAN`, `PROSEDUR`, `INSTRUKSI_KERJA`, `FORMULIR`. Unik. Dipakai kode & seed, bukan nama tampilan. |
| `Nama_Jenis` | `nvarchar(100)` | NO | Label layar. Boleh diubah admin tanpa memutus kode. |
| `Level_Hirarki` | `int` | NO | 1 = puncak piramid. Tidak unik: satu tingkat boleh punya lebih dari satu jenis. |
| `Deskripsi` | `nvarchar(500)` | YES | |
| `Urutan` | `int` | NO, default 0 | Urutan dalam satu tingkat yang sama. |
| `Flag_Aktif` | `char(1)` | NO, default `Y` | `Y`/`T` sesuai konvensi. |
| `Created_At` | `datetime2(0)` | NO, default `SYSDATETIME()` | |
| `Created_By` | `int` | YES | `KPI_Users.Id_Users` — identifier yang sama dengan `N_SOP_Kategori.Created_By` dan `N_SOP_Dokumen.Created_By`. Sengaja **bukan** `varchar(100)`: konsistensi dalam domain SOP menang atas default lintas-modul. |
| `Updated_At` | `datetime2(0)` | YES | |
| `Updated_By` | `int` | YES | |

Indeks:
- `UX_SOP_Jenis_Dokumen_Kode` UNIQUE (`Kode_Jenis`) — kode adalah rujukan kode & seed; kembar membuat pemetaan ambigu.
- `IX_SOP_Jenis_Dokumen_Urut` (`Flag_Aktif`, `Level_Hirarki`, `Urutan`) — bentuk baca yang selalu dipakai dropdown dan tampilan piramid.

### `N_SOP_Master_Jenis_Dokumen_Tipe_Item`

Pemetaan jenis ↔ tipe item yang sah. Ini yang membuat aturan pasangan bisa diubah
bisnis tanpa rilis.

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `Id_SOP_Jenis_Dokumen_Tipe_Item` | `int IDENTITY` | NO | PK |
| `Jenis_Dokumen_Id` | `int` | NO | FK → `N_SOP_Master_Jenis_Dokumen`. Tanpa cascade: baris pemetaan dihapus lewat aplikasi, bukan efek samping. |
| `Tipe_Item` | `varchar(12)` | NO | Lebar mengikuti `N_SOP_Paket_Item.Tipe_Item`, diukur bukan ditebak. |
| `Flag_Aktif` | `char(1)` | NO, default `Y` | |
| `Created_At` | `datetime2(0)` | NO, default `SYSDATETIME()` | |
| `Created_By` | `int` | YES | |

Indeks:
- `UX_SOP_Jenis_Tipe_Pasangan` UNIQUE (`Jenis_Dokumen_Id`, `Tipe_Item`)
- `IX_SOP_Jenis_Tipe_Cari` (`Tipe_Item`, `Flag_Aktif`) — menjawab arah sebaliknya: "jenis apa saja yang menerima FORM", yang persis dibutuhkan dropdown Master Dokumen saat tipe sudah dipilih.

Seed pemetaan:

```text
KEBIJAKAN        -> DOKUMEN
PROSEDUR         -> DOKUMEN
INSTRUKSI_KERJA  -> DOKUMEN, VIDEO
FORMULIR         -> FORM
```

`INSTRUKSI_KERJA` menerima `VIDEO` karena instruksi kerja yang direkam sebagai video
adalah praktik nyata di lantai produksi; menutupnya akan memaksa video diklasifikasikan
salah. `KUIS` sengaja tidak dipetakan ke jenis mana pun — kuis adalah alat verifikasi
pemahaman, bukan dokumen terkendali, dan memberinya tingkat piramid akan mengaburkan arti
piramid itu sendiri. Konsekuensinya eksplisit: dokumen bertipe `KUIS` tidak wajib berjenis.

### Kolom baru pada `N_SOP_Dokumen`

| Kolom | Tipe | Null | Catatan |
|---|---|---|---|
| `Jenis_Dokumen_Id` | `int` | YES | FK → `N_SOP_Master_Jenis_Dokumen`. Nullable karena 41 dokumen lama belum terklasifikasi. |
| `Divisi_Id` | `bigint` | YES | `HRIS_Divisi.ID_Divisi` (bigint, diukur). |
| `Sub_Divisi_Id` | `bigint` | YES | `HRIS_Sub_Divisi.ID_Sub_Divisi`. NULL = seluruh divisi. |

Indeks:
- `IX_SOP_Dokumen_Jenis` (`Flag_Aktif`, `Jenis_Dokumen_Id`)
- `IX_SOP_Dokumen_Pemilik` (`Flag_Aktif`, `Divisi_Id`, `Sub_Divisi_Id`)

**Tanpa FK ke `HRIS_Divisi`/`HRIS_Sub_Divisi`.** Keduanya master legacy lintas sistem
yang tidak dikelola modul ini; presedennya sama dengan `N_SOP_Kepatuhan_Riwayat.Aktor_Id`
yang sengaja tanpa FK ke `KPI_Users`. Kesahihan nilainya dijaga backend saat menulis.

FK ke `N_SOP_Master_Jenis_Dokumen` **dipasang**, karena tabel itu milik domain SOP dan
sudah ada preseden FK dalam domain (`FK_N_SOP_Paket_Item_Dokumen`, `FK_N_SOP_Item_Versi_Dokumen`).

## Aturan validasi dan tempat tinggalnya

Ada dua pintu tempat dokumen lahir — Master Dokumen dan Builder (`Dokumen_Baru`) — dan
keduanya bermuara di `SopDokumenService::buat()`. Pembagiannya:

**Di controller (pintu manusia)** — kewajiban mengisi:
- `SopAdminDokumenController::validateDokumen`: `Jenis_Dokumen_Id` dan `Divisi_Id` wajib.
- `SopAdminBuilderController::validateItem`: `Dokumen_Baru.Jenis_Dokumen_Id` dan
  `Dokumen_Baru.Divisi_Id` wajib bila `Dokumen_Baru` dikirim.

Kewajiban **tidak** ditaruh di service, dan itu keputusan sadar: `buat()` dipanggil 48
kali oleh test sebagai fixture, dan `createForItem()` melahirkan dokumen dari item lama
(backfill, penyalinan paket) yang secara definisi tidak punya jawaban untuk kedua field
ini. Menjadikannya wajib di service akan menolak jalur yang sah.

**Di service (semantik)** — kesahihan nilai, berlaku untuk siapa pun pemanggilnya, hanya
saat nilainya ada:
- `pastikanJenisSesuaiTipe()`: jenis harus aktif, dan pasangan (jenis, tipe) harus ada di
  tabel pemetaan dengan `Flag_Aktif = 'Y'`.
- `pastikanUnitSah()`: divisi harus ada dan, bila sub-divisi diisi, pasangan
  divisi+sub-divisi harus ada di `View_Divisi_Sub_Divisi`. Sub-divisi tanpa divisi ditolak.

Konsekuensi yang disengaja: **menyunting dokumen lama lewat form identitas akan memaksa
klasifikasinya diisi**. Itu jalur backfill terpandu, bukan efek samping — dan tidak
menyentuh terbit, rollback, atau fan-out yang tidak lewat form ini.

Interaksi dengan penguncian tipe: `Tipe` sudah terkunci selama dokumen dipakai paket
operasional. Jenis dokumen **tidak** ikut terkunci — mengoreksi klasifikasi mutu tidak
mengubah apa pun yang dilihat karyawan. Tapi jenis baru tetap harus sah terhadap tipe
yang sedang berlaku.

## Permukaan aplikasi

### Halaman baru: Master Jenis Dokumen

Meniru vertical slice `SopCategory` apa adanya, bukan pola baru.

```text
Route      : GET /sop/admin/jenis-dokumen  + /api/v1/sop/admin/jenis-dokumen/*
Page code  : menuSopJenisDokumenPage   (gerbang check.jabatan)
Controller : app/Http/Controllers/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenController.php
Service    : app/Services/LMS/SOP/SopJenisDokumenService.php
Halaman    : resources/js/Pages/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenPage.vue
Route file : routes/LMS/SOP/Governance/SopGovernance.php (blok baru, file yang sama)
```

CRUD: list + cari, buat, ubah (termasuk tingkat, urutan, dan tipe item sah), nonaktifkan.
Nonaktifkan ditolak bila jenis masih dipakai dokumen aktif — persis pola
`SopKategoriService::softDelete()` yang mengembalikan jumlah pemakai.

### Endpoint opsi bersama

```text
GET /api/v1/sop/admin/master-data/jenis-dokumen?tipe=DOKUMEN
GET /api/v1/sop/admin/master-data/unit-kerja
```

Digerbangi ganda `check.jabatan:menuSopDokumenPage,menuSopBuilderPage` — satu
implementasi untuk dua layar, mengikuti preseden route token secure-PDF. Menduplikasinya
per halaman adalah persis kesalahan yang sudah dihindari di sana.

### Master Dokumen (`SopMasterDokumen.vue`)

- Modal identitas: dua field baru — **Jenis Dokumen** (tersaring oleh Tipe yang dipilih)
  dan **Unit Pemilik** (Divisi wajib → Sub-Divisi opsional, tersaring oleh divisi).
- Kartu detail: badge jenis + tingkat, dan baris pemilik.
- Daftar: kolom/pill jenis, filter jenis, filter unit, dan filter **"Belum diklasifikasi"**
  supaya sisa backfill kelihatan angkanya, bukan cuma terasa.
- Label dibuat eksplisit "Jenis Dokumen (hirarki mutu)" untuk memisahkannya dari halaman
  **Kebijakan & Otomasi SOP** yang sudah ada dan artinya aturan otomasi, bukan dokumen
  kebijakan.

### Builder (`SopBuilder.vue`)

Form "Dokumen Baru" saat define item mendapat dua field yang sama, tersaring oleh
`Tipe_Item` yang sedang dipilih. Item yang menautkan dokumen yang sudah ada hanya
menampilkan badge jenis + pemilik — mewarisi, tidak menyunting.

## Perubahan database

| Change ID | Jenis | Isi |
|---|---|---|
| `DDL-2026-016-sop-jenis-dokumen-kepemilikan` | DDL | 2 tabel baru, 3 kolom di `N_SOP_Dokumen`, 5 indeks, 2 FK |
| `DML-2026-017-seed-sop-jenis-dokumen` | DML | 4 baris jenis + 5 baris pemetaan + pendaftaran ke Database Change Dashboard |
| `DML-2026-018-register-sop-jenis-dokumen-menu` | DML | `N_HRIS_Menus`, `N_HRIS_Grup_Fitur_Map` (Id_Grup_Fitur=3), `HRIS_Page_Access` diturunkan dari pemegang `menuSopKategoriPage` |

Semua idempoten (`IF NOT EXISTS`). Tidak ada DROP, tidak ada perubahan yang menimpa data.

## Pengujian

| Berkas | Membuktikan |
|---|---|
| `SopJenisDokumenSkemaTest` | Dua tabel + tiga kolom benar-benar ada dengan tipe dan nullability yang dijanjikan — satu-satunya bukti DDL manual sudah jalan |
| `SopJenisDokumenMasterTest` | CRUD service, unik kode, tolak nonaktif saat dipakai, pemetaan tipe item |
| `SopDokumenJenisPemilikTest` | Pasangan jenis↔tipe ditolak saat tak sah; sub-divisi asing ditolak; sub-divisi tanpa divisi ditolak; nilai kosong tetap lolos untuk jalur backfill |
| `SopMasterDokumenPageTest` (tambahan) | Endpoint store/update menolak tanpa jenis/divisi; detail mengembalikan jenis + pemilik |
| `SopJenisDokumenPage.test.js` | Render daftar, form, dan penyaringan tipe item |

## Yang sengaja TIDAK dikerjakan

- Tautan induk antar dokumen (Formulir → Prosedur → Kebijakan).
- Pembatasan hak kelola berdasarkan unit pemilik.
- Pola/prefix/nomor otomatis per jenis.
- Backfill tebakan untuk 41 dokumen lama — dibiarkan kosong dan dibuat kelihatan lewat filter.
- Kepemilikan bersama (banyak unit untuk satu dokumen).
