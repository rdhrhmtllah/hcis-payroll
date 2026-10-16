# N_PA_Result_Note

Catatan naratif pada satu hasil: kekuatan dan area pengembangan.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Result |
| Change ID | DDL-2026-081-performance-appraisal-result-detail |
| Forward SQL | `database/ddl/DDL-2026-081-performance-appraisal-result-detail/forward.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Result_Note` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Result_Id` | `int` | T | | Hasil yang dicatatkan |
| `PA_Result_Note_Type_Id` | `int` | T | | `STRENGTH` atau `DEVELOPMENT_AREA` |
| `PA_Assignment_Id` | `int` | Y | | **Kosong = diketik admin; terisi = dari jawaban penilai** |
| `Isi` | `nvarchar(max)` | T | | Tidak boleh kosong atau spasi saja |
| `Urutan_Tampil` | `smallint` | Y | | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Catatan mengingat asal-usulnya

`PA_Assignment_Id` membedakan dua hal yang tampak sama di layar tetapi tidak sama
maknanya:

- **Kosong** — admin atau atasan mengetiknya sendiri saat meninjau hasil.
- **Terisi** — berasal dari jawaban kualitatif seorang penilai, dan penugasan itu yang
  menyimpan siapa penilainya serta kategorinya.

Bedanya penting bagi karyawan yang membacanya. "Atasan Anda menulis ini" dan "inilah
yang dikatakan rekan-rekan Anda" tidak membawa bobot yang sama, dan tanpa kolom ini
keduanya menjadi satu tumpukan tanpa asal.

Kolom ini juga yang memungkinkan catatan dari penilai ditampilkan tanpa nama bila
kebijakan menghendakinya — anonimitas ditentukan cara membacanya, bukan dengan membuang
jejaknya.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Result_Note` | Primary key | |
| `FK_PA_Result_Note_Result` | Foreign key | → `N_PA_Result` |
| `FK_PA_Result_Note_Type` | Foreign key | → `N_PA_Result_Note_Type` |
| `FK_PA_Result_Note_Assignment` | Foreign key | → `N_PA_Assignment`, nullable |
| `IX_PA_Result_Note_Result` | Index | Jalur baca per hasil |
| `CK_PA_Result_Note_Isi` | Check | Isi tidak boleh kosong atau spasi saja |
| `CK_PA_Result_Note_Urutan` | Check | Minimal 1 bila diisi |

## Yang sengaja tidak dijaga database

Bahwa penugasan yang ditunjuk benar-benar milik peserta yang sama dengan hasilnya.
Memeriksanya melibatkan tiga tabel dan sifatnya dihitung, bukan dibatasi.

## Kedudukan

Anak dari `N_PA_Result`.

## Pemakaian aplikasi

Belum ada per 1 September 2026.

## Keadaan data

Kosong.
