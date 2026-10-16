# N_PA_Score_Usage

Master kegunaan pita nilai. Tiga baris: HAVMAP, GRADE, KONVERSI. Menutup kolom
`N_PA_Score_Range.Score_Usage` yang sebelumnya `varchar` bebas, sekaligus menyatakan
kolom opsional mana yang dipakai tiap kegunaan.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Score Range |
| Change ID | DDL-2026-070-performance-appraisal-status-and-axis-masters |
| Forward SQL | `database/ddl/DDL-2026-070-performance-appraisal-status-and-axis-masters/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Score_Kegunaan` | `int IDENTITY(1,1)` | T | | Primary key |
| `Kode_Usage` | `varchar(20)` | T | | Kode bisnis, **UNIQUE**; dirujuk `N_PA_Score_Range.Score_Usage` |
| `Nama_Usage` | `nvarchar(100)` | T | | Nama tampil |
| `Deskripsi` | `nvarchar(500)` | Y | | |
| `Flag_Pakai_Nilai_Hasil` | `char(1)` | T | `'T'` | `Y` bila kegunaan ini memakai kolom `Nilai_Hasil` |
| `Flag_Pakai_Target` | `char(1)` | T | `'T'` | `Y` bila kegunaan ini memakai kolom `Target_Persen` |
| `Urutan_Tampil` | `smallint` | T | | Mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Isi

| # | Kode | Nama | Nilai_Hasil | Target_Persen | Arti |
|---|---|---|:---:|:---:|---|
| 1 | `HAVMAP` | Pita HAV Map | T | T | Tiga tingkat Tinggi, Sedang, Rendah yang menentukan kotak seseorang pada 9-box |
| 2 | `GRADE` | Pita Grade | T | **Y** | Batas grade akhir beserta target distribusinya untuk kalibrasi |
| 3 | `KONVERSI` | Konversi Skala | **Y** | T | Pemetaan nilai dari satu skala ke skala lain; hasilnya di `Nilai_Hasil` |

### Dua flag menjelaskan kolom nullable

`N_PA_Score_Range.Nilai_Hasil` dan `Target_Persen` keduanya nullable, dan alasannya
bukan karena keduanya opsional secara umum — masing-masing milik tepat satu kegunaan.
`Nilai_Hasil` hanya bermakna untuk `KONVERSI`, dan `Target_Persen` hanya untuk
`GRADE`.

Menyimpan pemetaan itu sebagai data membuat aturannya terlihat oleh siapa pun yang
membaca skema, alih-alih terkubur di kode. Validasi bahwa kolom terisi hanya pada
kegunaan yang benar tetap dijalankan aplikasi, karena aturannya melibatkan dua tabel.

## Kenapa ini tabel, padahal daftarnya tidak akan tumbuh

Alasannya sama dengan `N_PA_Score_Type`, dan tercatat sebagai K31: risikonya bukan
daftarnya bertambah melainkan nilai tetap yang dieja berbeda antar sesi. `varchar`
bebas menerima setiap ejaan dan baru ketahuan salah ketika pencarian tidak menemukan
apa pun. Dokumentasi DDL-2026-067 sempat menyimpulkan sebaliknya; kesimpulan itu
sudah dikoreksi di README-nya.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Score_Usage` | Primary key | |
| `UQ_PA_Score_Usage_Kode` | Unique constraint | `Kode_Usage` unik; inilah yang membuatnya sah jadi sasaran foreign key |
| `CK_PA_Score_Usage_Urutan` | Check | `Urutan_Tampil >= 1` |
| `DF_PA_Score_Usage_Hasil` / `_Target` / `_Aktif` / `_Created` | Default | `'T'`, `'T'`, `'Y'`, `SYSDATETIME()` |

## Hubungan

`N_PA_Score_Range.Score_Usage` menunjuk ke sini lewat `FK_PA_Score_Range_Usage`,
dengan sasaran kolom kode agar baris pita nilai tetap terbaca tanpa join.

## Kedudukan

Lapisan pertama, dan **kamus** menurut K26 — tidak disnapshot per siklus.

## Pemakaian aplikasi

Belum ada per 2026-08-30.

## Keadaan data

Tiga baris seed aktif.
