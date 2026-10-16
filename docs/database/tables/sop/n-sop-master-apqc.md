# N_SOP_Master_APQC

Master klasifikasi proses bisnis APQC untuk dokumen SOP.

| Bidang | Nilai |
|---|---|
| Modul | LMS / SOP / Governance — Master Data |
| Change ID | `DDL-2026-023-sop-master-apqc` |
| Seed | `database/dml/DML-2026-024-seed-sop-apqc/data-change.sql` |

## Tujuan

Menjawab "dokumen ini memetakan proses bisnis yang mana". Sumbu **keempat** pada
identitas dokumen, dan sengaja berbeda dari tiga yang sudah ada:

```text
N_SOP_Dokumen.Tipe          -> berkasnya apa
N_SOP_Master_Jenis_Dokumen  -> tingkat hirarki mutu
N_SOP_Kategori              -> topiknya apa (melekat di PAKET, bukan dokumen)
```

## Kolom

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `Id_SOP_APQC` | `int IDENTITY` | NO | — | PK |
| `Kode_Proses` | `varchar(10)` | NO | — | `01`…`13`. Unik. TEKS, bukan angka: nol di depan bagian dari kodenya, dan sub-level kelak ditulis `01.1` di kolom yang sama. |
| `Nama_Proses` | `nvarchar(200)` | NO | — | Nama kategori APQC PCF. |
| `Deskripsi` | `nvarchar(500)` | YES | — | |
| `Urutan` | `int` | NO | 0 | Urutan tampil. |
| `Flag_Aktif` | `char(1)` | NO | `'Y'` | Nonaktif ditolak dipakai dokumen baru. |
| `Created_At` | `datetime2(0)` | NO | `SYSDATETIME()` | |
| `Created_By` | `int` | YES | — | `KPI_Users.Id_Users`. |
| `Updated_At` | `datetime2(0)` | YES | — | |
| `Updated_By` | `int` | YES | — | |

## Tabel pemetaan `N_SOP_Master_APQC_Tipe_Item`

| Kolom | Tipe | Null | Keterangan |
|---|---|---|---|
| `Id_SOP_APQC_Tipe_Item` | `int IDENTITY` | NO | PK |
| `APQC_Id` | `int` | NO | FK ke `N_SOP_Master_APQC`, tanpa cascade. |
| `Tipe_Item` | `varchar(12)` | NO | Lebar mengikuti `N_SOP_Paket_Item.Tipe_Item`. |
| `Flag_Aktif` | `char(1)` | NO | Pencabutan **menonaktifkan** baris, tidak menghapusnya. |
| `Created_At` / `Created_By` | | | |

Unique index `(APQC_Id, Tipe_Item)` sengaja TIDAK menyertakan `Flag_Aktif`, jadi
menghidupkan kembali pasangan yang pernah dicabut harus lewat `UPDATE` — dan
`SopApqcService::setTipeItem()` melakukannya begitu.

## Aturan yang membuat tabel ini berharga

**Kewajiban mengisi APQC pada dokumen DITURUNKAN dari pemetaan ini.** Sebuah tipe
item yang tak punya satu pun APQC sah tidak akan pernah diminta mengisinya —
itulah sebabnya `KUIS` tak menawarkan APQC, dan sebabnya admin bisa mengubah
kapan APQC wajib **lewat master, bukan lewat rilis**.

Terbukti di layar: mengganti tipe dokumen menjadi `KUIS` membuat field APQC
hilang sendiri dari form.

## Indeks dan constraint

| Nama | Bentuk | Alasan |
|---|---|---|
| `UX_SOP_APQC_Kode` | UNIQUE (`Kode_Proses`) | Nomor proses dikutip di audit; kembar membuat rujukan ambigu. |
| `IX_SOP_APQC_Urut` | (`Flag_Aktif`, `Urutan`) INCLUDE (`Kode_Proses`, `Nama_Proses`) | Bentuk baca dropdown. |
| `UX_SOP_APQC_Tipe_Pasangan` | UNIQUE (`APQC_Id`, `Tipe_Item`) | |
| `IX_SOP_APQC_Tipe_Cari` | (`Tipe_Item`, `Flag_Aktif`) INCLUDE (`APQC_Id`) | Menjawab arah sebaliknya: "APQC apa saja yang menerima FORM". |
| `IX_SOP_Dokumen_APQC` | `N_SOP_Dokumen` (`Flag_Aktif`, `APQC_Id`) | Filter dan laporan per proses. |

Tanpa CHECK constraint: daftar proses berkembang, validasinya di backend.

## Pemakai di aplikasi

| Lapis | Berkas |
|---|---|
| Service | `app/Services/LMS/SOP/SopApqcService.php` |
| Penjaga pemakaian | `app/Services/LMS/SOP/SopDokumenService.php` (`pastikanApqcSesuaiTipe`) |
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopApqc/SopApqcController.php` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` — `menuSopApqcPage` |
| Halaman | `resources/js/Pages/LMS/SOP/Governance/SopApqc/SopApqcPage.vue` |
| Konsumen | `SopMasterDokumen.vue`, `ItemFields.vue` (blok "Dokumen Baru" di Builder) |

## Endpoint

```text
GET    /api/v1/sop/admin/apqc/current
POST   /api/v1/sop/admin/apqc/store
PUT    /api/v1/sop/admin/apqc/update/{id}
DELETE /api/v1/sop/admin/apqc/delete/{id}
GET    /api/v1/sop/admin/master-data/apqc?tipe=DOKUMEN   (gerbang ganda)
```

## Alur data

```text
Admin membuka /sop/admin/apqc
  -> paginate(): daftar + tipe item sah + jumlah dokumen pemakai
  -> setTipeItem() menyamakan pemetaan; mencabut semua tipe = APQC berhenti diminta

Admin mendeklarasikan dokumen (Master Dokumen ATAU Builder)
  -> GET /master-data/apqc?tipe=<tipe>  -> opsi() menyaring per tipe
  -> controller mewajibkan HANYA bila opsi()-nya tidak kosong
  -> SopDokumenService::pastikanApqcSesuaiTipe() menegakkan kesahihan
  -> N_SOP_Dokumen.APQC_Id terisi

Membaca
  -> daftar & detail menerjemahkan id jadi "kode · nama"
  -> jejak audit (N_SOP_Log) menyimpan "01 Develop Vision and Strategy", bukan id
```

## Aturan lifecycle

Nonaktifkan ditolak selama masih ada dokumen aktif yang memakainya; service
mengembalikan jumlah pemakainya supaya pesan galat menyebut angka.
