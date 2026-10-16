# N_PA_Assignment_Status

Kamus tertutup status satu penugasan penilai.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Assignment |
| Change ID | DDL-2026-075-performance-appraisal-assignment |
| Forward SQL | `database/ddl/DDL-2026-075-performance-appraisal-assignment/forward.sql` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Assignment_Status` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Status` | `varchar(20)` | T | | `UNIQUE`; inilah yang ditunjuk FK |
| `Nama_Status` | `nvarchar(100)` | T | | Label layar |
| `Deskripsi` | `nvarchar(500)` | Y | | |
| `Urutan_Alur` | `smallint` | T | | Urutan maju, mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| Kode | Nama | Urutan | Arti |
|---|---|:---:|---|
| `BELUM_MULAI` | Belum Mulai | 1 | Penugasan sudah dibentuk, penilainya belum membuka form |
| `BERJALAN` | Berjalan | 2 | Form sudah dibuka di HC Learn, hasilnya belum disetor |
| `SELESAI` | Selesai | 3 | Hasil diterima lewat callback dan tersimpan sebagai payload mentah |
| `DIBATALKAN` | Dibatalkan | 4 | Penugasan dicabut |

`DIBATALKAN` bukan sekadar penanda. Jawaban yang telanjur masuk **tetap tersimpan tetapi
berhenti ikut dihitung**, dan bobot penilai lain di kategori yang sama wajib dinormalkan
ulang — kalau tidak, jumlahnya berhenti di 70 dan hasilnya diam-diam salah.

## Kenapa master, bukan varchar bebas

K31. Risikonya bukan daftarnya bertambah melainkan salah eja lintas sesi: `SELESAI`
versus `Selesai` akan diterima `varchar` lalu diam-diam tidak cocok dengan apa pun.

## Constraint

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Assignment_Status` | Primary key | |
| `UQ_PA_Assignment_Status_Kode` | Unique | Sasaran FK `N_PA_Assignment.Status_Penugasan`; FK menunjuk kode bukan Id supaya `DEFAULT ('BELUM_MULAI')` tetap bekerja |
| `CK_PA_Assignment_Status_Urutan` | Check | `Urutan_Alur >= 1` |

## Kedudukan

Kamus lapisan pelaksanaan. Tidak punya salinan siklus — makna sebuah status tidak boleh
berbeda antar siklus (K26).

## Keadaan data

Empat baris, semuanya aktif.
