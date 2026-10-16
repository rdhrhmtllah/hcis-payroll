# N_PA_Assignment_Source

Kamus tertutup asal-usul sebuah penugasan: bagaimana baris itu lahir.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Assignment |
| Change ID | DDL-2026-075-performance-appraisal-assignment |
| Forward SQL | `database/ddl/DDL-2026-075-performance-appraisal-assignment/forward.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Assignment_Source` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Sumber` | `varchar(20)` | T | | `UNIQUE`; inilah yang ditunjuk FK |
| `Nama_Sumber` | `nvarchar(100)` | T | | Label layar |
| `Deskripsi` | `nvarchar(500)` | Y | | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| Kode | Nama | Arti |
|---|---|---|
| `USULAN` | Usulan Sistem | Lahir dari resolver relasi organisasi lalu disetujui admin; barisnya menyimpan jejak relasi asalnya |
| `MANUAL` | Ditunjuk Admin | Admin menunjuk penilainya sendiri tanpa dasar relasi; alasannya wajib |

## Tidak ada Urutan_Alur di sini

Berbeda dari `N_PA_Assignment_Status`, ini bukan alur yang dilalui berurutan melainkan
dua asal-usul yang sederajat. Menambahkan kolom urutan hanya akan mengisyaratkan
perpindahan yang tidak pernah terjadi.

## Bedanya dengan Organization_Relationship_Id

`Sumber_Penugasan` menjawab **dari mana asalnya**; `Organization_Relationship_Id` di
tabel penugasan menjawab **dari baris mana**. Yang kedua kosong saat sumbernya `MANUAL`.

Sistem tidak pernah menetapkan penilai sendiri (K10) — hasil resolver hanya usulan
sampai baris penugasannya dibuat. Setelah siklus dikunci, perubahan relasi organisasi
tidak menulis ulang riwayat penugasan.

## Constraint

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Assignment_Source` | Primary key | |
| `UQ_PA_Assignment_Source_Kode` | Unique | Sasaran FK `N_PA_Assignment.Sumber_Penugasan`; FK menunjuk kode supaya `DEFAULT ('USULAN')` tetap bekerja |

## Kedudukan

Kamus lapisan pelaksanaan, tanpa salinan siklus (K26).

## Keadaan data

Dua baris, keduanya aktif.
