# N_PA_Assignment_Score

Hasil proyeksi pemeta: satu baris per butir soal di dalam satu payload. Inilah angka
yang benar-benar dibaca perhitungan.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Assignment |
| Change ID | DDL-2026-076-performance-appraisal-payload-and-score |
| Forward SQL | `database/ddl/DDL-2026-076-performance-appraisal-payload-and-score/forward.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Assignment_Score` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Assignment_Payload_Id` | `int` | T | | Payload asalnya; penugasannya diturunkan dari sini |
| `PA_Competency_Id` | `int` | Y | | **NULL berarti kode soalnya belum dikenali** |
| `Kode_Soal_Sumber` | `varchar(100)` | T | | Kunci sambung ke `N_PA_Competency_Kode_Soal` |
| `Jenis_Soal_Sumber` | `varchar(100)` | Y | | Keterangan dari HC Learn |
| `Skor` | `decimal(6,2)` | Y | | |
| `Skor_Maksimal` | `decimal(6,2)` | Y | | Skala butir itu, dibaca dari data |
| `Bobot_Sumber` | `decimal(5,2)` | Y | | Bobot antar butir milik mereka; alat uji silang |
| `Jawaban_Teks` | `nvarchar(max)` | Y | | Untuk butir kualitatif |
| `Mapper_Version` | `int` | T | | Pemeta versi berapa yang menghasilkan baris ini |
| `Urutan_Tampil` | `smallint` | Y | | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` memensiunkan hasil pemetaan lama |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## Penugasan tidak disimpan di sini

Sketsa awal membawa `Id_Assignment` **dan** `Id_Payload`. Hanya payload yang bertahan:
satu payload milik tepat satu penugasan, jadi penugasannya sudah tertentu. Alasannya
sama dengan `Id_Cycle` yang dibuang dari `N_PA_Assignment` — 75.000 baris murah dijoin,
sedangkan dua sumber untuk satu kebenaran tidak pernah murah.

## Memetakan ulang tidak menghapus apa pun

Pemeta punya nomor versi. Saat v2 menggantikan v1, baris lama diberi `Flag_Aktif = 'T'`
dan baris baru disisipkan di sebelahnya, sehingga proyeksi lama tetap bisa diaudit —
termasuk **kenapa** hasil lama tampak seperti itu.
`UX_PA_Assignment_Score_Butir` tersaring pada baris aktif, jadi satu kode soal boleh ada
dua kali dalam satu payload selama hanya satu yang hidup.

## Skor melebihi skalanya ditolak database

Ini pilihan sadar, bukan kelalaian. Menormalkan nilai semacam itu menghasilkan lebih
dari 100% dan merusak nilai akhir diam-diam, sementara gagal dengan keras tidak
merugikan apa pun: payload mentahnya sudah tersimpan lebih dulu, jadi tidak ada data
hilang dan barisnya tetap terlihat untuk diperbaiki.

## Skala dibaca dari data

Contoh payload HC Learn mengembalikan skala 100, sementara deck PIC menampilkan 1–5, dan
T05 belum dijawab. Satu payload berisi butir skala 5 dan butir skala 100 sekaligus sudah
diuji dan diterima — jadi paket bercampur skala pun tidak bisa merusak perhitungan
diam-diam.

## Bobot_Sumber adalah alat uji silang

Pada contoh payload, `(41×20 + 60×30 + 80×50) ÷ 100` menghasilkan tepat `66,2`, sama
persis dengan `Total_Nilai` yang mereka laporkan. Menyimpan bobot mereka membuat pemeta
kita bisa **membuktikan** menghitung hal yang sama, bukan sekadar mempercayainya.

## Status_Interpretation dibuang

"Belum dikenali" sudah terbaca dari `PA_Competency_Id` yang NULL. Kolom kedua yang
menyatakan hal sama hanya menambah peluang keduanya bertentangan — argumen yang sama
yang menahan kolom jenis override keluar dari DDL-074.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Assignment_Score` | Primary key | |
| `FK_PA_Assignment_Score_Payload` | Foreign key | → `N_PA_Assignment_Payload` |
| `FK_PA_Assignment_Score_Competency` | Foreign key | → `N_PA_Competency`, nullable |
| `UX_PA_Assignment_Score_Butir` | Unique index tersaring | `(payload, kode soal)` saat aktif |
| `IX_PA_Assignment_Score_Agregasi` | Index | Jalur agregasi, `INCLUDE` kompetensi, skor, skala, bobot |
| `IX_PA_Assignment_Score_Competency` | Index | Jalur laporan per kompetensi |
| `CK_PA_Assignment_Score_Maksimal` | Check | Skala NULL atau lebih dari 0 |
| `CK_PA_Assignment_Score_Skor` | Check | Skor NULL atau tidak negatif |
| `CK_PA_Assignment_Score_Batas` | Check | Skor tidak boleh melebihi skalanya |
| `CK_PA_Assignment_Score_Bobot` | Check | 0–100 |
| `CK_PA_Assignment_Score_Mapper` | Check | Versi pemeta minimal 1 |
| `CK_PA_Assignment_Score_Urutan` | Check | Urutan minimal 1 bila diisi |

Skala nol dilarang karena normalisasi akan membagi dengan nol — nilai yang tidak
diketahui ditulis NULL, bukan 0.

## Kedudukan

Lapisan pelaksanaan, anak dari `N_PA_Assignment_Payload`. Menutup prasyarat ketiga
`N_PA_Result`.

## Pemakaian aplikasi

Belum ada per 2026-08-31.

## Keadaan data

Kosong.
