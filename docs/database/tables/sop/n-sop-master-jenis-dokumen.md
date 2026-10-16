# N_SOP_Master_Jenis_Dokumen

Master tingkat hirarki mutu untuk dokumen SOP: Kebijakan → Prosedur →
Instruksi Kerja → Formulir.

| Bidang | Nilai |
|---|---|
| Modul | LMS / SOP / Governance — Master Data |
| Change ID | `DDL-2026-016-sop-jenis-dokumen-kepemilikan` |
| Forward SQL | `database/ddl/DDL-2026-016-sop-jenis-dokumen-kepemilikan/forward.sql` |
| Seed | `database/dml/DML-2026-017-seed-sop-jenis-dokumen/data-change.sql` |

## Tujuan

Menjawab pertanyaan "dokumen ini berada di tingkat mana dalam piramid mutu".
Sumbu ini **berbeda** dari dua sumbu yang sudah ada dan tidak boleh
dicampuradukkan dengannya:

- `N_SOP_Dokumen.Tipe` — berkasnya apa, dan apa yang diminta dari karyawan.
- `N_SOP_Kategori` — topiknya apa (HSE, HR, QA), dan itu melekat pada **paket**.

Halaman **Kebijakan & Otomasi SOP** (`menuSopKebijakanPage`) juga tidak ada
hubungannya: yang diatur di sana adalah aturan penugasan otomatis, bukan dokumen
kebijakan.

## Kolom

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `Id_SOP_Jenis_Dokumen` | `int IDENTITY` | NO | — | PK. Penamaan mengikuti preseden `N_SOP_Master_Paket` → `Id_SOP_Paket`. |
| `Kode_Jenis` | `varchar(30)` | NO | — | **Kunci mesin.** Rujukan tetap seed, test, dan integrasi: `KEBIJAKAN`, `SURAT_KEPUTUSAN`, `PROSEDUR`, `INSTRUKSI_KERJA`, `STANDAR`, `FORMULIR`, `MANUAL`. Unik, selalu huruf besar. Terkunci setelah dibuat. |
| `Kode_Jenis_Dokumen` | `varchar(10)` | YES | — | **Kode cetak.** DDL-2026-030. Segmen PERTAMA nomor dokumen gabungan: `PLC`, `SK`, `SOP`, `IK`, `STD`, `FRM`, `MAN`. Bebas disunting admin — kunci mesinnya sudah dipegang kolom di atas. Kosong berarti nomor lengkap tak terbit bagi jenis ini. |
| `Nama_Jenis` | `nvarchar(100)` | NO | — | Label layar. Boleh diubah admin tanpa memutus kode. |
| `Level_Hirarki` | `int` | NO | — | 1 = puncak piramid. **Tidak unik**: satu tingkat boleh punya lebih dari satu jenis. |
| `Deskripsi` | `nvarchar(500)` | YES | — | |
| `Urutan` | `int` | NO | 0 | Urutan dalam tingkat yang sama. |
| `Flag_Aktif` | `char(1)` | NO | `'Y'` | `Y` aktif, `T` nonaktif. Nonaktif ditolak dipakai dokumen baru. |
| `Created_At` | `datetime2(0)` | NO | `SYSDATETIME()` | |
| `Created_By` | `int` | YES | — | `KPI_Users.Id_Users`. |
| `Updated_At` | `datetime2(0)` | YES | — | |
| `Updated_By` | `int` | YES | — | `KPI_Users.Id_Users`. |

## Indeks dan constraint

| Nama | Bentuk | Alasan |
|---|---|---|
| `PK_SOP_Jenis_Dokumen` | PK (`Id_SOP_Jenis_Dokumen`) | |
| `UX_SOP_Jenis_Dokumen_Kode` | UNIQUE (`Kode_Jenis`) | Kode adalah rujukan seed dan integrasi; kembar membuat pemetaan ambigu. |
| `UX_SOP_Jenis_Kode_Jenis_Dokumen` | UNIQUE (`Kode_Jenis_Dokumen`) **filtered** `WHERE ... IS NOT NULL` | Dua jenis yang memakai kode cetak sama membuat nomor dokumen tak bisa dibaca kembali menjadi satu jenis. Filtered karena SQL Server menganggap seluruh NULL sama, sehingga unique index biasa akan menolak baris kedua yang kodenya belum diisi. |
| `IX_SOP_Jenis_Dokumen_Urut` | (`Flag_Aktif`, `Level_Hirarki`, `Urutan`) INCLUDE (`Kode_Jenis`, `Nama_Jenis`) | Bentuk baca yang selalu dipakai dropdown dan tampilan piramid. |

Tanpa CHECK constraint pada `Kode_Jenis` maupun `Level_Hirarki`: keduanya daftar
nilai bisnis yang dapat berkembang, jadi validasinya di backend
(`docs/database/table-design-rules.md`).

## Relasi

```text
N_SOP_Master_Jenis_Dokumen 1 --- n N_SOP_Master_Jenis_Dokumen_Tipe_Item
                                     (FK_SOP_Jenis_Tipe_Jenis)
N_SOP_Master_Jenis_Dokumen 1 --- n N_SOP_Dokumen.Jenis_Dokumen_Id
                                     (FK_SOP_Dokumen_Jenis)
```

## Pemakai di aplikasi

| Lapis | Berkas |
|---|---|
| Service | `app/Services/LMS/SOP/SopJenisDokumenService.php` |
| Penjaga pemakaian | `app/Services/LMS/SOP/SopDokumenService.php` (`pastikanJenisSesuaiTipe`) |
| Controller | `app/Http/Controllers/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenController.php` |
| Route | `routes/LMS/SOP/Governance/SopGovernance.php` |
| Halaman | `resources/js/Pages/LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenPage.vue` |
| Pemakai lain | `SopMasterDokumen.vue`, `ItemFields.vue` (blok "dokumen baru" di Builder) |

## Endpoint

```text
GET    /api/v1/sop/admin/jenis-dokumen/current
POST   /api/v1/sop/admin/jenis-dokumen/store
PUT    /api/v1/sop/admin/jenis-dokumen/update/{id}
DELETE /api/v1/sop/admin/jenis-dokumen/delete/{id}
GET    /api/v1/sop/admin/master-data/jenis-dokumen?tipe=DOKUMEN   (gerbang ganda)
```

## Aturan lifecycle

Nonaktifkan ditolak selama masih ada dokumen aktif yang memakainya; layanan
mengembalikan jumlah pemakainya supaya pesan galat bisa menyebut angka, bukan
sekadar "masih dipakai".

## Alur data

```text
Admin membuka /sop/admin/jenis-dokumen
  -> SopJenisDokumenService::paginate()  (daftar + tipe sah + jumlah pemakai)
  -> simpan/ubah  -> create()/update()  -> setTipeItem() menyamakan pemetaan

Admin mendeklarasikan dokumen (Master Dokumen ATAU Builder)
  -> GET /master-data/jenis-dokumen?tipe=<tipe>  -> opsi() menyaring per tipe
  -> POST/PUT dokumen
       -> controller menegakkan KEWAJIBAN isi
       -> SopDokumenService::pastikanJenisSesuaiTipe() menegakkan KESAHIHAN
       -> N_SOP_Dokumen.Jenis_Dokumen_Id terisi

Membaca
  -> daftar & detail Master Dokumen menerjemahkan id menjadi nama + tingkat
  -> jejak audit (N_SOP_Log) menyimpan NAMA jenis, bukan id
```

## Dua kode, dua peran

Kolom kode di tabel ini menjawab dua pertanyaan yang berbeda dan berumur
berbeda, dan karena itu dipisah sejak DDL-2026-030:

| | `Kode_Jenis` | `Kode_Jenis_Dokumen` |
|---|---|---|
| Menjawab | baris mana yang dimaksud | apa yang tercetak di dokumen |
| Pemakainya | seed, test, integrasi | manusia yang membaca dokumen |
| Umur | selamanya tetap | ikut keputusan bisnis |
| Disunting admin | tidak | **ya** |

Menyatukan keduanya memaksa mengorbankan salah satu sifat: entah admin tak bisa
menyunting kodenya, atau setiap suntingan diam-diam memutus rujukan yang tak
terlihat dari layar mana pun.

Nama panjang dan deskripsi tujuh baris hasil `DML-2026-031` masih **placeholder**
— kepanjangan resminya belum ditetapkan, dan halaman Master Jenis Dokumen adalah
tempat melengkapinya tanpa rilis.

## Kode cetak dan `Nomor_SOP_Resmi`

`Kode_Jenis_Dokumen` adalah segmen PERTAMA nomor dokumen gabungan, dan nomor itu
adalah isi `N_SOP_Dokumen.Nomor_SOP_Resmi` — bukan nomor terpisah di sebelahnya.

Konsekuensinya: mengubah kode cetak sebuah jenis akan mengubah nomor resmi
seluruh dokumen berjenis itu pada penyegaran berikutnya. Itu memang yang
diinginkan selama kepanjangan resminya belum ditetapkan, tetapi berhenti menjadi
tak berbahaya begitu nomornya sudah tercetak di luar sistem.
