# N_PA_Result_Rater_Category

Jejak nilai dan bobot per kategori penilai untuk satu hasil. Inilah yang menjawab
"kenapa nilai kompetensinya segitu".

| Atribut | Nilai |
|---|---|
| Modul / Submodul / Fitur | KPI / Performance Appraisal / Result |
| Change ID | DDL-2026-081-performance-appraisal-result-detail |
| Forward SQL | `database/ddl/DDL-2026-081-performance-appraisal-result-detail/forward.sql` |
| Konsep | `plans/kpi/2026-09-01-lapisan-hasil-pa-rancangan.md` |

## Kolom

| Kolom | Tipe | Null | Default | Arti |
|---|---|:---:|---|---|
| `Id_PA_Result_Rater_Category` | `int IDENTITY(1,1)` | T | | Primary key |
| `PA_Result_Id` | `int` | T | | Hasil yang dirinci |
| `PA_Rater_Category_Id` | `int` | T | | Kategori penilai |
| `Nilai` | `decimal(6,2)` | Y | | Nilai kompetensi menurut kategori ini |
| `Jumlah_Rater_Diharapkan` | `smallint` | Y | | Sesuai aturan yang berlaku bagi peserta |
| `Jumlah_Rater_Mengisi` | `smallint` | Y | | Yang benar-benar menyelesaikan |
| `Bobot_Terpakai` | `decimal(5,2)` | Y | | **Bobot yang benar-benar dipakai**, bukan yang tertulis di aturan |
| `Calculation_Version` | `int` | T | `1` | |
| `Flag_Aktif` | `char(1)` | T | `'Y'` | `T` memensiunkan hasil perhitungan lama |
| `Created_At` / `Created_By` | `datetime2(0)` / `int` | T / Y | `SYSDATETIME()` | |
| `Updated_At` / `Updated_By` | `datetime2(0)` / `int` | Y | | |

## `Bobot_Terpakai` bukan salinan bobot aturan

Keduanya berbeda begitu satu kategori penilai kosong dan bobotnya diredistribusi ke
kategori yang terisi. Contoh: aturan level Manager memberi Atasan 45, Self 10, Rekan 15,
Bawahan 30. Kalau tidak ada rekan yang mengisi, 15 itu dinormalkan ulang ke tiga
kategori sisanya — dan bobot yang benar-benar dipakai menjadi 50, 11,11, dan 33,33.

Tanpa kolom ini, hasilnya menampilkan nilai kompetensi yang **tidak bisa direproduksi**
dari bobot mana pun yang terdokumentasi. Dan itu justru pertanyaan pertama yang muncul
saat seseorang keberatan dengan nilainya.

## Bobot di dalam kategori tidak diringkas di sini

Yang di tabel ini bobot **antar** kategori. Bobot satu penilai relatif terhadap penilai
lain di dalam kategori yang sama tetap tinggal di
`N_PA_Assignment.Bobot_Dalam_Kategori` masing-masing, karena di situlah ia berbeda per
orang.

## Menghitung ulang tidak menghapus

Baris lama diberi `Flag_Aktif = 'T'`, baris baru disisipkan dengan `Calculation_Version`
berikutnya. Pola yang sama dengan pemetaan ulang di `N_PA_Assignment_Score`, dan
alasannya sama: hasil lama harus tetap bisa dijelaskan.

## Constraint dan index

| Nama | Jenis | Alasan |
|---|---|---|
| `PK_PA_Result_Rater_Category` | Primary key | |
| `FK_PA_Result_Rater_Cat_Result` | Foreign key | → `N_PA_Result` |
| `FK_PA_Result_Rater_Cat_Category` | Foreign key | → `N_PA_Rater_Category` |
| `UX_PA_Result_Rater_Cat_Aktif` | Unique index tersaring | Satu baris aktif per (hasil, kategori) |
| `CK_PA_Result_Rater_Cat_Nilai` | Check | Tidak negatif |
| `CK_PA_Result_Rater_Cat_Bobot` | Check | 0–100 |
| `CK_PA_Result_Rater_Cat_Jumlah` | Check | Mengisi tidak melebihi diharapkan |
| `CK_PA_Result_Rater_Cat_Calc_Version` | Check | Minimal 1 |

## Yang sengaja tidak dijaga database

Bahwa `Bobot_Terpakai` seluruh kategori aktif berjumlah 100. Itu lintas baris, dan
setelah redistribusi jumlahnya memang harus 100 — tetapi menegakkannya di tingkat baris
mustahil, jadi pemeriksaannya dilakukan mesin perhitungan.

## Kedudukan

Anak dari `N_PA_Result`.

## Pemakaian aplikasi

Belum ada per 1 September 2026.

## Keadaan data

Kosong.
