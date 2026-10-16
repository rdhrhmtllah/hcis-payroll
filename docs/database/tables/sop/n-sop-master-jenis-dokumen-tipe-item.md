# N_SOP_Master_Jenis_Dokumen_Tipe_Item

Pemetaan jenis dokumen ↔ tipe item yang sah baginya.

| Bidang | Nilai |
|---|---|
| Modul | LMS / SOP / Governance — Master Data |
| Change ID | `DDL-2026-016-sop-jenis-dokumen-kepemilikan` |
| Seed | `database/dml/DML-2026-017-seed-sop-jenis-dokumen/data-change.sql` |

## Tujuan

Menjadikan aturan "jenis apa boleh dipasangkan dengan tipe item apa" sebagai
**data**, bukan enum di kode dan bukan CHECK constraint di skema. Daftar tingkat
mutu adalah hal yang diubah bagian mutu; aturan yang ditulis di kode menuntut
rilis untuk setiap perubahan kebijakan.

Isi seed:

```text
KEBIJAKAN        -> DOKUMEN
PROSEDUR         -> DOKUMEN
INSTRUKSI_KERJA  -> DOKUMEN, VIDEO
FORMULIR         -> FORM
```

`KUIS` sengaja tak dipetakan ke jenis mana pun — ia alat verifikasi pemahaman,
bukan dokumen terkendali.

## Kolom

| Kolom | Tipe | Null | Default | Keterangan |
|---|---|---|---|---|
| `Id_SOP_Jenis_Dokumen_Tipe_Item` | `int IDENTITY` | NO | — | PK |
| `Jenis_Dokumen_Id` | `int` | NO | — | FK ke `N_SOP_Master_Jenis_Dokumen`. |
| `Tipe_Item` | `varchar(12)` | NO | — | Lebar mengikuti `N_SOP_Paket_Item.Tipe_Item`, diukur bukan ditebak. |
| `Flag_Aktif` | `char(1)` | NO | `'Y'` | Pencabutan pasangan **menonaktifkan** baris, tidak menghapusnya. |
| `Created_At` | `datetime2(0)` | NO | `SYSDATETIME()` | |
| `Created_By` | `int` | YES | — | `KPI_Users.Id_Users`. |

## Indeks dan constraint

| Nama | Bentuk | Alasan |
|---|---|---|
| `PK_SOP_Jenis_Dokumen_Tipe_Item` | PK | |
| `FK_SOP_Jenis_Tipe_Jenis` | FK → `N_SOP_Master_Jenis_Dokumen` | Baris pemetaan tak berarti tanpa induknya. Tanpa cascade: pencabutan lewat aplikasi, bukan efek samping. |
| `UX_SOP_Jenis_Tipe_Pasangan` | UNIQUE (`Jenis_Dokumen_Id`, `Tipe_Item`) | Satu pasangan satu baris. |
| `IX_SOP_Jenis_Tipe_Cari` | (`Tipe_Item`, `Flag_Aktif`) INCLUDE (`Jenis_Dokumen_Id`) | Menjawab arah sebaliknya: "jenis apa saja yang menerima FORM" — persis yang dibutuhkan dropdown saat tipe sudah dipilih. |

**Catatan penting**: unique index sengaja TIDAK menyertakan `Flag_Aktif`.
Konsekuensinya, menghidupkan kembali pasangan yang pernah dicabut harus lewat
`UPDATE`, bukan `INSERT` baru — `SopJenisDokumenService::setTipeItem()`
melakukannya begitu, dan perilakunya diuji.

## Pemakai di aplikasi

| Lapis | Berkas |
|---|---|
| Service | `app/Services/LMS/SOP/SopJenisDokumenService.php` (`setTipeItem`, `pasanganSah`, `tipeSah`, `opsi`) |
| Penjaga | `app/Services/LMS/SOP/SopDokumenService.php` (`pastikanJenisSesuaiTipe`) |

## Alur data

```text
Master Jenis Dokumen (simpan/ubah)
  -> SopJenisDokumenService::setTipeItem()
       baris baru      -> INSERT
       diaktifkan lagi -> UPDATE Flag_Aktif = 'Y'   (bukan INSERT: unique index)
       dicabut         -> UPDATE Flag_Aktif = 'T'   (bukan DELETE: jejak tetap ada)

Dropdown jenis di Master Dokumen dan Builder
  -> opsi($tipe) -> whereExists ke tabel ini (Flag_Aktif = 'Y')

Penyimpanan dokumen
  -> pasanganSah($jenisId, $tipe) -> tabel ini adalah satu-satunya sumber jawaban
```
