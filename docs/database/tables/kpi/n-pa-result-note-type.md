# N_PA_Result_Note_Type

Kamus tertutup jenis catatan hasil.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Result |
| Change ID | DDL-2026-080-performance-appraisal-result |
| Forward SQL | `database/ddl/DDL-2026-080-performance-appraisal-result/forward.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Result_Note_Type` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Type` | `varchar(30)` | T | | `UNIQUE`. **Tidak ditunjuk FK mana pun** — kedua FK ke tabel ini (`FK_PA_Result_Note_Type` dan `FK_PA_Cycle_Question_Item_Note_Type`) menunjuk `Id_PA_Result_Note_Type`. Bandingkan `N_PA_Result_Status` yang FK-nya memang menunjuk `Kode_Status`, karena di sana `DEFAULT` butuh nilai kodenya |
| `Nama_Type` | `nvarchar(100)` | T | | Label layar |
| `Deskripsi` | `nvarchar(500)` | Y | | |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| Kode | Nama | Urutan |
|---|---|:---:|
| `STRENGTH` | Kekuatan | 1 |
| `DEVELOPMENT_AREA` | Area Pengembangan | 2 |

## Kenapa master, bukan varchar

K31. Dua nilai ini tidak akan tumbuh, tetapi risikonya bukan pertumbuhan melainkan salah
eja lintas sesi: `DEVELOPMENT` alih-alih `DEVELOPMENT_AREA` akan diterima `varchar` lalu
diam-diam tidak cocok dengan apa pun, dan catatannya hilang dari layar tanpa error.

Bandingkan dengan kolom metode nilai KPI yang **dibatalkan** pada change yang sama: yang
itu selamanya berisi satu nilai, jadi tidak punya daftar. Yang ini punya dua nilai yang
benar-benar berbeda per baris.

## Constraint

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Result_Note_Type` | Primary key | |
| `UQ_PA_Result_Note_Type_Kode` | Unique | Sasaran FK |
| `CK_PA_Result_Note_Type_Urutan` | Check | `Urutan_Tampil >= 1` |

## Kedudukan

Kamus lapisan hasil, tanpa salinan siklus (K26).

## Keadaan data

Dua baris, keduanya aktif.
