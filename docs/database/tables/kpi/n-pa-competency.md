# N_PA_Competency

Master kompetensi yang dinilai. Dua puluh empat baris dari deck PIC.

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Competency |
| Change ID | DDL-2026-067-performance-appraisal-competency-and-score-range; diperluas DDL-2026-096 (`Nilai_Ambang`, `Flag_Radar`) |
| Forward SQL | `database/ddl/DDL-2026-067-performance-appraisal-competency-and-score-range/forward.sql` |
| DML | `database/dml/DDL-2026-067-performance-appraisal-competency-and-score-range/data-change.sql` |
| Konsep | `docs/superpowers/specs/2026-08-29-performance-appraisal-design.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Competency` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Competency_Type_Id` | `int` | T | | FK ke `N_PA_Competency_Type` |
| `Kode_Competency` | `varchar(40)` | T | | Kode bisnis, unik. K01–K11, CV01–CV06, BH01–BH03, QL01–QL04 |
| `Nama_Competency` | `nvarchar(150)` | T | | Nama tampil |
| `Deskripsi` | `nvarchar(500)` | Y | | Penjelasan kompetensi |
| `Flag_Berskor` | `char(1)` | T | `'Y'` | `T` berarti butir teks tanpa angka; dilewati saat menghitung |
| `Nilai_Ambang` | `decimal(5,2)` | Y | | Ambang nilai (KKM), skala 0–100. `NULL` = belum ditetapkan (DDL-2026-096) |
| `Flag_Radar` | `char(1)` | T | `'Y'` | `Y` = menjadi sumbu radar kompetensi (DDL-2026-096) |
| `Urutan_Tampil` | `smallint` | T | | Urutan dalam jenisnya, mulai 1 |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | |
| `Created_At` | `datetime2(0)` | T | `SYSDATETIME()` | |
| `Created_By` | `int` | Y | | `KPI_Users.Id_Users` |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

### `Flag_Berskor` menjaga empat butir kualitatif

Strength, Development Area, Initiative, dan Recommendation adalah bagian dari
formulir asesmen yang sama — deck menyebutnya "satu form yang terdiri dari Rating
Scale dan Soal Qualitative" — tetapi jawabannya teks, bukan angka.

Tanpa penanda ini, pemeta bisa merata-ratakan keempatnya sebagai nol dan nilai
kompetensi ikut turun tanpa ada yang sadar; hasilnya tetap terlihat wajar. DML
menolak commit kalau jumlah baris `Flag_Berskor = 'T'` bukan tepat empat.

Jangan tertukar dengan `N_PA_Result_Note`. Butir kualitatif di sini diisi **penilai**
dan masuk `N_PA_Assignment_Score`; sedangkan Strength dan Area Pengembangan pada
report peserta diisi **admin** dan masuk `N_PA_Result_Note`. Namanya mirip, sumbernya
berbeda.

### `Nilai_Ambang` dan `Flag_Radar` — DDL-2026-096

Radar kompetensi semula menggambar satu jaring saja: nilai orangnya. Jaring itu menjawab
"berapa", tidak pernah "cukup atau belum". `Nilai_Ambang` memberi radar jaring acuan kedua.
`Flag_Radar` memilih sumbunya, supaya menambah kompetensi ke kamus tidak diam-diam merusak
setiap radar yang sudah ada.

Skalanya **0–100**, mengikuti `PaResultCalculationService::normal()` yang menormalkan setiap
butir menjadi `Skor / Skor_Maksimal * 100`. Angka `max: 125` pada radar layar admin adalah
kelonggaran gambar, bukan skala nilai.

`NULL` pada `Nilai_Ambang` berarti **belum ditetapkan**, dan sumbunya dilewati saat
menggambar jaring KKM. Ia sengaja tidak berdefault `0`: nol akan membuat jaring KKM penyok
ke pusat pada sumbu yang sebenarnya belum diputuskan, terbaca sebagai standar yang sangat
rendah, tanpa satu pun galat.

Kompetensi tak berskor tidak boleh masuk radar dan tidak boleh punya ambang. Aturannya
ditegakkan di `PaMasterWriteService::simpanKompetensi()`, bukan sebagai `CHECK` lintas
kolom, supaya galatnya bisa menyebutkan sebabnya kepada HC.

**Batas keputusan menurut K26.** Kedua kolom ini tinggal di kamus karena ambang belum
dipakai menghitung apa pun — tidak masuk `N_PA_Result`, tidak menentukan grade, tidak
menentukan kuadran HAV. Begitu ia dipakai untuk memutuskan sesuatu (lulus/tidak, syarat
promosi, penyaring kalibrasi), ia menjadi **aturan** dan wajib pindah ke snapshot per siklus
lewat perubahan baru. Tanpa itu, mengubah KKM tahun depan menulis ulang kesimpulan siklus
yang sudah ditutup.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Competency` | Primary key | `Id_PA_Competency` |
| `FK_PA_Competency_Type` | Foreign key | `PA_Competency_Type_Id` → `N_PA_Competency_Type` |
| `UX_PA_Competency_Kode` | Unique index | `Kode_Competency` unik |
| `IX_PA_Competency_Type_Urutan` | Index | `(PA_Competency_Type_Id, Flag_Aktif, Urutan_Tampil)` dengan `INCLUDE (Kode_Competency, Nama_Competency, Flag_Berskor)` — jalur penyusunan formulir |
| `CK_PA_Competency_Urutan` | Check | `Urutan_Tampil >= 1` |
| `CK_PA_Competency_Ambang` | Check | `Nilai_Ambang IS NULL OR 0 <= Nilai_Ambang <= 100` — rentang tetap dari rumus normalisasi, bukan daftar nilai bisnis |
| `DF_PA_Competency_Berskor` / `_Aktif` / `_Created` / `_Radar` | Default | `'Y'`, `'Y'`, `SYSDATETIME()`, `'Y'` |

Tidak ada indeks pada `Flag_Radar`: tabel ini kamus berisi puluhan baris dan selalu dibaca
utuh lalu disaring di memori.

Tidak ada kolom kode sumber HC Learn di sini. Pemetaannya ada di tabel anak
`N_PA_Competency_Kode_Soal`, karena satu kompetensi bisa datang dengan kode berbeda
dari paket yang berbeda.

## Kedudukan

Lapisan pertama, dan **kamus** menurut K26 — tidak disnapshot per siklus. Hasil lama
tetap menunjuk `Id_PA_Competency` yang sama walau namanya diganti.

## Pemakaian aplikasi

| Berkas | Peran |
|---|---|
| `PaMasterReadService` (`KATALOG['kompetensi']`) | Katalog baca master; kolom yang tidak terdaftar di sini tidak pernah sampai ke layar |
| `PaMasterWriteService::simpanKompetensi()` | Jalur tulis dan penjaga kualitatif/ambang |
| `PaPesertaService::hasil()` | Rincian + sumbu radar layar peserta |
| `PaParticipantReadService::nilaiKompetensi()` | Rincian + sumbu radar layar admin |
| `PaLeaderService::radar()` | Rincian kompetensi layar leader |

Ketiga jalur baca **mengirim** `Flag_Radar` tanpa menyaringnya. Baris yang sama melayani
radar dan daftar rincian sekaligus; menyaring di kueri akan melenyapkan kompetensi non-radar
dari rincian juga. Yang memilih sumbu adalah layar.

## Keadaan data

24 baris: 11 MANAJERIAL (K01 Analytical Thinking sampai K11 Team Leadership),
6 CORE_VALUE, 3 BEHAVIOR, 4 KUALITATIF. Yang empat terakhir `Flag_Berskor = 'T'`, dan
DML-2026-121 mengeluarkan keempatnya dari radar.

`Nilai_Ambang` = **80,00** pada 20 baris berskor (`DML-2026-122`, keputusan HC 11 September
2026: satu ambang yang sama untuk semua kompetensi). Empat butir kualitatif tetap `NULL` —
memberi mereka ambang berarti menetapkan standar bagi sesuatu yang tidak pernah diukur.
